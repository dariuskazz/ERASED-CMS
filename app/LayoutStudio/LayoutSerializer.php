<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;
use JsonException;
use RuntimeException;

final class LayoutSerializer
{
    /** @param list<BlockPlacement> $placements */
    public function encode(string $profileId, array $placements): string
    {
        $rows = array_map(static fn(BlockPlacement $placement): array => [
            'instance_id' => $placement->instanceId(),
            'profile_id' => $placement->profileId(),
            'region' => $placement->region(),
            'block_id' => $placement->blockId(),
            'position' => $placement->position(),
            'visible' => $placement->visible(),
            'settings' => $placement->settings(),
        ], $placements);

        try {
            return json_encode([
                'schema' => 'erased.layout-studio',
                'version' => 1,
                'profile_id' => $profileId,
                'placements' => $rows,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not serialize Layout Studio document.', 0, $error);
        }
    }

    /** @return list<BlockPlacement> */
    public function decode(string $json): array
    {
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Layout Studio document JSON is invalid.', 0, $error);
        }
        if (!is_array($document) || ($document['schema'] ?? null) !== 'erased.layout-studio' || ($document['version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported Layout Studio document.');
        }
        $profileId = $document['profile_id'] ?? null;
        $rows = $document['placements'] ?? null;
        if (!is_string($profileId) || !is_array($rows)) {
            throw new RuntimeException('Layout Studio document is incomplete.');
        }

        $placements = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['profile_id'] ?? null) !== $profileId) {
                throw new RuntimeException('Layout Studio placement profile does not match the document profile.');
            }
            $placements[] = new BlockPlacement(
                (string)($row['instance_id'] ?? ''),
                $profileId,
                (string)($row['region'] ?? ''),
                (string)($row['block_id'] ?? ''),
                (int)($row['position'] ?? -1),
                (bool)($row['visible'] ?? true),
                is_array($row['settings'] ?? null) ? $row['settings'] : [],
            );
        }
        return $placements;
    }
}
