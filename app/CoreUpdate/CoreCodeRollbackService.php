<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use RuntimeException;

/**
 * Mirrors PackageRollbackService: exposes the backups CoreCodeInstaller
 * already leaves behind (every apply moves the replaced paths into the
 * rollback root instead of deleting them) as an on-demand admin action, even
 * after a "successful" apply, in case a problem surfaces later than the
 * apply-step's own automatic revert-on-failure would have caught.
 *
 * Code-only by default, matching PackageRollbackService's own precedent -
 * there is no down-migration mechanism in this codebase (MySQL DDL
 * auto-commits, so migrations can't be wrapped in a transaction to reverse),
 * so schema is never silently auto-reverted by this path. Restoring the
 * database snapshot taken at apply time is a separate, explicit, clearly
 * more invasive opt-in that reuses the existing restore_database().
 */
final class CoreCodeRollbackService
{
    /** @return list<array{apply_id:string,backed_up_at:string}> */
    public function listBackups(string $rollbackRoot): array
    {
        if (!is_dir($rollbackRoot)) {
            return [];
        }

        $entries = [];
        foreach (scandir($rollbackRoot) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!preg_match('/^(\d{8})-(\d{6})-[0-9a-f]{16}$/', $item, $m)) {
                continue;
            }
            $path = $rollbackRoot.DIRECTORY_SEPARATOR.$item;
            if (!is_dir($path)) {
                continue;
            }

            $entries[] = [
                'apply_id' => $item,
                'backed_up_at' => $this->formatTimestamp($m[1], $m[2]),
            ];
        }

        usort($entries, static fn(array $a, array $b): int => strcmp($b['apply_id'], $a['apply_id']));

        return $entries;
    }

    /**
     * Restores every whitelisted path recorded for the given apply, code
     * only. The currently-live version of each restored path is itself
     * preserved as a fresh backup first, so a rollback is always reversible.
     * @param list<string> $corePaths
     * @return list<string> the paths actually restored
     */
    public function rollbackTo(string $applyId, string $liveRoot, string $rollbackRoot, array $corePaths): array
    {
        if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{16}$/', $applyId)) {
            throw new RuntimeException('Invalid rollback reference.');
        }

        $resolvedRollbackRoot = realpath($rollbackRoot);
        if ($resolvedRollbackRoot === false) {
            throw new RuntimeException('Rollback storage is unavailable.');
        }

        $backupPath = $resolvedRollbackRoot.DIRECTORY_SEPARATOR.$applyId;
        $resolvedBackupPath = realpath($backupPath);
        if ($resolvedBackupPath === false || !is_dir($resolvedBackupPath)
            || !str_starts_with($resolvedBackupPath, $resolvedRollbackRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Rollback backup not found.');
        }

        $liveRoot = rtrim($liveRoot, DIRECTORY_SEPARATOR);
        $precedingBackupId = date('Ymd-His').'-'.bin2hex(random_bytes(8));
        $precedingBackupDir = $resolvedRollbackRoot.DIRECTORY_SEPARATOR.$precedingBackupId;

        $restored = [];
        foreach ($corePaths as $path) {
            $fromPath = $resolvedBackupPath.DIRECTORY_SEPARATOR.$path;
            if (!file_exists($fromPath)) {
                continue; // this backup doesn't cover this path - leave the live version alone
            }

            $livePath = $liveRoot.DIRECTORY_SEPARATOR.$path;
            if (file_exists($livePath)) {
                $precedingPath = $precedingBackupDir.DIRECTORY_SEPARATOR.$path;
                $this->ensureDirectory(dirname($precedingPath));
                if (!$this->moveOrCopy($livePath, $precedingPath)) {
                    throw new RuntimeException("Could not preserve the current version of {$path} before rolling back.");
                }
            }

            if (!$this->moveOrCopy($fromPath, $livePath)) {
                throw new RuntimeException("Could not restore {$path} from the selected backup.");
            }

            $restored[] = $path;
        }

        return $restored;
    }

    /**
     * Same rename()-with-copy-fallback as CoreCodeInstaller - see that
     * class's moveOrCopy() doc comment for why the fallback exists
     * (overlayfs can reject a rename() that df would suggest should work)
     * and why $from is only ever deleted after clearstatcache() + a fresh
     * file_exists($to) confirms the copy actually landed - a failed
     * rename() can otherwise leave a stale negative stat-cache entry for
     * $to, causing a fully-successful copy to be misreported as failed
     * after its source was already deleted.
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
            } elseif (!copy($source, $dest)) {
                throw new RuntimeException("Could not copy file: {$source}");
            }
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

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create directory: {$directory}");
        }
    }

    private function formatTimestamp(string $ymd, string $his): string
    {
        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2)
            .' '.substr($his, 0, 2).':'.substr($his, 2, 2).':'.substr($his, 4, 2);
    }
}
