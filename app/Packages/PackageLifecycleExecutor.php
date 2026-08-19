<?php
declare(strict_types=1);

namespace Erased\Packages;

use Erased\Capabilities\CapabilityResolver;
use Erased\Events\EventDispatcher;
use Erased\Events\PackageEvent;
use RuntimeException;
use Throwable;

final class PackageLifecycleExecutor
{
    public function __construct(
        private readonly PackageStateManager $stateManager,
        private readonly PackageLifecycleLoader $loader,
        private readonly ?InstalledPackageRepository $healthRepository = null,
        private readonly ?CapabilityResolver $capabilities = null,
        private readonly ?EventDispatcher $events = null,
        private readonly ?PackageLicenseChecker $license = null,
    ) {
    }

    public function enable(string $packageId): void
    {
        $package = $this->stateManager->assertCanEnable($packageId);
        if ($package['enabled']) {
            return;
        }

        $manifest = $this->manifestFromPackage($package);
        $this->capabilities?->assertRequirementsSatisfied($manifest);
        $this->license?->assertEnableAllowed($manifest);
        $lifecycle = $this->loader->load($manifest, $this->installedPath($package));

        try {
            $lifecycle->enable($manifest);
            $this->stateManager->persistEnabled($packageId, true);
            $this->healthRepository?->clearHealthError($packageId);
            $this->events?->dispatch('package.enabled', new PackageEvent($manifest, $this->installedPath($package)));
            if ($manifest->provides() !== []) {
                $this->events?->dispatch('capability.provider.changed', new PackageEvent($manifest, $this->installedPath($package), ['action' => 'enabled']));
            }
        } catch (Throwable $error) {
            $this->stateManager->persistEnabled($packageId, false);
            $this->healthRepository?->recordHealthError($packageId, "enable hook failed: {$error->getMessage()}");
            throw new RuntimeException(
                "Package '{$packageId}' enable hook failed: {$error->getMessage()}",
                0,
                $error,
            );
        }
    }

    public function disable(string $packageId): void
    {
        $package = $this->stateManager->assertCanDisable($packageId);
        if (!$package['enabled']) {
            return;
        }

        $manifest = $this->manifestFromPackage($package);
        $lifecycle = $this->loader->load($manifest, $this->installedPath($package));

        try {
            $lifecycle->disable($manifest);
            $this->stateManager->persistEnabled($packageId, false);
            $this->healthRepository?->clearHealthError($packageId);
            $this->events?->dispatch('package.disabled', new PackageEvent($manifest, $this->installedPath($package)));
            if ($manifest->provides() !== []) {
                $this->events?->dispatch('capability.provider.changed', new PackageEvent($manifest, $this->installedPath($package), ['action' => 'disabled']));
            }
        } catch (Throwable $error) {
            $this->stateManager->persistEnabled($packageId, true);
            $this->healthRepository?->recordHealthError($packageId, "disable hook failed: {$error->getMessage()}");
            throw new RuntimeException(
                "Package '{$packageId}' disable hook failed: {$error->getMessage()}",
                0,
                $error,
            );
        }
    }

    /** @param array<string,mixed> $package */
    private function manifestFromPackage(array $package): PackageManifest
    {
        $manifest = $package['manifest'] ?? null;
        if (!is_array($manifest)) {
            throw new RuntimeException('Installed package manifest is unavailable.');
        }

        return new PackageManifest($manifest);
    }

    /** @param array<string,mixed> $package */
    private function installedPath(array $package): string
    {
        $path = $package['installed_path'] ?? null;
        if (!is_string($path) || trim($path) === '') {
            throw new RuntimeException('Installed package path is unavailable.');
        }

        return $path;
    }
}
