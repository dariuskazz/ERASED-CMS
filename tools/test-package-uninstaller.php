<?php
declare(strict_types=1);

/**
 * Same isolated-subprocess approach as test-package-update-orchestrator.php:
 * install and uninstall must each get a fresh process because PHP cannot
 * redeclare the same lifecycle class twice in one process, and this test
 * needs distinct lifecycle classes per phase to prove the uninstall hook
 * actually ran (or didn't, in the failure case).
 */

$root = dirname(__DIR__);
$workspace = sys_get_temp_dir().'/erased-package-uninstall-'.bin2hex(random_bytes(6));
$stagingRoot = $workspace.'/staging';
$packagesRoot = $workspace.'/installed';
$rollbackRoot = $workspace.'/rollback';
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

$buildZip = static function (string $zipPath, string $packageId, string $hookBody, string $className, string $logPath): void {
    $classSource = "<?php\ndeclare(strict_types=1);\n"
        ."final class {$className} implements \\Erased\\Packages\\PackageLifecycle\n{\n"
        ."    private const LOG = ".var_export($logPath, true).";\n"
        ."    public function install(\\Erased\\Packages\\PackageManifest \$manifest, string \$packagePath): void {}\n"
        ."    public function enable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function disable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function upgrade(\\Erased\\Packages\\PackageManifest \$manifest, string \$fromVersion): void {}\n"
        ."    public function uninstall(\\Erased\\Packages\\PackageManifest \$manifest, bool \$removeData = false): void { {$hookBody} }\n"
        ."}\n";

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create fixture ZIP.');
    }
    $zip->addFromString('pkg/package.json', json_encode([
        'id' => $packageId, 'type' => 'module', 'name' => 'Uninstaller Test', 'version' => '1.0.0',
        'requires' => '0.3.0', 'author' => 'ERASED CMS', 'description' => 'Uninstaller smoke test fixture.',
        'dependencies' => [], 'lifecycle' => ['file' => 'src/Lifecycle.php', 'class' => $className],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('pkg/data.txt', 'package data marker');
    $zip->addFromString('pkg/src/Lifecycle.php', $classSource);
    $zip->close();
};

$phaseSnippet = <<<'PHP'
$root = $argv[1];
$dbPath = $argv[2];
$stagingRoot = $argv[3];
$packagesRoot = $argv[4];
$rollbackRoot = $argv[5];
$archivePath = $argv[6];
$mode = $argv[7];
$packageId = $argv[8] ?? '';

foreach ([
    'app/Packages/PackageManifest.php', 'app/Packages/PackageValidator.php',
    'app/Packages/PackageArchiveInspector.php', 'app/Packages/PackageZipExtractor.php', 'app/Packages/PackageArchiveStager.php',
    'app/Packages/PackageInstaller.php', 'app/Packages/PackageLifecycle.php',
    'app/Packages/PackageLifecycleLoader.php', 'app/Packages/InstalledPackageRepository.php',
    'app/Packages/PackageInstallOrchestrator.php', 'app/Packages/PackageUninstaller.php',
    'app/Packages/PackageMigrationRunner.php', 'app/Packages/PackageRollbackService.php',
] as $file) require_once $root.'/'.$file;

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageArchiveInspector;
use Erased\Packages\PackageArchiveStager;
use Erased\Packages\PackageInstallOrchestrator;
use Erased\Packages\PackageInstaller;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageMigrationRunner;
use Erased\Packages\PackageRollbackService;
use Erased\Packages\PackageUninstaller;
use Erased\Packages\PackageValidator;

$pdo = new PDO('sqlite:'.$dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS installed_packages_uninstall_test (
    id INTEGER PRIMARY KEY AUTOINCREMENT, package_id TEXT NOT NULL UNIQUE, package_type TEXT NOT NULL,
    name TEXT NOT NULL, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
    health_status TEXT NOT NULL DEFAULT \'ok\', last_error TEXT NULL, last_error_at TEXT NULL,
    installed_path TEXT NOT NULL, manifest_json TEXT NOT NULL, integrity_manifest_json TEXT NULL, integrity_status TEXT NOT NULL DEFAULT \'unknown\', integrity_checked_at TEXT NULL,
    installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

$repository = new InstalledPackageRepository($pdo, 'installed_packages_uninstall_test');

try {
    if ($mode === 'install') {
        $installer = new PackageInstaller(new PackageValidator());
        $stager = new PackageArchiveStager(new PackageArchiveInspector(), new PackageValidator());
        $orchestrator = new PackageInstallOrchestrator($stager, $installer, $repository, new PackageLifecycleLoader(), new PackageMigrationRunner($pdo));
        $orchestrator->installArchive($archivePath, $stagingRoot, $packagesRoot, $rollbackRoot);
        echo json_encode(['ok' => true]);
    } elseif ($mode === 'uninstall') {
        $uninstaller = new PackageUninstaller($repository, new PackageLifecycleLoader());
        $uninstaller->removePreservingData($packageId);
        echo json_encode(['ok' => true]);
    } elseif ($mode === 'delete_data') {
        $uninstaller = new PackageUninstaller($repository, new PackageLifecycleLoader());
        $uninstaller->removeAndDeleteData($packageId, $rollbackRoot);
        echo json_encode(['ok' => true]);
    } elseif ($mode === 'list_backups') {
        $backups = (new PackageRollbackService($repository))->listBackups($packageId, $rollbackRoot);
        echo json_encode(['ok' => true, 'backups' => $backups]);
    } elseif ($mode === 'rollback') {
        $backupDirectory = $argv[9] ?? '';
        $manifest = (new PackageRollbackService($repository))->rollbackTo($packageId, $backupDirectory, $packagesRoot, $rollbackRoot);
        $row = $repository->find($packageId);
        echo json_encode(['ok' => true, 'version' => $manifest->version(), 'enabled' => $row['enabled'] ?? null]);
    }
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
}
PHP;

$phaseFile = null;

$runPhase = function (string $archivePath, string $mode, string $packageId = '', string $extra = '') use (
    $root, $dbPath, $stagingRoot, $packagesRoot, $rollbackRoot, $phaseSnippet, &$phaseFile
): array {
    if ($phaseFile === null) {
        $phaseFile = tempnam(sys_get_temp_dir(), 'erased-uninstall-phase-').'.php';
        file_put_contents($phaseFile, "<?php\n".$phaseSnippet);
    }
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($phaseFile).' '
        .escapeshellarg($root).' '.escapeshellarg($dbPath).' '
        .escapeshellarg($stagingRoot).' '.escapeshellarg($packagesRoot).' '.escapeshellarg($rollbackRoot).' '
        .escapeshellarg($archivePath).' '.escapeshellarg($mode).' '.escapeshellarg($packageId).' '.escapeshellarg($extra);
    exec($cmd, $outputLines, $exitCode);
    $decoded = json_decode(implode('', $outputLines), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Phase '{$mode}' produced no valid output (exit {$exitCode}): ".implode("\n", $outputLines));
    }
    return $decoded;
};

try {
    mkdir($workspace, 0750, true);

    if (!class_exists(ZipArchive::class)) {
        fwrite(STDERR, "ERROR: ZipArchive extension is not available.\n");
        exit(1);
    }

    // --- Reject uninstalling a package that was never installed ---
    $r = ['ok' => null];
    $phaseFileTmp = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($phaseFileTmp, "<?php\n".$phaseSnippet);
    $r = $runPhase('/dev/null', 'uninstall', 'erased.never-installed');
    if ($r['ok'] !== false || !str_contains($r['error'], 'is not installed')) {
        throw new RuntimeException('Uninstaller accepted a package that was never installed.');
    }

    // --- Successful uninstall: hook runs, files and registry entry are both gone ---
    $okId = 'erased.uninstall-ok';
    $okZip = $workspace.'/ok.zip';
    $hookLog = $workspace.'/hook-ran.log';
    $buildZip($okZip, $okId, 'file_put_contents(self::LOG, "uninstalled:".($removeData?"1":"0"));', 'ErasedUninstallOk', $hookLog);
    $r = $runPhase($okZip, 'install');
    if ($r['ok'] !== true) {
        throw new RuntimeException('Fresh install for the uninstaller test failed: '.($r['error'] ?? 'unknown'));
    }
    $installedDir = $packagesRoot.'/'.$okId;
    if (!is_dir($installedDir)) {
        throw new RuntimeException('Install did not create the expected package directory.');
    }
    $r = $runPhase($okZip, 'uninstall', $okId);
    if ($r['ok'] !== true) {
        throw new RuntimeException('removePreservingData failed: '.($r['error'] ?? 'unknown'));
    }
    if (is_dir($installedDir)) {
        throw new RuntimeException('Package directory still exists after uninstall.');
    }
    if (!is_file($hookLog) || trim((string)file_get_contents($hookLog)) !== 'uninstalled:0') {
        throw new RuntimeException('uninstall() hook did not run with removeData=false.');
    }
    $pdoCheck = new PDO('sqlite:'.$dbPath);
    $row = $pdoCheck->query("SELECT * FROM installed_packages_uninstall_test WHERE package_id='{$okId}'")->fetch();
    if ($row !== false) {
        throw new RuntimeException('Registry entry still exists after uninstall.');
    }

    // --- A failing uninstall() hook must block removal entirely: files and registry stay put ---
    $failId = 'erased.uninstall-fail';
    $failZip = $workspace.'/fail.zip';
    $buildZip($failZip, $failId, 'throw new \RuntimeException("nope");', 'ErasedUninstallFail', $hookLog);
    $r = $runPhase($failZip, 'install');
    if ($r['ok'] !== true) {
        throw new RuntimeException('Fresh install for the failing-hook case failed: '.($r['error'] ?? 'unknown'));
    }
    $failDir = $packagesRoot.'/'.$failId;
    $r = $runPhase($failZip, 'uninstall', $failId);
    if ($r['ok'] !== false || !str_contains($r['error'], 'uninstall hook failed')) {
        throw new RuntimeException('A failing uninstall() hook did not block the removal.');
    }
    if (!is_dir($failDir)) {
        throw new RuntimeException('Package directory was removed despite the uninstall hook failing.');
    }
    $pdoCheck2 = new PDO('sqlite:'.$dbPath);
    $row2 = $pdoCheck2->query("SELECT * FROM installed_packages_uninstall_test WHERE package_id='{$failId}'")->fetch();
    if ($row2 === false) {
        throw new RuntimeException('Registry entry was removed despite the uninstall hook failing.');
    }

    // --- Successful delete-data uninstall: hook runs with removeData=true, code is backed up, registry is gone ---
    $delId = 'erased.uninstall-delete-data';
    $delZip = $workspace.'/del.zip';
    $buildZip($delZip, $delId, 'file_put_contents(self::LOG, "uninstalled:".($removeData?"1":"0"));', 'ErasedUninstallDelete', $hookLog);
    $r = $runPhase($delZip, 'install');
    if ($r['ok'] !== true) {
        throw new RuntimeException('Fresh install for the delete-data case failed: '.($r['error'] ?? 'unknown'));
    }
    $delDir = $packagesRoot.'/'.$delId;
    $r = $runPhase($delZip, 'delete_data', $delId);
    if ($r['ok'] !== true) {
        throw new RuntimeException('removeAndDeleteData failed: '.($r['error'] ?? 'unknown'));
    }
    if (is_dir($delDir)) {
        throw new RuntimeException('Package directory still exists at its original path after delete-data uninstall.');
    }
    if (!is_file($hookLog) || trim((string)file_get_contents($hookLog)) !== 'uninstalled:1') {
        throw new RuntimeException('uninstall() hook did not run with removeData=true.');
    }
    $pdoCheck3 = new PDO('sqlite:'.$dbPath);
    $row3 = $pdoCheck3->query("SELECT * FROM installed_packages_uninstall_test WHERE package_id='{$delId}'")->fetch();
    if ($row3 !== false) {
        throw new RuntimeException('Registry entry still exists after delete-data uninstall.');
    }
    $r = $runPhase($delZip, 'list_backups', $delId);
    if ($r['ok'] !== true || count($r['backups']) !== 1 || $r['backups'][0]['version'] !== '1.0.0') {
        throw new RuntimeException('delete_data uninstall did not leave exactly one restorable backup with the correct version.');
    }
    $backupDirectory = $r['backups'][0]['directory'];

    // --- A failing hook must block delete-data removal too: no backup, files and registry stay put ---
    $delFailId = 'erased.uninstall-delete-fail';
    $delFailZip = $workspace.'/del-fail.zip';
    $buildZip($delFailZip, $delFailId, 'throw new \RuntimeException("nope");', 'ErasedUninstallDeleteFail', $hookLog);
    $r = $runPhase($delFailZip, 'install');
    if ($r['ok'] !== true) {
        throw new RuntimeException('Fresh install for the failing delete-data case failed: '.($r['error'] ?? 'unknown'));
    }
    $delFailDir = $packagesRoot.'/'.$delFailId;
    $r = $runPhase($delFailZip, 'delete_data', $delFailId);
    if ($r['ok'] !== false || !str_contains($r['error'], 'uninstall hook failed')) {
        throw new RuntimeException('A failing uninstall() hook did not block delete-data removal.');
    }
    if (!is_dir($delFailDir)) {
        throw new RuntimeException('Package directory was removed despite the uninstall hook failing in delete-data mode.');
    }
    $r = $runPhase($delFailZip, 'list_backups', $delFailId);
    if ($r['ok'] !== true || count($r['backups']) !== 0) {
        throw new RuntimeException('A failing hook produced a backup anyway - it should never have gotten that far.');
    }

    // --- The backup from a delete-data uninstall is restorable even with no registry row left ---
    $r = $runPhase($delZip, 'rollback', $delId, $backupDirectory);
    if ($r['ok'] !== true || $r['version'] !== '1.0.0') {
        throw new RuntimeException('Restoring a delete-data backup with no prior registry row failed: '.($r['error'] ?? 'unknown'));
    }
    if ((int)$r['enabled'] !== 0) {
        throw new RuntimeException('Restoring a fully-deleted package came back enabled - it must always restore disabled.');
    }
    if (!is_dir($delDir)) {
        throw new RuntimeException('Restoring a delete-data backup did not recreate the package directory.');
    }
    $pdoCheck4 = new PDO('sqlite:'.$dbPath);
    $row4 = $pdoCheck4->query("SELECT * FROM installed_packages_uninstall_test WHERE package_id='{$delId}'")->fetch();
    if ($row4 === false) {
        throw new RuntimeException('Restoring a delete-data backup did not recreate the registry entry.');
    }

    fwrite(STDOUT, "Package uninstaller smoke test passed.\n");
    fwrite(STDOUT, "Validated not-installed rejection, keep-data removal, failed-hook safety for both modes, delete-data removal with removeData=true, and that a delete-data backup is restorable with no registry row left.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    if ($phaseFile !== null) {
        @unlink($phaseFile);
    }
    if (isset($phaseFileTmp)) {
        @unlink($phaseFileTmp);
    }
    $remove($workspace);
}
