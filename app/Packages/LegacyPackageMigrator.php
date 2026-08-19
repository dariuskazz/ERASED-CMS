<?php
declare(strict_types=1);

namespace Erased\Packages;

use PDO;

/**
 * One-time, purely additive bridge from the old `packages` table (written by
 * the now-removed safe_zip_install(), installing into ROOT/addons/{slug} or
 * ROOT/themes/{slug} with a loose manifest.json/{type}.json/package.json)
 * into the modern installed_packages registry.
 *
 * Never deletes or moves anything: it only reads the legacy row and
 * whatever manifest file it can find next to it, synthesizes a manifest
 * that satisfies the modern PackageManifest's stricter required fields
 * (id, requires, author, description - none of which the legacy format
 * guaranteed), and inserts a new installed_packages row. Idempotent - a
 * slug that's already present in installed_packages is skipped, so this
 * is safe to run more than once or as part of every deploy.
 */
final class LegacyPackageMigrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly InstalledPackageRepository $repository,
        private readonly string $rootPath,
    ) {
    }

    /** @return array{migrated:list<string>,skipped:list<string>,failed:array<string,string>} */
    public function migrate(): array
    {
        $migrated = [];
        $skipped = [];
        $failed = [];

        $rows = $this->legacyRows();
        foreach ($rows as $row) {
            $slug = (string)$row['slug'];
            $legacyType = (string)$row['package_type'];
            $newId = 'legacy.'.$legacyType.'.'.$slug;

            if ($this->repository->find($newId) !== null) {
                $skipped[] = $slug;
                continue;
            }

            try {
                $installedPath = $this->rootPath.'/'.($legacyType === 'theme' ? 'themes' : 'addons').'/'.$slug;
                if (!is_dir($installedPath)) {
                    throw new \RuntimeException("Package directory is missing: {$installedPath}");
                }

                $existingManifest = $this->readExistingManifest($installedPath);
                $manifestData = [
                    'id' => $newId,
                    'type' => $legacyType === 'theme' ? 'theme' : 'module',
                    'name' => (string)$row['name'],
                    'version' => (string)($row['version'] !== '' ? $row['version'] : '1.0.0'),
                    'requires' => '0.2.0',
                    'author' => (string)($existingManifest['author'] ?? 'Unknown'),
                    'description' => (string)($existingManifest['description'] ?? ('Migrated from the legacy package registry ('.$legacyType.').')),
                    'dependencies' => [],
                ];

                $manifestPath = $installedPath.'/package.json';
                if (!is_file($manifestPath)) {
                    file_put_contents($manifestPath, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }

                $manifest = new PackageManifest($manifestData);
                $this->repository->save($manifest, $installedPath, (bool)$row['enabled']);
                $migrated[] = $slug;
            } catch (\Throwable $error) {
                $failed[$slug] = $error->getMessage();
            }
        }

        return ['migrated' => $migrated, 'skipped' => $skipped, 'failed' => $failed];
    }

    /** @return list<array<string,mixed>> */
    private function legacyRows(): array
    {
        if (!$this->tableExists('packages')) {
            return [];
        }

        $statement = $this->pdo->query('SELECT * FROM packages');

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    private function tableExists(string $table): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        } else {
            $statement = $this->pdo->prepare(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
        }
        $statement->execute([$table]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed> */
    private function readExistingManifest(string $installedPath): array
    {
        foreach (['manifest.json', 'addon.json', 'theme.json', 'package.json'] as $candidate) {
            $path = $installedPath.'/'.$candidate;
            if (is_file($path)) {
                $decoded = json_decode((string)file_get_contents($path), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }
}
