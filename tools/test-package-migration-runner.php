<?php
declare(strict_types=1);

use Erased\Packages\PackageMigrationRunner;

require_once dirname(__DIR__).'/app/Packages/PackageMigrationRunner.php';

$workspace = sys_get_temp_dir().'/erased-package-migrations-'.bin2hex(random_bytes(6));
$packageDir = $workspace.'/pkg';
$migrationsDir = $packageDir.'/migrations';

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
    mkdir($migrationsDir, 0750, true);

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    file_put_contents($migrationsDir.'/20260101_001_create_widgets.sql', 'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT);');
    file_put_contents($migrationsDir.'/20260101_002_seed_widgets.sql', "INSERT INTO widgets (id, name) VALUES (1, 'first');");

    $runner = new PackageMigrationRunner($pdo);

    // --- A package with no migrations/ directory is a no-op, not an error ---
    $noMigrationsDir = $workspace.'/no-migrations-pkg';
    mkdir($noMigrationsDir, 0750, true);
    $applied = $runner->run('erased.no-migrations', $noMigrationsDir);
    if ($applied !== []) {
        throw new RuntimeException('Runner reported applying migrations for a package with none.');
    }

    // --- First run applies both migrations, in filename order ---
    $applied = $runner->run('erased.migration-test', $packageDir);
    if ($applied !== ['20260101_001_create_widgets.sql', '20260101_002_seed_widgets.sql']) {
        throw new RuntimeException('First run did not apply migrations in the expected order: '.json_encode($applied));
    }
    $row = $pdo->query('SELECT name FROM widgets WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || $row['name'] !== 'first') {
        throw new RuntimeException('Migration SQL did not actually run.');
    }

    // --- Second run against the same package applies nothing new ---
    $appliedAgain = $runner->run('erased.migration-test', $packageDir);
    if ($appliedAgain !== []) {
        throw new RuntimeException('Runner re-applied already-applied migrations: '.json_encode($appliedAgain));
    }

    // --- A different package id with a same-named migration file is tracked independently ---
    $otherPackageDir = $workspace.'/other-pkg';
    mkdir($otherPackageDir.'/migrations', 0750, true, );
    file_put_contents($otherPackageDir.'/migrations/20260101_001_create_widgets.sql', 'CREATE TABLE other_widgets (id INTEGER PRIMARY KEY);');
    $appliedOther = $runner->run('erased.other-package', $otherPackageDir);
    if ($appliedOther !== ['20260101_001_create_widgets.sql']) {
        throw new RuntimeException('A same-named migration for a different package id was incorrectly skipped.');
    }

    // --- Adding a third migration file later ("an update") only applies the new one ---
    file_put_contents($migrationsDir.'/20260102_001_add_index.sql', 'CREATE INDEX idx_widgets_name ON widgets(name);');
    $appliedThird = $runner->run('erased.migration-test', $packageDir);
    if ($appliedThird !== ['20260102_001_add_index.sql']) {
        throw new RuntimeException('A newly added migration was not picked up on a later run: '.json_encode($appliedThird));
    }

    fwrite(STDOUT, "Package migration runner smoke test passed.\n");
    fwrite(STDOUT, "Validated no-op for packages without migrations, ordered first-run application, idempotent re-runs, per-package isolation, and picking up new migrations added after an update.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
