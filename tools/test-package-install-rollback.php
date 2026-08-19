<?php
declare(strict_types=1);

use Erased\Packages\PackageInstaller;
use Erased\Packages\PackageValidator;

require dirname(__DIR__).'/app/Packages/PackageManifest.php';
require dirname(__DIR__).'/app/Packages/PackageValidator.php';
require dirname(__DIR__).'/app/Packages/PackageInstaller.php';

$root = sys_get_temp_dir().'/erased-package-install-'.bin2hex(random_bytes(6));
$stageRoot = $root.'/stage';
$packagesRoot = $root.'/installed';
$rollbackRoot = $root.'/rollback';

$remove = static function (string $directory) use (&$remove): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory.'/'.$item;
        is_dir($path) && !is_link($path) ? $remove($path) : unlink($path);
    }
    rmdir($directory);
};

$createPackage = static function (string $directory, string $version, string $marker): void {
    mkdir($directory, 0750, true);
    file_put_contents($directory.'/package.json', json_encode([
        'id' => 'erased.test-install',
        'type' => 'module',
        'name' => 'Installer Test',
        'version' => $version,
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Installer promotion and rollback smoke test.',
        'dependencies' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    file_put_contents($directory.'/marker.txt', $marker);
};

try {
    $installer = new PackageInstaller(new PackageValidator());

    $stageOne = $stageRoot.'/one';
    $createPackage($stageOne, '1.0.0', 'version-one');
    $first = $installer->install($stageOne, $packagesRoot, $rollbackRoot);

    if ($first['backup_directory'] !== null) {
        throw new RuntimeException('Fresh installation unexpectedly created a rollback backup.');
    }
    if (trim((string)file_get_contents($first['package_directory'].'/marker.txt')) !== 'version-one') {
        throw new RuntimeException('Fresh package was not promoted correctly.');
    }

    $stageTwo = $stageRoot.'/two';
    $createPackage($stageTwo, '2.0.0', 'version-two');
    $second = $installer->install($stageTwo, $packagesRoot, $rollbackRoot);

    if ($second['backup_directory'] === null || !is_dir($second['backup_directory'])) {
        throw new RuntimeException('Package update did not create a rollback backup.');
    }
    if (trim((string)file_get_contents($second['package_directory'].'/marker.txt')) !== 'version-two') {
        throw new RuntimeException('Updated package was not promoted correctly.');
    }

    $installer->rollback($second['package_directory'], $second['backup_directory']);
    if (trim((string)file_get_contents($second['package_directory'].'/marker.txt')) !== 'version-one') {
        throw new RuntimeException('Rollback did not restore the previous package.');
    }

    echo "Package install and rollback smoke test passed.\n";
    echo "Validated fresh install, update backup, atomic promotion, and rollback.\n";
} finally {
    $remove($root);
}
