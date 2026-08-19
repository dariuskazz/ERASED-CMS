<?php
declare(strict_types=1);

use Erased\Packages\PackageArchiveInspector;
use Erased\Packages\PackageArchiveStager;
use Erased\Packages\PackageValidator;

require dirname(__DIR__).'/app/Packages/PackageManifest.php';
require dirname(__DIR__).'/app/Packages/PackageValidator.php';
require dirname(__DIR__).'/app/Packages/PackageArchiveInspector.php';
require dirname(__DIR__).'/app/Packages/PackageZipExtractor.php';
require dirname(__DIR__).'/app/Packages/PackageArchiveStager.php';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ERROR: PHP ext-zip is required for package ZIP support.\n");
    exit(1);
}

$workspace = sys_get_temp_dir().'/erased-package-zip-'.bin2hex(random_bytes(6));
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
    $validZip = $workspace.'/valid.zip';
    $zip = new ZipArchive();
    if ($zip->open($validZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create test ZIP.');
    }
    $zip->addFromString('example/package.json', json_encode([
        'id' => 'erased.test-zip',
        'type' => 'module',
        'name' => 'ZIP Test Package',
        'version' => '0.1.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Tests secure package staging.',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $zip->addFromString('example/README.txt', "Safe staged file.\n");
    $zip->close();

    $stager = new PackageArchiveStager(new PackageArchiveInspector(), new PackageValidator());
    $result = $stager->stage($validZip, $staging);
    if ($result['manifest']->id() !== 'erased.test-zip' || !is_file($result['directory'].'/README.txt')) {
        throw new RuntimeException('Valid package was not staged correctly.');
    }
    $stager->discard($result['directory'], $staging);

    $unsafeZip = $workspace.'/unsafe.zip';
    $zip = new ZipArchive();
    $zip->open($unsafeZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('package.json', '{}');
    $zip->addFromString('../escape.txt', 'blocked');
    $zip->close();

    $blocked = false;
    try {
        $stager->stage($unsafeZip, $staging);
    } catch (Throwable $error) {
        $blocked = str_contains($error->getMessage(), 'traversal') || str_contains($error->getMessage(), 'Unsafe');
    }
    if (!$blocked) {
        throw new RuntimeException('Path-traversal ZIP was not rejected.');
    }

    echo "Package ZIP staging smoke test passed.\n";
    echo "Validated archive staging and path-traversal rejection.\n";
} finally {
    $remove($workspace);
}
