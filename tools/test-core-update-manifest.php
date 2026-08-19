<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/CoreUpdate/CoreUpdateManifest.php';

use Erased\CoreUpdate\CoreUpdateManifest;

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
    $manifest = CoreUpdateManifest::fromJson(json_encode([
        'version' => '0.4.0',
        'requires' => '0.3.0',
        'name' => 'v0.4.0 release',
        'notes' => 'Some notes.',
    ]));
    $check($manifest->version() === '0.4.0', 'parses version');
    $check($manifest->requires() === '0.3.0', 'parses requires');
    $check($manifest->name() === 'v0.4.0 release', 'parses optional name');
    $check($manifest->notes() === 'Some notes.', 'parses optional notes');

    $minimal = CoreUpdateManifest::fromJson(json_encode(['version' => '1.0.0', 'requires' => '0.9.0']));
    $check($minimal->name() === null, 'name is null when absent');
    $check($minimal->notes() === null, 'notes is null when absent');

    $missingVersion = false;
    try {
        CoreUpdateManifest::fromJson(json_encode(['requires' => '0.9.0']));
    } catch (\InvalidArgumentException $e) {
        $missingVersion = true;
    }
    $check($missingVersion, 'rejects a manifest missing version');

    $missingRequires = false;
    try {
        CoreUpdateManifest::fromJson(json_encode(['version' => '1.0.0']));
    } catch (\InvalidArgumentException $e) {
        $missingRequires = true;
    }
    $check($missingRequires, 'rejects a manifest missing requires');

    $badSemver = false;
    try {
        CoreUpdateManifest::fromJson(json_encode(['version' => 'not-a-version', 'requires' => '0.9.0']));
    } catch (\InvalidArgumentException $e) {
        $badSemver = true;
    }
    $check($badSemver, 'rejects a non-semver version string');

    $notObject = false;
    try {
        CoreUpdateManifest::fromJson('"just a string"');
    } catch (\InvalidArgumentException $e) {
        $notObject = true;
    }
    $check($notObject, 'rejects JSON that does not decode to an object');

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll CoreUpdateManifest checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
