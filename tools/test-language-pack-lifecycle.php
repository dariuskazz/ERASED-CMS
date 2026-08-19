<?php
declare(strict_types=1);

use Erased\Packages\LanguagePackLifecycle;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageManifest;

$root = dirname(__DIR__);
foreach ([
    'app/Packages/PackageManifest.php',
    'app/Packages/PackageLifecycle.php',
    'app/Packages/NoopPackageLifecycle.php',
    'app/Packages/PackageLifecycleLoader.php',
    'app/Packages/LanguagePackLifecycle.php',
    'app/Language/TranslationValidator.php',
] as $file) {
    require_once $root.'/'.$file;
}

$workspace = sys_get_temp_dir().'/erased-language-pack-lifecycle-'.bin2hex(random_bytes(6));
$languagesRoot = $workspace.'/storage-languages';
$packageDir = $workspace.'/package';

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

// Minimal global-function stubs matching this codebase's established harness
// convention for exercising code that reaches for app/bootstrap.php globals,
// without loading the real, DB-connecting bootstrap.php (see the stubbing
// note in docs/STATUS.md's "Footer rebuilt" entry for the precedent).
$GLOBALS['__test_pdo'] = null;
$GLOBALS['__test_settings'] = [];
$GLOBALS['__test_languages_root'] = $languagesRoot;

function db(): PDO
{
    return $GLOBALS['__test_pdo'];
}
function setting(string $key, string $default = ''): string
{
    return $GLOBALS['__test_settings'][$key] ?? $default;
}
function set_setting(string $key, string $value): void
{
    $GLOBALS['__test_settings'][$key] = $value;
}
function language_dir(string $language): string
{
    return $GLOBALS['__test_languages_root'].'/'.$language;
}
/** @return array<string,string> */
function erased_master_translation_defaults(string $group = 'site'): array
{
    return $group === 'admin'
        ? ['dashboard' => 'Dashboard', 'save' => 'Save']
        : ['home' => 'Home', 'welcome' => 'Welcome, {name}!'];
}
function erased_delete_language_completely(string $code): void
{
    $code = strtolower(trim($code));
    if ($code === '' || $code === 'en') {
        throw new RuntimeException('English is the built-in fallback language and cannot be deleted.');
    }
    db()->prepare('DELETE FROM languages WHERE code=?')->execute([$code]);
    $dir = language_dir($code);
    if (is_dir($dir)) {
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            @unlink($dir.'/'.$file);
        }
        @rmdir($dir);
    }
}

try {
    mkdir($languagesRoot, 0750, true);
    mkdir($packageDir, 0750, true);
    file_put_contents($packageDir.'/site.json', json_encode(['home' => 'Hjem', 'welcome' => 'Velkommen, {name}!']));
    file_put_contents($packageDir.'/admin.json', json_encode(['dashboard' => 'Kontrollpanel', 'save' => 'Lagre']));

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE languages (code TEXT PRIMARY KEY, name TEXT NOT NULL, native_name TEXT NOT NULL, '
        .'is_default INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1, is_rtl INTEGER NOT NULL DEFAULT 0)'
    );
    $GLOBALS['__test_pdo'] = $pdo;

    $manifest = new PackageManifest([
        'id' => 'erased.language-zz-test',
        'type' => 'language',
        'name' => 'Test Language',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Language pack lifecycle test fixture.',
        'language_code' => 'zz',
        'language_name' => 'Test Language',
        'language_native_name' => 'Test Language Native',
    ]);

    // --- PackageLifecycleLoader falls back to LanguagePackLifecycle for a
    //     lifecycle-less type=language manifest, mirroring the theme/Noop fallback ---
    $loader = new PackageLifecycleLoader();
    $lifecycle = $loader->load($manifest, $packageDir);
    if (!$lifecycle instanceof LanguagePackLifecycle) {
        throw new RuntimeException('PackageLifecycleLoader did not fall back to LanguagePackLifecycle for a type=language manifest.');
    }

    // --- install() copies files and creates the languages row with is_active=0 ---
    $lifecycle->install($manifest, $packageDir);
    $row = $pdo->query("SELECT * FROM languages WHERE code='zz'")->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new RuntimeException('install() did not create a languages row.');
    }
    if ((int)$row['is_active'] !== 0) {
        throw new RuntimeException('install() should leave a freshly installed language inactive until explicitly enabled.');
    }
    if (!is_file($languagesRoot.'/zz/site.json') || !is_file($languagesRoot.'/zz/admin.json')) {
        throw new RuntimeException('install() did not copy both translation files into storage/languages/zz/.');
    }
    $copiedSite = json_decode((string)file_get_contents($languagesRoot.'/zz/site.json'), true);
    if (($copiedSite['home'] ?? null) !== 'Hjem') {
        throw new RuntimeException('install() did not copy the real file content.');
    }

    // --- enable() flips is_active ---
    $lifecycle->enable($manifest);
    $row = $pdo->query("SELECT is_active FROM languages WHERE code='zz'")->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['is_active'] !== 1) {
        throw new RuntimeException('enable() did not activate the language.');
    }

    // --- disable() flips is_active back and resets settings that pointed at it ---
    set_setting('site_language', 'zz');
    set_setting('admin_language', 'zz');
    $lifecycle->disable($manifest);
    $row = $pdo->query("SELECT is_active FROM languages WHERE code='zz'")->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['is_active'] !== 0) {
        throw new RuntimeException('disable() did not deactivate the language.');
    }
    if (setting('site_language') !== 'en' || setting('admin_language') !== 'en') {
        throw new RuntimeException('disable() did not reset site_language/admin_language away from the disabled code.');
    }

    // --- uninstall(removeData: false) leaves the row and files intact, just inactive ---
    $lifecycle->enable($manifest);
    $lifecycle->uninstall($manifest, false);
    if ($pdo->query("SELECT COUNT(*) FROM languages WHERE code='zz'")->fetchColumn() != 1) {
        throw new RuntimeException('uninstall(removeData:false) should preserve the languages row.');
    }
    if (!is_file($languagesRoot.'/zz/site.json')) {
        throw new RuntimeException('uninstall(removeData:false) should preserve the on-disk translation files.');
    }
    $row = $pdo->query("SELECT is_active FROM languages WHERE code='zz'")->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['is_active'] !== 0) {
        throw new RuntimeException('uninstall(removeData:false) should deactivate the language.');
    }

    // --- Reinstalling after a keep-data uninstall works cleanly ---
    $lifecycle->install($manifest, $packageDir);
    if ($pdo->query("SELECT COUNT(*) FROM languages WHERE code='zz'")->fetchColumn() != 1) {
        throw new RuntimeException('Reinstalling after a keep-data uninstall did not leave exactly one row.');
    }

    // --- install() rejects a bundled file that fails validation (the real
    //     enforcement backstop, since a language ZIP can also reach the
    //     lifecycle via the generic, validation-blind /admin/packages screen),
    //     and leaves whatever was already in place untouched rather than
    //     half-applying the bad update ---
    $badPackageDir = $workspace.'/bad-package';
    mkdir($badPackageDir, 0750, true);
    file_put_contents($badPackageDir.'/site.json', json_encode(['home' => 'Hjem', 'welcome' => 'Velkommen!'])); // dropped {name} placeholder
    file_put_contents($badPackageDir.'/admin.json', json_encode(['dashboard' => 'Kontrollpanel', 'save' => 'Lagre']));
    $rejected = false;
    try {
        $lifecycle->install($manifest, $badPackageDir);
    } catch (RuntimeException $error) {
        $rejected = str_contains($error->getMessage(), 'validation');
    }
    if (!$rejected) {
        throw new RuntimeException('install() did not reject a bundled translation file with a placeholder mismatch.');
    }
    if ($pdo->query("SELECT COUNT(*) FROM languages WHERE code='zz'")->fetchColumn() != 1) {
        throw new RuntimeException('A rejected install() should leave the previously installed row untouched, not remove or duplicate it.');
    }
    $siteAfterRejectedInstall = json_decode((string)file_get_contents($languagesRoot.'/zz/site.json'), true);
    if (($siteAfterRejectedInstall['welcome'] ?? null) !== 'Velkommen, {name}!') {
        throw new RuntimeException('A rejected install() should not have overwritten the previously copied translation files.');
    }

    // --- uninstall(removeData: true) removes the row and the files ---
    $lifecycle->uninstall($manifest, true);
    if ($pdo->query("SELECT COUNT(*) FROM languages WHERE code='zz'")->fetchColumn() != 0) {
        throw new RuntimeException('uninstall(removeData:true) did not remove the languages row.');
    }
    if (is_dir($languagesRoot.'/zz')) {
        throw new RuntimeException('uninstall(removeData:true) did not remove the storage/languages/zz/ directory.');
    }

    // --- erased_delete_language_completely() refuses to remove English ---
    $enRejected = false;
    try {
        erased_delete_language_completely('en');
    } catch (RuntimeException) {
        $enRejected = true;
    }
    if (!$enRejected) {
        throw new RuntimeException('erased_delete_language_completely() did not refuse to delete English.');
    }

    fwrite(STDOUT, "Language pack lifecycle smoke test passed.\n");
    fwrite(STDOUT, "Validated the loader fallback, install/enable/disable/uninstall(keep)/reinstall/uninstall(delete) round-trip, the validation backstop, and the English delete guard.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
