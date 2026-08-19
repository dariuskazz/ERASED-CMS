<?php
declare(strict_types=1);

namespace Erased\Language;

use RuntimeException;
use ZipArchive;

/**
 * Builds install-ready language-pack ZIPs (package.json + site.json +
 * admin.json), the same shape LanguagePackLifecycle::syncFiles() and
 * PackageValidator's language-type required-file check already expect -
 * two entry points sharing that one bundle format:
 *
 * - buildBase(): a new-language starter, pre-filled with the master
 *   English values as editable content (more useful than blank strings).
 * - buildExport(): an existing language's real live content, for
 *   improving/updating an already-installed pack.
 *
 * Both filter their translation values down to keys erased_master_translation_defaults()
 * actually recognizes - translation_data()'s merge is a union of master +
 * on-disk keys, so a stale key left over in storage/languages/{code}/*.json
 * from a since-removed dictionary entry would otherwise round-trip into the
 * export and immediately fail TranslationValidator's "unrecognized key"
 * check on the very next install, self-inflicted by the export itself.
 */
final class LanguagePackZipBuilder
{
    public function buildBase(string $code, string $name, string $nativeName, bool $rtl): string
    {
        $site = $this->filterToRecognizedKeys(erased_master_translation_defaults('site'), 'site');
        $admin = $this->filterToRecognizedKeys(erased_master_translation_defaults('admin'), 'admin');
        return $this->buildZipBytes($this->manifestFor($code, $name, $nativeName, $rtl), $site, $admin);
    }

    public function buildExport(string $code): string
    {
        $catalog = erased_language_catalog();
        $meta = $catalog[$code] ?? null;
        if ($meta === null) {
            throw new RuntimeException("Unknown language code '{$code}'.");
        }
        $site = $this->filterToRecognizedKeys(translation_data($code, 'site'), 'site');
        $admin = $this->filterToRecognizedKeys(translation_data($code, 'admin'), 'admin');
        $name = (string)($meta['name'] ?? $code);
        $native = (string)($meta['native'] ?? $name);
        $rtl = (bool)($meta['rtl'] ?? false);
        return $this->buildZipBytes($this->manifestFor($code, $name, $native, $rtl), $site, $admin);
    }

    /** @param array<string,string> $values */
    private function filterToRecognizedKeys(array $values, string $group): array
    {
        $recognized = array_flip(array_keys(erased_master_translation_defaults($group)));
        return array_intersect_key($values, $recognized);
    }

    /** @return array<string,mixed> */
    private function manifestFor(string $code, string $name, string $nativeName, bool $rtl): array
    {
        return [
            'id' => 'erased.language-' . $code,
            'type' => 'language',
            'name' => $name . ' Language Pack',
            'version' => '1.0.0',
            'requires' => '0.3.0',
            'author' => 'ERASED CMS',
            'description' => 'Translation pack for ' . $name . ' (' . $nativeName . ').',
            'language_code' => $code,
            'language_name' => $name,
            'language_native_name' => $nativeName,
            'language_rtl' => $rtl,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,string> $site
     * @param array<string,string> $admin
     */
    private function buildZipBytes(array $manifest, array $site, array $admin): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'erased-lang-pack-');
        if ($tmpPath === false) {
            throw new RuntimeException('Could not allocate a temp file for the language pack ZIP.');
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open($tmpPath, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the language pack ZIP.');
            }
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $zip->addFromString('package.json', (string)json_encode($manifest, $flags));
            $zip->addFromString('site.json', (string)json_encode($site, $flags));
            $zip->addFromString('admin.json', (string)json_encode($admin, $flags));
            $zip->close();
            $bytes = file_get_contents($tmpPath);
            if ($bytes === false) {
                throw new RuntimeException('Could not read back the generated language pack ZIP.');
            }
            return $bytes;
        } finally {
            @unlink($tmpPath);
        }
    }
}
