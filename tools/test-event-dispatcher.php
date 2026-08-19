<?php
declare(strict_types=1);

use Erased\Events\EventDispatcher;
use Erased\Events\PackageEvent;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Events/EventDispatcher.php';
require_once dirname(__DIR__).'/app/Events/PackageEvent.php';

try {
    $dispatcher = new EventDispatcher();
    $calls = [];

    $dispatcher->listen('package.enabled', static function (PackageEvent $event) use (&$calls): string {
        $calls[] = 'normal-a:'.$event->packageId();
        return 'normal-a';
    });
    $dispatcher->listen('package.enabled', static function (PackageEvent $event) use (&$calls): string {
        $calls[] = 'high:'.$event->packageId();
        return 'high';
    }, 100);
    $dispatcher->listen('package.enabled', static function (PackageEvent $event) use (&$calls): string {
        $calls[] = 'normal-b:'.$event->packageId();
        return 'normal-b';
    });
    $dispatcher->listen('package.enabled', static function (PackageEvent $event) use (&$calls): string {
        $calls[] = 'low:'.$event->packageId();
        return 'low';
    }, -10);

    $manifest = new PackageManifest([
        'id' => 'erased.test-events',
        'type' => 'module',
        'name' => 'Event Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Event dispatcher test fixture.',
        'dependencies' => [],
    ]);
    $event = new PackageEvent($manifest, '/packages/erased.test-events', ['source' => 'smoke-test']);

    $results = $dispatcher->dispatch('package.enabled', $event);
    assert($calls === [
        'high:erased.test-events',
        'normal-a:erased.test-events',
        'normal-b:erased.test-events',
        'low:erased.test-events',
    ]);
    assert($results === ['high', 'normal-a', 'normal-b', 'low']);
    assert($event->packagePath() === '/packages/erased.test-events');
    assert($event->context()['source'] === 'smoke-test');
    assert($dispatcher->hasListeners('package.enabled') === true);
    assert($dispatcher->listenerCounts() === ['package.enabled' => 4]);

    $dispatcher->forget('package.enabled');
    assert($dispatcher->hasListeners('package.enabled') === false);
    assert($dispatcher->dispatch('package.enabled', $event) === []);

    $dispatcher->listen('package.failed', static function (): void {
        throw new RuntimeException('simulated listener failure');
    });
    $failed = false;
    try {
        $dispatcher->dispatch('package.failed', $event);
    } catch (RuntimeException $error) {
        $failed = str_contains($error->getMessage(), "Event listener failed for 'package.failed'")
            && $error->getPrevious() instanceof RuntimeException;
    }
    assert($failed === true);

    $invalid = false;
    try {
        $dispatcher->listen('Invalid Event', static fn(): null => null);
    } catch (RuntimeException) {
        $invalid = true;
    }
    assert($invalid === true);

    fwrite(STDOUT, "Package event system smoke test passed.\n");
    fwrite(STDOUT, "Validated priority, stable order, payloads, results, removal, and failure wrapping.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
