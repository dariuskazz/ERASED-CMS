<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';

$root = dirname(__DIR__);
$table = 'installed_packages_test_'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

try {
    if (extension_loaded('pdo_sqlite')) {
        $driver = 'sqlite';
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE `'.$table.'` ('
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
            .')'
        );
    } else {
        $driver = 'mysql';
        require_once $root.'/app/bootstrap.php';
        $pdo = db();
        $pdo->exec(
            'CREATE TABLE `'.$table.'` ('
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
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    $repository = new InstalledPackageRepository($pdo, $table);

    $manifestV1 = new PackageManifest([
        'id' => 'erased.test-registry',
        'type' => 'module',
        'name' => 'Registry Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Installed package repository smoke-test fixture.',
        'dependencies' => [],
    ]);

    $repository->save($manifestV1, '/packages/erased.test-registry');
    $stored = $repository->find('erased.test-registry');
    assert(is_array($stored));
    assert($stored['version'] === '1.0.0');
    assert($stored['enabled'] === false);
    assert($stored['manifest']['id'] === 'erased.test-registry');

    $repository->setEnabled('erased.test-registry', true);
    $enabled = $repository->find('erased.test-registry');
    assert(is_array($enabled) && $enabled['enabled'] === true);

    $manifestV2 = new PackageManifest([
        'id' => 'erased.test-registry',
        'type' => 'module',
        'name' => 'Registry Test',
        'version' => '1.1.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Updated installed package repository fixture.',
        'dependencies' => [],
    ]);

    $repository->save($manifestV2, '/packages/erased.test-registry', true);
    $updated = $repository->find('erased.test-registry');
    assert(is_array($updated));
    assert($updated['version'] === '1.1.0');
    assert($updated['enabled'] === true);
    assert(count($repository->all('module')) === 1);
    assert(count($repository->all('theme')) === 0);

    $repository->remove('erased.test-registry');
    assert($repository->find('erased.test-registry') === null);

    fwrite(STDOUT, "Installed package repository smoke test passed.\n");
    fwrite(STDOUT, "Validated save, update, enable, filter, and removal operations using {$driver}.\n");
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
