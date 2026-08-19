<?php
declare(strict_types=1);

/**
 * Runs each phase (install, then each update attempt) in its own PHP
 * subprocess, matching real deployment: install and a later update always
 * happen in separate HTTP requests, so a package's lifecycle class is only
 * ever require_once'd once per process. Testing this in a single process
 * would hit PHP's "cannot redeclare a class" ceiling on the second load of
 * the same class name, which is not a bug in the orchestrator - PHP simply
 * cannot redefine a class within one process. This runner sidesteps that by
 * giving every phase a fresh `php` process, the same way production does.
 */

$root = dirname(__DIR__);
$workspace = sys_get_temp_dir().'/erased-package-update-'.bin2hex(random_bytes(6));
$stagingRoot = $workspace.'/staging';
$packagesRoot = $workspace.'/installed';
$rollbackRoot = $workspace.'/rollback';
$logPath = $workspace.'/hooks.log';
$dbPath = $workspace.'/registry.sqlite';

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

/** @param array<int,string> $dependencies */
$buildZip = static function (string $zipPath, string $version, string $marker, array $dependencies, string $hookBody, string $className, string $logPath): void {
    $classSource = "<?php\ndeclare(strict_types=1);\n"
        ."final class {$className} implements \\Erased\\Packages\\PackageLifecycle\n{\n"
        ."    private const LOG = ".var_export($logPath, true).";\n"
        ."    public function install(\\Erased\\Packages\\PackageManifest \$manifest, string \$packagePath): void {}\n"
        ."    public function enable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function disable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function upgrade(\\Erased\\Packages\\PackageManifest \$manifest, string \$fromVersion): void { {$hookBody} }\n"
        ."    public function uninstall(\\Erased\\Packages\\PackageManifest \$manifest, bool \$removeData = false): void {}\n"
        ."}\n";

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create fixture ZIP.');
    }
    $zip->addFromString('pkg/package.json', json_encode([
        'id' => 'erased.test-update',
        'type' => 'module',
        'name' => 'Update Orchestrator Test',
        'version' => $version,
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Update orchestrator smoke test fixture.',
        'dependencies' => $dependencies,
        'lifecycle' => ['file' => 'src/Lifecycle.php', 'class' => $className],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('pkg/marker.txt', $marker);
    $zip->addFromString('pkg/src/Lifecycle.php', $classSource);
    $zip->close();
};

// Each phase is a standalone PHP snippet, run in its own process via `php -r`.
// $mode selects which orchestrator call to make; results are reported as
// JSON on stdout so the parent process can assert on them.
$phaseSnippet = <<<'PHP'
$root = $argv[1];
$dbPath = $argv[2];
$stagingRoot = $argv[3];
$packagesRoot = $argv[4];
$rollbackRoot = $argv[5];
$archivePath = $argv[6];
$mode = $argv[7];

foreach ([
    'app/Packages/PackageManifest.php', 'app/Packages/PackageValidator.php',
    'app/Packages/PackageArchiveInspector.php', 'app/Packages/PackageZipExtractor.php', 'app/Packages/PackageArchiveStager.php',
    'app/Packages/PackageInstaller.php', 'app/Packages/PackageLifecycle.php',
    'app/Packages/PackageLifecycleLoader.php', 'app/Packages/InstalledPackageRepository.php',
    'app/Packages/PackageInstallOrchestrator.php', 'app/Packages/PackageDependencyResolver.php',
    'app/Packages/PackageMigrationRunner.php', 'app/Packages/PackageUpdateOrchestrator.php',
] as $file) require_once $root.'/'.$file;

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageArchiveInspector;
use Erased\Packages\PackageArchiveStager;
use Erased\Packages\PackageDependencyResolver;
use Erased\Packages\PackageInstallOrchestrator;
use Erased\Packages\PackageInstaller;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageMigrationRunner;
use Erased\Packages\PackageUpdateOrchestrator;
use Erased\Packages\PackageValidator;

$pdo = new PDO('sqlite:'.$dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS installed_packages_update_test (
    id INTEGER PRIMARY KEY AUTOINCREMENT, package_id TEXT NOT NULL UNIQUE, package_type TEXT NOT NULL,
    name TEXT NOT NULL, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
    health_status TEXT NOT NULL DEFAULT \'ok\', last_error TEXT NULL, last_error_at TEXT NULL,
    installed_path TEXT NOT NULL, manifest_json TEXT NOT NULL, integrity_manifest_json TEXT NULL, integrity_status TEXT NOT NULL DEFAULT \'unknown\', integrity_checked_at TEXT NULL,
    installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

$repository = new InstalledPackageRepository($pdo, 'installed_packages_update_test');
$installer = new PackageInstaller(new PackageValidator());
$stager = new PackageArchiveStager(new PackageArchiveInspector(), new PackageValidator());
$lifecycleLoader = new PackageLifecycleLoader();

try {
    if ($mode === 'install') {
        $orchestrator = new PackageInstallOrchestrator($stager, $installer, $repository, $lifecycleLoader, new PackageMigrationRunner($pdo));
        $orchestrator->installArchive($archivePath, $stagingRoot, $packagesRoot, $rollbackRoot);
        $repository->setEnabled('erased.test-update', true);
        echo json_encode(['ok' => true]);
    } elseif ($mode === 'update') {
        $orchestrator = new PackageUpdateOrchestrator($stager, $installer, $repository, $lifecycleLoader, new PackageDependencyResolver(), new PackageMigrationRunner($pdo));
        $result = $orchestrator->updateArchive($archivePath, $stagingRoot, $packagesRoot, $rollbackRoot);
        echo json_encode(['ok' => true, 'from_version' => $result['from_version'], 'to_version' => $result['manifest']->version()]);
    }
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
}
PHP;

$phaseFile = null;

$runPhase = function (string $archivePath, string $mode) use (
    $root, $dbPath, $stagingRoot, $packagesRoot, $rollbackRoot, $phaseSnippet, &$phaseFile
): array {
    if ($phaseFile === null) {
        $phaseFile = tempnam(sys_get_temp_dir(), 'erased-phase-').'.php';
        file_put_contents($phaseFile, "<?php\n".$phaseSnippet);
    }
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($phaseFile).' '
        .escapeshellarg($root).' '.escapeshellarg($dbPath).' '
        .escapeshellarg($GLOBALS['stagingRoot'] ?? '').' '
        .escapeshellarg($GLOBALS['packagesRoot'] ?? '').' '
        .escapeshellarg($GLOBALS['rollbackRoot'] ?? '').' '
        .escapeshellarg($archivePath).' '.escapeshellarg($mode);
    exec($cmd, $outputLines, $exitCode);
    $decoded = json_decode(implode('', $outputLines), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Phase '{$mode}' produced no valid output (exit {$exitCode}): ".implode("\n", $outputLines));
    }
    return $decoded;
};

try {
    mkdir($workspace, 0750, true);
    $GLOBALS['stagingRoot'] = $stagingRoot;
    $GLOBALS['packagesRoot'] = $packagesRoot;
    $GLOBALS['rollbackRoot'] = $rollbackRoot;

    if (!class_exists(ZipArchive::class)) {
        fwrite(STDERR, "ERROR: ZipArchive extension is not available.\n");
        exit(1);
    }

    // --- Reject updating a package that was never installed ---
    $neverInstalledZip = $workspace.'/never-installed.zip';
    $buildZip($neverInstalledZip, '1.0.0', 'n/a', [], '', 'ErasedUpdateNeverInstalled', $logPath);
    $r = $runPhase($neverInstalledZip, 'update');
    if ($r['ok'] !== false || !str_contains($r['error'], 'not currently installed')) {
        throw new RuntimeException('Update orchestrator accepted a package with no prior installation.');
    }

    // --- Install v1.0.0 and enable it ---
    $v1Zip = $workspace.'/v1.zip';
    $buildZip($v1Zip, '1.0.0', 'version-one', [], '', 'ErasedUpdateV1', $logPath);
    $r = $runPhase($v1Zip, 'install');
    if ($r['ok'] !== true) {
        throw new RuntimeException('Fresh install for the update test failed: '.($r['error'] ?? 'unknown'));
    }

    // --- Reject a same-version "update" ---
    $sameVersionZip = $workspace.'/same-version.zip';
    $buildZip($sameVersionZip, '1.0.0', 'still-one', [], '', 'ErasedUpdateSame', $logPath);
    $r = $runPhase($sameVersionZip, 'update');
    if ($r['ok'] !== false || !str_contains($r['error'], 'is not newer than')) {
        throw new RuntimeException('Update orchestrator accepted a non-newer version.');
    }
    if (trim((string)file_get_contents($packagesRoot.'/erased.test-update/marker.txt')) !== 'version-one') {
        throw new RuntimeException('Rejected same-version update mutated installed files.');
    }

    // --- A failing upgrade() hook must roll back files and leave the registry untouched ---
    $failingZip = $workspace.'/v2-failing.zip';
    $buildZip($failingZip, '2.0.0', 'version-two-failed', [], 'throw new \RuntimeException("boom");', 'ErasedUpdateV2Failing', $logPath);
    $r = $runPhase($failingZip, 'update');
    if ($r['ok'] !== false || !str_contains($r['error'], 'Package update failed')) {
        throw new RuntimeException('A failing upgrade() hook did not surface as a failed update.');
    }
    if (trim((string)file_get_contents($packagesRoot.'/erased.test-update/marker.txt')) !== 'version-one') {
        throw new RuntimeException('Failed upgrade did not restore the previous package files.');
    }

    // --- A successful upgrade must replace files, run upgrade() with the correct from-version, and preserve enabled state ---
    $v2Zip = $workspace.'/v2-ok.zip';
    $buildZip($v2Zip, '2.0.0', 'version-two', [], 'file_put_contents(self::LOG, $fromVersion."\n", FILE_APPEND);', 'ErasedUpdateV2Ok', $logPath);
    $r = $runPhase($v2Zip, 'update');
    if ($r['ok'] !== true || $r['from_version'] !== '1.0.0' || $r['to_version'] !== '2.0.0') {
        throw new RuntimeException('Successful update result metadata is incorrect: '.json_encode($r));
    }
    if (trim((string)file_get_contents($packagesRoot.'/erased.test-update/marker.txt')) !== 'version-two') {
        throw new RuntimeException('Successful update did not promote the new package files.');
    }

    $pdoCheck = new PDO('sqlite:'.$dbPath);
    $row = $pdoCheck->query("SELECT version, enabled FROM installed_packages_update_test WHERE package_id='erased.test-update'")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || $row['version'] !== '2.0.0') {
        throw new RuntimeException('Registry was not updated to the new version after a successful upgrade.');
    }
    if ((int)$row['enabled'] !== 1) {
        throw new RuntimeException('Successful update did not preserve the enabled state.');
    }

    $hookLog = is_file($logPath) ? file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    if ($hookLog !== ['1.0.0']) {
        throw new RuntimeException('upgrade() hook was not called exactly once with the correct from-version. Got: '.json_encode($hookLog));
    }

    fwrite(STDOUT, "Package update orchestrator smoke test passed.\n");
    fwrite(STDOUT, "Validated not-installed rejection, non-newer-version rejection, failed-upgrade rollback, and a successful upgrade across isolated processes.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    if ($phaseFile !== null) {
        @unlink($phaseFile);
    }
    $remove($workspace);
}
