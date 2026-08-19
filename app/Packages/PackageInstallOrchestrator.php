<?php
declare(strict_types=1);

namespace Erased\Packages;

use Erased\Capabilities\CapabilityResolver;
use Erased\Events\EventDispatcher;
use Erased\Events\PackageEvent;
use RuntimeException;
use Throwable;

final class PackageInstallOrchestrator
{
    public function __construct(
        private readonly PackageArchiveStager $stager,
        private readonly PackageInstaller $installer,
        private readonly InstalledPackageRepository $repository,
        private readonly PackageLifecycleLoader $lifecycleLoader,
        private readonly PackageMigrationRunner $migrationRunner,
        private readonly ?CapabilityResolver $capabilities = null,
        private readonly ?EventDispatcher $events = null,
        private readonly ?PackageIntegrityChecker $integrityChecker = null,
    ) {
    }

    /** @return array{manifest:PackageManifest,package_directory:string,backup_directory:?string} */
    public function installArchive(
        string $archivePath,
        string $stagingRoot,
        string $packagesRoot,
        string $rollbackRoot,
    ): array {
        $stage = null;
        $installation = null;
        $existing = null;
        $migrationRan = false;
        $lifecycleInstalled = null;
        $manifest = null;

        try {
            $stage = $this->stager->stage($archivePath, $stagingRoot);
            $manifest = $stage['manifest'];
            $existing = $this->repository->find($manifest->id());

            $this->capabilities?->assertRequirementsSatisfied($manifest);

            $installation = $this->installer->install(
                $stage['directory'],
                $packagesRoot,
                $rollbackRoot,
            );
            $stage = null;

            $this->migrationRunner->run($manifest->id(), $installation['package_directory']);
            $migrationRan = true;

            $lifecycle = $this->lifecycleLoader->load(
                $manifest,
                $installation['package_directory'],
            );
            $lifecycle->install($manifest, $installation['package_directory']);
            $lifecycleInstalled = $lifecycle;

            $enabled = is_array($existing) ? (bool)$existing['enabled'] : false;
            $this->repository->save($manifest, $installation['package_directory'], $enabled);

            if ($this->integrityChecker !== null) {
                $this->repository->recordIntegrityManifest(
                    $manifest->id(),
                    $this->integrityChecker->computeManifest($installation['package_directory']),
                );
            }

            $this->events?->dispatch('package.installed', new PackageEvent($manifest, $installation['package_directory']));

            return [
                'manifest' => $manifest,
                'package_directory' => $installation['package_directory'],
                'backup_directory' => $installation['backup_directory'],
            ];
        } catch (Throwable $error) {
            $rollbackError = null;

            // Best-effort undo of the lifecycle hook's own side effects
            // (cron registration, config writes, etc.) if it already ran but
            // something later (repository save, integrity recording) still
            // failed - previously only the file-level install was reverted,
            // leaving the lifecycle's install() effects applied with no DB
            // row ever saved to represent the package as installed.
            if ($lifecycleInstalled !== null && is_array($installation) && $manifest !== null) {
                try {
                    $lifecycleInstalled->uninstall($manifest, false);
                } catch (Throwable $lifecycleRevertError) {
                    $rollbackError = $lifecycleRevertError;
                }
            }

            if (is_array($installation)) {
                try {
                    $this->installer->revert($installation);
                } catch (Throwable $revertError) {
                    $rollbackError = $revertError;
                }
            } elseif (is_array($stage)) {
                try {
                    $this->stager->discard($stage['directory'], $stagingRoot);
                } catch (Throwable $discardError) {
                    $rollbackError = $discardError;
                }
            }

            // There is no down-migration mechanism in this codebase - a
            // migration that already ran (schema changes, seed rows) cannot
            // be undone here. Surface that plainly instead of staying
            // silent, since the package's code/DB-row are being reverted
            // right above while its migration's effects are not.
            $migrationNote = $migrationRan
                ? ' The package\'s database migration already ran and could not be automatically reverted (no down-migration exists) - manual review of its schema changes may be needed.'
                : '';

            if ($rollbackError !== null) {
                throw new RuntimeException(
                    'Package installation failed and rollback also failed: '.$rollbackError->getMessage().$migrationNote,
                    0,
                    $error,
                );
            }

            throw new RuntimeException('Package installation failed: '.$error->getMessage().$migrationNote, 0, $error);
        }
    }
}
