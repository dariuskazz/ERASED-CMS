<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;
use Throwable;

final class PackageInstaller
{
    public function __construct(private readonly PackageValidator $validator) {}

    /**
     * Promote a validated staged package into permanent storage.
     *
     * Existing package files are moved to a rollback directory first. If the
     * new package cannot be promoted or validated, the previous package is
     * restored automatically.
     *
     * @return array{package_directory:string,backup_directory:?string,manifest:PackageManifest}
     */
    public function install(string $stageDirectory, string $packagesRoot, string $rollbackRoot): array
    {
        $stage = realpath($stageDirectory);
        if ($stage === false || !is_dir($stage)) {
            throw new RuntimeException('Staged package directory does not exist.');
        }

        $validation = $this->validator->validateDirectory($stage);
        if (!$validation['valid'] || !$validation['manifest'] instanceof PackageManifest) {
            throw new RuntimeException('Refusing to install an invalid staged package: '.implode('; ', $validation['errors']));
        }

        $manifest = $validation['manifest'];
        $this->ensureDirectory($packagesRoot);
        $this->ensureDirectory($rollbackRoot);

        $packageDirectory = rtrim($packagesRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$manifest->id();

        // Two concurrent installArchive()/updateArchive() calls for the SAME
        // package (two admins, a double-click, two tabs) both passed the
        // file_exists() check below and both called rename($stage,
        // $packageDirectory) - on Linux, rename() onto an existing
        // non-empty directory fails for whichever request lost the race,
        // and its own catch block then saw $packageDirectory containing the
        // WINNER's freshly-promoted files and deleted them, with no
        // $backupDirectory to restore from. A per-package-id lock, held for
        // the whole promotion, makes the loser fail fast with a clear
        // message instead of destroying the winner's install.
        $lock = $this->acquirePackageLock($packagesRoot, $manifest->id());

        try {
            $backupDirectory = null;

            if (file_exists($packageDirectory)) {
                if (!is_dir($packageDirectory) || is_link($packageDirectory)) {
                    throw new RuntimeException('Installed package path is not a safe directory.');
                }

                $backupDirectory = rtrim($rollbackRoot, DIRECTORY_SEPARATOR)
                    .DIRECTORY_SEPARATOR
                    .$manifest->id().'-'.date('Ymd-His').'-'.bin2hex(random_bytes(5));

                if (!rename($packageDirectory, $backupDirectory)) {
                    throw new RuntimeException('Could not move the existing package into rollback storage.');
                }
            }

            try {
                if (!rename($stage, $packageDirectory)) {
                    throw new RuntimeException('Could not promote the staged package into permanent storage.');
                }

                $installedValidation = $this->validator->validateDirectory($packageDirectory);
                if (!$installedValidation['valid'] || !$installedValidation['manifest'] instanceof PackageManifest) {
                    throw new RuntimeException('Installed package failed post-promotion validation: '.implode('; ', $installedValidation['errors']));
                }
            } catch (Throwable $error) {
                if (is_dir($packageDirectory)) {
                    $this->removeDirectory($packageDirectory);
                }
                if ($backupDirectory !== null && is_dir($backupDirectory)) {
                    @rename($backupDirectory, $packageDirectory);
                }
                throw $error;
            }

            return [
                'package_directory' => $packageDirectory,
                'backup_directory' => $backupDirectory,
                'manifest' => $manifest,
            ];
        } finally {
            $this->releasePackageLock($lock);
        }
    }

    /** @return resource */
    private function acquirePackageLock(string $packagesRoot, string $packageId)
    {
        $lockDirectory = rtrim($packagesRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.locks';
        $this->ensureDirectory($lockDirectory);
        $lockPath = $lockDirectory.DIRECTORY_SEPARATOR.preg_replace('/[^a-z0-9._-]/i', '_', $packageId).'.lock';

        $handle = fopen($lockPath, 'c');
        if ($handle === false) {
            throw new RuntimeException("Could not open install lock file: {$lockPath}");
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException("Another install or update for '{$packageId}' is already in progress. Please wait for it to finish and try again.");
        }
        return $handle;
    }

    /** @param resource $lock */
    private function releasePackageLock($lock): void
    {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    /** @param array{package_directory:string,backup_directory:?string,manifest:PackageManifest} $installation */
    public function revert(array $installation): void
    {
        $packageDirectory = $installation['package_directory'];
        $backupDirectory = $installation['backup_directory'];

        if ($backupDirectory !== null) {
            $this->rollback($packageDirectory, $backupDirectory);
            return;
        }

        if (is_dir($packageDirectory)) {
            $this->removeDirectory($packageDirectory);
        }
    }

    public function rollback(string $packageDirectory, string $backupDirectory): void
    {
        if (!is_dir($backupDirectory) || is_link($backupDirectory)) {
            throw new RuntimeException('Rollback package directory does not exist or is unsafe.');
        }

        $failedDirectory = null;
        if (is_dir($packageDirectory)) {
            $failedDirectory = $packageDirectory.'.failed-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
            if (!rename($packageDirectory, $failedDirectory)) {
                throw new RuntimeException('Could not move the current package aside for rollback.');
            }
        }

        if (!rename($backupDirectory, $packageDirectory)) {
            if ($failedDirectory !== null && is_dir($failedDirectory)) {
                @rename($failedDirectory, $packageDirectory);
            }
            throw new RuntimeException('Could not restore the rollback package.');
        }

        if ($failedDirectory !== null && is_dir($failedDirectory)) {
            $this->removeDirectory($failedDirectory);
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
