<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;

/**
 * Core's real default LicenseGate. This is NOT cryptographic or
 * server-validated authenticity checking - ERASED CMS does not operate a
 * license server, so there is nothing to validate a key's authenticity
 * against (see docs/COMMERCIAL-MODEL.md). It genuinely blocks enabling a
 * paid package until a locally-stored, minimally-shaped key is activated;
 * a free package is completely unaffected. A commercial distributor
 * wanting real authenticity validation implements LicenseGate directly
 * and wires it in through package_license_checker() - core never assumes
 * one exists.
 */
final class LocalLicenseGate implements LicenseGate
{
    public const MIN_LICENSE_KEY_LENGTH = 8;

    public function assertLicensed(PackageManifest $manifest, ?string $licenseKey): void
    {
        if (!$manifest->isPaid()) {
            return;
        }

        $key = trim((string)$licenseKey);
        if ($key === '' || strlen($key) < self::MIN_LICENSE_KEY_LENGTH) {
            throw new RuntimeException(
                "'{$manifest->name()}' is a paid package and requires an activated license key ".
                '(at least '.self::MIN_LICENSE_KEY_LENGTH." characters) before it can be enabled. ".
                "Activate one from this package's detail page."
            );
        }
    }
}
