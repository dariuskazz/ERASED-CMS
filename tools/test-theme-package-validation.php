<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Packages/PackageManifest.php';

use Erased\Packages\PackageManifest;

$base = [
    'id' => 'my-theme',
    'type' => 'theme',
    'name' => 'My Theme',
    'version' => '1.0.0',
    'requires' => '0.5.0',
    'author' => 'Someone',
    'description' => 'A theme.',
];

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

// A complete, valid theme manifest is accepted, and its accessors work.
try {
    $manifest = new PackageManifest($base + ['theme_scope' => 'website', 'assets' => 'theme.css']);
    $check($manifest->themeScope() === 'website', 'valid theme manifest accepted, themeScope() correct');
    $check($manifest->assetsPath() === 'theme.css', 'valid theme manifest accepted, assetsPath() correct');
} catch (Throwable $e) {
    $check(false, 'valid theme manifest accepted ('.$e->getMessage().')');
}

// Missing theme_scope is rejected.
try {
    new PackageManifest($base + ['assets' => 'theme.css']);
    $check(false, 'missing theme_scope is rejected');
} catch (InvalidArgumentException $e) {
    $check(str_contains($e->getMessage(), 'theme_scope'), 'missing theme_scope is rejected');
}

// Invalid theme_scope value is rejected.
try {
    new PackageManifest($base + ['theme_scope' => 'both', 'assets' => 'theme.css']);
    $check(false, 'invalid theme_scope value is rejected');
} catch (InvalidArgumentException $e) {
    $check(str_contains($e->getMessage(), 'theme_scope'), 'invalid theme_scope value is rejected');
}

// Missing assets is rejected.
try {
    new PackageManifest($base + ['theme_scope' => 'admin']);
    $check(false, 'missing assets is rejected');
} catch (InvalidArgumentException $e) {
    $check(str_contains($e->getMessage(), 'assets'), 'missing assets is rejected');
}

// assets not ending in .css is rejected.
try {
    new PackageManifest($base + ['theme_scope' => 'admin', 'assets' => 'theme.js']);
    $check(false, 'non-.css assets is rejected');
} catch (InvalidArgumentException $e) {
    $check(str_contains($e->getMessage(), 'assets'), 'non-.css assets is rejected');
}

// assets with a subdirectory is rejected (the /theme-asset/ route only serves flat filenames).
try {
    new PackageManifest($base + ['theme_scope' => 'admin', 'assets' => 'css/theme.css']);
    $check(false, 'assets with a subdirectory is rejected');
} catch (InvalidArgumentException $e) {
    $check(str_contains($e->getMessage(), 'assets'), 'assets with a subdirectory is rejected');
}

// Non-theme package types are completely unaffected by the new rules.
try {
    $moduleManifest = new PackageManifest([
        'id' => 'my-module',
        'type' => 'module',
        'name' => 'My Module',
        'version' => '1.0.0',
        'requires' => '0.5.0',
        'author' => 'Someone',
        'description' => 'A module.',
    ]);
    $check($moduleManifest->themeScope() === null, 'non-theme package: themeScope() is null');
    $check($moduleManifest->assetsPath() === null, 'non-theme package: assetsPath() is null, no assets required');
} catch (Throwable $e) {
    $check(false, 'non-theme package unaffected by theme rules ('.$e->getMessage().')');
}

if ($fail > 0) {
    fwrite(STDERR, "\n{$fail} assertion(s) failed.\n");
    exit(1);
}

echo "\nTheme package validation test passed.\n";
