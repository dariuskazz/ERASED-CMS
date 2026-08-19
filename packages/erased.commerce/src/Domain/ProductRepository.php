<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;

final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM commerce_products ORDER BY created_at DESC')->fetchAll();
    }

    /**
     * @param array{sort?:string,in_stock_only?:bool,featured_only?:bool,min_price_minor?:?int,max_price_minor?:?int,brand?:?string} $filters
     * @return list<array<string,mixed>>
     */
    public function published(array $filters = []): array
    {
        return $this->filtered(null, [], $filters);
    }

    /**
     * Products in category $categoryId, plus (by default) every descendant
     * category - a parent category page shows everything nested under it,
     * not just products filed directly on that exact row.
     * @param list<int> $descendantIds
     * @param array{sort?:string,in_stock_only?:bool,featured_only?:bool,min_price_minor?:?int,max_price_minor?:?int,brand?:?string} $filters
     * @return list<array<string,mixed>>
     */
    public function publishedInCategory(int $categoryId, array $descendantIds = [], array $filters = []): array
    {
        return $this->filtered($categoryId, $descendantIds, $filters);
    }

    /**
     * @param list<int> $descendantIds
     * @param array{sort?:string,in_stock_only?:bool,featured_only?:bool,min_price_minor?:?int,max_price_minor?:?int,brand?:?string} $filters
     * @return list<array<string,mixed>>
     */
    private function filtered(?int $categoryId, array $descendantIds, array $filters): array
    {
        $sql = "SELECT * FROM commerce_products WHERE status='published'";
        $args = [];

        if ($categoryId !== null) {
            $ids = array_unique([$categoryId, ...$descendantIds]);
            $sql .= ' AND category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($args, ...$ids);
        }
        if (!empty($filters['in_stock_only'])) {
            $sql .= ' AND (track_inventory=0 OR stock_quantity>0)';
        }
        if (!empty($filters['featured_only'])) {
            $sql .= ' AND featured=1';
        }
        if (isset($filters['min_price_minor']) && $filters['min_price_minor'] !== null) {
            $sql .= ' AND price_minor >= ?';
            $args[] = $filters['min_price_minor'];
        }
        if (isset($filters['max_price_minor']) && $filters['max_price_minor'] !== null) {
            $sql .= ' AND price_minor <= ?';
            $args[] = $filters['max_price_minor'];
        }
        if (!empty($filters['brand'])) {
            $sql .= ' AND name LIKE ?';
            $args[] = '%' . $filters['brand'] . '%';
        }
        $sql .= ' ORDER BY ' . $this->sortClause((string)($filters['sort'] ?? 'featured'));

        $statement = $this->pdo->prepare($sql);
        $statement->execute($args);
        return $statement->fetchAll();
    }

    /** The lowest and highest published price, for the shop sidebar's price-range control bounds. Falls back to a 0-0 range with nothing published, so the caller never divides by zero. */
    public function priceRange(): array
    {
        $row = $this->pdo->query("SELECT COALESCE(MIN(price_minor),0) AS min_minor, COALESCE(MAX(price_minor),0) AS max_minor FROM commerce_products WHERE status='published'")->fetch();
        return ['min_minor' => (int)($row['min_minor'] ?? 0), 'max_minor' => (int)($row['max_minor'] ?? 0)];
    }

    /**
     * Real vehicle makes actually present among current products in scope,
     * not a hardcoded checkbox list - scans product names for a curated
     * set of common automotive brands and returns only the ones that
     * genuinely match at least one published product, so the "Brand fit"
     * filter is never shown empty or offering a brand with zero results.
     * @param list<int> $descendantIds
     * @return list<string>
     */
    public function distinctBrandsInCategory(?int $categoryId, array $descendantIds = []): array
    {
        $knownBrands = ['Toyota', 'Honda', 'BMW', 'Ford', 'Volkswagen', 'Volvo', 'Subaru', 'Mazda', 'Chevrolet', 'Nissan', 'Jeep', 'Audi', 'Mercedes-Benz', 'Hyundai', 'Kia'];
        $sql = "SELECT name FROM commerce_products WHERE status='published'";
        $args = [];
        if ($categoryId !== null) {
            $ids = array_unique([$categoryId, ...$descendantIds]);
            $sql .= ' AND category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($args, ...$ids);
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($args);
        $names = $statement->fetchAll(PDO::FETCH_COLUMN);

        $found = [];
        foreach ($knownBrands as $brand) {
            foreach ($names as $name) {
                if (stripos((string)$name, $brand) !== false) {
                    $found[] = $brand;
                    break;
                }
            }
        }
        return $found;
    }

    /**
     * The shop homepage's "Recommended" section: products an admin has
     * explicitly marked featured come first, then the newest published
     * products fill any remaining slots - so the section is never empty on
     * a fresh install before anyone has flagged a single product.
     * @return list<array<string,mixed>>
     */
    public function recommended(int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM commerce_products WHERE status='published' ORDER BY featured DESC, created_at DESC LIMIT ?"
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** Whitelisted ORDER BY clauses only - $sort is never interpolated as free text. */
    private function sortClause(string $sort): string
    {
        return match ($sort) {
            'newest' => 'created_at DESC',
            'price_asc' => 'price_minor ASC',
            'price_desc' => 'price_minor DESC',
            'name_asc' => 'name ASC',
            default => 'featured DESC, created_at DESC',
        };
    }

    /**
     * Real search: FULLTEXT natural-language ranking across name/
     * description/sku, OR'd with a plain LIKE on name/sku so short or
     * highly-structured queries (a part number like "BR-204", too short for
     * FULLTEXT's default 3-char minimum token size and not "natural
     * language" text to begin with) still match reliably.
     * @return list<array<string,mixed>>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->published();
        }
        $statement = $this->pdo->prepare(
            "SELECT *, MATCH(name, description, sku) AGAINST(:q_natural IN NATURAL LANGUAGE MODE) AS relevance "
            . "FROM commerce_products WHERE status='published' AND ("
            . 'MATCH(name, description, sku) AGAINST(:q_bool IN BOOLEAN MODE) '
            . 'OR name LIKE :q_like_name OR sku LIKE :q_like_sku'
            . ') ORDER BY relevance DESC, created_at DESC'
        );
        $statement->execute([
            ':q_natural' => $query,
            ':q_bool' => $this->booleanModeQuery($query),
            ':q_like_name' => '%' . $query . '%',
            ':q_like_sku' => '%' . $query . '%',
        ]);
        return $statement->fetchAll();
    }

    /** Wraps each word in a trailing wildcard for BOOLEAN MODE, so a partial word (e.g. "recl" for "reclaimed") still matches instead of requiring a complete token. */
    private function booleanModeQuery(string $query): string
    {
        $words = preg_split('/\s+/', trim($query)) ?: [];
        $words = array_filter($words, static fn ($w) => $w !== '');
        if ($words === []) {
            return '';
        }
        return implode(' ', array_map(static fn ($w) => '+' . preg_replace('/[+\-<>()~*"@]/', '', $w) . '*', $words));
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_products WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM commerce_products WHERE slug=? AND status='published' LIMIT 1");
        $statement->execute([$slug]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO commerce_products (name, slug, description, price_minor, currency, sku, stock_quantity, track_inventory, category_id, featured_media_id, status, featured, kind, subscription_interval) '
            .'VALUES (:name, :slug, :description, :price_minor, :currency, :sku, :stock_quantity, :track_inventory, :category_id, :featured_media_id, :status, :featured, :kind, :subscription_interval)'
        );
        $statement->execute($this->bindParams($data));
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commerce_products SET name=:name, slug=:slug, description=:description, price_minor=:price_minor, '
            .'currency=:currency, sku=:sku, stock_quantity=:stock_quantity, track_inventory=:track_inventory, '
            .'category_id=:category_id, featured_media_id=:featured_media_id, status=:status, featured=:featured, kind=:kind, subscription_interval=:subscription_interval WHERE id=:id'
        );
        $params = $this->bindParams($data);
        $params[':id'] = $id;
        $statement->execute($params);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM commerce_products WHERE id=?');
        $statement->execute([$id]);
    }

    /** Guarded decrement - never lets stock go negative even under a race. */
    public function decrementStock(int $id, int $quantity): bool
    {
        $statement = $this->pdo->prepare('UPDATE commerce_products SET stock_quantity = stock_quantity - ? WHERE id=? AND track_inventory=1 AND stock_quantity >= ?');
        $statement->execute([$quantity, $id, $quantity]);
        return $statement->rowCount() > 0;
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $suffix = 2;
        while (true) {
            $sql = 'SELECT id FROM commerce_products WHERE slug=?'.($ignoreId !== null ? ' AND id<>?' : '').' LIMIT 1';
            $statement = $this->pdo->prepare($sql);
            $args = [$slug];
            if ($ignoreId !== null) {
                $args[] = $ignoreId;
            }
            $statement->execute($args);
            if (!$statement->fetch()) {
                return $slug;
            }
            $slug = $base.'-'.$suffix++;
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function bindParams(array $data): array
    {
        return [
            ':name' => (string)($data['name'] ?? ''),
            ':slug' => (string)($data['slug'] ?? ''),
            ':description' => (string)($data['description'] ?? ''),
            ':price_minor' => (int)($data['price_minor'] ?? 0),
            ':currency' => (string)($data['currency'] ?? 'EUR'),
            ':sku' => ($data['sku'] ?? '') !== '' ? (string)$data['sku'] : null,
            ':stock_quantity' => (int)($data['stock_quantity'] ?? 0),
            ':track_inventory' => !empty($data['track_inventory']) ? 1 : 0,
            ':category_id' => ($data['category_id'] ?? 0) ? (int)$data['category_id'] : null,
            ':featured_media_id' => ($data['featured_media_id'] ?? 0) ? (int)$data['featured_media_id'] : null,
            ':status' => in_array($data['status'] ?? 'draft', ['draft', 'published'], true) ? $data['status'] : 'draft',
            ':featured' => !empty($data['featured']) ? 1 : 0,
            ':kind' => in_array($kind = (string)($data['kind'] ?? 'physical'), ['physical', 'digital', 'subscription'], true) ? $kind : 'physical',
            ':subscription_interval' => $kind === 'subscription' && in_array($interval = (string)($data['subscription_interval'] ?? ''), ['month', 'year'], true) ? $interval : null,
        ];
    }
}
