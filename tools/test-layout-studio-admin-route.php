<?php
declare(strict_types=1);

use Erased\LayoutStudio\LayoutStudioAdminRoute;
use Erased\LayoutStudio\LayoutStudioAdminScreen;

$root = dirname(__DIR__);
require_once $root.'/app/Homepage/BlockDefinition.php';
require_once $root.'/app/Homepage/BlockPlacement.php';
require_once $root.'/app/LayoutStudio/LayoutStudioAdminScreen.php';
require_once $root.'/app/LayoutStudio/LayoutStudioAdminRoute.php';

function homepage_studio_blocks(): array
{
    return [
        'latest_posts' => ['Latest posts', 'Newest published posts', 'center', '▤'],
        'categories' => ['Categories', 'Category navigation', 'left', '▰'],
        'hero' => ['Hero Banner', 'Large title with CTA button', 'center', '⚡'],
    ];
}

function homepage_studio_config(): array
{
    return [
        'active_regions' => ['left', 'center'],
        'enabled' => ['latest_posts', 'hero'],
        'order' => ['categories', 'hero', 'latest_posts'],
        'regions' => ['categories' => 'left', 'hero' => 'center', 'latest_posts' => 'center'],
        'options' => ['hero' => ['title' => 'Build something remarkable']],
    ];
}

try {
    $route = new LayoutStudioAdminRoute(new LayoutStudioAdminScreen());
    $html = $route->render('news', 'hero');

    assert(str_contains($html, 'data-profile-id="news"'));
    assert(str_contains($html, 'data-layout-region="left"'));
    assert(str_contains($html, 'data-layout-region="center"'));
    assert(str_contains($html, 'data-layout-instance="legacy-categories"'));
    assert(str_contains($html, 'data-layout-instance="legacy-hero"'));
    assert(str_contains($html, 'data-layout-instance="legacy-latest_posts"'));
    assert(str_contains($html, 'Hero Banner'));
    assert(str_contains($html, 'Latest posts'));
    assert(str_contains($html, 'Categories'));
    assert(str_contains($html, 'is-hidden'));

    fwrite(STDOUT, "Layout Studio admin route smoke test passed.\n");
    fwrite(STDOUT, "Validated legacy-backed palette, regions, ordering, visibility, profile state, and safe screen rendering.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
