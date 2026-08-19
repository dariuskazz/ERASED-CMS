<?php
declare(strict_types=1);

namespace Erased\Packages;

use JsonException;
use RuntimeException;

/**
 * Exposes the backups PackageInstaller::install() already leaves behind
 * (every install/update moves the previous version into the rollback root
 * instead of deleting it) as an on-demand admin action. Deliberately does
 * its own directory swap rather than reusing PackageInstaller::rollback():
 * that method is built for restoring after a *failed* install, so it
 * discards the directory it replaces - here the replaced directory is a
 * good, currently-installed version, so it is kept as a fresh backup,
 * making a rollback itself always reversible.
 *
 * rollbackTo() also doubles as the recovery path for
 * PackageUninstaller::removeAndDeleteData(): that method's backup uses this
 * exact same naming convention but leaves no registry row behind (the
 * package was fully uninstalled), so restoring it is not "install over an
 * existing row" but a genuine reinstall from the backup - handled the same
 * way either way, since InstalledPackageRepository::save() already upserts.
 */
final class PackageRollbackService
{
    public function __construct(
        private readonly InstalledPackageRepository $repository,
    ) {
    }

    /** @return list<array{directory:string,version:string,backed_up_at:string}> */
    public function listBackups(string $packageId, string $rollbackRoot): array
    {
        if (!is_dir($rollbackRoot)) {
            return [];
        }

        $entries = [];
        foreach (scandir($rollbackRoot) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!preg_match('/^'.preg_quote($packageId, '/').'-(\d{8})-(\d{6})-[0-9a-f]{10}$/', $item, $m)) {
                continue;
            }
            $path = $rollbackRoot.DIRECTORY_SEPARATOR.$item;
            if (!is_dir($path)) {
                continue;
            }

            $entries[] = [
                'directory' => $item,
                'version' => $this->readVersion($path),
                'backed_up_at' => $this->formatTimestamp($m[1], $m[2]),
            ];
        }

        usort($entries, static fn(array $a, array $b): int => strcmp($b['directory'], $a['directory']));

        return $entries;
    }

    public function rollbackTo(string $packageId, string $backupDirectory, string $packagesRoot, string $rollbackRoot): PackageManifest
    {
        if (!preg_match('/^([a-z0-9][a-z0-9._-]*)-\d{8}-\d{6}-[0-9a-f]{10}$/', $backupDirectory, $m) || $m[1] !== $packageId) {
            throw new RuntimeException('Invalid rollback backup reference.');
        }

        $resolvedRollbackRoot = realpath($rollbackRoot);
        if ($resolvedRollbackRoot === false) {
            throw new RuntimeException('Rollback storage is unavailable.');
        }

        $backupPath = $resolvedRollbackRoot.DIRECTORY_SEPARATOR.$backupDirectory;
        $resolvedBackupPath = realpath($backupPath);
        if ($resolvedBackupPath === false || !is_dir($resolvedBackupPath)
            || !str_starts_with($resolvedBackupPath, $resolvedRollbackRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Rollback backup not found.');
        }

        $packageDirectory = rtrim($packagesRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$packageId;
        $currentRow = $this->repository->find($packageId);
        // No row (e.g. restoring after removeAndDeleteData()) starts disabled,
        // same as any other fresh install - never silently re-enable on restore.
        $wasEnabled = $currentRow !== null && (bool)$currentRow['enabled'];

        $precedingBackup = null;
        if (is_dir($packageDirectory)) {
            if (is_link($packageDirectory)) {
                throw new RuntimeException('Installed package path is not a safe directory.');
            }
            $precedingBackup = $resolvedRollbackRoot.DIRECTORY_SEPARATOR
                .$packageId.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(5));
            if (!rename($packageDirectory, $precedingBackup)) {
                throw new RuntimeException('Could not preserve the currently installed version before rolling back.');
            }
        }

        if (!rename($resolvedBackupPath, $packageDirectory)) {
            if ($precedingBackup !== null && is_dir($precedingBackup)) {
                @rename($precedingBackup, $packageDirectory);
            }
            throw new RuntimeException('Could not restore the selected backup.');
        }

        $manifest = $this->readManifest($packageDirectory);
        $this->repository->save($manifest, $packageDirectory, $wasEnabled);

        return $manifest;
    }

    private function readVersion(string $packageDirectory): string
    {
        try {
            return $this->readManifest($packageDirectory)->version();
        } catch (RuntimeException) {
            return 'unknown';
        }
    }

    private function readManifest(string $packageDirectory): PackageManifest
    {
        $manifestPath = $packageDirectory.DIRECTORY_SEPARATOR.'package.json';
        $raw = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if ($raw === false) {
            throw new RuntimeException("Could not read package manifest at {$manifestPath}.");
        }

        try {
            return PackageManifest::fromJson($raw);
        } catch (JsonException $error) {
            throw new RuntimeException('Rolled-back package manifest is invalid JSON.', 0, $error);
        }
    }

    private function formatTimestamp(string $ymd, string $his): string
    {
        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2)
            .' '.substr($his, 0, 2).':'.substr($his, 2, 2).':'.substr($his, 4, 2);
    }
}
