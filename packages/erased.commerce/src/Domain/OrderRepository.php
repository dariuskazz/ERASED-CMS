<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;
use RuntimeException;

final class OrderRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductRepository $products,
        private readonly CouponRepository $coupons,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM commerce_orders ORDER BY created_at DESC')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_orders WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findByToken(int $id, string $token): ?array
    {
        $order = $this->find($id);
        if ($order === null || !hash_equals((string)$order['confirmation_token'], $token)) {
            return null;
        }
        return $order;
    }

    /** @return list<array<string,mixed>> */
    public function items(int $orderId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_order_items WHERE order_id=? ORDER BY id');
        $statement->execute([$orderId]);
        return $statement->fetchAll();
    }

    /**
     * Buckets orders by placement day for the admin calendar. total_minor
     * (every status) and paid_minor (paid only) are reported separately so
     * a pending/cancelled order never inflates the "revenue" figure.
     *
     * @return list<array{day:string,order_count:int,total_minor:int,paid_minor:int}>
     */
    public function activityByRange(string $startDate, string $endDateExclusive): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS order_count, SUM(total_minor) AS total_minor, "
            ."SUM(CASE WHEN status='paid' THEN total_minor ELSE 0 END) AS paid_minor "
            .'FROM commerce_orders WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day'
        );
        $statement->execute([$startDate, $endDateExclusive]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = [
                'day' => (string)$row['day'],
                'order_count' => (int)$row['order_count'],
                'total_minor' => (int)$row['total_minor'],
                'paid_minor' => (int)$row['paid_minor'],
            ];
        }
        return $rows;
    }

    /** Orders placed on exactly this date - the calendar's click-through target. @return list<array<string,mixed>> */
    public function forDate(string $date): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_orders WHERE DATE(created_at)=? ORDER BY created_at DESC');
        $statement->execute([$date]);
        return $statement->fetchAll();
    }

    /**
     * Transitions a pending order to paid: flips both the order and its
     * linked payment_transactions row, then - only here, never at checkout
     * time - decrements stock for each line. A pending order is a promise,
     * not a captured payment, so stock is only actually committed once an
     * admin confirms the transfer arrived. Rejects a replayed POST (status
     * must be 'pending') so double-processing can never double-decrement.
     *
     * @return list<string> stock-shortfall warnings, if any (does not fail
     *   the whole mark-paid - the payment already arrived, refusing to
     *   record that would be worse than a stock discrepancy to resolve by hand)
     */
    public function markPaid(int $orderId): array
    {
        $order = $this->find($orderId);
        if ($order === null) {
            throw new RuntimeException('Order not found.');
        }
        if ($order['status'] !== 'pending') {
            throw new RuntimeException('This order has already been processed.');
        }

        $warnings = [];
        $this->pdo->beginTransaction();
        try {
            $updateOrder = $this->pdo->prepare("UPDATE commerce_orders SET status='paid' WHERE id=? AND status='pending'");
            $updateOrder->execute([$orderId]);
            if ($updateOrder->rowCount() === 0) {
                throw new RuntimeException('This order has already been processed.');
            }

            if (!empty($order['payment_transaction_id'])) {
                $updateTransaction = $this->pdo->prepare("UPDATE payment_transactions SET status='paid' WHERE id=?");
                $updateTransaction->execute([(int)$order['payment_transaction_id']]);
            }

            foreach ($this->items($orderId) as $item) {
                if ($item['product_id'] === null) {
                    continue;
                }
                $decremented = $this->products->decrementStock((int)$item['product_id'], (int)$item['quantity']);
                if (!$decremented) {
                    $warnings[] = "Stock for \"{$item['product_name']}\" could not be fully decremented - check inventory manually.";
                    // The flash warning above is one-time and easy to miss -
                    // this is the only durable record that this order was
                    // marked paid despite insufficient stock (two concurrent
                    // checkouts for the last unit can both reach a real
                    // pending order; this is the point where the second one's
                    // shortfall actually surfaces), so an admin reviewing
                    // inventory discrepancies later has something to find.
                    if (function_exists('audit')) {
                        audit('commerce.order.stock_oversold', [
                            'order_id' => $orderId,
                            'product_id' => (int)$item['product_id'],
                            'product_name' => (string)$item['product_name'],
                            'quantity' => (int)$item['quantity'],
                        ]);
                    }
                }

                if (($item['product_kind'] ?? 'physical') === 'digital') {
                    $this->issueDownloads((int)$item['id'], (int)$item['product_id']);
                } elseif (($item['product_kind'] ?? 'physical') === 'subscription') {
                    $this->createOrExtendSubscription((int)$item['product_id'], (string)$item['product_name'], (string)$order['customer_email'], $orderId);
                }
            }

            // Coupon usage commits here too, never at checkout - the same
            // "a pending order is a promise, not a captured payment" reasoning
            // that already governs stock decrements applies identically here.
            if (!empty($order['coupon_id'])) {
                $this->coupons->incrementUsage((int)$order['coupon_id']);
            }

            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        return $warnings;
    }

    /** Issues one gated download token per file attached to the product, skipping any that already exist for this order item. */
    private function issueDownloads(int $orderItemId, int $productId): void
    {
        $files = $this->pdo->prepare('SELECT id FROM commerce_product_files WHERE product_id=?');
        $files->execute([$productId]);
        foreach ($files->fetchAll() as $file) {
            $fileId = (int)$file['id'];
            $existing = $this->pdo->prepare('SELECT id FROM commerce_downloads WHERE order_item_id=? AND product_file_id=? LIMIT 1');
            $existing->execute([$orderItemId, $fileId]);
            if ($existing->fetch() !== false) {
                continue;
            }
            $insert = $this->pdo->prepare('INSERT INTO commerce_downloads (order_item_id, product_file_id, token) VALUES (?, ?, ?)');
            $insert->execute([$orderItemId, $fileId, bin2hex(random_bytes(20))]);
        }
    }

    /**
     * Admin-managed subscription record - no payment gateway exists in this
     * codebase to actually re-charge a customer, so this only tracks a
     * period end date. Extending always measures from today (the confirmed
     * payment date), never stacking onto the old end date, so a late
     * mark-paid never silently grants extra time.
     */
    private function createOrExtendSubscription(int $productId, string $productName, string $customerEmail, int $orderId): void
    {
        $product = $this->products->find($productId);
        $interval = $product !== null && in_array($product['subscription_interval'] ?? '', ['month', 'year'], true) ? $product['subscription_interval'] : 'month';
        $periodEnd = $interval === 'year' ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));

        $existing = $this->pdo->prepare("SELECT id FROM commerce_subscriptions WHERE product_id=? AND customer_email=? AND status='active' LIMIT 1");
        $existing->execute([$productId, $customerEmail]);
        $row = $existing->fetch();

        if ($row !== false) {
            $update = $this->pdo->prepare('UPDATE commerce_subscriptions SET current_period_end=?, order_id=?, interval_name=? WHERE id=?');
            $update->execute([$periodEnd, $orderId, $interval, (int)$row['id']]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO commerce_subscriptions (product_id, product_name, customer_email, order_id, status, interval_name, current_period_end) '
            .'VALUES (?, ?, ?, ?, \'active\', ?, ?)'
        );
        $insert->execute([$productId, $productName, $customerEmail, $orderId, $interval, $periodEnd]);
    }
}
