<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;

final class PackageRegistry
{
    public function __construct(
        private readonly string $packagesDirectory,
        private readonly PackageValidator $validator = new PackageValidator(),
    ) {}

    /** @return array<string,array{path:string,manifest:PackageManifest}> */
    public function discover(): array
    {
        if (!is_dir($this->packagesDirectory)) {
            return [];
        }

        $packages = [];
        $entries = scandir($this->packagesDirectory);
        if ($entries === false) {
            throw new RuntimeException('Could not scan the packages directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->packagesDirectory.DIRECTORY_SEPARATOR.$entry;
            if (!is_dir($path)) {
                continue;
            }

            $result = $this->validator->validateDirectory($path);
            if (!$result['valid'] || !$result['manifest'] instanceof PackageManifest) {
                continue;
            }

            $manifest = $result['manifest'];
            $packages[$manifest->id()] = ['path' => $path, 'manifest' => $manifest];
        }

        ksort($packages);
        return $packages;
    }

    public function find(string $packageId): ?PackageManifest
    {
        return $this->discover()[$packageId]['manifest'] ?? null;
    }

    /** @return array<string,PackageManifest> */
    public function byType(string $type): array
    {
        $matches = [];
        foreach ($this->discover() as $id => $package) {
            if ($package['manifest']->type() === $type) {
                $matches[$id] = $package['manifest'];
            }
        }
        return $matches;
    }
}
