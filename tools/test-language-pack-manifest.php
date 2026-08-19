<?php
declare(strict_types=1);

use Erased\Packages\PackageManifest;
use Erased\Packages\PackageValidator;

$root = dirname(__DIR__);
foreach ([
    'app/Packages/PackageManifest.php',
    'app/Packages/PackageValidator.php',
] as $file) {
    require_once $root.'/'.$file;
}

$workspace = sys_get_temp_dir().'/erased-language-pack-manifest-'.bin2hex(random_bytes(6));

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

/** @return array<string,mixed> */
$baseManifest = static function (array $overrides = []): array {
    return array_replace([
        'id' => 'erased.language-zz',
        'type' => 'language',
        'name' => 'Test Language',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'A test language pack.',
        'language_code' => 'zz',
        'language_name' => 'Test Language',
        'language_native_name' => 'Test Language Native',
    ], $overrides);
};

try {
    // --- A well-formed language manifest is accepted, and its accessors work ---
    $manifest = new PackageManifest($baseManifest());
    if ($manifest->languageCode() !== 'zz') {
        throw new RuntimeException('languageCode() did not return the declared code.');
    }
    if ($manifest->languageName() !== 'Test Language') {
        throw new RuntimeException('languageName() did not return the declared name.');
    }
    if ($manifest->languageNativeName() !== 'Test Language Native') {
        throw new RuntimeException('languageNativeName() did not return the declared native name.');
    }
    if ($manifest->languageIsRtl() !== false) {
        throw new RuntimeException('languageIsRtl() should default to false when not declared.');
    }

    // --- language_code is case-normalized and a regional variant is accepted ---
    $regional = new PackageManifest($baseManifest(['id' => 'erased.language-pt-br', 'language_code' => 'PT-br']));
    if ($regional->languageCode() !== 'pt-br') {
        throw new RuntimeException('languageCode() did not lowercase a regional code.');
    }

    // --- language_rtl is honored when declared true ---
    $rtl = new PackageManifest($baseManifest(['id' => 'erased.language-he', 'language_code' => 'he', 'language_rtl' => true]));
    if ($rtl->languageIsRtl() !== true) {
        throw new RuntimeException('languageIsRtl() did not honor a declared true value.');
    }

    // --- Malformed language_code is rejected ---
    $missingCode = $baseManifest();
    unset($missingCode['language_code']);
    $cases = [
        'missing language_code' => $missingCode,
        'empty language_code' => $baseManifest(['language_code' => '']),
        'too-long bare code' => $baseManifest(['language_code' => 'zzz']),
        'invalid characters' => $baseManifest(['language_code' => 'z9']),
    ];
    foreach ($cases as $label => $data) {
        $rejected = false;
        try {
            new PackageManifest($data);
        } catch (InvalidArgumentException $error) {
            $rejected = str_contains($error->getMessage(), 'language_code');
        }
        if (!$rejected) {
            throw new RuntimeException("Expected rejection for case '{$label}'.");
        }
    }

    // --- Missing language_name / language_native_name are rejected ---
    $missingName = $baseManifest();
    unset($missingName['language_name']);
    $rejected = false;
    try {
        new PackageManifest($missingName);
    } catch (InvalidArgumentException $error) {
        $rejected = str_contains($error->getMessage(), 'language_name');
    }
    if (!$rejected) {
        throw new RuntimeException('Missing language_name was not rejected.');
    }

    $missingNative = $baseManifest();
    unset($missingNative['language_native_name']);
    $rejected = false;
    try {
        new PackageManifest($missingNative);
    } catch (InvalidArgumentException $error) {
        $rejected = str_contains($error->getMessage(), 'language_native_name');
    }
    if (!$rejected) {
        throw new RuntimeException('Missing language_native_name was not rejected.');
    }

    // --- A non-boolean language_rtl is rejected ---
    $rejected = false;
    try {
        new PackageManifest($baseManifest(['language_rtl' => 'yes']));
    } catch (InvalidArgumentException $error) {
        $rejected = str_contains($error->getMessage(), 'language_rtl');
    }
    if (!$rejected) {
        throw new RuntimeException('A non-boolean language_rtl was not rejected.');
    }

    // --- PackageValidator requires site.json and admin.json to exist on disk ---
    $validator = new PackageValidator();

    $completeDir = $workspace.'/complete';
    mkdir($completeDir, 0750, true);
    file_put_contents($completeDir.'/package.json', json_encode($baseManifest()));
    file_put_contents($completeDir.'/site.json', json_encode(['home' => 'Home']));
    file_put_contents($completeDir.'/admin.json', json_encode(['dashboard' => 'Dashboard']));
    $result = $validator->validateDirectory($completeDir);
    if ($result['valid'] !== true) {
        throw new RuntimeException('A complete language package directory was rejected: '.implode(' ', $result['errors']));
    }

    $missingAdminDir = $workspace.'/missing-admin';
    mkdir($missingAdminDir, 0750, true);
    file_put_contents($missingAdminDir.'/package.json', json_encode($baseManifest(['id' => 'erased.language-missing-admin'])));
    file_put_contents($missingAdminDir.'/site.json', json_encode(['home' => 'Home']));
    $result = $validator->validateDirectory($missingAdminDir);
    if ($result['valid'] !== false || !in_array('Declared package path is missing: admin.json', $result['errors'], true)) {
        throw new RuntimeException('A language package missing admin.json was not rejected with the expected error.');
    }

    $missingSiteDir = $workspace.'/missing-site';
    mkdir($missingSiteDir, 0750, true);
    file_put_contents($missingSiteDir.'/package.json', json_encode($baseManifest(['id' => 'erased.language-missing-site'])));
    file_put_contents($missingSiteDir.'/admin.json', json_encode(['dashboard' => 'Dashboard']));
    $result = $validator->validateDirectory($missingSiteDir);
    if ($result['valid'] !== false || !in_array('Declared package path is missing: site.json', $result['errors'], true)) {
        throw new RuntimeException('A language package missing site.json was not rejected with the expected error.');
    }

    // --- A theme package is unaffected by the new language-specific checks ---
    $themeDir = $workspace.'/theme';
    mkdir($themeDir, 0750, true);
    file_put_contents($themeDir.'/package.json', json_encode([
        'id' => 'erased.theme-manifest-test', 'type' => 'theme', 'name' => 'Theme', 'version' => '1.0.0',
        'requires' => '0.3.0', 'author' => 'ERASED CMS', 'description' => 'Theme.',
        'theme_scope' => 'website', 'assets' => 'theme.css',
    ]));
    file_put_contents($themeDir.'/theme.css', 'body{}');
    $result = $validator->validateDirectory($themeDir);
    if ($result['valid'] !== true) {
        throw new RuntimeException('A valid theme package was rejected after adding language-specific validation: '.implode(' ', $result['errors']));
    }

    fwrite(STDOUT, "Language pack manifest smoke test passed.\n");
    fwrite(STDOUT, "Validated language_code/language_name/language_native_name/language_rtl acceptance and rejection, and PackageValidator's new site.json/admin.json existence check.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
