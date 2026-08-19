<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;
use RuntimeException;

final class LayoutCanvas
{
    /** @param list<string> $regions */
    public function __construct(private readonly array $regions)
    {
        if ($regions === []) {
            throw new RuntimeException('Layout canvas requires at least one region.');
        }
        foreach ($regions as $region) {
            if (!is_string($region) || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $region) !== 1) {
                throw new RuntimeException('Layout canvas contains an invalid region.');
            }
        }
    }

    /** @param list<BlockPlacement> $placements
     *  @return array<string,list<BlockPlacement>>
     */
    public function arrange(array $placements): array
    {
        $canvas = array_fill_keys($this->regions, []);
        foreach ($placements as $placement) {
            if (!$placement instanceof BlockPlacement) {
                throw new RuntimeException('Layout canvas accepts only BlockPlacement values.');
            }
            if (!array_key_exists($placement->region(), $canvas)) {
                throw new RuntimeException("Layout region '{$placement->region()}' is not provided by the active layout target.");
            }
            $canvas[$placement->region()][] = $placement;
        }

        foreach ($canvas as &$items) {
            usort($items, static fn(BlockPlacement $a, BlockPlacement $b): int => [$a->position(), $a->instanceId()] <=> [$b->position(), $b->instanceId()]);
        }
        unset($items);

        return $canvas;
    }

    /** @return list<string> */
    public function regions(): array
    {
        return array_values($this->regions);
    }
}
