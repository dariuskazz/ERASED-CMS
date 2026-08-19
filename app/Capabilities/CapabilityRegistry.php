<?php
declare(strict_types=1);

namespace Erased\Capabilities;

use Erased\Packages\PackageManifest;
use RuntimeException;

final class CapabilityRegistry
{
    /** @var array<string,array<string,PackageManifest>> */
    private array $providers = [];

    /** @var array<string,PackageManifest> */
    private array $packages = [];

    public function register(PackageManifest $manifest): void
    {
        $this->packages[$manifest->id()] = $manifest;

        foreach ($manifest->provides() as $capability) {
            $this->providers[$capability][$manifest->id()] = $manifest;
        }
    }

    public function unregister(string $packageId): void
    {
        unset($this->packages[$packageId]);

        foreach ($this->providers as $capability => $providers) {
            unset($this->providers[$capability][$packageId]);
            if ($this->providers[$capability] === []) {
                unset($this->providers[$capability]);
            }
        }
    }

    public function has(string $capability): bool
    {
        return isset($this->providers[$capability]) && $this->providers[$capability] !== [];
    }

    /** @return list<PackageManifest> */
    public function providers(string $capability): array
    {
        return array_values($this->providers[$capability] ?? []);
    }

    public function preferredProvider(string $capability, ?string $preferredPackageId = null): PackageManifest
    {
        $providers = $this->providers($capability);
        if ($providers === []) {
            throw new RuntimeException("No package provides capability '{$capability}'.");
        }

        if ($preferredPackageId !== null) {
            foreach ($providers as $provider) {
                if ($provider->id() === $preferredPackageId) {
                    return $provider;
                }
            }
            throw new RuntimeException(
                "Preferred package '{$preferredPackageId}' does not provide capability '{$capability}'.",
            );
        }

        if (count($providers) > 1) {
            $ids = array_map(static fn(PackageManifest $manifest): string => $manifest->id(), $providers);
            sort($ids);
            throw new RuntimeException(
                "Multiple packages provide capability '{$capability}': ".implode(', ', $ids).'. Select a preferred provider.',
            );
        }

        return $providers[0];
    }

    /** @return list<string> */
    public function missingRequirements(PackageManifest $manifest): array
    {
        $missing = [];
        foreach ($manifest->requiredCapabilities() as $capability) {
            if (!$this->has($capability)) {
                $missing[] = $capability;
            }
        }
        sort($missing);
        return $missing;
    }

    public function assertRequirementsSatisfied(PackageManifest $manifest): void
    {
        $missing = $this->missingRequirements($manifest);
        if ($missing !== []) {
            throw new RuntimeException(
                "Package '{$manifest->id()}' requires unavailable capabilities: ".implode(', ', $missing).'.',
            );
        }
    }

    /** @return array<string,list<string>> */
    public function map(): array
    {
        $result = [];
        foreach ($this->providers as $capability => $providers) {
            $ids = array_keys($providers);
            sort($ids);
            $result[$capability] = $ids;
        }
        ksort($result);
        return $result;
    }
}
