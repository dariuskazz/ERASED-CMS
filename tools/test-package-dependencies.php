<?php
declare(strict_types=1);

use Erased\Packages\PackageDependencyResolver;
use Erased\Packages\PackageManifest;

require dirname(__DIR__).'/app/Packages/PackageManifest.php';
require dirname(__DIR__).'/app/Packages/PackageDependencyResolver.php';

function manifest(array $data): PackageManifest
{
    return new PackageManifest(array_merge([
        'type' => 'module',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Dependency test package.',
        'dependencies' => [],
    ], $data));
}

$core = manifest([
    'id' => 'erased.test-core',
    'name' => 'Test Core',
    'version' => '1.2.0',
]);

$feature = manifest([
    'id' => 'erased.test-feature',
    'name' => 'Test Feature',
    'version' => '1.0.0',
    'dependencies' => ['erased.test-core ^1.0.0'],
]);

$app = manifest([
    'id' => 'erased.test-app',
    'name' => 'Test App',
    'version' => '1.0.0',
    'dependencies' => ['erased.test-feature >=1.0.0'],
]);

$available = [
    $core->id() => $core,
    $feature->id() => $feature,
    $app->id() => $app,
];

$resolver = new PackageDependencyResolver();
$plan = $resolver->resolve($app, $available);
$ids = array_map(static fn (PackageManifest $package): string => $package->id(), $plan);

$expected = [
    'erased.test-core',
    'erased.test-feature',
    'erased.test-app',
];

if ($ids !== $expected) {
    fwrite(STDERR, 'Unexpected dependency order: '.json_encode($ids).PHP_EOL);
    exit(1);
}

try {
    $missing = manifest([
        'id' => 'erased.test-missing',
        'name' => 'Missing Dependency Test',
        'version' => '1.0.0',
        'dependencies' => ['erased.not-installed ^1.0.0'],
    ]);
    $resolver->resolve($missing, $available);
    fwrite(STDERR, 'Missing dependency was not rejected.'.PHP_EOL);
    exit(1);
} catch (RuntimeException) {
}

try {
    $cycleA = manifest([
        'id' => 'erased.cycle-a',
        'name' => 'Cycle A',
        'version' => '1.0.0',
        'dependencies' => ['erased.cycle-b *'],
    ]);
    $cycleB = manifest([
        'id' => 'erased.cycle-b',
        'name' => 'Cycle B',
        'version' => '1.0.0',
        'dependencies' => ['erased.cycle-a *'],
    ]);
    $resolver->resolve($cycleA, [
        $cycleA->id() => $cycleA,
        $cycleB->id() => $cycleB,
    ]);
    fwrite(STDERR, 'Circular dependency was not rejected.'.PHP_EOL);
    exit(1);
} catch (RuntimeException) {
}

echo "Package dependency smoke test passed.\n";
echo 'Install order: '.implode(' -> ', $ids)."\n";
