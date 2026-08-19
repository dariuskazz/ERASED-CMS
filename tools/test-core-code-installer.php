<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/CoreUpdate/CoreCodeInstaller.php';

use Erased\CoreUpdate\CoreCodeInstaller;

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

$workspace = sys_get_temp_dir().'/erased-core-installer-'.bin2hex(random_bytes(6));
$live = $workspace.'/live';
$staged = $workspace.'/staged';
$rollback = $workspace.'/rollback';

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

$write = static function (string $path, string $content): void {
    @mkdir(dirname($path), 0750, true);
    file_put_contents($path, $content);
};

try {
    // --- Test 1: a clean swap over a whitelist, one path absent from the update ---
    $write($live.'/app/old.php', 'old app code');
    $write($live.'/routes/old.php', 'old routes');
    $write($live.'/VERSION', '0.3.0-dev');
    $write($live.'/config/keep-me.php', 'untouched config'); // present live, but NOT in the staged update

    $write($staged.'/app/new.php', 'new app code');
    $write($staged.'/routes/new.php', 'new routes');
    $write($staged.'/VERSION', '0.4.0');
    // deliberately no staged/config - this path shouldn't be touched

    $installer = new CoreCodeInstaller();
    $result = $installer->install($staged, $live, $rollback, ['app', 'routes', 'config', 'VERSION'], 'test-apply-1');

    $check(in_array('app', $result['swapped'], true), 'app/ is reported as swapped');
    $check(in_array('routes', $result['swapped'], true), 'routes/ is reported as swapped');
    $check(in_array('VERSION', $result['swapped'], true), 'VERSION is reported as swapped');
    $check(!in_array('config', $result['swapped'], true), 'config/ (absent from the staged update) is NOT reported as swapped');

    $check(is_file($live.'/app/new.php') && !is_file($live.'/app/old.php'), 'live app/ now contains the staged content, not the old content');
    $check(file_get_contents($live.'/VERSION') === '0.4.0', 'live VERSION now reads the staged value');
    $check(is_file($live.'/config/keep-me.php'), 'live config/ was left untouched (absent from staged update)');
    $check(is_dir($result['backup_directory']), 'a backup directory was created');
    $check(is_file($result['backup_directory'].'/app/old.php'), 'the old app/ content was preserved in the backup');
    $check(!is_dir($staged.'/app'), 'the staged app/ directory was consumed (renamed away, not copied)');

    $remove($workspace);
    mkdir($workspace, 0750, true);

    // --- Test 2: partial-swap-then-revert restores exactly what was swapped ---
    $write($live.'/app/old.php', 'old app code');
    $write($live.'/routes/old.php', 'old routes');

    $write($staged.'/app/new.php', 'new app code');
    $write($staged.'/routes/new.php', 'new routes');
    // install() falls back from rename() to copy+delete when rename()
    // fails (a real overlayfs quirk found via live testing - see
    // CoreCodeInstaller::moveOrCopy()'s doc comment), and that fallback
    // auto-creates missing parent directories, so a missing live parent is
    // no longer enough to force a failure. Instead: make the live parent
    // path itself exist as a *file* where a directory is needed - neither
    // rename() nor the copy fallback's ensureDirectory() can recover from
    // that, giving a genuine, unrecoverable failure after app/ and routes/
    // have already been swapped successfully.
    $write($live.'/blocked', 'this is a file, not a directory');
    $write($staged.'/blocked/VERSION', '0.4.0');

    $installer2 = new CoreCodeInstaller();
    $threw = false;
    try {
        $installer2->install($staged, $live, $rollback, ['app', 'routes', 'blocked/VERSION'], 'test-apply-2');
    } catch (Throwable $e) {
        $threw = true;
    }
    $check($threw, 'install() throws when a later path in the whitelist fails to promote');

    $check(file_get_contents($live.'/app/old.php') === 'old app code', 'after auto-revert, app/ is back to its original content');
    $check(file_get_contents($live.'/routes/old.php') === 'old routes', 'after auto-revert, routes/ is back to its original content');
    $check(file_get_contents($live.'/blocked') === 'this is a file, not a directory', 'the path that failed to promote is untouched live');

    $remove($workspace);
    mkdir($workspace, 0750, true);

    // --- Test 3: regression for the exact bug found during live podman
    // verification - a path whose BACKUP succeeded but whose PROMOTION
    // then failed must still be restored by revert(). The original code
    // only added a path to the revert list *after* a full backup+promote
    // success, so a failure in between left that path's live version
    // backed up but never restored. Exercised directly against revert()
    // (a public method) with a hand-built "backed up, never promoted"
    // scenario, since reliably forcing rename() to succeed for backup but
    // fail for promotion of the very same path isn't reproducible through
    // plain filesystem calls in a portable way - the real failure mode
    // (overlayfs rejecting a rename between image layers) only reproduces
    // in that exact container environment, not a temp directory. ---
    $write($live.'/config/settings.php', 'live config content');
    $write($rollback.'/regression-apply/config/settings.php', 'backed-up config content');
    // Deliberately do NOT create anything at $live.'/config' post-backup -
    // this simulates exactly what a real backup-succeeded/promotion-failed
    // sequence leaves behind: the live path already moved out, nothing
    // promoted in to replace it.
    $remove($live.'/config');

    $installer3 = new CoreCodeInstaller();
    $installer3->revert(['config'], $live, $rollback.'/regression-apply');
    $check(
        file_exists($live.'/config/settings.php') && file_get_contents($live.'/config/settings.php') === 'backed-up config content',
        'revert() restores a path that was backed up even if it was never actually promoted (the exact live-verification bug)'
    );

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll CoreCodeInstaller checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
