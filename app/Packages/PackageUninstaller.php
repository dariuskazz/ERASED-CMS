<?php
declare(strict_types=1);

namespace Erased\Packages;

use Erased\Events\EventDispatcher;
use Erased\Events\PackageEvent;
use RuntimeException;
use Throwable;

/**
 * Safe uninstall, covering all three modes the roadmap calls for.
 * "Disable only" needs no dedicated method here - it's exactly
 * PackageLifecycleExecutor::disable(), just exposed through the uninstall
 * flow's UI with uninstall-flavored labeling.
 *
 * Permanently removing a package's own data is destructive, so
 * removeAndDeleteData() backs up the code directory first, using the exact
 * same convention PackageInstaller::install() already uses for its own
 * pre-replace backups - the result is automatically restorable through the
 * existing PackageRollbackService, with no separate recovery mechanism to
 * build or maintain. That backup covers the package's code and registry
 * state only: whatever the package's own uninstall() hook deletes (its
 * database tables, uploaded content) is gone for good, since the engine
 * has no way to know what that data even is.
 */
final class PackageUninstaller
{
    public function __construct(
        private readonly InstalledPackageRepository $repository,
        private readonly PackageLifecycleLoader $lifecycleLoader,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    /**
     * Removes the package's code from disk and its registry entry, but
     * calls its uninstall() hook with $removeData=false so the package's
     * own data (its database tables, uploaded content, settings) is left
     * alone. Reinstalling the same package id later will find that data
     * still there.
     */
    public function removePreservingData(string $packageId): void
    {
        $existing = $this->repository->find($packageId);
        if ($existing === null) {
            throw new RuntimeException("Package '{$packageId}' is not installed.");
        }

        $manifest = new PackageManifest($existing['manifest']);
        $installedPath = (string) $existing['installed_path'];

        if (!is_dir($installedPath) || is_link($installedPath)) {
            throw new RuntimeException("Installed package path is missing or unsafe: {$installedPath}");
        }

        try {
            $lifecycle = $this->lifecycleLoader->load($manifest, $installedPath);
            $lifecycle->uninstall($manifest, false);
        } catch (Throwable $error) {
            throw new RuntimeException(
                "Package '{$packageId}' uninstall hook failed, nothing was removed: ".$error->getMessage(),
                0,
                $error,
            );
        }

        $this->removeDirectory($installedPath);
        $this->repository->remove($packageId);
        $this->events?->dispatch('package.uninstalled', new PackageEvent($manifest, $installedPath, ['mode' => 'keep_data']));
    }

    /**
     * Permanently removes the package, including its own data: calls its
     * uninstall() hook with $removeData=true, then - only once that hook
     * has succeeded - moves its code directory into $rollbackRoot using the
     * same "{id}-{Ymd}-{His}-{10 hex}" naming PackageInstaller already uses,
     * so it shows up automatically as a restorable backup on the package's
     * details screen. If the hook fails, nothing is touched, exactly like
     * removePreservingData(). If the hook succeeds but the backup move
     * itself fails, the registry entry is deliberately left in place rather
     * than silently dropped, since the package's data is already gone at
     * that point and an admin needs to see it's in a bad state, not have it
     * vanish quietly.
     */
    public function removeAndDeleteData(string $packageId, string $rollbackRoot): void
    {
        $existing = $this->repository->find($packageId);
        if ($existing === null) {
            throw new RuntimeException("Package '{$packageId}' is not installed.");
        }

        $manifest = new PackageManifest($existing['manifest']);
        $installedPath = (string) $existing['installed_path'];

        if (!is_dir($installedPath) || is_link($installedPath)) {
            throw new RuntimeException("Installed package path is missing or unsafe: {$installedPath}");
        }

        try {
            $lifecycle = $this->lifecycleLoader->load($manifest, $installedPath);
            $lifecycle->uninstall($manifest, true);
        } catch (Throwable $error) {
            throw new RuntimeException(
                "Package '{$packageId}' uninstall hook failed, nothing was removed: ".$error->getMessage(),
                0,
                $error,
            );
        }

        if (!is_dir($rollbackRoot) && !mkdir($rollbackRoot, 0750, true) && !is_dir($rollbackRoot)) {
            throw new RuntimeException("Could not create rollback directory: {$rollbackRoot}");
        }

        $backupDirectory = rtrim($rollbackRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$packageId.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(5));

        if (!rename($installedPath, $backupDirectory)) {
            throw new RuntimeException(
                "Package '{$packageId}' had its data deleted by its uninstall hook, but its code could not be ".
                'backed up, so the registry entry was left in place. This needs manual cleanup.'
            );
        }

        $this->repository->remove($packageId);
        $this->events?->dispatch('package.uninstalled', new PackageEvent($manifest, $backupDirectory, ['mode' => 'delete_data', 'original_path' => $installedPath]));
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if ($items === false) {
            throw new RuntimeException("Could not read directory for removal: {$directory}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } elseif (!unlink($path)) {
                throw new RuntimeException("Could not remove file: {$path}");
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException("Could not remove directory: {$directory}");
        }
    }
}
