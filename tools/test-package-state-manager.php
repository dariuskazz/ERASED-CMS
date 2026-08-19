<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageStateManager;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';
require_once dirname(__DIR__).'/app/Packages/PackageStateManager.php';

$root = dirname(__DIR__);
$table = 'installed_packages_state_test_'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

try {
    if (extension_loaded('pdo_sqlite')) {
        $driver = 'sqlite';
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        $driver = 'mysql';
        require_once $root.'/app/bootstrap.php';
        $pdo = db();
    }

    $schema = $driver === 'sqlite'
        ? 'CREATE TABLE `'.$table.'` ('
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
        : 'CREATE TABLE `'.$table.'` ('
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
    $pdo->exec($schema);

    $repository = new InstalledPackageRepository($pdo, $table);
    $manager = new PackageStateManager($repository);

    $core = new PackageManifest([
        'id' => 'erased.test-core',
        'type' => 'module',
        'name' => 'Test Core',
        'version' => '1.2.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'State manager dependency fixture.',
        'dependencies' => [],
    ]);
    $feature = new PackageManifest([
        'id' => 'erased.test-feature',
        'type' => 'module',
        'name' => 'Test Feature',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'State manager dependent fixture.',
        'dependencies' => ['erased.test-core ^1.0.0'],
    ]);

    $repository->save($core, '/packages/erased.test-core', false);
    $repository->save($feature, '/packages/erased.test-feature', false);

    $failed = false;
    try {
        $manager->enable('erased.test-feature');
    } catch (RuntimeException $error) {
        $failed = str_contains($error->getMessage(), 'is disabled');
    }
    assert($failed === true);

    $manager->enable('erased.test-core');
    $manager->enable('erased.test-feature');
    assert($repository->find('erased.test-core')['enabled'] === true);
    assert($repository->find('erased.test-feature')['enabled'] === true);

    $blocked = false;
    try {
        $manager->disable('erased.test-core');
    } catch (RuntimeException $error) {
        $blocked = str_contains($error->getMessage(), 'depends on it');
    }
    assert($blocked === true);

    $manager->disable('erased.test-feature');
    $manager->disable('erased.test-core');
    assert($repository->find('erased.test-feature')['enabled'] === false);
    assert($repository->find('erased.test-core')['enabled'] === false);

    fwrite(STDOUT, "Package state manager smoke test passed.\n");
    fwrite(STDOUT, "Validated dependency-safe enable and disable operations using {$driver}.\n");
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
