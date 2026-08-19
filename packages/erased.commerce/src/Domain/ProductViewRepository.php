<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;

/**
 * Deliberately Commerce's own lightweight view counter rather than a
 * dependency on erased.analytics-pro (an entirely separate, optional paid
 * plugin - Commerce must work fully with it never installed). One row per
 * (product, day), matching the day-bucketed dedup convention already used
 * by core's analytics_visits and analytics-pro's own tables, so a visitor
 * refreshing the same product page ten times in one day is still one
 * meaningful count, not ten - "most viewed" should mean interest, not
 * accidental reloads.
 */
final class ProductViewRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function track(int $productId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO commerce_product_views (product_id, view_day, views) VALUES (?, CURDATE(), 1) '
            . 'ON DUPLICATE KEY UPDATE views = views + 1'
        );
        $statement->execute([$productId]);
    }

    /** Total views for one product, all time. */
    public function totalFor(int $productId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(views),0) FROM commerce_product_views WHERE product_id=?');
        $statement->execute([$productId]);
        return (int)$statement->fetchColumn();
    }

    /**
     * Most-viewed products in the last $days days, joined with the product
     * row so the caller doesn't need a second lookup.
     * @return list<array<string,mixed>>
     */
    public function mostViewed(int $limit, int $days = 30): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.slug, p.price_minor, p.currency, SUM(v.views) AS total_views '
            . 'FROM commerce_product_views v JOIN commerce_products p ON p.id = v.product_id '
            . 'WHERE v.view_day >= DATE_SUB(CURDATE(), INTERVAL ? DAY) '
            . 'GROUP BY p.id ORDER BY total_views DESC LIMIT ?'
        );
        $statement->bindValue(1, $days, PDO::PARAM_INT);
        $statement->bindValue(2, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
