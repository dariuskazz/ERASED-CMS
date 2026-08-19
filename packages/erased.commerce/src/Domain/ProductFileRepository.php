<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use finfo;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Storage for digital-good files attached to a product. Deliberately does
 * not reuse the site-wide upload_one()/media pipeline (app/bootstrap.php):
 * that allow-list is images/video/PDF only and its /media/{stored} route is
 * unguarded-public, both wrong for a purchased digital good. Files live
 * under their own subdirectory of the existing outside-webroot UPLOAD_DIR
 * and are only ever served through StorefrontRoute's per-order-item gated
 * download route.
 */
final class ProductFileRepository
{
    private const ALLOWED = [
        'application/zip' => ['zip', 200 * 1024 * 1024],
        'application/pdf' => ['pdf', 200 * 1024 * 1024],
        'application/epub+zip' => ['epub', 200 * 1024 * 1024],
        'audio/mpeg' => ['mp3', 200 * 1024 * 1024],
        'video/mp4' => ['mp4', 200 * 1024 * 1024],
        'text/plain' => ['txt', 200 * 1024 * 1024],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx', 200 * 1024 * 1024],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function directory(): string
    {
        return UPLOAD_DIR.'/commerce-digital';
    }

    /** @return list<array<string,mixed>> */
    public function forProduct(int $productId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_product_files WHERE product_id=? ORDER BY created_at DESC');
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_product_files WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $file the raw $_FILES entry */
    public function upload(int $productId, array $file): int
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            ];
            throw new RuntimeException($messages[$error] ?? 'The upload failed.');
        }
        $temporary = (string)($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new RuntimeException('The uploaded file could not be verified.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1) {
            throw new RuntimeException('The uploaded file is empty.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: 'application/octet-stream';
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('This file type is not allowed for digital delivery.');
        }
        [$extension, $limit] = self::ALLOWED[$mime];
        if ($size > $limit) {
            throw new RuntimeException('The uploaded file is too large.');
        }

        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the digital delivery directory.');
        }
        $stored = bin2hex(random_bytes(24)).'.'.$extension;
        $destination = $directory.'/'.$stored;
        if (!move_uploaded_file($temporary, $destination)) {
            throw new RuntimeException('Could not store the uploaded file.');
        }
        @chmod($destination, 0660);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO commerce_product_files (product_id, original_filename, stored_filename, size_bytes) VALUES (?, ?, ?, ?)'
            );
            $statement->execute([$productId, basename((string)($file['name'] ?? 'file.'.$extension)), $stored, $size]);
            return (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            @unlink($destination);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $file = $this->find($id);
        if ($file === null) {
            return;
        }
        $statement = $this->pdo->prepare('DELETE FROM commerce_product_files WHERE id=?');
        $statement->execute([$id]);
        $path = self::directory().'/'.basename((string)$file['stored_filename']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
