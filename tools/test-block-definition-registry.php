<?php
declare(strict_types=1);

use Erased\Homepage\BlockDefinition;
use Erased\Homepage\BlockDefinitionRegistry;

$root = dirname(__DIR__);
require_once $root.'/app/Homepage/BlockDefinition.php';
require_once $root.'/app/Homepage/BlockDefinitionRegistry.php';
require_once $root.'/app/HomepageLayout.php';

try {
    $registry = new BlockDefinitionRegistry();

    // All built-in homepage_studio_blocks() entries register automatically.
    assert(count($registry->all()) === count(homepage_studio_blocks()));
    assert($registry->exists('features') === true);
    assert($registry->exists('does-not-exist') === false);

    $features = $registry->find('features');
    assert($features instanceof BlockDefinition);
    assert($features->category() === 'marketing');
    assert($features->packageId() === 'erased.legacy-homepage');
    assert($features->serviceId() === 'legacy.homepage.features');

    $categoriesBlock = $registry->find('categories');
    assert($categoriesBlock instanceof BlockDefinition);
    assert($categoriesBlock->category() === 'content');

    $grouped = $registry->byCategory();
    assert(isset($grouped['marketing']) && isset($grouped['content']));
    assert(in_array('features', array_map(static fn(BlockDefinition $d): string => $d->id(), $grouped['marketing']), true));

    // A plugin-shaped registration (v0.6 groundwork) works the same way as a built-in.
    $registry->register(new BlockDefinition('custom-widget', 'example.plugin', 'Custom Widget', 'other', 'example.plugin.custom-widget', ['content.view'], 'A third-party section'));
    assert($registry->exists('custom-widget') === true);
    assert($registry->find('custom-widget')->packageId() === 'example.plugin');
    assert(in_array('custom-widget', $registry->ids(), true));

    fwrite(STDOUT, "Block definition registry test passed.\n");
    fwrite(STDOUT, "Validated built-in registration from homepage_studio_blocks(), lookup, category grouping, and plugin-shaped registration.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
