<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageInstaller;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageRollbackService;
use Erased\Packages\PackageValidator;

$root = dirname(__DIR__);
foreach ([
    'app/Packages/PackageManifest.php', 'app/Packages/PackageValidator.php',
    'app/Packages/InstalledPackageRepository.php', 'app/Packages/PackageInstaller.php',
    'app/Packages/PackageRollbackService.php',
] as $file) require_once $root.'/'.$file;

$workspace = sys_get_temp_dir().'/erased-package-rollback-'.bin2hex(random_bytes(6));
$packagesRoot = $workspace.'/installed';
$rollbackRoot = $workspace.'/rollback';

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

$writePackage = static function (string $stageDir, string $version) {
    mkdir($stageDir, 0750, true);
    file_put_contents($stageDir.'/package.json', json_encode([
        'id' => 'erased.rollback-test',
        'type' => 'module',
        'name' => 'Rollback Test',
        'version' => $version,
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Rollback service smoke test v'.$version.'.',
    ], JSON_PRETTY_PRINT));
};

try {
    mkdir($packagesRoot, 0750, true);
    mkdir($rollbackRoot, 0750, true);

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE installed_packages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, package_id TEXT NOT NULL UNIQUE, package_type TEXT NOT NULL,
        name TEXT NOT NULL, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
        health_status TEXT NOT NULL DEFAULT 'ok', last_error TEXT NULL, last_error_at TEXT NULL,
        installed_path TEXT NOT NULL, manifest_json TEXT NOT NULL, integrity_manifest_json TEXT NULL, integrity_status TEXT NOT NULL DEFAULT 'unknown', integrity_checked_at TEXT NULL,
        installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");

    $repository = new InstalledPackageRepository($pdo);
    $installer = new PackageInstaller(new PackageValidator());
    $rollbackService = new PackageRollbackService($repository);

    // --- No backups yet: a fresh install has nothing to roll back to ---
    $stageV1 = $workspace.'/stage-v1';
    $writePackage($stageV1, '1.0.0');
    $installationV1 = $installer->install($stageV1, $packagesRoot, $rollbackRoot);
    $repository->save($installationV1['manifest'], $installationV1['package_directory'], true);

    if ($rollbackService->listBackups('erased.rollback-test', $rollbackRoot) !== []) {
        throw new RuntimeException('A package with no prior version reported a backup that does not exist.');
    }

    // --- Updating to v2 leaves v1 behind as an untouched, on-demand backup ---
    $stageV2 = $workspace.'/stage-v2';
    $writePackage($stageV2, '2.0.0');
    $installationV2 = $installer->install($stageV2, $packagesRoot, $rollbackRoot);
    $repository->save($installationV2['manifest'], $installationV2['package_directory'], true);

    $backups = $rollbackService->listBackups('erased.rollback-test', $rollbackRoot);
    if (count($backups) !== 1) {
        throw new RuntimeException('Expected exactly one backup after one update, got '.count($backups));
    }
    if ($backups[0]['version'] !== '1.0.0') {
        throw new RuntimeException('The listed backup did not report the correct prior version.');
    }
    $backupDirectory = $backups[0]['directory'];

    // --- A backup name that does not belong to this package id is rejected ---
    $rejected = false;
    try {
        $rollbackService->rollbackTo('erased.rollback-test', 'erased.some-other-package-20260101-000000-abcdef0123', $packagesRoot, $rollbackRoot);
    } catch (RuntimeException) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('A backup reference for a different package id was not rejected.');
    }

    // --- Rolling back to v1 restores the old files and preserves the enabled flag ---
    $repository->setEnabled('erased.rollback-test', false);
    $restored = $rollbackService->rollbackTo('erased.rollback-test', $backupDirectory, $packagesRoot, $rollbackRoot);
    if ($restored->version() !== '1.0.0') {
        throw new RuntimeException('Rollback did not restore version 1.0.0.');
    }

    $row = $repository->find('erased.rollback-test');
    if ($row['version'] !== '1.0.0') {
        throw new RuntimeException('The registry was not updated to reflect the rolled-back version.');
    }
    if ($row['enabled'] !== false) {
        throw new RuntimeException('Rollback did not preserve the enabled=false state that was set before it.');
    }

    $onDiskManifest = json_decode((string)file_get_contents($packagesRoot.'/erased.rollback-test/package.json'), true);
    if (($onDiskManifest['version'] ?? null) !== '1.0.0') {
        throw new RuntimeException('The on-disk package.json was not actually restored to version 1.0.0.');
    }

    // --- The v2 files that were replaced are now themselves an available backup ---
    $backupsAfterRollback = $rollbackService->listBackups('erased.rollback-test', $rollbackRoot);
    if (count($backupsAfterRollback) !== 1 || $backupsAfterRollback[0]['version'] !== '2.0.0') {
        throw new RuntimeException('Rolling back did not leave the replaced version available as its own backup.');
    }

    // --- A backup restores even with no registry row at all - the recovery path
    //     PackageUninstaller::removeAndDeleteData() depends on, since it removes
    //     the row entirely. Simulate that by removing the row directly. ---
    $orphanBackupDirectory = $backupsAfterRollback[0]['directory'];
    $pdo->exec("DELETE FROM installed_packages WHERE package_id = 'erased.rollback-test'");
    if ($repository->find('erased.rollback-test') !== null) {
        throw new RuntimeException('Test setup failed: registry row still present after direct delete.');
    }

    $restoredFromOrphan = $rollbackService->rollbackTo('erased.rollback-test', $orphanBackupDirectory, $packagesRoot, $rollbackRoot);
    if ($restoredFromOrphan->version() !== '2.0.0') {
        throw new RuntimeException('Restoring a backup with no existing registry row did not restore the expected version.');
    }
    $rowAfterOrphanRestore = $repository->find('erased.rollback-test');
    if ($rowAfterOrphanRestore === null) {
        throw new RuntimeException('Restoring a backup with no existing registry row did not create a fresh one.');
    }
    if ($rowAfterOrphanRestore['enabled'] !== false) {
        throw new RuntimeException('Restoring a backup with no prior row came back enabled - it must default to disabled, like any fresh install.');
    }

    fwrite(STDOUT, "Package rollback service smoke test passed.\n");
    fwrite(STDOUT, "Validated backup discovery after an update, cross-package backup rejection, on-demand restore with enabled-state preservation, that a rollback itself produces a new backup, and that a backup restores cleanly with no registry row present at all.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
