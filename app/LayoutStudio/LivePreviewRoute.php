<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;
use PDO;

final class LivePreviewRoute
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LayoutSerializer $serializer,
        private readonly LivePreviewRenderer $renderer,
    ) {
    }

    public function render(string $profileId = 'default', string $device = 'desktop'): string
    {
        $draft = (new LayoutDraftRepository($this->pdo))->find(LayoutDraftService::key($profileId, 'homepage', 'homepage'));
        $placements = [];
        $revision = 1;

        if ($draft !== null) {
            $json = json_encode($draft->payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                $placements = $this->serializer->decode($json);
            }
            $revision = $draft->revision();
        }

        if (empty($placements)) {
            // Block ids here must match homepage_studio_public_blocks()'s real
            // catalog (app/HomepageLayout.php) - the previous set (hero-section,
            // article-body, pricing-table, cta-banner, sidebar-widgets) predates
            // the 2026-08-16 core-block removal and no longer exists, so
            // LivePreviewRenderer silently dropped every one of them, leaving
            // the preview showing only "No homepage content is enabled." for
            // any profile with no draft row yet.
            $placements = [
                new BlockPlacement('inst-featured', $profileId, 'center', 'featured', 0, true, []),
                new BlockPlacement('inst-latest', $profileId, 'center', 'latest_posts', 1, true, []),
                new BlockPlacement('inst-features', $profileId, 'center', 'features', 2, true, []),
                new BlockPlacement('inst-categories', $profileId, 'right', 'categories', 0, true, []),
                new BlockPlacement('inst-tags', $profileId, 'right', 'popular_tags', 1, true, []),
            ];
        }

        $regions = [];
        foreach ($placements as $placement) {
            if (!in_array($placement->region(), $regions, true)) {
                $regions[] = $placement->region();
            }
        }
        if ($regions === []) {
            $regions = ['center', 'right'];
        }

        return $this->renderer->render(
            new LivePreviewDocument($profileId, 'homepage', 'homepage', $regions, $placements, $revision),
            $device,
            function_exists('homepage_studio_plugin_block_resolver') ? homepage_studio_plugin_block_resolver() : null,
        );
    }
}
