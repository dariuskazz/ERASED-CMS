<?php
declare(strict_types=1);

namespace Erased\Container;

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;
use RuntimeException;
use Throwable;

final class InstalledServiceRuntime
{
    /** @var array<string,string> service id => package id */
    private array $owners = [];

    public function __construct(
        private readonly InstalledPackageRepository $packages,
        private readonly ServiceContainer $container,
        private readonly PackageServiceRegistrar $registrar,
    ) {
    }

    public function refresh(): void
    {
        $this->clearManagedServices();

        try {
            foreach ($this->packages->all() as $row) {
                if (($row['enabled'] ?? false) !== true) {
                    continue;
                }

                $manifestData = $row['manifest'] ?? null;
                $installedPath = $row['installed_path'] ?? null;
                if (!is_array($manifestData) || !is_string($installedPath) || trim($installedPath) === '') {
                    throw new RuntimeException('Installed package registry contains invalid service runtime metadata.');
                }

                $manifest = new PackageManifest($manifestData);
                $serviceIds = $this->declaredServiceIds($manifest);

                foreach ($serviceIds as $serviceId) {
                    if (isset($this->owners[$serviceId])) {
                        throw new RuntimeException(
                            "Enabled packages '{$this->owners[$serviceId]}' and '{$manifest->id()}' both declare service '{$serviceId}'.",
                        );
                    }
                    if ($this->container->has($serviceId)) {
                        throw new RuntimeException(
                            "Package '{$manifest->id()}' cannot replace existing service '{$serviceId}'.",
                        );
                    }
                }

                $this->registrar->register($this->container, $manifest, $installedPath);
                foreach ($serviceIds as $serviceId) {
                    $this->owners[$serviceId] = $manifest->id();
                }
            }
        } catch (Throwable $error) {
            $this->clearManagedServices();
            throw $error;
        }
    }

    public function owner(string $serviceId): ?string
    {
        return $this->owners[$serviceId] ?? null;
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    /** @return array<string,string> */
    public function owners(): array
    {
        $owners = $this->owners;
        ksort($owners);
        return $owners;
    }

    /** @return list<string> */
    private function declaredServiceIds(PackageManifest $manifest): array
    {
        $services = $manifest->all()['services'] ?? [];
        if ($services === []) {
            return [];
        }
        if (!is_array($services)) {
            throw new RuntimeException("Package '{$manifest->id()}' services declaration must be an object.");
        }

        $ids = [];
        foreach (array_keys($services) as $serviceId) {
            if (!is_string($serviceId) || trim($serviceId) === '') {
                throw new RuntimeException("Package '{$manifest->id()}' contains an invalid service id.");
            }
            $ids[] = $serviceId;
        }
        sort($ids);
        return $ids;
    }

    private function clearManagedServices(): void
    {
        foreach (array_keys($this->owners) as $serviceId) {
            $this->container->remove($serviceId);
        }
        $this->owners = [];
    }
}
