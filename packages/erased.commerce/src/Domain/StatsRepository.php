<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;

/**
 * Aggregate reporting queries for the admin Statistics screen - kept
 * separate from OrderRepository/ProductRepository (which own single-record
 * CRUD and the storefront's own read paths) since these are exclusively
 * GROUP BY/aggregate reads with no write side and no storefront caller.
 */
final class StatsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Order counts and revenue per status, over all time - the basis for
     * both the status-breakdown chart and the cancellation/refund rate.
     * @return array<string,array{count:int,total_minor:int}>
     */
    public function orderStatusBreakdown(): array
    {
        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS n, COALESCE(SUM(total_minor),0) AS total_minor FROM commerce_orders GROUP BY status'
        )->fetchAll();
        $out = ['pending' => ['count' => 0, 'total_minor' => 0], 'paid' => ['count' => 0, 'total_minor' => 0], 'cancelled' => ['count' => 0, 'total_minor' => 0], 'refunded' => ['count' => 0, 'total_minor' => 0]];
        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if (isset($out[$status])) {
                $out[$status] = ['count' => (int)$row['n'], 'total_minor' => (int)$row['total_minor']];
            }
        }
        return $out;
    }

    /** @return array{orders:int,revenue_minor:int,average_order_value_minor:int} Paid orders only - the only ones that represent real revenue. */
    public function revenueSummary(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total_minor),0) AS total_minor FROM commerce_orders WHERE status='paid'"
        )->fetch();
        $orders = (int)($row['n'] ?? 0);
        $revenue = (int)($row['total_minor'] ?? 0);
        return [
            'orders' => $orders,
            'revenue_minor' => $revenue,
            'average_order_value_minor' => $orders > 0 ? (int)round($revenue / $orders) : 0,
        ];
    }

    /**
     * Best sellers by quantity sold - counted from order items on paid
     * orders only, so an abandoned cart or a cancelled order never
     * inflates "most sold". A product deleted after the sale still shows
     * (product_name is a snapshot on the order item itself), just without
     * a working link - matching how the order-detail screen already
     * treats a since-deleted product.
     * @return list<array{product_id:?int,product_name:string,quantity:int,revenue_minor:int}>
     */
    public function topSellingProducts(int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.line_total_minor) AS revenue_minor "
            . "FROM commerce_order_items oi JOIN commerce_orders o ON o.id = oi.order_id WHERE o.status='paid' "
            . 'GROUP BY COALESCE(oi.product_id, 0), oi.product_name ORDER BY quantity DESC LIMIT ?'
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * Revenue by category, walking each sold item's product back to its
     * category - items whose product was deleted or was never categorized
     * are grouped under "Uncategorized" rather than silently dropped, so
     * this total always reconciles with topSellingProducts()'s total.
     * @return list<array{category:string,revenue_minor:int,quantity:int}>
     */
    public function topCategories(int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(c.name, 'Uncategorized') AS category, SUM(oi.quantity) AS quantity, SUM(oi.line_total_minor) AS revenue_minor "
            . "FROM commerce_order_items oi JOIN commerce_orders o ON o.id = oi.order_id "
            . 'LEFT JOIN commerce_products p ON p.id = oi.product_id '
            . 'LEFT JOIN commerce_categories c ON c.id = p.category_id '
            . "WHERE o.status='paid' GROUP BY category ORDER BY revenue_minor DESC LIMIT ?"
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** Products low on tracked stock (1-$threshold left) and separately, fully out of stock - two different urgency levels for the same restock report. */
    public function stockAlerts(int $threshold = 5): array
    {
        $low = $this->pdo->prepare(
            'SELECT id, name, stock_quantity FROM commerce_products WHERE track_inventory=1 AND stock_quantity>0 AND stock_quantity<=? '
            . "AND status='published' ORDER BY stock_quantity ASC"
        );
        $low->execute([$threshold]);
        $out = $this->pdo->query(
            "SELECT id, name, stock_quantity FROM commerce_products WHERE track_inventory=1 AND stock_quantity=0 AND status='published' ORDER BY name"
        )->fetchAll();
        return ['low' => $low->fetchAll(), 'out' => $out];
    }

    /** @return list<array<string,mixed>> Every coupon with its real usage, not just the raw used_count column - active/expired state matters for whether that count is still climbing. */
    public function couponPerformance(): array
    {
        return $this->pdo->query(
            'SELECT code, type, value, used_count, max_uses, active, expires_at FROM commerce_coupons ORDER BY used_count DESC'
        )->fetchAll();
    }
}
