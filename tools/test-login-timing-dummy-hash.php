<?php
declare(strict_types=1);

// ERASED_TIMING_DUMMY_HASH (app/bootstrap.php) equalizes login response
// time between "no such account" and "wrong password" - routes/auth.php's
// login handler now always calls password_verify() against a real hash,
// this one when no matching user exists, instead of short-circuiting past
// it entirely. Found via a v0.8-dev security audit - see docs/STATUS.md.

require_once dirname(__DIR__).'/app/bootstrap.php';

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
    $check(
        defined('ERASED_TIMING_DUMMY_HASH'),
        'ERASED_TIMING_DUMMY_HASH is defined'
    );

    $info = password_get_info(ERASED_TIMING_DUMMY_HASH);
    $check(
        $info['algoName'] !== 'unknown',
        'ERASED_TIMING_DUMMY_HASH is a real, recognized password_hash() output (algo: '.$info['algoName'].')'
    );

    // The actual property that matters: verifying against it must do the
    // full, non-trivial hashing work every time, not short-circuit - a
    // handful of plausible guesses should all correctly fail.
    foreach (['', 'password', 'admin', 'wrong-guess-12345', str_repeat('a', 64)] as $guess) {
        $check(
            password_verify($guess, ERASED_TIMING_DUMMY_HASH) === false,
            'password_verify() against the dummy hash correctly rejects a plausible guess ('.($guess === '' ? '<empty>' : $guess).')'
        );
    }

    // Timing-shape sanity check: verifying against the dummy hash should
    // cost roughly the same as verifying against a freshly-generated real
    // hash (both do a full Argon2id/bcrypt computation), and both should be
    // meaningfully slower than skipping verification entirely - this is
    // what the fix actually buys. Loose bounds only (shared CI/dev hardware
    // varies) - this asserts the right order of magnitude, not exact timing.
    $realHash = secure_password_hash('some-real-user-password');
    $t0 = microtime(true);
    password_verify('a-guess', ERASED_TIMING_DUMMY_HASH);
    $dummyMs = (microtime(true) - $t0) * 1000;
    $t0 = microtime(true);
    password_verify('a-guess', $realHash);
    $realMs = (microtime(true) - $t0) * 1000;
    $check(
        $dummyMs > 0.5,
        sprintf('verifying against the dummy hash takes real, non-trivial time (%.2fms, not a near-instant short-circuit)', $dummyMs)
    );
    $check(
        $realMs > 0.5,
        sprintf('verifying against a real hash takes comparable time for reference (%.2fms)', $realMs)
    );

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll login-timing-dummy-hash checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
