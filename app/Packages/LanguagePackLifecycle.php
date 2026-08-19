<?php
declare(strict_types=1);

namespace Erased\Packages;

use Erased\Language\TranslationValidator;
use PDO;
use RuntimeException;

/**
 * Shared lifecycle for every language-pack (type=language) package. Every
 * pack's install/enable/disable/uninstall behavior is identical - sync one
 * `languages` row and copy two JSON translation files - so this is used as
 * PackageLifecycleLoader's type-based fallback (mirroring NoopPackageLifecycle
 * for themes) rather than requiring every pack to ship its own duplicate
 * Lifecycle.php. A pack that genuinely needs different behavior can still
 * declare its own `lifecycle` block, which takes precedence unchanged.
 */
final class LanguagePackLifecycle implements PackageLifecycle
{
    public function install(PackageManifest $manifest, string $packagePath): void
    {
        $this->syncFiles($manifest, $packagePath);
        $this->upsertRow($manifest, false);
    }

    public function upgrade(PackageManifest $manifest, string $fromVersion): void
    {
        $this->syncFiles($manifest, $this->installedPath($manifest));
        $this->upsertRow($manifest, null);
    }

    public function enable(PackageManifest $manifest): void
    {
        $code = $this->requireCode($manifest);
        db()->prepare('UPDATE languages SET is_active=1 WHERE code=?')->execute([$code]);
    }

    public function disable(PackageManifest $manifest): void
    {
        $code = $this->requireCode($manifest);
        if ($code === (string)setting('admin_language', 'en')) {
            set_setting('admin_language', 'en');
        }
        if ($code === (string)setting('site_language', 'en')) {
            set_setting('site_language', 'en');
        }
        db()->prepare('UPDATE languages SET is_active=0 WHERE code=?')->execute([$code]);
    }

    public function uninstall(PackageManifest $manifest, bool $removeData = false): void
    {
        $code = $this->requireCode($manifest);
        if ($removeData) {
            erased_delete_language_completely($code);
            return;
        }
        // Preserving-data mode: leave the languages row and storage/languages/{code}/
        // files exactly as they are (reinstalling later finds them again), just take
        // the language out of circulation the same way disable() does.
        db()->prepare('UPDATE languages SET is_active=0 WHERE code=?')->execute([$code]);
    }

    private function syncFiles(PackageManifest $manifest, string $packagePath): void
    {
        $code = $this->requireCode($manifest);
        $validator = new TranslationValidator();

        // Validate both files fully before copying either - otherwise a package
        // whose site.json passes but admin.json fails would leave the language
        // half-updated (site.json overwritten, admin.json untouched), silently
        // corrupting a previously good installation. Either both apply, or neither does.
        foreach (['site.json', 'admin.json'] as $file) {
            $source = rtrim($packagePath, '/').'/'.$file;
            if (!is_file($source)) {
                throw new RuntimeException("Language package is missing '{$file}'.");
            }
            $decoded = json_decode((string)file_get_contents($source), true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Language package's '{$file}' is not valid JSON.");
            }
            $group = $file === 'admin.json' ? 'admin' : 'site';
            $result = $validator->validate($group, $decoded);
            if ($result['errors'] !== []) {
                throw new RuntimeException("Language package '{$file}' failed validation: ".implode(' ', $result['errors']));
            }
        }

        $dir = language_dir($code);
        $baseDir = dirname($dir);
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            error_log("ERASED CMS: Could not create base language directory for '{$code}'.");
        }
        if (is_dir($baseDir)) {
            @chmod($baseDir, 0755);
        }
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create language directory for '{$code}'.");
        }
        if (is_dir($dir)) {
            @chmod($dir, 0755);
        }
        foreach (['site.json', 'admin.json'] as $file) {
            $source = rtrim($packagePath, '/').'/'.$file;
            if (!copy($source, $dir.'/'.$file)) {
                throw new RuntimeException("Could not copy '{$file}' into place.");
            }
        }
    }

    /**
     * Explicit select-then-insert-or-update rather than an `ON DUPLICATE KEY
     * UPDATE` upsert - MySQL-only syntax that would make this untestable
     * against the sqlite fixtures this test suite uses elsewhere (see
     * InstalledPackageRepository::save() for the same established pattern).
     */
    private function upsertRow(PackageManifest $manifest, ?bool $activate): void
    {
        $code = $this->requireCode($manifest);
        $name = $manifest->languageName() ?? $code;
        $native = $manifest->languageNativeName() ?? $name;
        $rtl = $manifest->languageIsRtl() ? 1 : 0;
        $pdo = $this->pdo();

        $existing = $pdo->prepare('SELECT is_active FROM languages WHERE code=?');
        $existing->execute([$code]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

        // Upgrade (activate === null): leave is_active exactly as it currently
        // is rather than resetting it, since an update should never silently
        // deactivate an already-enabled language.
        $isActive = $activate ?? (int)($existingRow['is_active'] ?? 0);

        if ($existingRow === false) {
            $pdo->prepare(
                'INSERT INTO languages(code,name,native_name,is_default,is_active,is_rtl) VALUES(?,?,?,0,?,?)'
            )->execute([$code, $name, $native, $isActive ? 1 : 0, $rtl]);
            return;
        }

        $pdo->prepare('UPDATE languages SET name=?,native_name=?,is_active=?,is_rtl=? WHERE code=?')
            ->execute([$name, $native, $isActive ? 1 : 0, $rtl, $code]);
    }

    private function requireCode(PackageManifest $manifest): string
    {
        $code = $manifest->languageCode();
        if ($code === null) {
            throw new RuntimeException("Language package '{$manifest->id()}' is missing a language_code.");
        }
        return $code;
    }

    private function installedPath(PackageManifest $manifest): string
    {
        $root = defined('ROOT') ? ROOT : dirname(__DIR__, 2);
        return $root.'/storage/plugins/installed/'.$manifest->id();
    }

    private function pdo(): PDO
    {
        return db();
    }
}
