<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Language/LanguagePackZipBuilder.php';

use Erased\Language\LanguagePackZipBuilder;

echo "Building Translated Files Update ZIPs...\n";

$root = dirname(__DIR__);
$distDir = $root . '/dist';
@mkdir($distDir, 0755, true);

// 1. Build raw storage/languages/ update zip: dist/erased-translations-update.zip
$rawZipPath = $distDir . '/erased-translations-update.zip';
if (file_exists($rawZipPath)) @unlink($rawZipPath);

$zip = new ZipArchive();
if ($zip->open($rawZipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create ZIP: {$rawZipPath}\n");
    exit(1);
}

$languagesDir = $root . '/storage/languages';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($languagesDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $relative = 'storage/languages/' . substr($item->getPathname(), strlen($languagesDir) + 1);
    if ($item->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($item->getPathname(), $relative);
    }
}
$zip->close();
echo "Created raw storage update ZIP: dist/erased-translations-update.zip (" . number_format(filesize($rawZipPath) / 1024, 1) . " KB)\n";

// 2. Build installable language pack ZIPs using LanguagePackZipBuilder for LT, NB, UA
$builder = new LanguagePackZipBuilder();
$catalog = [
    'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių kalba', 'rtl' => false],
    'nb' => ['name' => 'Norwegian Bokmål', 'native' => 'Norsk bokmål', 'rtl' => false],
    'ua' => ['name' => 'Ukrainian', 'native' => 'Українська мова', 'rtl' => false],
];

foreach ($catalog as $code => $meta) {
    $packZipPath = $distDir . "/lang-{$code}-1.0.0.zip";
    $bytes = $builder->buildBase($code, $meta['name'], $meta['native'], $meta['rtl']);
    
    // Merge live site & admin json data
    $siteData = json_decode((string)file_get_contents($languagesDir . '/' . $code . '/site.json'), true) ?: [];
    $adminData = json_decode((string)file_get_contents($languagesDir . '/' . $code . '/admin.json'), true) ?: [];
    
    $tempZip = sys_get_temp_dir() . "/lang_{$code}_temp.zip";
    file_put_contents($tempZip, $bytes);
    
    $z = new ZipArchive();
    if ($z->open($tempZip) === true) {
        $z->addFromString('site.json', json_encode($siteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $z->addFromString('admin.json', json_encode($adminData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $z->close();
    }
    
    rename($tempZip, $packZipPath);
    echo "Created installable Language Pack ZIP: dist/lang-{$code}-1.0.0.zip (" . number_format(filesize($packZipPath) / 1024, 1) . " KB)\n";
}

// 3. Build single master bundle: dist/erased-all-languages-update.zip
$masterZipPath = $distDir . '/erased-all-languages-update.zip';
if (file_exists($masterZipPath)) @unlink($masterZipPath);

$mZip = new ZipArchive();
if ($mZip->open($masterZipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create ZIP: {$masterZipPath}\n");
    exit(1);
}

// Add raw storage directory structure
foreach ($iterator as $item) {
    $relative = 'storage/languages/' . substr($item->getPathname(), strlen($languagesDir) + 1);
    if ($item->isDir()) {
        $mZip->addEmptyDir($relative);
    } else {
        $mZip->addFile($item->getPathname(), $relative);
    }
}

// Add individual language pack ZIPs inside packages/ folder
foreach (['lt', 'nb', 'ua'] as $code) {
    $packPath = $distDir . "/lang-{$code}-1.0.0.zip";
    if (file_exists($packPath)) {
        $mZip->addFile($packPath, "packages/lang-{$code}-1.0.0.zip");
    }
}

$mZip->close();
echo "Created Master Bundle ZIP: dist/erased-all-languages-update.zip (" . number_format(filesize($masterZipPath) / 1024, 1) . " KB)\n";

echo "\nAll Translation Update ZIPs created successfully.\n";
