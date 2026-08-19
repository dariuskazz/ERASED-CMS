<?php
declare(strict_types=1);

use Erased\Capabilities\InstalledCapabilityRuntime;
use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityRegistry.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityResolver.php';
require_once dirname(__DIR__).'/app/Capabilities/InstalledCapabilityRuntime.php';

$table = 'installed_capability_runtime_test_'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

try {
    if (extension_loaded('pdo_sqlite')) {
        $driver = 'sqlite';
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = 'CREATE TABLE `'.$table.'` ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            .'package_id TEXT NOT NULL UNIQUE,'
            .'package_type TEXT NOT NULL,'
            .'name TEXT NOT NULL,'
            .'version TEXT NOT NULL,'
            .'enabled INTEGER NOT NULL DEFAULT 0,'
            ."health_status TEXT NOT NULL DEFAULT 'ok',"
            .'last_error TEXT NULL,'
            .'last_error_at TEXT NULL,'
            .'installed_path TEXT NOT NULL,'
            .'manifest_json TEXT NOT NULL,'
            .'integrity_manifest_json TEXT NULL,'
            ."integrity_status TEXT NOT NULL DEFAULT 'unknown',"
            .'integrity_checked_at TEXT NULL,'
            .'installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            .')';
    } else {
        $driver = 'mysql';
        require_once dirname(__DIR__).'/app/bootstrap.php';
        $pdo = db();
        $schema = 'CREATE TABLE `'.$table.'` ('
            .'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            .'package_id VARCHAR(190) NOT NULL UNIQUE,'
            .'package_type VARCHAR(40) NOT NULL,'
            .'name VARCHAR(190) NOT NULL,'
            .'version VARCHAR(64) NOT NULL,'
            .'enabled TINYINT(1) NOT NULL DEFAULT 0,'
            ."health_status VARCHAR(20) NOT NULL DEFAULT 'ok',"
            .'last_error TEXT NULL,'
            .'last_error_at DATETIME NULL,'
            .'installed_path VARCHAR(500) NOT NULL,'
            .'manifest_json LONGTEXT NOT NULL,'
            .'integrity_manifest_json LONGTEXT NULL,'
            ."integrity_status VARCHAR(20) NOT NULL DEFAULT 'unknown',"
            .'integrity_checked_at DATETIME NULL,'
            .'installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    $pdo->exec($schema);
    $repository = new InstalledPackageRepository($pdo, $table);

    $media = new PackageManifest([
        'id' => 'erased.test-media',
        'type' => 'module',
        'name' => 'Media',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Media provider.',
        'provides' => ['media'],
    ]);
    $galleryLite = new PackageManifest([
        'id' => 'erased.test-gallery-lite',
        'type' => 'module',
        'name' => 'Gallery Lite',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Gallery provider.',
        'provides' => ['gallery'],
        'requires_capabilities' => ['media'],
    ]);
    $galleryPro = new PackageManifest([
        'id' => 'erased.test-gallery-pro',
        'type' => 'module',
        'name' => 'Gallery Pro',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Alternative gallery provider.',
        'provides' => ['gallery'],
        'requires_capabilities' => ['media'],
    ]);

    $repository->save($media, '/packages/media', true);
    $repository->save($galleryLite, '/packages/gallery-lite', true);
    $repository->save($galleryPro, '/packages/gallery-pro', false);

    $runtime = new InstalledCapabilityRuntime($repository);
    assert($runtime->registry()->has('gallery') === true);
    assert(count($runtime->registry()->providers('gallery')) === 2);
    assert($runtime->resolver()->resolve('gallery')->id() === 'erased.test-gallery-lite');
    $runtime->resolver()->assertRequirementsSatisfied($galleryLite);

    $repository->setEnabled('erased.test-gallery-pro', true);
    $runtime->refresh();

    $conflictDetected = false;
    try {
        $runtime->resolver()->resolve('gallery');
    } catch (RuntimeException $error) {
        $conflictDetected = str_contains($error->getMessage(), 'Multiple active packages');
    }
    assert($conflictDetected === true);

    $runtime->prefer('gallery', 'erased.test-gallery-pro');
    assert($runtime->resolver()->resolve('gallery')->id() === 'erased.test-gallery-pro');

    $repository->setEnabled('erased.test-media', false);
    $runtime->refresh();

    $missingDetected = false;
    try {
        $runtime->resolver()->assertRequirementsSatisfied($galleryPro);
    } catch (RuntimeException $error) {
        $missingDetected = str_contains($error->getMessage(), "No active package provides capability 'media'");
    }
    assert($missingDetected === true);

    fwrite(STDOUT, "Installed capability runtime smoke test passed.\n");
    fwrite(STDOUT, "Validated database-backed providers, enabled state, preferences, refresh, conflicts, and requirements using {$driver}.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    if ($pdo instanceof PDO && $driver === 'mysql') {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
        } catch (Throwable) {
        }
    }
}
