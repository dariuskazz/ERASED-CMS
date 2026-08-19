<?php
declare(strict_types=1);

namespace Erased\Capabilities;

use Erased\Packages\PackageManifest;
use RuntimeException;

final class CapabilityResolver
{
    /** @var array<string,true> */
    private array $activePackages = [];

    /** @var array<string,string> */
    private array $preferences = [];

    /**
     * @param list<string> $activePackageIds
     * @param array<string,string> $preferences capability => package id
     */
    public function __construct(
        private readonly CapabilityRegistry $registry,
        array $activePackageIds = [],
        array $preferences = [],
    ) {
        $this->setActivePackages($activePackageIds);
        foreach ($preferences as $capability => $packageId) {
            $this->prefer($capability, $packageId);
        }
    }

    /** @param list<string> $packageIds */
    public function setActivePackages(array $packageIds): void
    {
        $this->activePackages = [];
        foreach ($packageIds as $packageId) {
            if (!is_string($packageId) || trim($packageId) === '') {
                throw new RuntimeException('Active package ids must be non-empty strings.');
            }
            $this->activePackages[$packageId] = true;
        }
    }

    public function activate(string $packageId): void
    {
        if (trim($packageId) === '') {
            throw new RuntimeException('Active package id cannot be empty.');
        }
        $this->activePackages[$packageId] = true;
    }

    public function deactivate(string $packageId): void
    {
        unset($this->activePackages[$packageId]);
    }

    public function prefer(string $capability, string $packageId): void
    {
        $this->assertCapabilityName($capability);
        if (trim($packageId) === '') {
            throw new RuntimeException('Preferred package id cannot be empty.');
        }
        $this->preferences[$capability] = $packageId;
    }

    public function clearPreference(string $capability): void
    {
        unset($this->preferences[$capability]);
    }

    public function has(string $capability): bool
    {
        return $this->activeProviders($capability) !== [];
    }

    public function resolve(string $capability): PackageManifest
    {
        $this->assertCapabilityName($capability);
        $providers = $this->activeProviders($capability);

        if ($providers === []) {
            throw new RuntimeException("No active package provides capability '{$capability}'.");
        }

        $preferredPackageId = $this->preferences[$capability] ?? null;
        if ($preferredPackageId !== null) {
            foreach ($providers as $provider) {
                if ($provider->id() === $preferredPackageId) {
                    return $provider;
                }
            }
            throw new RuntimeException(
                "Preferred package '{$preferredPackageId}' is not an active provider of capability '{$capability}'.",
            );
        }

        if (count($providers) > 1) {
            $ids = array_map(static fn(PackageManifest $provider): string => $provider->id(), $providers);
            sort($ids);
            throw new RuntimeException(
                "Multiple active packages provide capability '{$capability}': ".implode(', ', $ids).'. Select a preferred provider.',
            );
        }

        return $providers[0];
    }

    /** @return list<PackageManifest> */
    public function activeProviders(string $capability): array
    {
        $this->assertCapabilityName($capability);
        $providers = array_filter(
            $this->registry->providers($capability),
            fn(PackageManifest $provider): bool => isset($this->activePackages[$provider->id()]),
        );

        usort(
            $providers,
            static fn(PackageManifest $left, PackageManifest $right): int => strcmp($left->id(), $right->id()),
        );

        return array_values($providers);
    }

    /** @return array<string,PackageManifest> */
    public function resolveRequirements(PackageManifest $manifest): array
    {
        $resolved = [];
        foreach ($manifest->requiredCapabilities() as $capability) {
            $resolved[$capability] = $this->resolve($capability);
        }
        ksort($resolved);
        return $resolved;
    }

    public function assertRequirementsSatisfied(PackageManifest $manifest): void
    {
        $this->resolveRequirements($manifest);
    }

    /** @return array<string,string> */
    public function preferences(): array
    {
        $preferences = $this->preferences;
        ksort($preferences);
        return $preferences;
    }

    private function assertCapabilityName(string $capability): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $capability) !== 1) {
            throw new RuntimeException("Invalid capability name '{$capability}'.");
        }
    }
}
