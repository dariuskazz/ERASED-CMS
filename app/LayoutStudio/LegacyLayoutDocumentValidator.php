<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;
use RuntimeException;

final class LegacyLayoutDocumentValidator implements LayoutDocumentValidator
{
    /** @param list<string> $blockIds @param list<string> $regions */
    public function __construct(
        private readonly array $blockIds,
        private readonly array $regions,
    ) {
    }

    public function assertValid(string $profileId, array $placements, LayoutCanvas $canvas): void
    {
        $allowedBlocks = array_fill_keys($this->blockIds, true);
        $allowedRegions = array_fill_keys($this->regions, true);
        $instances = [];
        $validPlacements = [];

        foreach ($placements as $placement) {
            if (!$placement instanceof BlockPlacement) {
                throw new RuntimeException('Layout contains an invalid placement.');
            }
            if ($placement->profileId() !== $profileId) {
                throw new RuntimeException('Layout placement belongs to another profile.');
            }
            // Every render path (PublishedHomepageRenderer, LivePreviewRenderer)
            // silently drops a placement whose block_id no longer exists (e.g.
            // a package providing it was disabled/uninstalled while this admin
            // tab was open with a stale knownBlockIds list) instead of erroring.
            // Throwing here instead used to reject the ENTIRE save over one
            // stale placement, blocking every other unrelated edit in the same
            // request - skip it instead, matching how it's already tolerated
            // everywhere else the layout is read.
            if (!isset($allowedBlocks[$placement->blockId()])) {
                continue;
            }
            if (!isset($allowedRegions[$placement->region()])) {
                throw new RuntimeException("Unknown layout region '{$placement->region()}'.");
            }
            if (isset($instances[$placement->instanceId()])) {
                throw new RuntimeException("Duplicate layout instance '{$placement->instanceId()}'.");
            }
            $instances[$placement->instanceId()] = true;
            $validPlacements[] = $placement;
        }

        $canvas->arrange($validPlacements);
    }
}
