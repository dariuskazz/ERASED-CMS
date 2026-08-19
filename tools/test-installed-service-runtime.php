<?php
declare(strict_types=1);

use Erased\Container\InstalledServiceRuntime;
use Erased\Container\PackageServiceRegistrar;
use Erased\Container\ServiceContainer;
use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';
require_once dirname(__DIR__).'/app/Container/ServiceContainer.php';
require_once dirname(__DIR__).'/app/Container/PackageServiceRegistrar.php';
require_once dirname(__DIR__).'/app/Container/InstalledServiceRuntime.php';

$table = 'installed_service_runtime_test_'.bin2hex(random_bytes(6));
$tempRoot = sys_get_temp_dir().'/erased-service-runtime-'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $path.DIRECTORY_SEPARATOR.$item;
        if (is_dir($full)) {
            removeTree($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

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
    if (!mkdir($tempRoot, 0770, true) && !is_dir($tempRoot)) {
        throw new RuntimeException('Could not create temporary package directory.');
    }

    $alphaPath = $tempRoot.'/alpha';
    $betaPath = $tempRoot.'/beta';
    mkdir($alphaPath.'/src', 0770, true);
    mkdir($betaPath.'/src', 0770, true);

    file_put_contents(
        $alphaPath.'/src/AlphaService.php',
        "<?php\ndeclare(strict_types=1);\nnamespace Erased\\TestServices;\nfinal class AlphaService { public function name(): string { return 'alpha'; } }\n",
    );
    file_put_contents(
        $betaPath.'/src/BetaService.php',
        "<?php\ndeclare(strict_types=1);\nnamespace Erased\\TestServices;\nfinal class BetaService { public function name(): string { return 'beta'; } }\n",
    );

    $alpha = new PackageManifest([
        'id' => 'erased.test-service-alpha',
        'type' => 'module',
        'name' => 'Alpha Service',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Enabled service provider.',
        'services' => [
            'test.alpha' => [
                'file' => 'src/AlphaService.php',
                'class' => 'Erased\\TestServices\\AlphaService',
                'shared' => true,
            ],
        ],
    ]);
    $beta = new PackageManifest([
        'id' => 'erased.test-service-beta',
        'type' => 'module',
        'name' => 'Beta Service',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Initially disabled service provider.',
        'services' => [
            'test.beta' => [
                'file' => 'src/BetaService.php',
                'class' => 'Erased\\TestServices\\BetaService',
                'shared' => true,
            ],
        ],
    ]);

    $repository = new InstalledPackageRepository($pdo, $table);
    $repository->save($alpha, $alphaPath, true);
    $repository->save($beta, $betaPath, false);

    $container = new ServiceContainer();
    $coreService = new stdClass();
    $container->instance('core.service', $coreService);

    $runtime = new InstalledServiceRuntime($repository, $container, new PackageServiceRegistrar());
    $runtime->refresh();

    assert($container->has('test.alpha') === true);
    assert($container->has('test.beta') === false);
    assert($runtime->owner('test.alpha') === 'erased.test-service-alpha');
    assert($container->get('test.alpha')->name() === 'alpha');
    assert($container->get('core.service') === $coreService);

    $repository->setEnabled('erased.test-service-beta', true);
    $runtime->refresh();
    assert($container->has('test.alpha') === true);
    assert($container->has('test.beta') === true);
    assert($container->get('test.beta')->name() === 'beta');

    $repository->setEnabled('erased.test-service-alpha', false);
    $runtime->refresh();
    assert($container->has('test.alpha') === false);
    assert($container->has('test.beta') === true);
    assert($container->has('core.service') === true);

    $conflictManifest = new PackageManifest([
        'id' => 'erased.test-service-conflict',
        'type' => 'module',
        'name' => 'Conflicting Service',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Declares an existing core service id.',
        'services' => [
            'core.service' => [
                'file' => 'src/BetaService.php',
                'class' => 'Erased\\TestServices\\BetaService',
                'shared' => true,
            ],
        ],
    ]);
    $repository->save($conflictManifest, $betaPath, true);

    $conflictDetected = false;
    try {
        $runtime->refresh();
    } catch (RuntimeException $error) {
        $conflictDetected = str_contains($error->getMessage(), "cannot replace existing service 'core.service'");
    }
    assert($conflictDetected === true);
    assert($container->has('test.beta') === false);
    assert($container->get('core.service') === $coreService);
    assert($runtime->owners() === []);

    fwrite(STDOUT, "Installed service runtime smoke test passed.\n");
    fwrite(STDOUT, "Validated enabled package services, refresh cleanup, ownership, disabled isolation, and conflict rollback using {$driver}.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    removeTree($tempRoot);
    if ($pdo instanceof PDO && $driver === 'mysql') {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
        } catch (Throwable) {
        }
    }
}
