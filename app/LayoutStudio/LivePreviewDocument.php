<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;
use RuntimeException;

final class LivePreviewDocument
{
    /** @param list<string> $regions @param list<BlockPlacement> $placements */
    public function __construct(
        private readonly string $profileId,
        private readonly string $targetType,
        private readonly string $targetKey,
        private readonly array $regions,
        private readonly array $placements,
        private readonly int $revision,
    ) {
        if ($profileId === '' || $targetType === '' || $targetKey === '') {
            throw new RuntimeException('Live Preview target metadata is incomplete.');
        }
        if ($regions === []) throw new RuntimeException('Live Preview requires at least one region.');
        if ($revision < 1) throw new RuntimeException('Live Preview revision must be positive.');
        foreach ($placements as $placement) {
            if (!$placement instanceof BlockPlacement) throw new RuntimeException('Live Preview contains an invalid placement.');
            if (!in_array($placement->region(), $regions, true)) {
                throw new RuntimeException("Live Preview placement uses unknown region '{$placement->region()}'.");
            }
        }
    }

    public function profileId(): string { return $this->profileId; }
    public function targetType(): string { return $this->targetType; }
    public function targetKey(): string { return $this->targetKey; }
    /** @return list<string> */ public function regions(): array { return $this->regions; }
    /** @return list<BlockPlacement> */ public function placements(): array { return $this->placements; }
    public function revision(): int { return $this->revision; }
}
