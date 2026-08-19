<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/Packages/PackageArchiveInspector.php';
require_once dirname(__DIR__).'/app/Packages/PackageZipExtractor.php';
require_once dirname(__DIR__).'/app/CoreUpdate/CoreUpdateManifest.php';
require_once dirname(__DIR__).'/app/CoreUpdate/CoreUpdateStager.php';

use Erased\CoreUpdate\CoreUpdateStager;

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ERROR: PHP ext-zip is required for core-update ZIP support.\n");
    exit(1);
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

$workspace = sys_get_temp_dir().'/erased-core-stager-'.bin2hex(random_bytes(6));
$staging = $workspace.'/staging';
mkdir($workspace, 0750, true);

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

try {
    $stager = new CoreUpdateStager();

    // 1. A valid update ZIP (update.json + a couple of core files) stages
    //    correctly and its manifest is readable.
    $zipPath = $workspace.'/update.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', json_encode(['version' => '0.4.0', 'requires' => '0.3.0']));
    $zip->addFromString('VERSION', '0.4.0');
    $zip->addFromString('app/example.php', '<?php echo "hi";');
    $zip->close();

    $staged = $stager->stage($zipPath, $staging);
    $check(is_dir($staged['directory']), 'stage() creates a staging directory');
    $check($staged['manifest']->version() === '0.4.0', 'stage() parses the version from update.json');
    $check(is_file($staged['directory'].'/VERSION'), 'stage() extracts sibling files alongside update.json');
    $check(is_file($staged['directory'].'/app/example.php'), 'stage() extracts nested files correctly');

    $stager->discard($staged['directory'], $staging);
    $check(!is_dir($staged['directory']), 'discard() removes the staging directory');

    // 2. A ZIP with no update.json is rejected (inherited from
    //    PackageArchiveInspector, configured for 'update.json' instead of
    //    'package.json').
    $noManifestZip = $workspace.'/no-manifest.zip';
    $zip = new ZipArchive();
    $zip->open($noManifestZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('app/example.php', '<?php');
    $zip->close();
    $rejectedNoManifest = false;
    try {
        $stager->stage($noManifestZip, $staging);
    } catch (Throwable $e) {
        $rejectedNoManifest = str_contains($e->getMessage(), 'update.json');
    }
    $check($rejectedNoManifest, 'a ZIP with no update.json is rejected');

    // 3. A ZIP with invalid JSON in update.json is rejected and cleans up
    //    its own staging directory.
    $badJsonZip = $workspace.'/bad-json.zip';
    $zip = new ZipArchive();
    $zip->open($badJsonZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', 'not valid json');
    $zip->close();
    $rejectedBadJson = false;
    try {
        $stager->stage($badJsonZip, $staging);
    } catch (Throwable $e) {
        $rejectedBadJson = str_contains($e->getMessage(), 'Invalid update.json');
    }
    $check($rejectedBadJson, 'a ZIP with malformed update.json content is rejected');

    // 4. Zip-slip protection is inherited "for free" from the shared
    //    PackageArchiveInspector - confirm a path-traversal entry is
    //    rejected without any core-update-specific code having to
    //    reimplement that check.
    $slipZip = $workspace.'/slip.zip';
    $zip = new ZipArchive();
    $zip->open($slipZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', json_encode(['version' => '0.4.0', 'requires' => '0.3.0']));
    $zip->addFromString('../../../etc/evil.php', '<?php');
    $zip->close();
    $rejectedSlip = false;
    try {
        $stager->stage($slipZip, $staging);
    } catch (Throwable $e) {
        $rejectedSlip = str_contains($e->getMessage(), 'traversal') || str_contains($e->getMessage(), 'Unsafe');
    }
    $check($rejectedSlip, 'a zip-slip path-traversal entry is rejected (inherited from PackageArchiveInspector)');

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll CoreUpdateStager checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
} finally {
    $remove($workspace);
}
