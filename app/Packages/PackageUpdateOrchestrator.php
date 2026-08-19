<?php
declare(strict_types=1);

namespace Erased\Packages;

use Erased\Events\EventDispatcher;
use Erased\Events\PackageEvent;
use RuntimeException;
use Throwable;

final class PackageUpdateOrchestrator
{
    public function __construct(
        private readonly PackageArchiveStager $stager,
        private readonly PackageInstaller $installer,
        private readonly InstalledPackageRepository $repository,
        private readonly PackageLifecycleLoader $lifecycleLoader,
        private readonly PackageDependencyResolver $dependencyResolver,
        private readonly PackageMigrationRunner $migrationRunner,
        private readonly ?EventDispatcher $events = null,
        private readonly ?PackageIntegrityChecker $integrityChecker = null,
    ) {
    }

    /** @return array{manifest:PackageManifest,from_version:string,package_directory:string} */
    public function updateArchive(
        string $archivePath,
        string $stagingRoot,
        string $packagesRoot,
        string $rollbackRoot,
    ): array {
        $stage = null;
        $installation = null;

        try {
            $stage = $this->stager->stage($archivePath, $stagingRoot);
            $manifest = $stage['manifest'];

            $existing = $this->repository->find($manifest->id());
            if ($existing === null) {
                throw new RuntimeException(
                    "Package '{$manifest->id()}' is not currently installed. Install it first instead of updating."
                );
            }
            $fromVersion = (string) $existing['version'];

            if (version_compare($manifest->version(), $fromVersion, '<=')) {
                throw new RuntimeException(
                    "Uploaded version {$manifest->version()} is not newer than the installed version {$fromVersion}."
                );
            }

            $this->dependencyResolver->resolve($manifest, [], $this->installedVersionsExcept($manifest->id()));

            $installation = $this->installer->install($stage['directory'], $packagesRoot, $rollbackRoot);
            $stage = null;

            $this->migrationRunner->run($manifest->id(), $installation['package_directory']);

            $lifecycle = $this->lifecycleLoader->load($manifest, $installation['package_directory']);
            $lifecycle->upgrade($manifest, $fromVersion);

            $this->repository->save($manifest, $installation['package_directory'], (bool) $existing['enabled']);

            if ($this->integrityChecker !== null) {
                $this->repository->recordIntegrityManifest(
                    $manifest->id(),
                    $this->integrityChecker->computeManifest($installation['package_directory']),
                );
            }

            $this->events?->dispatch('package.updated', new PackageEvent($manifest, $installation['package_directory'], ['from_version' => $fromVersion]));

            return [
                'manifest' => $manifest,
                'from_version' => $fromVersion,
                'package_directory' => $installation['package_directory'],
            ];
        } catch (Throwable $error) {
            $rollbackError = null;

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

            if ($rollbackError !== null) {
                throw new RuntimeException(
                    'Package update failed and rollback also failed: '.$rollbackError->getMessage(),
                    0,
                    $error,
                );
            }

            throw new RuntimeException('Package update failed: '.$error->getMessage(), 0, $error);
        }
    }

    /** @return array<string,string> */
    private function installedVersionsExcept(string $excludeId): array
    {
        $versions = [];
        foreach ($this->repository->all() as $row) {
            if ($row['package_id'] === $excludeId) {
                continue;
            }
            $versions[$row['package_id']] = (string) $row['version'];
        }
        return $versions;
    }
}
