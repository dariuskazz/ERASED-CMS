<?php
declare(strict_types=1);

namespace Erased\Homepage;

use InvalidArgumentException;

final class BlockPlacement
{
    /** @param array<string,mixed> $settings */
    public function __construct(
        private readonly string $instanceId,
        private readonly string $profileId,
        private readonly string $region,
        private readonly string $blockId,
        private readonly int $position,
        private readonly bool $visible = true,
        private readonly array $settings = [],
    ) {
        foreach (['instance id' => $instanceId, 'profile id' => $profileId, 'region' => $region, 'block id' => $blockId] as $label => $value) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value) !== 1) {
                throw new InvalidArgumentException("Homepage block placement {$label} is invalid.");
            }
        }
        if ($position < 0) {
            throw new InvalidArgumentException('Homepage block placement position cannot be negative.');
        }
    }

    public function instanceId(): string { return $this->instanceId; }
    public function profileId(): string { return $this->profileId; }
    public function region(): string { return $this->region; }
    public function blockId(): string { return $this->blockId; }
    public function position(): int { return $this->position; }
    public function visible(): bool { return $this->visible; }

    /** @return array<string,mixed> */
    public function settings(): array { return $this->settings; }

    public function withPosition(int $position): self
    {
        return new self($this->instanceId, $this->profileId, $this->region, $this->blockId, $position, $this->visible, $this->settings);
    }

    public function withVisibility(bool $visible): self
    {
        return new self($this->instanceId, $this->profileId, $this->region, $this->blockId, $this->position, $visible, $this->settings);
    }

    /** @param array<string,mixed> $settings */
    public function withSettings(array $settings): self
    {
        return new self($this->instanceId, $this->profileId, $this->region, $this->blockId, $this->position, $this->visible, $settings);
    }

    public function withRegion(string $region): self
    {
        return new self($this->instanceId, $this->profileId, $region, $this->blockId, $this->position, $this->visible, $this->settings);
    }
}
