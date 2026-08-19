<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/CoreUpdate/CoreUpdateRepository.php';

use Erased\CoreUpdate\CoreUpdateRepository;

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // sqlite-compatible mirror of database/migrations/20260814_0004_create_core_updates.sql
    // (no ENUM support in sqlite - status is a plain TEXT column here).
    $pdo->exec(
        "CREATE TABLE core_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'staged',
            from_version TEXT NOT NULL,
            to_version TEXT NOT NULL,
            staged_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            applied_at TEXT NULL,
            staged_by INTEGER NULL,
            archive_original_name TEXT NOT NULL,
            diff_summary TEXT NULL,
            pending_migrations TEXT NULL,
            code_backup_directory TEXT NULL,
            db_backup_file TEXT NULL,
            error_message TEXT NULL
        )"
    );

    $repo = new CoreUpdateRepository($pdo);

    $repo->create([
        'token' => 'tok-1',
        'from_version' => '0.3.0-dev',
        'to_version' => '0.4.0',
        'staged_by' => 1,
        'archive_original_name' => 'update-0.4.0.zip',
        'diff_summary' => ['counts' => ['added' => 2, 'changed' => 1, 'removed' => 0, 'unchanged' => 100]],
        'pending_migrations' => ['20260901_0001_example.sql'],
    ]);

    $active = $repo->findActiveStaged();
    $check($active !== null && $active['token'] === 'tok-1', 'findActiveStaged() finds the just-created staged row');
    $check($active['diff_summary']['counts']['added'] === 2, 'diff_summary JSON round-trips correctly');
    $check($active['pending_migrations'] === ['20260901_0001_example.sql'], 'pending_migrations JSON round-trips correctly');

    $byToken = $repo->findByToken('tok-1');
    $check($byToken !== null && $byToken['to_version'] === '0.4.0', 'findByToken() finds the row by token');
    $check($repo->findByToken('does-not-exist') === null, 'findByToken() returns null for an unknown token');

    $rejectedSecondStage = false;
    try {
        $repo->create([
            'token' => 'tok-2',
            'from_version' => '0.3.0-dev',
            'to_version' => '0.5.0',
            'staged_by' => 1,
            'archive_original_name' => 'update-0.5.0.zip',
            'diff_summary' => [],
            'pending_migrations' => [],
        ]);
    } catch (RuntimeException $e) {
        $rejectedSecondStage = true;
    }
    $check($rejectedSecondStage, 'create() rejects a second stage while one is already active');

    $repo->markApplying('tok-1');
    $check($repo->findByToken('tok-1')['status'] === 'applying', 'markApplying() updates status');

    $repo->markApplied('tok-1', '/rollback/20260101-000000-abc', 'erased-cms-2026-01-01.sql');
    $applied = $repo->findByToken('tok-1');
    $check($applied['status'] === 'applied', 'markApplied() updates status to applied');
    $check($applied['code_backup_directory'] === '/rollback/20260101-000000-abc', 'markApplied() records the code backup directory');
    $check($applied['db_backup_file'] === 'erased-cms-2026-01-01.sql', 'markApplied() records the db backup file');
    $check($repo->findActiveStaged() === null, 'findActiveStaged() is null after the row is applied (no longer staged/applying)');

    // A new stage should now be allowed since nothing is active anymore.
    $allowedAfterApplied = true;
    try {
        $repo->create([
            'token' => 'tok-3',
            'from_version' => '0.4.0',
            'to_version' => '0.5.0',
            'staged_by' => null,
            'archive_original_name' => 'update-0.5.0.zip',
            'diff_summary' => [],
            'pending_migrations' => [],
        ]);
    } catch (RuntimeException $e) {
        $allowedAfterApplied = false;
    }
    $check($allowedAfterApplied, 'a new stage is allowed once the previous one is no longer active');

    $repo->markFailed('tok-3', 'something went wrong');
    $failed = $repo->findByToken('tok-3');
    $check($failed['status'] === 'failed', 'markFailed() updates status to failed');
    $check($failed['error_message'] === 'something went wrong', 'markFailed() records the error message');

    $repo->delete('tok-3');
    $check($repo->findByToken('tok-3') === null, 'delete() removes the row');

    // Expiry sweep: a row staged "long ago" should be returned by sweepExpired().
    $pdo->prepare("UPDATE core_updates SET staged_at = :old WHERE token = 'tok-1'")
        ->execute([':old' => date('Y-m-d H:i:s', time() - 10000)]);
    $pdo->exec("UPDATE core_updates SET status = 'staged' WHERE token = 'tok-1'"); // simulate an abandoned stage
    $expired = $repo->sweepExpired(3600);
    $check(count($expired) === 1 && $expired[0]['token'] === 'tok-1', 'sweepExpired() finds a stale staged row past the TTL');

    $notExpired = $repo->sweepExpired(1000000);
    $check($notExpired === [], 'sweepExpired() finds nothing with a TTL longer than the row\'s age');

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll CoreUpdateRepository checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
