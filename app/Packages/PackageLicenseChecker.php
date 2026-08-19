<?php
declare(strict_types=1);

namespace Erased\Packages;

final class PackageLicenseChecker
{
    public function __construct(
        private readonly LicenseGate $gate,
        private readonly PackageLicenseRepository $repository,
    ) {
    }

    /** @throws \RuntimeException when $manifest is paid and no valid license key is activated */
    public function assertEnableAllowed(PackageManifest $manifest): void
    {
        $this->gate->assertLicensed($manifest, $this->repository->findKey($manifest->id()));
    }
}
