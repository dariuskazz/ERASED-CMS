<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/Homepage/BlockPlacement.php';
require_once $root.'/app/LayoutStudio/LivePreviewDocument.php';
require_once $root.'/app/LayoutStudio/LivePreviewRenderer.php';

use Erased\Homepage\BlockPlacement;
use Erased\LayoutStudio\LivePreviewDocument;
use Erased\LayoutStudio\LivePreviewRenderer;

if (!function_exists('homepage_studio_public_blocks')) {
    function homepage_studio_public_blocks(): array
    {
        return [
            'hero' => '<section data-test-block="hero">Hero preview</section>',
            'popular_tags' => '<section data-test-block="popular-tags">Popular tags preview</section>',
        ];
    }
}

if (!function_exists('homepage_studio_config')) {
    function homepage_studio_config(): array
    {
        return [
            'active_regions' => ['left','center','right'],
            'show_empty' => true,
            'left' => 20,
            'right' => 20,
            'gap' => 24,
            'max_width' => 1600,
            'widget_gap' => 16,
            'sticky_offset' => 18,
        ];
    }
}

if (!function_exists('homepage_studio_width_style')) {
    function homepage_studio_width_style(array $config, array $visibleRegions): string
    {
        return '--homepage-columns:minmax(0,20fr) minmax(0,60fr) minmax(0,20fr);';
    }
}

if (!function_exists('homepage_studio_public_css')) {
    function homepage_studio_public_css(): string
    {
        return '<style data-test-public-css></style>';
    }
}

if (!function_exists('tr')) {
    function tr(string $key): string
    {
        return $key;
    }
}

if (!function_exists('layout')) {
    function layout(string $title, string $body, bool $admin=false): void
    {
        echo '<!doctype html><html><head><meta name="robots" content="noindex,nofollow"><title>'
            .htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            .'</title></head><body>'.$body.'</body></html>';
    }
}

try {
    $placements=[
        new BlockPlacement('hero-1','default','center','hero',0,true,['title'=>'Hello']),
        new BlockPlacement('tags-1','default','left','popular_tags',1,false,[]),
    ];
    $document=new LivePreviewDocument('default','homepage','homepage',['left','center','right'],$placements,7);
    $renderer=new LivePreviewRenderer();
    $html=$renderer->render($document,'tablet');

    foreach ([
        '<!doctype html>',
        'data-preview-device="tablet"',
        'data-preview-revision="7"',
        'data-live-preview-grid',
        'data-test-block="hero"',
        'Empty region',
        'meta name="robots" content="noindex,nofollow"',
        'composition:changed',
        'layout:changed',
    ] as $needle) {
        if (!str_contains($html,$needle)) throw new RuntimeException("Missing Live Preview marker: {$needle}");
    }

    if (str_contains($html,'data-test-block="popular-tags"')) {
        throw new RuntimeException('Hidden placement was rendered in the live preview.');
    }

    if (!str_contains($renderer->render($document,'invalid'),'data-preview-device="desktop"')) {
        throw new RuntimeException('Invalid device did not fall back to desktop.');
    }

    fwrite(STDOUT,"Live Preview foundation smoke test passed.\n");
    fwrite(STDOUT,"Validated full-document rendering, revision/device metadata, visible/hidden placement handling, empty regions, and preview sync hooks.\n");
} catch(Throwable $error){
    fwrite(STDERR,'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
