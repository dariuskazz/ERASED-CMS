<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageLifecycle;
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
$workspace = sys_get_temp_dir().'/erased-lifecycle-integration-'.bin2hex(random_bytes(6));
$packagePath = $workspace.'/package';
$lifecycleDirectory = $packagePath.'/src';
$logPath = $workspace.'/hooks.log';
$table = 'installed_packages_lifecycle_test_'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

try {
    if (!mkdir($lifecycleDirectory, 0750, true) && !is_dir($lifecycleDirectory)) {
        throw new RuntimeException('Could not create lifecycle integration workspace.');
    }

    $class = 'ErasedLifecycleIntegration_'.bin2hex(random_bytes(6));
    $classFile = $lifecycleDirectory.'/Lifecycle.php';
    $classSource = "<?php\ndeclare(strict_types=1);\n"
        ."final class {$class} implements \\Erased\\Packages\\PackageLifecycle\n{\n"
        ."    private const LOG = ".var_export($logPath, true).";\n"
        ."    public function install(\\Erased\\Packages\\PackageManifest \$manifest, string \$packagePath): void {}\n"
        ."    public function enable(\\Erased\\Packages\\PackageManifest \$manifest): void { file_put_contents(self::LOG, \"enable\\n\", FILE_APPEND); }\n"
        ."    public function disable(\\Erased\\Packages\\PackageManifest \$manifest): void { file_put_contents(self::LOG, \"disable\\n\", FILE_APPEND); }\n"
        ."    public function upgrade(\\Erased\\Packages\\PackageManifest \$manifest, string \$fromVersion): void {}\n"
        ."    public function uninstall(\\Erased\\Packages\\PackageManifest \$manifest, bool \$removeData = false): void {}\n"
        ."}\n";
    if (file_put_contents($classFile, $classSource) === false) {
        throw new RuntimeException('Could not create lifecycle integration class.');
    }

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

    $manifest = new PackageManifest([
        'id' => 'erased.test-lifecycle-integration',
        'type' => 'module',
        'name' => 'Lifecycle Integration Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Lifecycle loader and executor integration fixture.',
        'dependencies' => [],
        'lifecycle' => [
            'file' => 'src/Lifecycle.php',
            'class' => $class,
        ],
    ]);

    $repository = new InstalledPackageRepository($pdo, $table);
    $repository->save($manifest, $packagePath, false);

    $executor = new PackageLifecycleExecutor(
        new PackageStateManager($repository),
        new PackageLifecycleLoader(),
    );

    $executor->enable($manifest->id());
    $enabled = $repository->find($manifest->id());
    assert(is_array($enabled) && $enabled['enabled'] === true);

    $executor->disable($manifest->id());
    $disabled = $repository->find($manifest->id());
    assert(is_array($disabled) && $disabled['enabled'] === false);

    $hooks = is_file($logPath) ? file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    assert($hooks === ['enable', 'disable']);

    fwrite(STDOUT, "Package lifecycle integration smoke test passed.\n");
    fwrite(STDOUT, "Validated registry-to-loader-to-executor lifecycle flow using {$driver}.\n");
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

    $remove = static function (string $directory) use (&$remove): void {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) && !is_link($path) ? $remove($path) : @unlink($path);
        }
        @rmdir($directory);
    };
    $remove($workspace);
}
