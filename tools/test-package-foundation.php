<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Packages/PackageManifest.php';
require dirname(__DIR__).'/app/Packages/PackageValidator.php';
require dirname(__DIR__).'/app/Packages/PackageRegistry.php';
require dirname(__DIR__).'/app/Packages/PackageLifecycle.php';

use Erased\Packages\PackageRegistry;
use Erased\Packages\PackageValidator;

$root = dirname(__DIR__);
$validator = new PackageValidator();
$result = $validator->validateDirectory($root.'/packages/example-module');

if (!$result['valid']) {
    fwrite(STDERR, "Package validation failed:\n- ".implode("\n- ", $result['errors'])."\n");
    exit(1);
}

$registry = new PackageRegistry($root.'/packages', $validator);
$packages = $registry->discover();

if (!isset($packages['erased.example-module'])) {
    fwrite(STDERR, "Example module was not discovered.\n");
    exit(1);
}

$manifest = $packages['erased.example-module']['manifest'];
if ($manifest->type() !== 'module' || $manifest->version() !== '0.1.0') {
    fwrite(STDERR, "Discovered manifest contains unexpected values.\n");
    exit(1);
}

echo "Package foundation smoke test passed.\n";
echo "Discovered: {$manifest->id()} ({$manifest->type()}) v{$manifest->version()}\n";
