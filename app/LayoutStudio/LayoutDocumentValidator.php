<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;

interface LayoutDocumentValidator
{
    /** @param list<BlockPlacement> $placements */
    public function assertValid(string $profileId, array $placements, LayoutCanvas $canvas): void;
}
