<?php
declare(strict_types=1);

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\LegacyPackageMigrator;

$root = dirname(__DIR__);
foreach ([
    'app/Packages/PackageManifest.php',
    'app/Packages/InstalledPackageRepository.php',
    'app/Packages/LegacyPackageMigrator.php',
] as $file) require_once $root.'/'.$file;

$workspace = sys_get_temp_dir().'/erased-legacy-migrator-'.bin2hex(random_bytes(6));

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
    mkdir($workspace.'/addons/old-addon', 0750, true);
    file_put_contents($workspace.'/addons/old-addon/manifest.json', json_encode([
        'name' => 'Old Addon', 'slug' => 'old-addon', 'version' => '2.1.0',
        'author' => 'A Legacy Author', 'description' => 'A pre-Package-Engine addon.',
    ]));
    // A legacy row whose directory is missing entirely - should fail gracefully, not abort the batch.
    // (No directory created for 'ghost-addon'.)

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE installed_packages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, package_id TEXT NOT NULL UNIQUE, package_type TEXT NOT NULL,
        name TEXT NOT NULL, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
        health_status TEXT NOT NULL DEFAULT 'ok', last_error TEXT NULL, last_error_at TEXT NULL,
        installed_path TEXT NOT NULL, manifest_json TEXT NOT NULL, integrity_manifest_json TEXT NULL, integrity_status TEXT NOT NULL DEFAULT 'unknown', integrity_checked_at TEXT NULL,
        installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE packages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, package_type TEXT NOT NULL, slug TEXT NOT NULL,
        name TEXT NOT NULL, version TEXT NOT NULL DEFAULT '1.0.0', enabled INTEGER NOT NULL DEFAULT 0,
        manifest TEXT, installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT INTO packages(package_type,slug,name,version,enabled) VALUES ('addon','old-addon','Old Addon','2.1.0',1)");
    $pdo->exec("INSERT INTO packages(package_type,slug,name,version,enabled) VALUES ('addon','ghost-addon','Ghost Addon','1.0.0',0)");

    $repository = new InstalledPackageRepository($pdo);
    $migrator = new LegacyPackageMigrator($pdo, $repository, $workspace);

    // --- First run: one migrates, one fails (missing directory), none skipped yet ---
    $result = $migrator->migrate();
    if ($result['migrated'] !== ['old-addon']) {
        throw new RuntimeException('Expected old-addon to migrate. Got: '.json_encode($result['migrated']));
    }
    if ($result['skipped'] !== []) {
        throw new RuntimeException('Nothing should be skipped on the first run. Got: '.json_encode($result['skipped']));
    }
    if (!isset($result['failed']['ghost-addon'])) {
        throw new RuntimeException('Expected ghost-addon (missing directory) to fail gracefully, not abort the batch.');
    }

    $migrated = $repository->find('legacy.addon.old-addon');
    if ($migrated === null) {
        throw new RuntimeException('Migrated package was not found under the expected legacy.* id.');
    }
    if ($migrated['name'] !== 'Old Addon' || $migrated['version'] !== '2.1.0' || $migrated['enabled'] !== true) {
        throw new RuntimeException('Migrated package did not preserve name, version, or enabled state.');
    }
    if ($migrated['manifest']['author'] !== 'A Legacy Author' || $migrated['manifest']['description'] !== 'A pre-Package-Engine addon.') {
        throw new RuntimeException('Migrated package did not carry over author/description from the existing legacy manifest.');
    }
    if ($migrated['manifest']['requires'] === '' || $migrated['manifest']['id'] === '') {
        throw new RuntimeException('Migrated manifest is missing required fields the old format never guaranteed.');
    }

    if (!is_dir($workspace.'/addons/old-addon')) {
        throw new RuntimeException('Migration must never delete the original package directory.');
    }
    $legacyStillThere = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if ((int)$legacyStillThere !== 2) {
        throw new RuntimeException('Migration must never delete rows from the legacy packages table.');
    }

    // --- Second run: idempotent - the already-migrated slug is skipped, not duplicated ---
    $second = $migrator->migrate();
    if ($second['migrated'] !== []) {
        throw new RuntimeException('A second run re-migrated an already-migrated package.');
    }
    if ($second['skipped'] !== ['old-addon']) {
        throw new RuntimeException('A second run did not correctly skip the already-migrated package.');
    }

    // --- No legacy table at all: a clean no-op, not an error ---
    $pdo->exec('DROP TABLE packages');
    $noTable = $migrator->migrate();
    if ($noTable !== ['migrated' => [], 'skipped' => [], 'failed' => []]) {
        throw new RuntimeException('Migrating with no legacy table present should be a clean no-op.');
    }

    fwrite(STDOUT, "Legacy package migrator smoke test passed.\n");
    fwrite(STDOUT, "Validated a real migration preserving name/version/enabled and synthesizing required fields, per-row failure isolation, idempotency, and that nothing in the legacy table or filesystem is ever touched.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
