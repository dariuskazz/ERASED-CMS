<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use RuntimeException;
use Throwable;

/**
 * Mirrors PackageInstaller's rename-based backup/promote/rollback pattern,
 * but over a *whitelist of top-level paths* instead of one package
 * directory - core isn't a single directory like a plugin is. Only paths
 * actually present in the staged update are swapped; a path absent from the
 * update is left untouched (no needless backup).
 */
final class CoreCodeInstaller
{
    /** Never includes "storage" - that holds runtime data (uploads, config, backups) that must survive a core update untouched. */
    public const CORE_UPDATE_PATHS = [
        'app', 'routes', 'public', 'config', 'bootstrap', 'resources',
        'database', 'themes', 'website-types', 'VERSION',
    ];

    /**
     * @param list<string> $corePaths
     * @return array{swapped:list<string>,backup_directory:string}
     */
    public function install(string $stagedRoot, string $liveRoot, string $rollbackRoot, array $corePaths, string $applyId): array
    {
        $stagedRoot = rtrim($stagedRoot, DIRECTORY_SEPARATOR);
        $liveRoot = rtrim($liveRoot, DIRECTORY_SEPARATOR);
        $backupDirectory = rtrim($rollbackRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$applyId;
        $this->ensureDirectory($backupDirectory);

        // v0.9-dev: two lists on purpose, not one. $touched is every path
        // whose *live* version was moved out to the backup directory,
        // regardless of whether promoting the staged version afterward
        // succeeded - that backup needs restoring on failure either way.
        // $swapped is the subset that fully completed both steps, returned
        // to the caller as "genuinely updated". Originally this was a
        // single list only appended to after a full success, which meant a
        // path whose backup succeeded but whose *promotion* then failed
        // never got included in the revert-on-failure call at all - live
        // caught this during manual verification: the path's live version
        // was correctly backed up, but never restored, because revert()
        // was never told about it.
        $touched = [];
        $swapped = [];
        try {
            foreach ($corePaths as $path) {
                $stagedPath = $stagedRoot.DIRECTORY_SEPARATOR.$path;
                if (!file_exists($stagedPath)) {
                    continue; // this update doesn't touch this path - leave it alone
                }

                $livePath = $liveRoot.DIRECTORY_SEPARATOR.$path;
                if (file_exists($livePath)) {
                    $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$path;
                    $this->ensureDirectory(dirname($backupPath));
                    if (!$this->moveOrCopy($livePath, $backupPath)) {
                        throw new RuntimeException("Could not back up existing path before swap: {$path}");
                    }
                    $touched[] = $path;
                }

                if (!$this->moveOrCopy($stagedPath, $livePath)) {
                    throw new RuntimeException("Could not promote staged path into place: {$path}");
                }

                $swapped[] = $path;
            }
        } catch (Throwable $error) {
            $this->revert($touched, $liveRoot, $backupDirectory);
            throw $error;
        }

        return ['swapped' => $swapped, 'backup_directory' => $backupDirectory];
    }

    /** @param list<string> $swappedPaths reverse order, best-effort - collects errors rather than stopping at the first */
    public function revert(array $swappedPaths, string $liveRoot, string $backupDirectory): void
    {
        $liveRoot = rtrim($liveRoot, DIRECTORY_SEPARATOR);
        $errors = [];

        foreach (array_reverse($swappedPaths) as $path) {
            try {
                $livePath = $liveRoot.DIRECTORY_SEPARATOR.$path;
                $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$path;

                if (is_dir($livePath) && !is_link($livePath)) {
                    $this->removeDirectory($livePath);
                } elseif (file_exists($livePath)) {
                    @unlink($livePath);
                }

                if (file_exists($backupPath)) {
                    if (!$this->moveOrCopy($backupPath, $livePath)) {
                        $errors[] = "Could not restore backed-up path: {$path}";
                    }
                }
            } catch (Throwable $error) {
                $errors[] = "{$path}: {$error->getMessage()}";
            }
        }

        if ($errors !== []) {
            throw new RuntimeException('Core update rollback encountered errors: '.implode('; ', $errors));
        }
    }

    /**
     * v0.9-dev incident: rename() failed with "Invalid cross-device link"
     * moving a top-level path between the live root and the rollback
     * directory, even though both reported the same filesystem via df -
     * overlayfs can still reject a metadata-only rename between a file that
     * only exists in a read-only lower image layer and a path in the
     * writable upper layer, independent of what df considers "the same
     * mount". rename() is tried first (fast, atomic, the common case on a
     * plain filesystem); on any failure this falls back to a full copy
     * followed by deleting the source - not atomic, but correct everywhere,
     * the same fallback pattern tools like npm use for exactly this reason.
     *
     * v0.9-dev incident #2: a failed rename() can leave PHP's stat/realpath
     * cache holding a stale "doesn't exist" result for $to - caught live
     * when a real apply() fully and correctly copied "app" into its backup
     * location, deleted the live source, and only then read a stale cached
     * file_exists($to)===false, reporting the whole swap as failed after it
     * had already, irreversibly, happened. Two fixes: clearstatcache()
     * before trusting any stat result following the failed rename(), and -
     * more fundamentally - never delete $from until $to is verified present,
     * so a stat-cache bug like this can never again turn into silent data
     * loss even if it recurs in some other form.
     */
    private function moveOrCopy(string $from, string $to): bool
    {
        if (@rename($from, $to)) {
            return true;
        }

        clearstatcache(true);

        if (is_dir($from) && !is_link($from)) {
            $this->copyDirectory($from, $to);
        } elseif (is_file($from)) {
            $this->ensureDirectory(dirname($to));
            if (!copy($from, $to)) {
                return false;
            }
        } else {
            return false;
        }

        clearstatcache(true);
        if (!file_exists($to)) {
            return false;
        }

        $this->removeDirectory($from);
        if (is_file($from)) {
            @unlink($from);
        }

        return true;
    }

    private function copyDirectory(string $from, string $to): void
    {
        $this->ensureDirectory($to);
        $items = scandir($from);
        if ($items === false) {
            throw new RuntimeException("Could not read directory for copy: {$from}");
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $source = $from.DIRECTORY_SEPARATOR.$item;
            $dest = $to.DIRECTORY_SEPARATOR.$item;
            if (is_dir($source) && !is_link($source)) {
                $this->copyDirectory($source, $dest);
            } elseif (!@copy($source, $dest)) {
                $detail = error_get_last()['message'] ?? 'no further detail available';
                throw new RuntimeException("Could not copy file: {$source} ({$detail})");
            }
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create directory: {$directory}");
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
