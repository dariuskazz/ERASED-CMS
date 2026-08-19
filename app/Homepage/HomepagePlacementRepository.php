<?php
declare(strict_types=1);

namespace Erased\Homepage;

use JsonException;
use PDO;
use RuntimeException;

final class HomepagePlacementRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $table = 'homepage_block_placements')
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new RuntimeException('Homepage placement table name is invalid.');
        }
        $this->table = $table;
    }

    public function save(BlockPlacement $placement): void
    {
        try {
            $settingsJson = json_encode($placement->settings(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not encode homepage block settings.', 0, $error);
        }

        $existing = $this->find($placement->instanceId());
        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO '.$this->quotedTable().' '
                .'(instance_id, profile_id, region, block_id, position_index, visible, settings_json) '
                .'VALUES (:instance_id, :profile_id, :region, :block_id, :position_index, :visible, :settings_json)'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE '.$this->quotedTable().' SET '
                .'profile_id = :profile_id, region = :region, block_id = :block_id, '
                .'position_index = :position_index, visible = :visible, settings_json = :settings_json, '
                .'updated_at = CURRENT_TIMESTAMP WHERE instance_id = :instance_id'
            );
        }

        $statement->execute([
            ':instance_id' => $placement->instanceId(),
            ':profile_id' => $placement->profileId(),
            ':region' => $placement->region(),
            ':block_id' => $placement->blockId(),
            ':position_index' => $placement->position(),
            ':visible' => $placement->visible() ? 1 : 0,
            ':settings_json' => $settingsJson,
        ]);
    }

    public function find(string $instanceId): ?BlockPlacement
    {
        $statement = $this->pdo->prepare(
            'SELECT instance_id, profile_id, region, block_id, position_index, visible, settings_json '
            .'FROM '.$this->quotedTable().' WHERE instance_id = :instance_id LIMIT 1'
        );
        $statement->execute([':instance_id' => $instanceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<BlockPlacement> */
    public function forProfile(string $profileId, ?string $region = null, bool $visibleOnly = false): array
    {
        $sql = 'SELECT instance_id, profile_id, region, block_id, position_index, visible, settings_json '
            .'FROM '.$this->quotedTable().' WHERE profile_id = :profile_id';
        $parameters = [':profile_id' => $profileId];

        if ($region !== null) {
            $sql .= ' AND region = :region';
            $parameters[':region'] = $region;
        }
        if ($visibleOnly) {
            $sql .= ' AND visible = 1';
        }
        $sql .= ' ORDER BY region, position_index, instance_id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_map(fn(array $row): BlockPlacement => $this->hydrate($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param list<string> $orderedInstanceIds */
    public function reorder(string $profileId, string $region, array $orderedInstanceIds): void
    {
        $current = $this->forProfile($profileId, $region);
        $currentIds = array_map(static fn(BlockPlacement $placement): string => $placement->instanceId(), $current);
        sort($currentIds);
        $requestedIds = $orderedInstanceIds;
        sort($requestedIds);

        if ($currentIds !== $requestedIds) {
            throw new RuntimeException('Homepage block reorder list must contain every region instance exactly once.');
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE '.$this->quotedTable().' SET position_index = :position_index, updated_at = CURRENT_TIMESTAMP '
                .'WHERE instance_id = :instance_id AND profile_id = :profile_id AND region = :region'
            );
            foreach (array_values($orderedInstanceIds) as $position => $instanceId) {
                $statement->execute([
                    ':position_index' => $position,
                    ':instance_id' => $instanceId,
                    ':profile_id' => $profileId,
                    ':region' => $region,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function remove(string $instanceId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM '.$this->quotedTable().' WHERE instance_id = :instance_id');
        $statement->execute([':instance_id' => $instanceId]);
    }

    public function clearProfile(string $profileId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM '.$this->quotedTable().' WHERE profile_id = :profile_id');
        $statement->execute([':profile_id' => $profileId]);
    }

    private function quotedTable(): string
    {
        return '`'.$this->table.'`';
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): BlockPlacement
    {
        try {
            $settings = json_decode((string)$row['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored homepage block settings JSON is invalid.', 0, $error);
        }

        return new BlockPlacement(
            (string)$row['instance_id'],
            (string)$row['profile_id'],
            (string)$row['region'],
            (string)$row['block_id'],
            (int)$row['position_index'],
            (bool)$row['visible'],
            is_array($settings) ? $settings : [],
        );
    }
}
