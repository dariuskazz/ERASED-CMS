<?php
declare(strict_types=1);

namespace Erased\Events;

use Erased\Packages\PackageManifest;

final class PackageEvent
{
    /** @param array<string,mixed> $context */
    public function __construct(
        private readonly PackageManifest $manifest,
        private readonly string $packagePath,
        private readonly array $context = [],
    ) {
    }

    public function manifest(): PackageManifest
    {
        return $this->manifest;
    }

    public function packageId(): string
    {
        return $this->manifest->id();
    }

    public function packagePath(): string
    {
        return $this->packagePath;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
