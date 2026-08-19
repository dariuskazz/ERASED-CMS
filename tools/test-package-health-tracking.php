<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageLifecycleExecutor;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageStateManager;

$root = dirname(__DIR__);
foreach ([
    'app/Packages/PackageManifest.php', 'app/Packages/PackageLifecycle.php',
    'app/Packages/InstalledPackageRepository.php', 'app/Packages/PackageStateManager.php',
    'app/Packages/PackageLifecycleLoader.php', 'app/Packages/PackageLifecycleExecutor.php',
] as $file) require_once $root.'/'.$file;

$workspace = sys_get_temp_dir().'/erased-package-health-'.bin2hex(random_bytes(6));
$packagePath = $workspace.'/package';
$lifecycleDirectory = $packagePath.'/src';

$remove = static function (string $directory) use (&$remove): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory.'/'.$item;
        is_dir($path) && !is_link($path) ? $remove($path) : @unlink($path);
    }
    @rmdir($directory);
};

try {
    if (!mkdir($lifecycleDirectory, 0750, true) && !is_dir($lifecycleDirectory)) {
        throw new RuntimeException('Could not create health-tracking test workspace.');
    }

    // A flag file, not a static property, controls whether enable() throws:
    // the class isn't loaded yet when the test needs to arm the failure, so
    // there is no PHP-level handle to toggle before that first load happens.
    $failFlag = $workspace.'/fail-enable.flag';
    $class = 'ErasedHealthTracking_'.bin2hex(random_bytes(6));
    $classFile = $lifecycleDirectory.'/Lifecycle.php';
    $classSource = "<?php\ndeclare(strict_types=1);\n"
        ."final class {$class} implements \\Erased\\Packages\\PackageLifecycle\n{\n"
        ."    private const FAIL_FLAG = ".var_export($failFlag, true).";\n"
        ."    public function install(\\Erased\\Packages\\PackageManifest \$manifest, string \$packagePath): void {}\n"
        ."    public function enable(\\Erased\\Packages\\PackageManifest \$manifest): void { if (is_file(self::FAIL_FLAG)) throw new \\RuntimeException('enable boom'); }\n"
        ."    public function disable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function upgrade(\\Erased\\Packages\\PackageManifest \$manifest, string \$fromVersion): void {}\n"
        ."    public function uninstall(\\Erased\\Packages\\PackageManifest \$manifest, bool \$removeData = false): void {}\n"
        ."}\n";
    if (file_put_contents($classFile, $classSource) === false) {
        throw new RuntimeException('Could not write health-tracking fixture lifecycle class.');
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE installed_packages_health_test (
        id INTEGER PRIMARY KEY AUTOINCREMENT, package_id TEXT NOT NULL UNIQUE, package_type TEXT NOT NULL,
        name TEXT NOT NULL, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
        health_status TEXT NOT NULL DEFAULT 'ok', last_error TEXT NULL, last_error_at TEXT NULL,
        installed_path TEXT NOT NULL, manifest_json TEXT NOT NULL, integrity_manifest_json TEXT NULL, integrity_status TEXT NOT NULL DEFAULT 'unknown', integrity_checked_at TEXT NULL,
        installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");

    $manifest = new PackageManifest([
        'id' => 'erased.health-tracking-test', 'type' => 'module', 'name' => 'Health Tracking Test',
        'version' => '1.0.0', 'requires' => '0.3.0', 'author' => 'ERASED CMS',
        'description' => 'Package health tracking smoke test.', 'dependencies' => [],
        'lifecycle' => ['file' => 'src/Lifecycle.php', 'class' => $class],
    ]);

    $repository = new InstalledPackageRepository($pdo, 'installed_packages_health_test');
    $repository->save($manifest, $packagePath, false);

    $executor = new PackageLifecycleExecutor(new PackageStateManager($repository), new PackageLifecycleLoader(), $repository);

    // --- A failing enable() hook must record health_status=error with a message ---
    touch($failFlag);
    $failed = false;
    try {
        $executor->enable('erased.health-tracking-test');
    } catch (RuntimeException) {
        $failed = true;
    }
    if (!$failed) {
        throw new RuntimeException('Executor did not surface the failing enable() hook.');
    }
    $afterFailure = $repository->find('erased.health-tracking-test');
    if ($afterFailure['health_status'] !== 'error') {
        throw new RuntimeException('Failed enable() did not record health_status=error.');
    }
    if (!str_contains((string)$afterFailure['last_error'], 'enable boom')) {
        throw new RuntimeException('Failed enable() did not record the underlying error message.');
    }
    if ($afterFailure['last_error_at'] === null) {
        throw new RuntimeException('Failed enable() did not record when the error happened.');
    }
    if ($afterFailure['enabled'] !== false) {
        throw new RuntimeException('A failed enable() incorrectly left the package enabled.');
    }

    // --- A subsequent successful enable() must clear the health error ---
    @unlink($failFlag);
    $executor->enable('erased.health-tracking-test');
    $afterSuccess = $repository->find('erased.health-tracking-test');
    if ($afterSuccess['health_status'] !== 'ok') {
        throw new RuntimeException('A successful enable() did not clear the previous health error.');
    }
    if ($afterSuccess['last_error'] !== null || $afterSuccess['last_error_at'] !== null) {
        throw new RuntimeException('A successful enable() left stale error details behind.');
    }
    if ($afterSuccess['enabled'] !== true) {
        throw new RuntimeException('A successful enable() did not persist the enabled state.');
    }

    // --- PackageLifecycleExecutor still works with no health repository passed at all (backward compatible) ---
    $executorWithoutHealth = new PackageLifecycleExecutor(new PackageStateManager($repository), new PackageLifecycleLoader());
    $executorWithoutHealth->disable('erased.health-tracking-test');
    $afterPlainDisable = $repository->find('erased.health-tracking-test');
    if ($afterPlainDisable['enabled'] !== false) {
        throw new RuntimeException('Executor without a health repository failed to disable the package.');
    }

    fwrite(STDOUT, "Package health tracking smoke test passed.\n");
    fwrite(STDOUT, "Validated failure recording with message and timestamp, success clearing prior errors, and backward compatibility with no health repository.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
