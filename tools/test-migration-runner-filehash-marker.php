<?php
declare(strict_types=1);

// Regression test for a real incident: MigrationRunner used to cache a
// directory *mtime* to decide whether to re-scan for pending migrations.
// rsync -a (and similar deploy tooling) preserves source mtimes, so a
// destination directory's mtime can coincidentally match a stale marker
// value inherited from an imported DB dump - silently skipping real
// pending migrations forever, with zero error output. See
// app/Core/MigrationRunner.php's run() doc comment for the full story.
//
// This test reproduces the failure mode directly: apply once (caching a
// marker), then simulate "the directory looks unchanged by mtime, but a
// new migration file was actually added" and assert the fix (hashing the
// filename list instead of trusting mtime) still detects and applies it.

require_once dirname(__DIR__).'/app/Core/MigrationRunner.php';

use Erased\Core\MigrationRunner;

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

$workspace = sys_get_temp_dir().'/erased-migration-runner-'.bin2hex(random_bytes(6));
$migrationsDir = $workspace.'/migrations';
mkdir($migrationsDir, 0750, true);

$remove = static function (string $path) use (&$remove): void {
    if (!file_exists($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') $remove($path.'/'.$item);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
};

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');

    file_put_contents($migrationsDir.'/001_create_widgets.sql', 'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT);');

    $runner = new MigrationRunner($pdo, $migrationsDir);
    $runner->run();

    $check(
        (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='widgets'")->fetchColumn() === 1,
        'first run() applies the initial migration file'
    );
    $check(
        (int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === 1,
        'the applied migration is recorded'
    );

    $markerAfterFirstRun = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='_migration_dir_filehash'")->fetchColumn();
    $check($markerAfterFirstRun !== false && $markerAfterFirstRun !== '', 'a filehash marker was cached after the first run');

    // Simulate the exact incident: a directory whose file list genuinely
    // changed (a new migration added) but whose OS-level mtime doesn't
    // reflect that from this cache's point of view - achieved here simply
    // by not touching mtime at all and confirming the fix doesn't consult
    // it in the first place. The old, buggy implementation would have
    // trusted a matching mtime and skipped this migration forever.
    file_put_contents($migrationsDir.'/002_create_gadgets.sql', 'CREATE TABLE gadgets (id INTEGER PRIMARY KEY, name TEXT);');
    touch($migrationsDir, filemtime($migrationsDir)); // deliberately leave mtime as-is / unchanged

    $runner2 = new MigrationRunner($pdo, $migrationsDir);
    $runner2->run();

    $check(
        (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='gadgets'")->fetchColumn() === 1,
        'a second migration file is detected and applied even though directory mtime was left unchanged (the actual incident)'
    );
    $check(
        (int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === 2,
        'both migrations are now recorded'
    );

    $markerAfterSecondRun = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='_migration_dir_filehash'")->fetchColumn();
    $check($markerAfterSecondRun !== $markerAfterFirstRun, 'the cached marker changed to reflect the new file list');

    // A third run with nothing new must be a true no-op (the cache still
    // needs to actually short-circuit in the common case - this isn't just
    // removing the optimization).
    $countBefore = (int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
    $runner3 = new MigrationRunner($pdo, $migrationsDir);
    $runner3->run();
    $check(
        (int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === $countBefore,
        'a third run with no new files is a no-op (cache still short-circuits correctly)'
    );

    // pending() preview: add a third file and confirm it's listed without
    // being applied and without disturbing the cache marker.
    file_put_contents($migrationsDir.'/003_create_widgets2.sql', 'CREATE TABLE widgets2 (id INTEGER PRIMARY KEY);');
    $markerBeforePending = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='_migration_dir_filehash'")->fetchColumn();
    $pendingRunner = new MigrationRunner($pdo, $migrationsDir);
    $pendingList = $pendingRunner->pending();
    $check($pendingList === ['003_create_widgets2.sql'], 'pending() lists the new unapplied file by name');
    $check(
        (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='widgets2'")->fetchColumn() === 0,
        'pending() does not actually apply the migration'
    );
    $markerAfterPending = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='_migration_dir_filehash'")->fetchColumn();
    $check($markerAfterPending === $markerBeforePending, 'pending() does not touch the cache marker');

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll MigrationRunner filehash-marker checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
