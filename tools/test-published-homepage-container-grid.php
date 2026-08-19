<?php
declare(strict_types=1);

use Erased\Homepage\BlockPlacement;
use Erased\Homepage\PublishedHomepageRenderer;

require_once dirname(__DIR__).'/app/Homepage/BlockPlacement.php';
require_once dirname(__DIR__).'/app/Homepage/PublishedHomepageRenderer.php';

try {
    $renderer = new PublishedHomepageRenderer();
    $config = ['active_regions' => ['left', 'center', 'right'], 'show_empty' => false, 'style' => 'standard'];
    $blocks = ['a' => '<p>A</p>', 'b' => '<p>B</p>', 'c' => '<p>C</p>', 'solo' => '<p>Solo</p>'];

    // --- No container_id at all: renders flat, exactly as before this feature existed ---
    $flat = $renderer->render([
        new BlockPlacement('p1', 'default', 'center', 'a', 0),
        new BlockPlacement('p2', 'default', 'center', 'b', 1),
    ], $config, $blocks);
    if (str_contains($flat, 'homepage-container-grid')) {
        throw new RuntimeException('Placements with no container_id should never produce a container grid.');
    }
    if (strpos($flat, '<p>A</p>') > strpos($flat, '<p>B</p>')) {
        throw new RuntimeException('Flat placements lost their order.');
    }

    // --- Two placements sharing a container_id: wrapped in a 2-column grid ---
    $twoCol = $renderer->render([
        new BlockPlacement('p1', 'default', 'center', 'a', 0, true, ['container_id' => 'c1', 'column_count' => 2, 'column_index' => 0]),
        new BlockPlacement('p2', 'default', 'center', 'b', 1, true, ['container_id' => 'c1', 'column_count' => 2, 'column_index' => 1]),
    ], $config, $blocks);
    if (!str_contains($twoCol, 'homepage-container-grid homepage-container-grid-2')) {
        throw new RuntimeException('Two placements sharing a container_id did not produce a 2-column grid wrapper.');
    }
    if (substr_count($twoCol, '<div class="homepage-container-grid ') !== 1) {
        throw new RuntimeException('Expected exactly one grid wrapper for one container.');
    }

    // --- Three placements sharing a container_id: 3-column grid ---
    $threeCol = $renderer->render([
        new BlockPlacement('p1', 'default', 'left', 'a', 0, true, ['container_id' => 'c2', 'column_count' => 3, 'column_index' => 0]),
        new BlockPlacement('p2', 'default', 'left', 'b', 1, true, ['container_id' => 'c2', 'column_count' => 3, 'column_index' => 1]),
        new BlockPlacement('p3', 'default', 'left', 'c', 2, true, ['container_id' => 'c2', 'column_count' => 3, 'column_index' => 2]),
    ], $config, $blocks);
    if (!str_contains($threeCol, 'homepage-container-grid-3')) {
        throw new RuntimeException('Three placements sharing a container_id did not produce a 3-column grid wrapper.');
    }

    // --- A grouped container followed by a flat placement in the same region: only the group is wrapped ---
    $mixed = $renderer->render([
        new BlockPlacement('p1', 'default', 'center', 'a', 0, true, ['container_id' => 'c3', 'column_count' => 2, 'column_index' => 0]),
        new BlockPlacement('p2', 'default', 'center', 'b', 1, true, ['container_id' => 'c3', 'column_count' => 2, 'column_index' => 1]),
        new BlockPlacement('p3', 'default', 'center', 'solo', 2),
    ], $config, $blocks);
    if (substr_count($mixed, '<div class="homepage-container-grid ') !== 1) {
        throw new RuntimeException('A trailing ungrouped placement should not be pulled into the prior container grid.');
    }
    if (!str_contains($mixed, '<p>Solo</p>')) {
        throw new RuntimeException('The trailing ungrouped placement is missing from the output.');
    }

    // --- Two different container_ids in the same region stay as two separate groups, not merged ---
    $twoGroups = $renderer->render([
        new BlockPlacement('p1', 'default', 'center', 'a', 0, true, ['container_id' => 'g1', 'column_count' => 2, 'column_index' => 0]),
        new BlockPlacement('p2', 'default', 'center', 'b', 1, true, ['container_id' => 'g1', 'column_count' => 2, 'column_index' => 1]),
        new BlockPlacement('p3', 'default', 'center', 'c', 2, true, ['container_id' => 'g2', 'column_count' => 2, 'column_index' => 0]),
        new BlockPlacement('p4', 'default', 'center', 'solo', 3, true, ['container_id' => 'g2', 'column_count' => 2, 'column_index' => 1]),
    ], $config, $blocks);
    if (substr_count($twoGroups, '<div class="homepage-container-grid ') !== 2) {
        throw new RuntimeException('Two distinct containers in the same region should produce two separate grid wrappers, not one merged group.');
    }

    fwrite(STDOUT, "Published homepage container-grid smoke test passed.\n");
    fwrite(STDOUT, "Validated flat rendering with no container_id (unchanged from before), 2- and 3-column grouping, mixed grouped/ungrouped placements, and that distinct container ids never merge.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
