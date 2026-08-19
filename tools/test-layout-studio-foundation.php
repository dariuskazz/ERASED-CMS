<?php
declare(strict_types=1);

use Erased\Homepage\BlockPlacement;
use Erased\LayoutStudio\LayoutCanvas;
use Erased\LayoutStudio\LayoutSerializer;

$root = dirname(__DIR__);
require_once $root.'/app/Homepage/BlockPlacement.php';
require_once $root.'/app/LayoutStudio/LayoutCanvas.php';
require_once $root.'/app/LayoutStudio/LayoutSerializer.php';

try {
    $hero = new BlockPlacement('hero-1', 'news', 'center', 'hero', 1, true, ['title' => 'Main story']);
    $categories = new BlockPlacement('categories-1', 'news', 'left', 'categories', 0, true, []);
    $latest = new BlockPlacement('latest-1', 'news', 'center', 'latest-posts', 0, false, ['limit' => 8]);

    $canvas = new LayoutCanvas(['left', 'center', 'right']);
    $arranged = $canvas->arrange([$hero, $categories, $latest]);
    assert(array_map(static fn(BlockPlacement $p): string => $p->instanceId(), $arranged['center']) === ['latest-1', 'hero-1']);
    assert($arranged['right'] === []);

    $invalidRegion = false;
    try {
        $canvas->arrange([new BlockPlacement('bad-1', 'news', 'footer', 'hero', 0)]);
    } catch (RuntimeException $error) {
        $invalidRegion = str_contains($error->getMessage(), 'not provided');
    }
    assert($invalidRegion === true);

    $serializer = new LayoutSerializer();
    $json = $serializer->encode('news', [$categories, $latest, $hero]);
    $decoded = $serializer->decode($json);
    assert(count($decoded) === 3);
    assert($decoded[1]->settings()['limit'] === 8);
    assert($decoded[1]->visible() === false);

    fwrite(STDOUT, "Layout Studio foundation smoke test passed.\n");
    fwrite(STDOUT, "Validated canvas regions and ordering, serializer round-trip, and invalid region rejection.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
