<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;
use RuntimeException;

final class LayoutDraftService
{
    public function __construct(
        private readonly LayoutDraftRepository $drafts,
        private readonly LayoutSerializer $serializer,
        private readonly LayoutDocumentValidator $validator,
    ) {
    }

    /** @param list<BlockPlacement> $published */
    public function createFromPublished(
        string $profileId,
        string $targetType,
        string $targetId,
        array $published,
        LayoutCanvas $canvas,
        ?int $authorId = null,
        string $name = 'Layout draft',
        string $notes = '',
    ): LayoutDraft {
        $key = self::key($profileId, $targetType, $targetId);
        $existing = $this->drafts->find($key);
        if ($existing !== null) return $existing;

        $this->validator->assertValid($profileId, $published, $canvas);
        $payload = $this->document($profileId, $published);
        $draft = new LayoutDraft($key, $profileId, $targetType, $targetId, $name, 'draft', 1, $authorId, $notes, $payload);
        $this->drafts->create($draft);
        return $draft;
    }

    public function load(string $profileId, string $targetType, string $targetId): ?LayoutDraft
    {
        return $this->drafts->find(self::key($profileId, $targetType, $targetId));
    }

    /** @param list<BlockPlacement> $placements */
    public function autosave(
        string $profileId,
        string $targetType,
        string $targetId,
        array $placements,
        LayoutCanvas $canvas,
        int $expectedRevision,
        ?int $authorId = null,
        ?string $name = null,
        ?string $notes = null,
    ): LayoutDraft {
        $key = self::key($profileId, $targetType, $targetId);
        $current = $this->drafts->find($key);
        if ($current === null) throw new RuntimeException('Layout draft does not exist.');

        $this->validator->assertValid($profileId, $placements, $canvas);
        $updated = new LayoutDraft(
            $current->key(),
            $current->profileId(),
            $current->targetType(),
            $current->targetId(),
            $name ?? $current->name(),
            'draft',
            $current->revision(),
            $authorId ?? $current->authorId(),
            $notes ?? $current->notes(),
            $this->document($profileId, $placements),
        );
        return $this->drafts->save($updated, $expectedRevision);
    }

    /** @return list<BlockPlacement> */
    public function placements(LayoutDraft $draft): array
    {
        $json = json_encode($draft->payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) throw new RuntimeException('Could not decode layout draft payload.');
        return $this->serializer->decode($json);
    }

    public function delete(string $profileId, string $targetType, string $targetId): void
    {
        $this->drafts->delete(self::key($profileId, $targetType, $targetId));
    }

    public static function key(string $profileId, string $targetType, string $targetId): string
    {
        foreach ([$profileId, $targetType, $targetId] as $value) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $value) !== 1) throw new RuntimeException('Layout draft target identifier is invalid.');
        }
        return $profileId.'.'.$targetType.'.'.$targetId;
    }

    /** @param list<BlockPlacement> $placements @return array<string,mixed> */
    private function document(string $profileId, array $placements): array
    {
        $json = $this->serializer->encode($profileId, $placements);
        $document = json_decode($json, true);
        if (!is_array($document)) throw new RuntimeException('Could not create layout draft document.');
        return $document;
    }
}
