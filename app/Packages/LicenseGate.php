<?php
declare(strict_types=1);

namespace Erased\Packages;

/**
 * Extension seam for real commercial license validation. Core ships only
 * LocalLicenseGate - a genuine local activation gate with no server-side
 * authenticity check, because no real ERASED CMS license server exists
 * (see docs/COMMERCIAL-MODEL.md). A commercial distributor implements this
 * interface with real server-side validation and wires it in through
 * package_license_checker() in app/bootstrap.php - the same seam
 * PackageLifecycle implementations are swapped in through without core
 * ever naming a real one directly.
 */
interface LicenseGate
{
    /**
     * No-ops for a package whose manifest doesn't declare pricing.model
     * as 'paid'. For a paid package, throws RuntimeException when
     * $licenseKey fails this gate's rule.
     */
    public function assertLicensed(PackageManifest $manifest, ?string $licenseKey): void;
}
