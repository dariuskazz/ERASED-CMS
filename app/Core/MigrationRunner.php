<?php
declare(strict_types=1);

namespace Erased\Core;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private PDO $db,
        private string $directory
    ) {}

    public function run(): void
    {
        // v0.9-dev incident: this used to cache a directory *mtime* to skip
        // the scan below on the overwhelmingly common case of "nothing
        // changed since last check". That's fragile against any deploy
        // method that preserves source mtimes on the migrations directory
        // (rsync -a, cp -p, some container COPY layers, an imported DB dump
        // whose settings table carries a marker from a *different*
        // filesystem's mtime) - a coincidentally-matching stale mtime meant
        // real pending migrations were silently skipped forever, with zero
        // error output, until the cached row was manually deleted. Hashing
        // the *filenames actually present* instead ties the cache to file
        // identity rather than a timestamp, so it can never give a false
        // "nothing changed" for a directory whose file list genuinely
        // changed. glob() is a single cheap directory read either way -
        // it was never the expensive part being avoided here; the
        // CREATE TABLE IF NOT EXISTS + SELECT round trip below is.
        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $fileHash = md5(implode(',', array_map('basename', $files)));

        if ($this->readMarker() === $fileHash) {
            return;
        }

        // v0.9-dev incident: a genuinely fresh install has no marker yet, so
        // the very first request to reach this point does a full scan and
        // apply. If a second request arrives before that finishes (a user
        // reloading the slow-looking install/login page - exactly the kind
        // of thing a real person does), it ALSO sees no marker and starts
        // its own full scan concurrently. Whichever one loses the race hits
        // a non-idempotent ALTER TABLE that the other process already
        // applied moments earlier ("Duplicate column"), throws before ever
        // writing the marker, and every request after that retries the same
        // doomed migration forever - a permanent hard-down 500 with no
        // self-recovery. A MySQL advisory lock serializes the two: the
        // loser just waits for the winner to finish, then re-checks the
        // marker (now written) and returns immediately instead of racing.
        // SQLite has no server-wide advisory lock primitive and no
        // meaningful multi-process contention here, so it skips straight to
        // the unlocked path.
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $this->runLocked($fileHash);
            return;
        }

        $this->applyPending($files, $fileHash);
    }

    private function runLocked(string $fileHash): void
    {
        $lockName = 'erased_cms_migrations';
        $statement = $this->db->prepare('SELECT GET_LOCK(?, 30)');
        $statement->execute([$lockName]);
        $acquired = (bool) $statement->fetchColumn();

        if (!$acquired) {
            throw new RuntimeException(
                'Another request is currently applying database migrations. Please retry in a moment.'
            );
        }

        try {
            // Re-check now that the lock is held - the process that held it
            // before us may have just finished and written the marker,
            // making this run's job already done.
            if ($this->readMarker() === $fileHash) {
                return;
            }

            $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
            sort($files, SORT_STRING);
            $this->applyPending($files, $fileHash);
        } finally {
            $release = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    /** @param list<string> $files */
    private function applyPending(array $files, string $fileHash): void
    {
        $this->ensureTable();

        $done = array_flip(
            $this->db->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN)
        );

        $batch = (int) $this->db
            ->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')
            ->fetchColumn();

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Cannot read migration {$name}");
            }

            // MariaDB/MySQL automatically commits many DDL statements such as
            // CREATE TABLE. Wrapping SQL migration files in a PDO transaction
            // therefore leaves no active transaction to commit afterwards.
            $this->db->exec($sql);

            $statement = $this->db->prepare(
                'INSERT INTO migrations (migration, batch) VALUES (?, ?)'
            );
            $statement->execute([$name, $batch]);
        }

        $this->writeMarker($fileHash);
    }

    /**
     * Read-only preview: which migration files in this directory haven't
     * been applied yet, without applying them and without touching the
     * cache marker. Pointed at a *staged* update's migrations directory
     * during a core-update review step, or at the live directory to answer
     * "what would run right now".
     *
     * @return list<string> sorted filenames not yet in the migrations table
     */
    public function pending(): array
    {
        $this->ensureTable();

        $done = array_flip(
            $this->db->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN)
        );

        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!isset($done[$name])) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    private function ensureTable(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    executed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );
            return;
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(190) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function readMarker(): ?string
    {
        try {
            $value = $this->db
                ->query("SELECT setting_value FROM settings WHERE setting_key='_migration_dir_filehash'")
                ->fetchColumn();
            return $value === false ? null : (string) $value;
        } catch (\Throwable $e) {
            return null; // settings table doesn't exist yet - fall through to the full scan
        }
    }

    private function writeMarker(string $fileHash): void
    {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = $driver === 'sqlite'
                ? "INSERT INTO settings(setting_key,setting_value) VALUES('_migration_dir_filehash',?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value"
                : "INSERT INTO settings(setting_key,setting_value) VALUES('_migration_dir_filehash',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)";
            $this->db->prepare($sql)->execute([$fileHash]);
            // The old mtime-based marker is permanently obsolete and, if left
            // behind, is dead weight at best - remove it so a server that
            // upgraded through this fix doesn't carry a stale, unused row.
            $this->db->exec("DELETE FROM settings WHERE setting_key='_migration_dir_mtime'");
        } catch (\Throwable $e) {
            // Best-effort: if this fails, the next request just re-scans.
        }
    }
}
