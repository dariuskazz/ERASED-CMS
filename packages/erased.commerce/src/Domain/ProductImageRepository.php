<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;

/**
 * Additional gallery images for a product, beyond its single
 * featured_media_id (untouched - this is additive, not a replacement).
 * A real junction table with ON DELETE CASCADE, matching this package's own
 * commerce_product_files/commerce_downloads convention - deliberately not
 * the older photo_galleries.images_json denormalized-snapshot pattern
 * elsewhere in this codebase, since that bakes in alt/caption/url at save
 * time and goes stale if the underlying media is edited later. alt text and
 * the stored filename are always read live via the join in forProduct().
 */
final class ProductImageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> each row plus media's stored_name/mime_type/original_name/alt_text/has_thumb */
    public function forProduct(int $productId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT commerce_product_images.id, commerce_product_images.product_id, commerce_product_images.media_id, commerce_product_images.created_at, '
            .'media.stored_name, media.mime_type, media.original_name, media.alt_text, media.has_thumb '
            .'FROM commerce_product_images JOIN media ON media.id = commerce_product_images.media_id '
            .'WHERE commerce_product_images.product_id=? ORDER BY commerce_product_images.id'
        );
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_product_images WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function attach(int $productId, int $mediaId): int
    {
        $statement = $this->pdo->prepare('INSERT INTO commerce_product_images (product_id, media_id) VALUES (?, ?)');
        $statement->execute([$productId, $mediaId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM commerce_product_images WHERE id=?');
        $statement->execute([$id]);
    }
}
