<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use PDO;
use RuntimeException;

/**
 * Owns the one transaction that turns a cart into a real order. Phase 1
 * only ever writes provider='bank' rows - manual bank transfer is the only
 * payment path buildable end-to-end without a real merchant API
 * integration - but the transaction shape (payment_transactions row first,
 * then commerce_orders referencing it, then commerce_order_items) is
 * written to extend cleanly to a real gateway later.
 */
final class CheckoutService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductRepository $products,
        private readonly CouponRepository $coupons,
        private readonly PricingCalculator $pricing,
    ) {
    }

    /**
     * @param list<array{product:array<string,mixed>,quantity:int,line_total_minor:int}> $lines
     * @return array{order_id:int,order_number:string,confirmation_token:string}
     */
    public function submit(array $lines, string $customerName, string $customerEmail, string $currency, ?string $couponCode = null): array
    {
        if ($lines === []) {
            throw new RuntimeException('Your cart is empty.');
        }
        if (trim($customerName) === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid name and email address.');
        }

        $this->pdo->beginTransaction();
        try {
            // Never trust the cart's cached copy - re-check live stock for
            // every line inside the transaction, right before committing to
            // an order, to close the window between "added to cart" and
            // "checked out" where another order could have consumed stock.
            foreach ($lines as $line) {
                $productId = (int)$line['product']['id'];
                $live = $this->products->find($productId);
                if ($live === null || $live['status'] !== 'published') {
                    throw new RuntimeException("\"{$line['product']['name']}\" is no longer available.");
                }
                if ((int)$live['track_inventory'] === 1 && (int)$live['stock_quantity'] < $line['quantity']) {
                    throw new RuntimeException("Not enough stock for \"{$live['name']}\" - only {$live['stock_quantity']} left.");
                }
            }

            $subtotalMinor = 0;
            foreach ($lines as $line) {
                $subtotalMinor += $line['line_total_minor'];
            }

            // Never trust a coupon that was validated before the request
            // reached here - re-resolve it live, inside the transaction,
            // exactly like the stock re-check above.
            $coupon = null;
            if ($couponCode !== null && trim($couponCode) !== '') {
                $coupon = $this->coupons->findValidByCode($couponCode);
                if ($coupon === null) {
                    throw new RuntimeException("Coupon code \"{$couponCode}\" is not valid or has expired.");
                }
            }
            $discountMinor = $coupon !== null ? $this->coupons->discountFor($coupon, $subtotalMinor) : 0;

            $pricing = $this->pricing->calculate($subtotalMinor, $discountMinor);
            $shippingMinor = $pricing['shipping_minor'];
            $taxMinor = $pricing['tax_minor'];
            $totalMinor = $pricing['total_minor'];

            $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            $insertTransaction = $this->pdo->prepare(
                'INSERT INTO payment_transactions (user_id, provider, provider_reference, provider_transaction_id, amount_minor, currency, status) '
                .'VALUES (NULL, :provider, :provider_reference, :provider_transaction_id, :amount_minor, :currency, :status)'
            );
            $insertTransaction->execute([
                ':provider' => 'bank',
                ':provider_reference' => $orderNumber,
                ':provider_transaction_id' => 'bank-'.$orderNumber,
                ':amount_minor' => $totalMinor,
                ':currency' => $currency,
                ':status' => 'pending',
            ]);
            $paymentTransactionId = (int)$this->pdo->lastInsertId();

            $confirmationToken = bin2hex(random_bytes(16));
            $insertOrder = $this->pdo->prepare(
                'INSERT INTO commerce_orders (order_number, customer_name, customer_email, status, subtotal_minor, '
                .'coupon_id, coupon_code, discount_minor, shipping_minor, tax_minor, total_minor, currency, payment_transaction_id, confirmation_token) '
                .'VALUES (:order_number, :customer_name, :customer_email, :status, :subtotal_minor, '
                .':coupon_id, :coupon_code, :discount_minor, :shipping_minor, :tax_minor, :total_minor, :currency, :payment_transaction_id, :confirmation_token)'
            );
            $insertOrder->execute([
                ':order_number' => $orderNumber,
                ':customer_name' => $customerName,
                ':customer_email' => $customerEmail,
                ':status' => 'pending',
                ':subtotal_minor' => $subtotalMinor,
                ':coupon_id' => $coupon !== null ? (int)$coupon['id'] : null,
                ':coupon_code' => $coupon !== null ? (string)$coupon['code'] : null,
                ':discount_minor' => $discountMinor,
                ':shipping_minor' => $shippingMinor,
                ':tax_minor' => $taxMinor,
                ':total_minor' => $totalMinor,
                ':currency' => $currency,
                ':payment_transaction_id' => $paymentTransactionId,
                ':confirmation_token' => $confirmationToken,
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $insertItem = $this->pdo->prepare(
                'INSERT INTO commerce_order_items (order_id, product_id, product_name, product_kind, unit_price_minor, quantity, line_total_minor, currency) '
                .'VALUES (:order_id, :product_id, :product_name, :product_kind, :unit_price_minor, :quantity, :line_total_minor, :currency)'
            );
            foreach ($lines as $line) {
                $insertItem->execute([
                    ':order_id' => $orderId,
                    ':product_id' => (int)$line['product']['id'],
                    ':product_name' => (string)$line['product']['name'],
                    ':product_kind' => in_array($line['product']['kind'] ?? 'physical', ['physical', 'digital', 'subscription'], true) ? $line['product']['kind'] : 'physical',
                    ':unit_price_minor' => (int)$line['product']['price_minor'],
                    ':quantity' => $line['quantity'],
                    ':line_total_minor' => $line['line_total_minor'],
                    ':currency' => $currency,
                ]);
            }

            $this->pdo->commit();

            return ['order_id' => $orderId, 'order_number' => $orderNumber, 'confirmation_token' => $confirmationToken];
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
