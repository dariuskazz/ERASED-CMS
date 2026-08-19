<?php
declare(strict_types=1);

namespace Erased\Packages;

use PDO;
use RuntimeException;

/**
 * Package-scoped counterpart to Erased\Core\MigrationRunner: a package may
 * ship a migrations/*.sql directory alongside its package.json, run once
 * per file, tracked per package id so two packages can each ship a
 * same-named migration file without colliding.
 */
final class PackageMigrationRunner
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<string> names of the migrations that were applied */
    public function run(string $packageId, string $packageDirectory): array
    {
        $this->ensureTable();

        $migrationsDirectory = rtrim($packageDirectory, DIRECTORY_SEPARATOR).'/migrations';
        if (!is_dir($migrationsDirectory)) {
            return [];
        }

        $applied = $this->appliedMigrations($packageId);

        $files = glob($migrationsDirectory.'/*.sql') ?: [];
        sort($files, SORT_STRING);

        $newlyApplied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Could not read package migration '{$name}'.");
            }
            if (trim($sql) === '') {
                continue;
            }

            // Matches Erased\Core\MigrationRunner: MariaDB/MySQL auto-commits
            // DDL such as CREATE TABLE, so there is no transaction left to
            // wrap this in even if we wanted to.
            $this->pdo->exec($sql);

            $statement = $this->pdo->prepare(
                'INSERT INTO package_migrations (package_id, migration) VALUES (:package_id, :migration)'
            );
            $statement->execute([':package_id' => $packageId, ':migration' => $name]);
            $newlyApplied[] = $name;
        }

        return $newlyApplied;
    }

    /** @return list<array{migration:string,applied_at:string}> */
    public function appliedMigrationsWithTimestamps(string $packageId): array
    {
        $this->ensureTable();

        $statement = $this->pdo->prepare(
            'SELECT migration, applied_at FROM package_migrations WHERE package_id = :package_id ORDER BY applied_at, migration'
        );
        $statement->execute([':package_id' => $packageId]);

        /** @var list<array{migration:string,applied_at:string}> */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,true> */
    private function appliedMigrations(string $packageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT migration FROM package_migrations WHERE package_id = :package_id'
        );
        $statement->execute([':package_id' => $packageId]);

        return array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);
    }

    private function ensureTable(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS package_migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    package_id TEXT NOT NULL,
                    migration TEXT NOT NULL,
                    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(package_id, migration)
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS package_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                package_id VARCHAR(190) NOT NULL,
                migration VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY package_migrations_unique (package_id, migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}
