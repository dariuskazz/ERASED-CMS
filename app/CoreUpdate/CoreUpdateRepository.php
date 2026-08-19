<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use JsonException;
use PDO;
use RuntimeException;

final class CoreUpdateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array{token:string,from_version:string,to_version:string,staged_by:?int,archive_original_name:string,diff_summary:array<string,mixed>,pending_migrations:list<string>} $data */
    public function create(array $data): void
    {
        if ($this->findActiveStaged() !== null) {
            throw new RuntimeException('A core update is already staged or applying. Discard it before staging a new one.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO core_updates '
            .'(token, status, from_version, to_version, staged_by, archive_original_name, diff_summary, pending_migrations) '
            .'VALUES (:token, \'staged\', :from_version, :to_version, :staged_by, :archive_original_name, :diff_summary, :pending_migrations)'
        );
        $statement->execute([
            ':token' => $data['token'],
            ':from_version' => $data['from_version'],
            ':to_version' => $data['to_version'],
            ':staged_by' => $data['staged_by'],
            ':archive_original_name' => $data['archive_original_name'],
            ':diff_summary' => $this->encode($data['diff_summary']),
            ':pending_migrations' => $this->encode($data['pending_migrations']),
        ]);
    }

    /** Only one update may be staged or applying at a time - apply() renames whitelisted directories, so two concurrent stages would race. */
    public function findActiveStaged(): ?array
    {
        $statement = $this->pdo->query(
            "SELECT * FROM core_updates WHERE status IN ('staged','applying') ORDER BY id DESC LIMIT 1"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function findByToken(string $token): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM core_updates WHERE token = :token LIMIT 1');
        $statement->execute([':token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function markApplying(string $token): void
    {
        $this->pdo->prepare("UPDATE core_updates SET status = 'applying' WHERE token = :token")
            ->execute([':token' => $token]);
    }

    public function markApplied(string $token, string $codeBackupDirectory, string $dbBackupFile): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE core_updates SET status = 'applied', applied_at = CURRENT_TIMESTAMP, "
            ."code_backup_directory = :code_backup_directory, db_backup_file = :db_backup_file WHERE token = :token"
        );
        $statement->execute([
            ':code_backup_directory' => $codeBackupDirectory,
            ':db_backup_file' => $dbBackupFile,
            ':token' => $token,
        ]);
    }

    public function markFailed(string $token, string $errorMessage, ?string $codeBackupDirectory = null, ?string $dbBackupFile = null): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE core_updates SET status = 'failed', error_message = :error_message, "
            ."code_backup_directory = COALESCE(:code_backup_directory, code_backup_directory), "
            ."db_backup_file = COALESCE(:db_backup_file, db_backup_file) WHERE token = :token"
        );
        $statement->execute([
            ':error_message' => $errorMessage,
            ':code_backup_directory' => $codeBackupDirectory,
            ':db_backup_file' => $dbBackupFile,
            ':token' => $token,
        ]);
    }

    public function markDiscarded(string $token): void
    {
        $this->pdo->prepare("UPDATE core_updates SET status = 'discarded' WHERE token = :token")
            ->execute([':token' => $token]);
    }

    public function delete(string $token): void
    {
        $this->pdo->prepare('DELETE FROM core_updates WHERE token = :token')
            ->execute([':token' => $token]);
    }

    /**
     * Rows past the TTL that are still 'staged' (never applied) - the
     * caller discards both the row and the on-disk staging directory it
     * points at (filesystem action stays out of the repository layer, same
     * separation InstalledPackageRepository keeps from the filesystem).
     * @return list<array<string,mixed>>
     */
    public function sweepExpired(int $ttlSeconds): array
    {
        // Computed in PHP rather than DATE_SUB(NOW(), INTERVAL ...) so the
        // comparison is portable across drivers (this repository's own
        // tests run against sqlite) instead of relying on MySQL date
        // arithmetic syntax.
        //
        // gmdate(), not date(): staged_at is populated by MySQL's own
        // CURRENT_TIMESTAMP default, which this project's DB runs in UTC
        // (confirmed: NOW() === UTC_TIMESTAMP() here). date() instead
        // formats using PHP's *current default timezone*, which a real
        // request changes mid-bootstrap via date_default_timezone_set()
        // from the 'timezone' site setting (public/index.php, defaults to
        // 'Europe/Oslo', i.e. UTC+1/+2) - every admin request was computing
        // a cutoff 1-2 hours in the *future* relative to the UTC staged_at
        // values it was compared against, so a TTL meant to be hours long
        // was effectively ~0 and every freshly staged update got swept and
        // discarded on the very next request. gmdate() always formats in
        // UTC regardless of the active default timezone, matching what's
        // actually stored in staged_at.
        $cutoff = gmdate('Y-m-d H:i:s', time() - $ttlSeconds);
        $statement = $this->pdo->prepare(
            "SELECT * FROM core_updates WHERE status = 'staged' AND staged_at < :cutoff"
        );
        $statement->execute([':cutoff' => $cutoff]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    private function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not encode core update data for storage.', 0, $error);
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        foreach (['diff_summary', 'pending_migrations'] as $jsonField) {
            if (isset($row[$jsonField]) && is_string($row[$jsonField]) && $row[$jsonField] !== '') {
                try {
                    $row[$jsonField] = json_decode($row[$jsonField], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $error) {
                    $row[$jsonField] = null;
                }
            }
        }
        return $row;
    }
}
