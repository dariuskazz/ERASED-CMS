<?php
declare(strict_types=1);

/**
 * LanguagePackZipBuilder depends on several app/bootstrap.php-hosted
 * globals (erased_master_translation_defaults(), erased_language_catalog(),
 * translation_data()) that need a real MySQL connection to run for real -
 * unavailable in this local test environment (only pdo_sqlite is
 * installed here, matching every other test that stubs just enough of the
 * real app rather than loading it - see tools/test-plugin-admin-surface.php's
 * own docblock for the same reasoning).
 */

use Erased\Language\LanguagePackZipBuilder;
use Erased\Language\TranslationValidator;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Language/LanguagePackZipBuilder.php';
require_once dirname(__DIR__).'/app/Language/TranslationValidator.php';
require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';

$masterDefaults = [
    'site' => ['dashboard' => 'Dashboard', 'save' => 'Save', 'cancel' => 'Cancel'],
    'admin' => ['users' => 'Users', 'settings' => 'Settings'],
];
function erased_master_translation_defaults(string $group): array
{
    global $masterDefaults;
    return $masterDefaults[$group] ?? [];
}

$catalog = [
    'zz' => ['name' => 'Test Language', 'native' => 'Testish', 'rtl' => false],
];
function erased_language_catalog(): array
{
    global $catalog;
    return $catalog;
}

// Deliberately includes 'stale_key' - not in $masterDefaults['site'] at all,
// simulating a leftover on-disk key from a since-removed dictionary entry -
// and overrides 'save' with a real, non-default translated value.
$onDiskSite = ['dashboard' => 'Dashboard', 'save' => 'Lagre', 'cancel' => 'Cancel', 'stale_key' => 'Should never appear in an export'];
$onDiskAdmin = ['users' => 'Brukere', 'settings' => 'Settings'];
function translation_data(string $language, string $group = 'site'): array
{
    global $onDiskSite, $onDiskAdmin;
    return $group === 'admin' ? $onDiskAdmin : $onDiskSite;
}

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

/** @return array{manifest:array<string,mixed>,site:array<string,string>,admin:array<string,string>} */
$extractZip = static function (string $bytes): array {
    $tmpPath = tempnam(sys_get_temp_dir(), 'erased-lang-zip-test-');
    file_put_contents($tmpPath, $bytes);
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        throw new RuntimeException('Could not open the built ZIP.');
    }
    $manifest = json_decode((string)$zip->getFromName('package.json'), true);
    $site = json_decode((string)$zip->getFromName('site.json'), true);
    $admin = json_decode((string)$zip->getFromName('admin.json'), true);
    $zip->close();
    unlink($tmpPath);
    return ['manifest' => $manifest, 'site' => $site, 'admin' => $admin];
};

try {
    $builder = new LanguagePackZipBuilder();
    $validator = new TranslationValidator();

    // --- buildBase(): new-language starter, pre-filled with English ---
    $baseBytes = $builder->buildBase('de', 'German', 'Deutsch', false);
    $base = $extractZip($baseBytes);
    $check(($base['manifest']['type'] ?? null) === 'language', 'buildBase() manifest type is language');
    $check(($base['manifest']['language_code'] ?? null) === 'de', 'buildBase() manifest language_code is de');
    $baseManifestObj = new PackageManifest($base['manifest']);
    $check($baseManifestObj->languageCode() === 'de', 'buildBase() manifest constructs a valid real PackageManifest');
    $check($base['site'] === $masterDefaults['site'], 'buildBase() site.json is exactly the master English defaults');
    $check($base['admin'] === $masterDefaults['admin'], 'buildBase() admin.json is exactly the master English defaults');
    $baseSiteValidation = $validator->validate('site', $base['site']);
    $check($baseSiteValidation['errors'] === [], 'buildBase() site.json passes TranslationValidator with zero errors');
    $baseAdminValidation = $validator->validate('admin', $base['admin']);
    $check($baseAdminValidation['errors'] === [], 'buildBase() admin.json passes TranslationValidator with zero errors');

    // --- buildExport(): real live content for an existing language, minus any stale keys ---
    $exportBytes = $builder->buildExport('zz');
    $export = $extractZip($exportBytes);
    $check(($export['manifest']['language_code'] ?? null) === 'zz', 'buildExport() manifest language_code is zz');
    $check(($export['manifest']['language_name'] ?? null) === 'Test Language', 'buildExport() manifest pulls the real catalog name');
    $check(($export['site']['save'] ?? null) === 'Lagre', 'buildExport() site.json carries the real translated value, not the English default');
    $check(!array_key_exists('stale_key', $export['site']), 'buildExport() drops a key not recognized by the master dictionary');
    $check(count($export['site']) === count($masterDefaults['site']), 'buildExport() site.json has exactly the recognized key count, no more');
    $exportSiteValidation = $validator->validate('site', $export['site']);
    $check($exportSiteValidation['errors'] === [], 'buildExport() site.json passes TranslationValidator with zero errors (the stale-key self-inflicted-failure this filtering exists to prevent)');
    $exportAdminValidation = $validator->validate('admin', $export['admin']);
    $check($exportAdminValidation['errors'] === [], 'buildExport() admin.json passes TranslationValidator with zero errors');

    // --- buildExport() for an unknown code throws cleanly ---
    try {
        $builder->buildExport('does-not-exist');
        $check(false, 'buildExport() throws for an unknown language code');
    } catch (RuntimeException $e) {
        $check(str_contains($e->getMessage(), 'does-not-exist'), 'buildExport() throws for an unknown language code');
    }

    if ($fail === 0) {
        fwrite(STDOUT, "Language pack ZIP builder test passed.\n");
        fwrite(STDOUT, "Validated buildBase()'s English-filled starter, buildExport()'s real-content export with stale-key filtering, and both outputs passing the real TranslationValidator.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
