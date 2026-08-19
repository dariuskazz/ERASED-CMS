<?php
declare(strict_types=1);

namespace Erased\Website;

use JsonException;
use PDO;
use RuntimeException;

final class WebsiteProfileRepository
{
    public function __construct(private readonly PDO $pdo, private readonly string $table = 'website_profiles')
    {
    }

    /** @param array<string,mixed> $config @return int the new profile's id */
    public function create(string $typeId, string $name, array $config, bool $isStarter = false, string $status = 'draft'): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO '.$this->quotedTable().' (type_id, name, status, is_starter, config_json) '
            .'VALUES (:type_id, :name, :status, :is_starter, :config_json)'
        );
        $statement->execute([
            ':type_id' => $typeId,
            ':name' => $name,
            ':status' => $status,
            ':is_starter' => $isStarter ? 1 : 0,
            ':config_json' => $this->encode($config),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $config */
    public function updateConfig(int $id, string $name, array $config): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE '.$this->quotedTable().' SET name = :name, config_json = :config_json, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':config_json' => $this->encode($config), ':id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE '.$this->quotedTable().' SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute([':status' => $status, ':id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM '.$this->quotedTable().' WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return array<string,mixed>|null */
    public function findActive(): ?array
    {
        $statement = $this->pdo->query('SELECT * FROM '.$this->quotedTable()." WHERE status = 'active' LIMIT 1");
        $row = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM '.$this->quotedTable().' ORDER BY status = \'active\' DESC, is_starter DESC, name');
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    /** @return list<array<string,mixed>> */
    public function byStatus(string $status): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM '.$this->quotedTable().' WHERE status = :status ORDER BY updated_at DESC');
        $statement->execute([':status' => $status]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    public function existsForType(string $typeId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM '.$this->quotedTable().' WHERE type_id = :type_id AND is_starter = 1 LIMIT 1');
        $statement->execute([':type_id' => $typeId]);

        return $statement->fetchColumn() !== false;
    }

    private function quotedTable(): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->table)) {
            throw new RuntimeException('Website profile table name is invalid.');
        }

        return '`'.$this->table.'`';
    }

    /** @param array<string,mixed> $config */
    private function encode(array $config): string
    {
        try {
            return json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not encode website profile configuration.', 0, $error);
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        try {
            $config = json_decode((string)$row['config_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored website profile configuration is invalid.', 0, $error);
        }

        $row['id'] = (int)$row['id'];
        $row['is_starter'] = (bool)$row['is_starter'];
        $row['config'] = is_array($config) ? $config : [];
        unset($row['config_json']);

        return $row;
    }
}
