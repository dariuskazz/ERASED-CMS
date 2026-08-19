<?php
declare(strict_types=1);

use Erased\Container\PackageServiceRegistrar;
use Erased\Container\ServiceContainer;
use Erased\Packages\PackageManifest;

require dirname(__DIR__).'/app/Packages/PackageManifest.php';
require dirname(__DIR__).'/app/Container/ServiceContainer.php';
require dirname(__DIR__).'/app/Container/PackageServiceRegistrar.php';

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

final class CounterService
{
    public int $value = 0;
}

$container = new ServiceContainer();
$container->set('counter', static fn(): object => new CounterService());
$first = $container->get('counter');
$second = $container->get('counter');
if ($first !== $second) fail('Shared services must return the same instance.');

$container->set('factory', static fn(): object => new CounterService(), false);
if ($container->get('factory') === $container->get('factory')) fail('Factory services must return new instances.');

$container->alias('counter.alias', 'counter');
if ($container->get('counter.alias') !== $first) fail('Aliases must resolve to their target service.');

$container->instance('prebuilt', new CounterService());
if (!$container->has('prebuilt')) fail('Prebuilt instance was not registered.');

$container->set('loop.a', static fn(ServiceContainer $c): object => $c->get('loop.b'));
$container->set('loop.b', static fn(ServiceContainer $c): object => $c->get('loop.a'));
try {
    $container->get('loop.a');
    fail('Circular service dependencies must be rejected.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), 'Circular service dependency')) {
        fail('Unexpected circular dependency error: '.$error->getMessage());
    }
}

$root = sys_get_temp_dir().'/erased-service-container-'.bin2hex(random_bytes(5));
if (!mkdir($root.'/src', 0770, true) && !is_dir($root.'/src')) fail('Could not create service fixture.');

$class = 'Erased\\TestServices\\GreetingService'.bin2hex(random_bytes(3));
$parts = explode('\\', $class);
$short = array_pop($parts);
$namespace = implode('\\', $parts);
$file = $root.'/src/GreetingService.php';
file_put_contents($file, "<?php\nnamespace {$namespace};\nfinal class {$short} { public function message(): string { return 'hello'; } }\n");

$manifest = new PackageManifest([
    'id' => 'erased.test-services',
    'type' => 'module',
    'name' => 'Service Test',
    'version' => '1.0.0',
    'requires' => '0.3.0',
    'author' => 'ERASED',
    'description' => 'Service registration fixture.',
    'services' => [
        'greeting' => [
            'file' => 'src/GreetingService.php',
            'class' => $class,
            'shared' => true,
        ],
    ],
]);

$registrar = new PackageServiceRegistrar();
$registrar->register($container, $manifest, $root);
$greeting = $container->get('greeting');
if (!method_exists($greeting, 'message') || $greeting->message() !== 'hello') {
    fail('Package service was not loaded correctly.');
}

$unsafe = new PackageManifest([
    'id' => 'erased.test-unsafe-service',
    'type' => 'module',
    'name' => 'Unsafe Service Test',
    'version' => '1.0.0',
    'requires' => '0.3.0',
    'author' => 'ERASED',
    'description' => 'Unsafe service fixture.',
    'services' => [
        'unsafe' => ['file' => '../outside.php', 'class' => 'Outside\\Service'],
    ],
]);
try {
    $registrar->register($container, $unsafe, $root);
    fail('Unsafe service paths must be rejected.');
} catch (RuntimeException) {
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

echo "Service container smoke test passed.\n";
echo "Validated shared services, factories, aliases, instances, circular protection, and safe package service loading.\n";
