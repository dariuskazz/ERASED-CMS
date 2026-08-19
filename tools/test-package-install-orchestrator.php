<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageArchiveInspector;
use Erased\Packages\PackageArchiveStager;
use Erased\Packages\PackageInstaller;
use Erased\Packages\PackageInstallOrchestrator;
use Erased\Packages\PackageLifecycle;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageMigrationRunner;
use Erased\Packages\PackageValidator;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycle.php';
require_once dirname(__DIR__).'/app/Packages/PackageValidator.php';
require_once dirname(__DIR__).'/app/Packages/PackageArchiveInspector.php';
require_once dirname(__DIR__).'/app/Packages/PackageZipExtractor.php';
require_once dirname(__DIR__).'/app/Packages/PackageArchiveStager.php';
require_once dirname(__DIR__).'/app/Packages/PackageInstaller.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycleLoader.php';
require_once dirname(__DIR__).'/app/Packages/PackageMigrationRunner.php';
require_once dirname(__DIR__).'/app/Packages/PackageInstallOrchestrator.php';

if (!extension_loaded('zip')) {
    fwrite(STDERR, "ERROR: ext-zip is required.\n");
    exit(1);
}
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "ERROR: pdo_sqlite is required for this isolated smoke test.\n");
    exit(1);
}

$root = sys_get_temp_dir().'/erased-install-orchestrator-'.bin2hex(random_bytes(6));
$archives = $root.'/archives';
$staging = $root.'/staging';
$packages = $root.'/packages';
$rollback = $root.'/rollback';

$remove = static function (string $directory) use (&$remove): void {
    if (!is_dir($directory)) return;
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $directory.'/'.$item;
        is_dir($path) && !is_link($path) ? $remove($path) : @unlink($path);
    }
    @rmdir($directory);
};

$makeArchive = static function (
    string $archivePath,
    string $packageId,
    string $className,
    bool $failInstall,
): void {
    $namespace = substr($className, 0, (int)strrpos($className, '\\'));
    $shortName = substr($className, (int)strrpos($className, '\\') + 1);
    $throw = $failInstall ? "throw new \\RuntimeException('simulated install failure');" : "file_put_contents(\$packagePath.'/installed.flag', 'ok');";

    $lifecycle = "<?php\ndeclare(strict_types=1);\nnamespace {$namespace};\n"
        ."use Erased\\Packages\\PackageLifecycle;\nuse Erased\\Packages\\PackageManifest;\n"
        ."final class {$shortName} implements PackageLifecycle {\n"
        ."public function install(PackageManifest \$manifest, string \$packagePath): void { {$throw} }\n"
        ."public function enable(PackageManifest \$manifest): void {}\n"
        ."public function disable(PackageManifest \$manifest): void {}\n"
        ."public function upgrade(PackageManifest \$manifest, string \$fromVersion): void {}\n"
        ."public function uninstall(PackageManifest \$manifest, bool \$removeData = false): void {}\n"
        ."}\n";

    $manifest = json_encode([
        'id' => $packageId,
        'type' => 'module',
        'name' => 'Install Orchestrator Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Installation orchestration smoke-test fixture.',
        'dependencies' => [],
        'lifecycle' => [
            'file' => 'src/Lifecycle.php',
            'class' => $className,
        ],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create test package ZIP.');
    }
    $zip->addFromString('package.json', $manifest."\n");
    $zip->addFromString('src/Lifecycle.php', $lifecycle);
    $zip->close();
};

try {
    foreach ([$archives, $staging, $packages, $rollback] as $directory) {
        if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create test directory.');
        }
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE installed_packages ('
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

    $validator = new PackageValidator();
    $repository = new InstalledPackageRepository($pdo);
    $orchestrator = new PackageInstallOrchestrator(
        new PackageArchiveStager(new PackageArchiveInspector(), $validator),
        new PackageInstaller($validator),
        $repository,
        new PackageLifecycleLoader(),
        new PackageMigrationRunner($pdo),
    );

    $successArchive = $archives.'/success.zip';
    $makeArchive($successArchive, 'erased.test-install-success', 'Erased\\TestInstallSuccess\\Lifecycle', false);
    $result = $orchestrator->installArchive($successArchive, $staging, $packages, $rollback);

    assert($result['manifest']->id() === 'erased.test-install-success');
    assert(is_file($result['package_directory'].'/installed.flag'));
    assert($repository->find('erased.test-install-success') !== null);
    assert($repository->find('erased.test-install-success')['enabled'] === false);

    $failureArchive = $archives.'/failure.zip';
    $makeArchive($failureArchive, 'erased.test-install-failure', 'Erased\\TestInstallFailure\\Lifecycle', true);

    $failed = false;
    try {
        $orchestrator->installArchive($failureArchive, $staging, $packages, $rollback);
    } catch (RuntimeException $error) {
        $failed = str_contains($error->getMessage(), 'simulated install failure');
    }

    assert($failed === true);
    assert(!is_dir($packages.'/erased.test-install-failure'));
    assert($repository->find('erased.test-install-failure') === null);

    fwrite(STDOUT, "Package installation orchestrator smoke test passed.\n");
    fwrite(STDOUT, "Validated ZIP staging, file promotion, install hook, registry persistence, and failure rollback.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($root);
}
