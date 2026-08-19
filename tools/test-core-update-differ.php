<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/Packages/PackageIntegrityChecker.php';
require_once dirname(__DIR__).'/app/CoreUpdate/CoreUpdateDiffer.php';

use Erased\CoreUpdate\CoreUpdateDiffer;

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

$workspace = sys_get_temp_dir().'/erased-core-differ-'.bin2hex(random_bytes(6));
$currentRoot = $workspace.'/current';
$stagedRoot = $workspace.'/staged';

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
    // current tree: app/unchanged.php, app/willChange.php, app/willBeRemoved.php, VERSION
    $write($currentRoot.'/app/unchanged.php', 'same content');
    $write($currentRoot.'/app/willChange.php', 'old content');
    $write($currentRoot.'/app/willBeRemoved.php', 'goodbye');
    $write($currentRoot.'/VERSION', '0.3.0-dev');

    // staged tree: app/unchanged.php (same), app/willChange.php (different),
    // app/willBeRemoved.php absent, app/added.php (new), VERSION changed
    $write($stagedRoot.'/app/unchanged.php', 'same content');
    $write($stagedRoot.'/app/willChange.php', 'new content');
    $write($stagedRoot.'/app/added.php', 'brand new file');
    $write($stagedRoot.'/VERSION', '0.4.0');

    $differ = new CoreUpdateDiffer();
    $result = $differ->diff($currentRoot, $stagedRoot, ['app', 'VERSION']);

    $check(in_array('app/added.php', $result['added'], true), 'a genuinely new file is reported as added');
    $check(in_array('app/willChange.php', $result['changed'], true), 'a file with different content is reported as changed');
    $check(in_array('VERSION', $result['changed'], true), 'a whitelisted lone-file path (VERSION) with different content is reported as changed');
    $check(in_array('app/willBeRemoved.php', $result['removed'], true), 'a file absent from the staged tree is reported as removed');
    $check(!in_array('app/unchanged.php', array_merge($result['added'], $result['changed'], $result['removed']), true), 'an identical file appears in none of added/changed/removed');

    $check($result['counts']['added'] === 1, 'added count is exactly 1');
    $check($result['counts']['changed'] === 2, 'changed count is exactly 2 (willChange.php + VERSION)');
    $check($result['counts']['removed'] === 1, 'removed count is exactly 1');
    $check($result['counts']['unchanged'] === 1, 'unchanged count is exactly 1 (app/unchanged.php)');

    // A whitelisted path absent from BOTH trees must not error and must
    // contribute nothing to the diff.
    $resultWithMissingPath = $differ->diff($currentRoot, $stagedRoot, ['app', 'VERSION', 'nonexistent-path']);
    $check($resultWithMissingPath['counts'] === $result['counts'], 'a whitelisted path present in neither tree is silently ignored');

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll CoreUpdateDiffer checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
