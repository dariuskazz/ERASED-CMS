<?php
declare(strict_types=1);

use Erased\Packages\LocalLicenseGate;
use Erased\Packages\PackageLicenseChecker;
use Erased\Packages\PackageLicenseRepository;
use Erased\Packages\PackageManifest;

$root = dirname(__DIR__);
foreach ([
    'app/Packages/PackageManifest.php',
    'app/Packages/LicenseGate.php',
    'app/Packages/LocalLicenseGate.php',
    'app/Packages/PackageLicenseRepository.php',
    'app/Packages/PackageLicenseChecker.php',
] as $file) require_once $root.'/'.$file;

function license_test_manifest(string $model, ?int $priceMinor = 4900, ?string $currency = 'EUR'): PackageManifest
{
    $data = [
        'id' => 'erased.license-test',
        'type' => 'module',
        'name' => 'License Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'License gate test fixture.',
    ];
    if ($model !== 'free') {
        $data['pricing'] = ['model' => $model, 'price_minor' => $priceMinor, 'currency' => $currency];
    }
    return new PackageManifest($data);
}

try {
    $gate = new LocalLicenseGate();
    $freeManifest = license_test_manifest('free');
    $paidManifest = license_test_manifest('paid');

    // --- LocalLicenseGate: free packages are never gated, regardless of key ---
    foreach ([null, '', 'short', 'a-perfectly-valid-license-key'] as $key) {
        $gate->assertLicensed($freeManifest, $key);
    }

    // --- LocalLicenseGate: paid packages require a non-blank, minimum-length key ---
    foreach ([null, '', '   ', 'short7'] as $badKey) {
        $threw = false;
        try {
            $gate->assertLicensed($paidManifest, $badKey);
        } catch (RuntimeException) {
            $threw = true;
        }
        if (!$threw) {
            throw new RuntimeException('LocalLicenseGate did not reject bad key: '.var_export($badKey, true));
        }
    }
    $gate->assertLicensed($paidManifest, 'a-perfectly-valid-license-key'); // must not throw

    // --- PackageLicenseRepository: sqlite-backed activate/upsert/deactivate/findKey ---
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE package_licenses_test (
        id INTEGER PRIMARY KEY AUTOINCREMENT, package_id TEXT NOT NULL UNIQUE, license_key TEXT NOT NULL,
        activated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $repository = new PackageLicenseRepository($pdo, 'package_licenses_test');

    if ($repository->findKey('erased.license-test') !== null) {
        throw new RuntimeException('findKey() returned a key before any activation.');
    }

    $repository->activate('erased.license-test', 'first-key-value');
    if ($repository->findKey('erased.license-test') !== 'first-key-value') {
        throw new RuntimeException('activate() did not persist the license key.');
    }

    // Activating again for the same package must upsert (replace), not duplicate.
    $repository->activate('erased.license-test', 'second-key-value');
    if ($repository->findKey('erased.license-test') !== 'second-key-value') {
        throw new RuntimeException('Re-activation did not upsert the license key.');
    }
    $count = (int)$pdo->query("SELECT COUNT(*) FROM package_licenses_test WHERE package_id = 'erased.license-test'")->fetchColumn();
    if ($count !== 1) {
        throw new RuntimeException('Re-activation created a duplicate row instead of upserting.');
    }

    $blankRejected = false;
    try {
        $repository->activate('erased.license-test', '   ');
    } catch (RuntimeException) {
        $blankRejected = true;
    }
    if (!$blankRejected) {
        throw new RuntimeException('activate() with a blank key did not throw.');
    }

    $repository->deactivate('erased.license-test');
    if ($repository->findKey('erased.license-test') !== null) {
        throw new RuntimeException('deactivate() did not remove the stored license key.');
    }

    // --- PackageLicenseChecker composes gate + repository correctly ---
    $checker = new PackageLicenseChecker(new LocalLicenseGate(), $repository);

    $checker->assertEnableAllowed($freeManifest); // must not throw, no key stored

    $unlicensedPaidBlocked = false;
    try {
        $checker->assertEnableAllowed($paidManifest);
    } catch (RuntimeException) {
        $unlicensedPaidBlocked = true;
    }
    if (!$unlicensedPaidBlocked) {
        throw new RuntimeException('PackageLicenseChecker allowed an unlicensed paid package to enable.');
    }

    $repository->activate('erased.license-test', 'a-perfectly-valid-license-key');
    $checker->assertEnableAllowed($paidManifest); // must not throw now

    $repository->deactivate('erased.license-test');
    $blockedAgain = false;
    try {
        $checker->assertEnableAllowed($paidManifest);
    } catch (RuntimeException) {
        $blockedAgain = true;
    }
    if (!$blockedAgain) {
        throw new RuntimeException('PackageLicenseChecker did not re-block after deactivation (live re-check failed).');
    }

    fwrite(STDOUT, "License gate smoke test passed.\n");
    fwrite(STDOUT, "Validated LocalLicenseGate free/paid behavior, PackageLicenseRepository CRUD/upsert, and PackageLicenseChecker composition including live re-check after deactivation.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
