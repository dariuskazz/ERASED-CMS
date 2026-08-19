<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/ProductRepository.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/Cart.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/CouponRepository.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/PricingCalculator.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/CheckoutService.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/OrderRepository.php';

use ErasedCommerce\Domain\Cart;
use ErasedCommerce\Domain\CheckoutService;
use ErasedCommerce\Domain\CouponRepository;
use ErasedCommerce\Domain\OrderRepository;
use ErasedCommerce\Domain\PricingCalculator;
use ErasedCommerce\Domain\ProductRepository;

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE commerce_products (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
        description TEXT NULL, price_minor INTEGER NOT NULL, currency TEXT NOT NULL, sku TEXT NULL UNIQUE,
        stock_quantity INTEGER NOT NULL DEFAULT 0, track_inventory INTEGER NOT NULL DEFAULT 1,
        category_id INTEGER NULL, featured_media_id INTEGER NULL, status TEXT NOT NULL DEFAULT \'draft\', featured INTEGER NOT NULL DEFAULT 0,
        kind TEXT NOT NULL DEFAULT \'physical\', subscription_interval TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE commerce_coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, type TEXT NOT NULL, value INTEGER NOT NULL,
        max_uses INTEGER NULL, used_count INTEGER NOT NULL DEFAULT 0, starts_at TEXT NULL, expires_at TEXT NULL,
        active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE payment_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, provider TEXT NOT NULL,
        provider_reference TEXT NOT NULL, provider_transaction_id TEXT NOT NULL,
        amount_minor INTEGER NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\',
        metadata TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(provider, provider_transaction_id)
    )');
    $pdo->exec('CREATE TABLE commerce_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_number TEXT NOT NULL UNIQUE, customer_name TEXT NOT NULL,
        customer_email TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\', subtotal_minor INTEGER NOT NULL,
        coupon_id INTEGER NULL, coupon_code TEXT NULL, discount_minor INTEGER NOT NULL DEFAULT 0,
        shipping_minor INTEGER NOT NULL DEFAULT 0, tax_minor INTEGER NOT NULL DEFAULT 0,
        total_minor INTEGER NOT NULL, currency TEXT NOT NULL, payment_transaction_id INTEGER NULL,
        confirmation_token TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE commerce_order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER NULL,
        product_name TEXT NOT NULL, product_kind TEXT NOT NULL DEFAULT \'physical\', unit_price_minor INTEGER NOT NULL, quantity INTEGER NOT NULL,
        line_total_minor INTEGER NOT NULL, currency TEXT NOT NULL
    )');

    $products = new ProductRepository($pdo);
    $coupons = new CouponRepository($pdo);
    $pricing = new PricingCalculator(0, 0, null);
    $widgetId = $products->create(['name' => 'Widget', 'slug' => 'widget', 'price_minor' => 1000, 'currency' => 'EUR', 'stock_quantity' => 5, 'status' => 'published', 'track_inventory' => true]);

    $_SESSION = [];
    $cart = new Cart($products);
    $cart->add($widgetId, 3);

    $checkout = new CheckoutService($pdo, $products, $coupons, $pricing);
    $placed = $checkout->submit($cart->lines(), 'Ada Lovelace', 'ada@example.com', 'EUR');
    $orderId = $placed['order_id'];

    $orders = new OrderRepository($pdo, $products, $coupons);

    $found = $orders->find($orderId);
    $check($found !== null && $found['status'] === 'pending', 'find() returns the freshly placed pending order');

    $wrongToken = $orders->findByToken($orderId, str_repeat('0', 32));
    $check($wrongToken === null, 'findByToken() rejects an incorrect token');
    $rightToken = $orders->findByToken($orderId, $placed['confirmation_token']);
    $check($rightToken !== null, 'findByToken() accepts the correct token');

    $items = $orders->items($orderId);
    $check(count($items) === 1 && (int)$items[0]['quantity'] === 3, 'items() returns the order\'s line items');

    $warnings = $orders->markPaid($orderId);
    $check($warnings === [], 'markPaid() succeeds with no stock-shortfall warnings when stock is sufficient');
    $check($orders->find($orderId)['status'] === 'paid', 'markPaid() transitions the order to paid');

    $transaction = $pdo->query('SELECT status FROM payment_transactions WHERE id='.(int)$found['payment_transaction_id'])->fetchColumn();
    $check($transaction === 'paid', 'markPaid() also transitions the linked payment_transactions row to paid');

    $check((int)$products->find($widgetId)['stock_quantity'] === 2, 'markPaid() decrements stock by exactly the ordered quantity (5 - 3 = 2)');

    $replayRejected = false;
    try {
        $orders->markPaid($orderId);
    } catch (RuntimeException $e) {
        $replayRejected = true;
    }
    $check($replayRejected, 'markPaid() rejects a replayed call on an already-paid order');
    $check((int)$products->find($widgetId)['stock_quantity'] === 2, 'a rejected replay does not double-decrement stock');

    // ---- markPaid() on an order whose product was since deleted must not crash ----
    $products->create(['name' => 'Temp', 'slug' => 'temp', 'price_minor' => 500, 'currency' => 'EUR', 'stock_quantity' => 2, 'status' => 'published', 'track_inventory' => true]);
    $tempId = (int)$pdo->query("SELECT id FROM commerce_products WHERE slug='temp'")->fetchColumn();
    $_SESSION['commerce_cart'] = [$tempId => 1];
    $cart2 = new Cart($products);
    $placed2 = $checkout->submit($cart2->lines(), 'Grace Hopper', 'grace@example.com', 'EUR');
    $products->delete($tempId);
    $noCrash = true;
    try {
        $orders->markPaid($placed2['order_id']);
    } catch (Throwable $e) {
        $noCrash = false;
    }
    $check($noCrash, 'markPaid() does not crash when an order item\'s product was deleted after the order was placed');

    // ---- markPaid() increments coupon usage, only at mark-paid time, never at checkout ----
    $couponId = $coupons->create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'active' => true]);
    $products->create(['name' => 'Gadget', 'slug' => 'gadget', 'price_minor' => 2000, 'currency' => 'EUR', 'stock_quantity' => 5, 'status' => 'published', 'track_inventory' => true]);
    $gadgetId = (int)$pdo->query("SELECT id FROM commerce_products WHERE slug='gadget'")->fetchColumn();
    $_SESSION['commerce_cart'] = [$gadgetId => 1];
    $cart3 = new Cart($products);
    $placed3 = $checkout->submit($cart3->lines(), 'Alan Turing', 'alan@example.com', 'EUR', 'SAVE10');
    $check((int)$coupons->find($couponId)['used_count'] === 0, 'placing an order with a coupon does not increment usage at checkout time');
    $orders->markPaid($placed3['order_id']);
    $check((int)$coupons->find($couponId)['used_count'] === 1, 'markPaid() increments coupon usage by exactly 1');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce OrderRepository test passed.\n");
        fwrite(STDOUT, "Validated find/findByToken/items, markPaid()'s order+payment_transactions transition and stock decrement, replay rejection, graceful handling of a deleted product, and coupon-usage increment at mark-paid time only.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
