<?php
declare(strict_types=1);

namespace Erased\Capabilities;

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;
use RuntimeException;
use Throwable;

final class InstalledCapabilityRuntime
{
    private CapabilityRegistry $registry;
    private CapabilityResolver $resolver;

    /** @param array<string,string> $preferences capability => package id */
    public function __construct(
        private readonly InstalledPackageRepository $packages,
        private array $preferences = [],
    ) {
        $this->refresh();
    }

    public function refresh(): void
    {
        $registry = new CapabilityRegistry();
        $activePackageIds = [];

        foreach ($this->packages->all() as $package) {
            $manifestData = $package['manifest'] ?? null;
            if (!is_array($manifestData)) {
                throw new RuntimeException('Installed package manifest is unavailable.');
            }

            try {
                $manifest = new PackageManifest($manifestData);
            } catch (Throwable $error) {
                $packageId = (string)($package['package_id'] ?? 'unknown');
                throw new RuntimeException(
                    "Installed package '{$packageId}' has an invalid manifest: {$error->getMessage()}",
                    0,
                    $error,
                );
            }

            $registry->register($manifest);
            if (($package['enabled'] ?? false) === true) {
                $activePackageIds[] = $manifest->id();
            }
        }

        $this->registry = $registry;
        $this->resolver = new CapabilityResolver($registry, $activePackageIds, $this->preferences);
    }

    public function registry(): CapabilityRegistry
    {
        return $this->registry;
    }

    public function resolver(): CapabilityResolver
    {
        return $this->resolver;
    }

    public function prefer(string $capability, string $packageId): void
    {
        $this->resolver->prefer($capability, $packageId);
        $this->preferences = $this->resolver->preferences();
    }

    public function clearPreference(string $capability): void
    {
        $this->resolver->clearPreference($capability);
        $this->preferences = $this->resolver->preferences();
    }

    /** @return array<string,string> */
    public function preferences(): array
    {
        return $this->preferences;
    }
}
