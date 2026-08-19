<?php
declare(strict_types=1);

$pkgDir = $argv[1] ?? 'packages/erased.newsletter-subscribers';
$outputZip = $argv[2] ?? 'dist/erased-newsletter-subscribers-1.0.0.zip';

$root = dirname(__DIR__);
$srcPath = $root . '/' . ltrim($pkgDir, '/');
$destZip = $root . '/' . ltrim($outputZip, '/');

if (!is_dir($srcPath)) {
    fwrite(STDERR, "Source package directory not found: {$srcPath}\n");
    exit(1);
}

@mkdir(dirname($destZip), 0755, true);
if (file_exists($destZip)) {
    @unlink($destZip);
}

$zip = new ZipArchive();
if ($zip->open($destZip, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create ZIP: {$destZip}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $relative = substr($item->getPathname(), strlen($srcPath) + 1);
    if ($item->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($item->getPathname(), $relative);
    }
}

$zip->close();
echo "Successfully created {$outputZip} (" . number_format(filesize($destZip) / 1024, 1) . " KB)\n";
