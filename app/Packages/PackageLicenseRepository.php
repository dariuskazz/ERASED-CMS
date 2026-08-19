<?php
declare(strict_types=1);

namespace Erased\Packages;

use PDO;
use RuntimeException;

/**
 * Deliberately independent of installed_packages - a license activation
 * must survive an "uninstall, keep data" then reinstall cycle, so this
 * table has no foreign key back to it (see docs/COMMERCIAL-MODEL.md's
 * "disabling ... must not delete content").
 */
final class PackageLicenseRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $table = 'package_licenses')
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('Package license table name is invalid.');
        }

        $this->table = $table;
    }

    public function findKey(string $packageId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT license_key FROM '.$this->quotedTable().' WHERE package_id = :package_id LIMIT 1'
        );
        $statement->execute([':package_id' => $packageId]);
        $key = $statement->fetchColumn();

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function activate(string $packageId, string $licenseKey): void
    {
        $key = trim($licenseKey);
        if ($key === '') {
            throw new RuntimeException('A license key is required.');
        }

        if ($this->findKey($packageId) === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO '.$this->quotedTable().' (package_id, license_key) VALUES (:package_id, :license_key)'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE '.$this->quotedTable().' SET license_key = :license_key, updated_at = CURRENT_TIMESTAMP '
                .'WHERE package_id = :package_id'
            );
        }

        $statement->execute([':package_id' => $packageId, ':license_key' => $key]);
    }

    public function deactivate(string $packageId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM '.$this->quotedTable().' WHERE package_id = :package_id'
        );
        $statement->execute([':package_id' => $packageId]);
    }

    private function quotedTable(): string
    {
        return '`'.$this->table.'`';
    }
}
