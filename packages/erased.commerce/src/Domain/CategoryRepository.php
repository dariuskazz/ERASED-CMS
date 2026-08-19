<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM commerce_categories ORDER BY parent_id IS NULL DESC, sort_order, name')->fetchAll();
    }

    /**
     * Every category with a `depth` (0 = top-level) so callers can render
     * an indented tree without walking parent/child links themselves.
     * @return list<array<string,mixed>>
     */
    public function tree(): array
    {
        $rows = $this->all();
        $byParent = [];
        foreach ($rows as $row) {
            $byParent[(int)($row['parent_id'] ?? 0)][] = $row;
        }
        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$byParent, &$out): void {
            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['depth'] = $depth;
                $out[] = $row;
                $walk((int)$row['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_categories WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_categories WHERE slug=? LIMIT 1');
        $statement->execute([$slug]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function children(int $parentId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_categories WHERE parent_id=? ORDER BY sort_order, name');
        $statement->execute([$parentId]);
        return $statement->fetchAll();
    }

    /** Every descendant id (children, grandchildren, ...) of $id, not including $id itself. @return list<int> */
    public function descendantIds(int $id): array
    {
        $all = $this->all();
        $byParent = [];
        foreach ($all as $row) {
            $byParent[(int)($row['parent_id'] ?? 0)][] = (int)$row['id'];
        }
        $out = [];
        $queue = $byParent[$id] ?? [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $out[] = $current;
            foreach ($byParent[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }
        return $out;
    }

    /** Published-product count per category id, including descendants. @return array<int,int> */
    public function productCounts(): array
    {
        $rows = $this->pdo->query(
            "SELECT category_id, COUNT(*) AS n FROM commerce_products WHERE status='published' AND category_id IS NOT NULL GROUP BY category_id"
        )->fetchAll();
        $direct = [];
        foreach ($rows as $row) {
            $direct[(int)$row['category_id']] = (int)$row['n'];
        }
        $totals = [];
        foreach ($this->all() as $category) {
            $id = (int)$category['id'];
            $total = $direct[$id] ?? 0;
            foreach ($this->descendantIds($id) as $descendantId) {
                $total += $direct[$descendantId] ?? 0;
            }
            $totals[$id] = $total;
        }
        return $totals;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO commerce_categories (name, slug, description, parent_id, image_media_id, sort_order, icon) '
            . 'VALUES (:name, :slug, :description, :parent_id, :image_media_id, :sort_order, :icon)'
        );
        $statement->execute($this->bindParams($data));
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commerce_categories SET name=:name, slug=:slug, description=:description, '
            . 'parent_id=:parent_id, image_media_id=:image_media_id, sort_order=:sort_order, icon=:icon WHERE id=:id'
        );
        $params = $this->bindParams($data);
        $params[':id'] = $id;
        $statement->execute($params);
    }

    /** Products in this category keep their row (category_id set to NULL via the FK's ON DELETE SET NULL), never deleted. */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM commerce_categories WHERE id=?');
        $statement->execute([$id]);
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $suffix = 2;
        while (true) {
            $sql = 'SELECT id FROM commerce_categories WHERE slug=?' . ($ignoreId !== null ? ' AND id<>?' : '') . ' LIMIT 1';
            $statement = $this->pdo->prepare($sql);
            $args = [$slug];
            if ($ignoreId !== null) {
                $args[] = $ignoreId;
            }
            $statement->execute($args);
            if (!$statement->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . $suffix++;
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function bindParams(array $data): array
    {
        return [
            ':name' => (string)($data['name'] ?? ''),
            ':slug' => (string)($data['slug'] ?? ''),
            ':description' => (string)($data['description'] ?? ''),
            ':parent_id' => ($data['parent_id'] ?? 0) ? (int)$data['parent_id'] : null,
            ':image_media_id' => ($data['image_media_id'] ?? 0) ? (int)$data['image_media_id'] : null,
            ':sort_order' => (int)($data['sort_order'] ?? 0),
            ':icon' => mb_substr(trim((string)($data['icon'] ?? '')), 0, 4),
        ];
    }
}
