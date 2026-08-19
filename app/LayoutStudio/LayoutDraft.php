<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use InvalidArgumentException;

final class LayoutDraft
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        private readonly string $key,
        private readonly string $profileId,
        private readonly string $targetType,
        private readonly string $targetId,
        private readonly string $name,
        private readonly string $status,
        private readonly int $revision,
        private readonly ?int $authorId,
        private readonly string $notes,
        private readonly array $payload,
    ) {
        foreach ([$key, $profileId, $targetType, $targetId] as $value) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value) !== 1) {
                throw new InvalidArgumentException('Layout draft identifier is invalid.');
            }
        }
        if (trim($name) === '') throw new InvalidArgumentException('Layout draft name cannot be empty.');
        if (!in_array($status, ['draft', 'published', 'archived'], true)) throw new InvalidArgumentException('Layout draft status is invalid.');
        if ($revision < 1) throw new InvalidArgumentException('Layout draft revision must be positive.');
    }

    public function key(): string { return $this->key; }
    public function profileId(): string { return $this->profileId; }
    public function targetType(): string { return $this->targetType; }
    public function targetId(): string { return $this->targetId; }
    public function name(): string { return $this->name; }
    public function status(): string { return $this->status; }
    public function revision(): int { return $this->revision; }
    public function authorId(): ?int { return $this->authorId; }
    public function notes(): string { return $this->notes; }
    /** @return array<string,mixed> */
    public function payload(): array { return $this->payload; }
}
