<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use Erased\Core\MigrationRunner;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Two entry points: stage() extracts + validates + diffs an uploaded update
 * ZIP without touching anything live; apply() actually promotes it. Mirrors
 * PackageUpdateOrchestrator's shape (stage -> validate -> install -> migrate
 * -> record, with auto-revert-on-failure), but as a two-step admin flow
 * (upload now, confirm later) rather than one call, and with a DB backup
 * step the package system doesn't need (a plugin's own migrations are
 * scoped to that plugin; a core update's migrations can touch anything).
 */
final class CoreUpdateOrchestrator
{
    public function __construct(
        private readonly PDO $db,
        private readonly CoreUpdateStager $stager,
        private readonly CoreUpdateDiffer $differ,
        private readonly CoreUpdateRepository $repository,
        private readonly CoreCodeInstaller $installer,
        private readonly string $stagingRoot,
        private readonly string $liveRoot,
        private readonly string $rollbackRoot,
        private readonly string $currentVersion,
    ) {
    }

    /** @return array{token:string,from_version:string,to_version:string,diff:array,pending_migrations:list<string>} */
    public function stage(string $archivePath, string $archiveOriginalName, ?int $stagedBy): array
    {
        if ($this->repository->findActiveStaged() !== null) {
            throw new RuntimeException('A core update is already staged or applying. Discard it before staging a new one.');
        }

        $staged = $this->stager->stage($archivePath, $this->stagingRoot);

        try {
            $manifest = $staged['manifest'];

            if (version_compare($manifest->version(), $this->currentVersion, '<=')) {
                throw new RuntimeException(
                    "Uploaded version {$manifest->version()} is not newer than the installed version {$this->currentVersion}."
                );
            }
            if (version_compare($this->currentVersion, $manifest->requires(), '<')) {
                throw new RuntimeException(
                    "This update requires CMS version {$manifest->requires()} or later; installed version is {$this->currentVersion}."
                );
            }

            $diff = $this->differ->diff($this->liveRoot, $staged['directory'], CoreCodeInstaller::CORE_UPDATE_PATHS);

            $stagedMigrationsDir = $staged['directory'].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
            $pendingMigrations = is_dir($stagedMigrationsDir)
                ? (new MigrationRunner($this->db, $stagedMigrationsDir))->pending()
                : [];

            $token = basename($staged['directory']);

            $this->repository->create([
                'token' => $token,
                'from_version' => $this->currentVersion,
                'to_version' => $manifest->version(),
                'staged_by' => $stagedBy,
                'archive_original_name' => $archiveOriginalName,
                'diff_summary' => $diff,
                'pending_migrations' => $pendingMigrations,
            ]);

            return [
                'token' => $token,
                'from_version' => $this->currentVersion,
                'to_version' => $manifest->version(),
                'diff' => $diff,
                'pending_migrations' => $pendingMigrations,
            ];
        } catch (Throwable $error) {
            $this->stager->discard($staged['directory'], $this->stagingRoot);
            throw $error;
        }
    }

    /** @return array{to_version:string} */
    public function apply(string $token): array
    {
        $row = $this->repository->findByToken($token);
        if ($row === null || $row['status'] !== 'staged') {
            throw new RuntimeException('No staged core update matches this token.');
        }

        $stagedDirectory = rtrim($this->stagingRoot, '/').'/'.$token;
        if (!is_dir($stagedDirectory)) {
            throw new RuntimeException('Staged update directory is missing.');
        }
        if ($row['from_version'] !== $this->currentVersion) {
            throw new RuntimeException(
                "CMS version changed since this update was staged (was {$row['from_version']}, now {$this->currentVersion}). Discard and re-stage."
            );
        }

        $this->repository->markApplying($token);

        $dbBackupFile = '';
        $applyId = null;

        try {
            // Step 1: DB backup. Cheapest possible failure point - nothing
            // on disk has changed yet if this throws.
            $dbBackupFile = backup_database();

            // Step 2: code backup + swap.
            $applyId = date('Ymd-His').'-'.bin2hex(random_bytes(8));
            $installation = $this->installer->install(
                $stagedDirectory,
                $this->liveRoot,
                $this->rollbackRoot,
                CoreCodeInstaller::CORE_UPDATE_PATHS,
                $applyId
            );

            try {
                // Step 3: migrations - the same call bootstrap.php's db()
                // already makes every request, now pointed at the
                // just-promoted live directory.
                (new MigrationRunner($this->db, rtrim($this->liveRoot, '/').'/database/migrations'))->run();

                // Step 4: VERSION already promoted as part of the whitelisted
                // path swap in step 2 (VERSION is itself a whitelisted path) -
                // nothing further to write here.

                // Step 5: best-effort cache refresh - does not trigger a
                // revert on failure, mirrors plugin_admin_surface()'s own
                // swallow-and-continue philosophy elsewhere in this codebase.
                try {
                    if (function_exists('capability_runtime')) capability_runtime()->refresh();
                    if (function_exists('service_runtime')) service_runtime()->refresh();
                    if (function_exists('plugin_admin_surface')) plugin_admin_surface()->refresh();
                } catch (Throwable $refreshError) {
                    // Next request self-heals; not fatal to the apply itself.
                }
                if (function_exists('opcache_reset')) {
                    @opcache_reset();
                }
            } catch (Throwable $migrationError) {
                // Migrations failed: there is no down-migration mechanism in
                // this codebase (MySQL DDL auto-commits, so there's no
                // transaction to roll back even a single file) - a full DB
                // restore from the pre-apply backup is the only sound
                // recovery, alongside reverting the code swap.
                $this->installer->revert($installation['swapped'], $this->liveRoot, $installation['backup_directory']);
                restore_database($dbBackupFile);
                throw new RuntimeException('Migrations failed after code was swapped; code and database were both rolled back: '.$migrationError->getMessage());
            }

            $this->repository->markApplied($token, $installation['backup_directory'], $dbBackupFile);

            return ['to_version' => $row['to_version']];
        } catch (Throwable $error) {
            $this->repository->markFailed($token, $error->getMessage(), null, $dbBackupFile !== '' ? $dbBackupFile : null);
            throw $error;
        }
    }

    public function discard(string $token): void
    {
        $row = $this->repository->findByToken($token);
        if ($row === null) {
            return;
        }
        $stagedDirectory = rtrim($this->stagingRoot, '/').'/'.$token;
        if (is_dir($stagedDirectory)) {
            $this->stager->discard($stagedDirectory, $this->stagingRoot);
        }
        $this->repository->markDiscarded($token);
    }
}
