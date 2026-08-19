<?php
declare(strict_types=1);

namespace Erased\Packages;

use JsonException;
use PDO;
use RuntimeException;

final class InstalledPackageRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $table = 'installed_packages')
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('Installed package table name is invalid.');
        }

        $this->table = $table;
    }

    public function save(PackageManifest $manifest, string $installedPath, bool $enabled = false): void
    {
        $payload = $manifest->all();

        try {
            $manifestJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not encode package manifest for storage.', 0, $error);
        }

        $existing = $this->find($manifest->id());
        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO '.$this->quotedTable().' '
                .'(package_id, package_type, name, version, enabled, installed_path, manifest_json) '
                .'VALUES (:package_id, :package_type, :name, :version, :enabled, :installed_path, :manifest_json)'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE '.$this->quotedTable().' SET '
                .'package_type = :package_type, name = :name, version = :version, enabled = :enabled, '
                .'installed_path = :installed_path, manifest_json = :manifest_json, updated_at = CURRENT_TIMESTAMP '
                .'WHERE package_id = :package_id'
            );
        }

        $statement->execute([
            ':package_id' => $manifest->id(),
            ':package_type' => $manifest->type(),
            ':name' => $manifest->name(),
            ':version' => $manifest->version(),
            ':enabled' => $enabled ? 1 : 0,
            ':installed_path' => $installedPath,
            ':manifest_json' => $manifestJson,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function find(string $packageId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT package_id, package_type, name, version, enabled, health_status, last_error, last_error_at, installed_path, manifest_json, integrity_manifest_json, integrity_status, integrity_checked_at, installed_at, updated_at '
            .'FROM '.$this->quotedTable().' WHERE package_id = :package_id LIMIT 1'
        );
        $statement->execute([':package_id' => $packageId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(?string $type = null): array
    {
        if ($type === null) {
            $statement = $this->pdo->query(
                'SELECT package_id, package_type, name, version, enabled, health_status, last_error, last_error_at, installed_path, manifest_json, integrity_manifest_json, integrity_status, integrity_checked_at, installed_at, updated_at '
                .'FROM '.$this->quotedTable().' ORDER BY package_type, name, package_id'
            );
        } else {
            $statement = $this->pdo->prepare(
                'SELECT package_id, package_type, name, version, enabled, health_status, last_error, last_error_at, installed_path, manifest_json, integrity_manifest_json, integrity_status, integrity_checked_at, installed_at, updated_at '
                .'FROM '.$this->quotedTable().' WHERE package_type = :package_type ORDER BY name, package_id'
            );
            $statement->execute([':package_type' => $type]);
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * Records the SHA-256 file manifest computed right after a successful
     * install/update (PackageIntegrityChecker::computeManifest()) as the
     * trusted baseline for future verify() calls - status is set to 'ok'
     * since the manifest is, by construction, an exact snapshot of what was
     * just installed.
     * @param array<string,string> $manifest
     */
    public function recordIntegrityManifest(string $packageId, array $manifest): void
    {
        try {
            $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not encode package integrity manifest for storage.', 0, $error);
        }
        $statement = $this->pdo->prepare(
            "UPDATE ".$this->quotedTable()." SET integrity_manifest_json = :manifest, integrity_status = 'ok', "
            ."integrity_checked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE package_id = :package_id"
        );
        $statement->execute([':manifest' => $json, ':package_id' => $packageId]);
    }

    /** Records the result of an on-demand PackageIntegrityChecker::verify() call without touching the stored baseline manifest. */
    public function updateIntegrityStatus(string $packageId, string $status): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE ".$this->quotedTable()." SET integrity_status = :status, integrity_checked_at = CURRENT_TIMESTAMP "
            ."WHERE package_id = :package_id"
        );
        $statement->execute([':status' => $status, ':package_id' => $packageId]);
    }

    public function recordHealthError(string $packageId, string $error): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE ".$this->quotedTable()." SET health_status = 'error', last_error = :last_error, "
            ."last_error_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE package_id = :package_id"
        );
        $statement->execute([':last_error' => $error, ':package_id' => $packageId]);
    }

    public function clearHealthError(string $packageId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE ".$this->quotedTable()." SET health_status = 'ok', last_error = NULL, "
            ."last_error_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE package_id = :package_id"
        );
        $statement->execute([':package_id' => $packageId]);
    }

    public function setEnabled(string $packageId, bool $enabled): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE '.$this->quotedTable().' SET enabled = :enabled, updated_at = CURRENT_TIMESTAMP '
            .'WHERE package_id = :package_id'
        );
        $statement->execute([
            ':enabled' => $enabled ? 1 : 0,
            ':package_id' => $packageId,
        ]);

        if ($statement->rowCount() === 0 && $this->find($packageId) === null) {
            throw new RuntimeException("Installed package not found: {$packageId}");
        }
    }

    public function remove(string $packageId): void
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

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        try {
            $manifest = json_decode((string)$row['manifest_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored package manifest JSON is invalid.', 0, $error);
        }

        $row['enabled'] = (bool)$row['enabled'];
        $row['manifest'] = is_array($manifest) ? $manifest : [];
        unset($row['manifest_json']);

        $integrityManifest = null;
        if (array_key_exists('integrity_manifest_json', $row)) {
            $raw = $row['integrity_manifest_json'];
            if (is_string($raw) && $raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $integrityManifest = is_array($decoded) ? $decoded : null;
                } catch (JsonException $error) {
                    $integrityManifest = null;
                }
            }
            unset($row['integrity_manifest_json']);
        }
        $row['integrity_manifest'] = $integrityManifest;

        return $row;
    }
}
