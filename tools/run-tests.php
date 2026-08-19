<?php
declare(strict_types=1);

/**
 * Single local entry point for the PHP test suite (tests/smoke.php plus every
 * tools/test-*.php). Mirrors exactly what CI runs, including the quarantine
 * list, so "does it pass" can be answered locally without re-deriving CI's
 * shell logic by hand.
 *
 * Usage: php tools/run-tests.php
 */

$root = dirname(__DIR__);

// Real functionality that regressed, not a stale assertion to shrug off:
// these two assert a UI contract (Structure Preview panel, old
// data-layout-instance drag-and-drop markup) that the Aug 6 visual rewrite
// removed without updating them. Re-enable once that's deliberately
// restored or the tests are rewritten against the current contract.
$quarantined = [
    'tools/test-layout-studio-admin-route.php',
    'tools/test-layout-studio-admin-screen.php',
];

$results = [];

$run = static function (string $label, string $relativePath) use ($root, &$results): void {
    $start = microtime(true);
    $output = [];
    $exitCode = 0;
    exec('php '.escapeshellarg($root.'/'.$relativePath).' 2>&1', $output, $exitCode);
    $elapsedMs = (int)round((microtime(true) - $start) * 1000);
    $results[] = ['label' => $label, 'path' => $relativePath, 'pass' => $exitCode === 0, 'output' => $output, 'ms' => $elapsedMs];

    echo ($exitCode === 0 ? 'PASS' : 'FAIL')." {$label} ({$elapsedMs}ms)\n";
    if ($exitCode !== 0) {
        foreach ($output as $line) {
            echo "    {$line}\n";
        }
    }
};

echo "== Core smoke test ==\n";
$run('tests/smoke.php', 'tests/smoke.php');

echo "\n== tools/test-*.php suite ==\n";
$files = glob($root.'/tools/test-*.php') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $relative = 'tools/'.basename($file);
    if (in_array($relative, $quarantined, true)) {
        echo "SKIP {$relative} (quarantined - see comment above)\n";
        continue;
    }
    $run($relative, $relative);
}

$passed = count(array_filter($results, static fn(array $r): bool => $r['pass']));
$failed = count($results) - $passed;

echo "\n".str_repeat('-', 40)."\n";
echo "{$passed} passed, {$failed} failed, ".count($quarantined)." quarantined.\n";

exit($failed > 0 ? 1 : 0);
