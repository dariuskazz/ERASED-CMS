<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageLifecycleExecutor;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageStateManager;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycle.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';
require_once dirname(__DIR__).'/app/Packages/PackageStateManager.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycleLoader.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycleExecutor.php';

$root = dirname(__DIR__);
$table = 'installed_packages_lifecycle_test_'.bin2hex(random_bytes(6));
$tempRoot = sys_get_temp_dir().'/erased-lifecycle-executor-'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory.DIRECTORY_SEPARATOR.$item;
        is_dir($path) && !is_link($path) ? $removeDirectory($path) : @unlink($path);
    }
    @rmdir($directory);
};

$writePackage = static function (
    string $directory,
    string $class,
    string $enableBody,
    string $disableBody,
): PackageManifest {
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create lifecycle test package directory.');
    }

    $namespace = substr($class, 0, (int)strrpos($class, '\\'));
    $shortClass = substr($class, (int)strrpos($class, '\\') + 1);
    $code = "<?php\ndeclare(strict_types=1);\nnamespace {$namespace};\n"
        ."use Erased\\Packages\\PackageLifecycle;\n"
        ."use Erased\\Packages\\PackageManifest;\n"
        ."final class {$shortClass} implements PackageLifecycle {\n"
        ."public function install(PackageManifest \$manifest, string \$packagePath): void {}\n"
        ."public function enable(PackageManifest \$manifest): void { {$enableBody} }\n"
        ."public function disable(PackageManifest \$manifest): void { {$disableBody} }\n"
        ."public function upgrade(PackageManifest \$manifest, string \$fromVersion): void {}\n"
        ."public function uninstall(PackageManifest \$manifest, bool \$removeData = false): void {}\n"
        ."}\n";
    file_put_contents($directory.'/Lifecycle.php', $code);

    return new PackageManifest([
        'id' => 'erased.test-lifecycle',
        'type' => 'module',
        'name' => 'Lifecycle Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Lifecycle executor smoke-test fixture.',
        'dependencies' => [],
        'lifecycle' => [
            'file' => 'Lifecycle.php',
            'class' => $class,
        ],
    ]);
};

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
        require_once $root.'/app/bootstrap.php';
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
    $stateManager = new PackageStateManager($repository);
    $executor = new PackageLifecycleExecutor($stateManager, new PackageLifecycleLoader());

    $successPath = $tempRoot.'/success';
    $eventsPath = $successPath.'/events.log';
    $manifest = $writePackage(
        $successPath,
        'Erased\\Tests\\LifecycleExecutorSuccess',
        "file_put_contents(".var_export($eventsPath, true).", \"enable\\n\", FILE_APPEND);",
        "file_put_contents(".var_export($eventsPath, true).", \"disable\\n\", FILE_APPEND);",
    );
    $repository->save($manifest, $successPath, false);

    $executor->enable('erased.test-lifecycle');
    assert($repository->find('erased.test-lifecycle')['enabled'] === true);
    assert(file_get_contents($eventsPath) === "enable\n");

    $executor->disable('erased.test-lifecycle');
    assert($repository->find('erased.test-lifecycle')['enabled'] === false);
    assert(file_get_contents($eventsPath) === "enable\ndisable\n");

    $failingEnablePath = $tempRoot.'/failing-enable';
    $failingEnableManifest = $writePackage(
        $failingEnablePath,
        'Erased\\Tests\\LifecycleExecutorFailEnable',
        "throw new \\RuntimeException('simulated enable failure');",
        '',
    );
    $repository->save($failingEnableManifest, $failingEnablePath, false);

    $failed = false;
    try {
        $executor->enable('erased.test-lifecycle');
    } catch (RuntimeException $error) {
        $failed = str_contains($error->getMessage(), 'enable hook failed');
    }
    assert($failed === true);
    assert($repository->find('erased.test-lifecycle')['enabled'] === false);

    $failingDisablePath = $tempRoot.'/failing-disable';
    $failingDisableManifest = $writePackage(
        $failingDisablePath,
        'Erased\\Tests\\LifecycleExecutorFailDisable',
        '',
        "throw new \\RuntimeException('simulated disable failure');",
    );
    $repository->save($failingDisableManifest, $failingDisablePath, true);

    $failed = false;
    try {
        $executor->disable('erased.test-lifecycle');
    } catch (RuntimeException $error) {
        $failed = str_contains($error->getMessage(), 'disable hook failed');
    }
    assert($failed === true);
    assert($repository->find('erased.test-lifecycle')['enabled'] === true);

    fwrite(STDOUT, "Package lifecycle executor smoke test passed.\n");
    fwrite(STDOUT, "Validated loader-backed lifecycle hooks and state rollback using {$driver}.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $removeDirectory($tempRoot);
    if ($pdo instanceof PDO && $driver === 'mysql') {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
        } catch (Throwable) {
        }
    }
}
