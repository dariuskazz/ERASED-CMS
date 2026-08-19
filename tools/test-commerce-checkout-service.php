<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/ProductRepository.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/Cart.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/CouponRepository.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/PricingCalculator.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/CheckoutService.php';

use ErasedCommerce\Domain\Cart;
use ErasedCommerce\Domain\CheckoutService;
use ErasedCommerce\Domain\CouponRepository;
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
    // Mirrors the real payment_transactions schema closely enough to prove
    // CheckoutService's writes (this is the table's first-ever real writer).
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
    $widgetId = $products->create(['name' => 'Widget', 'slug' => 'widget', 'price_minor' => 1000, 'currency' => 'EUR', 'stock_quantity' => 3, 'status' => 'published', 'track_inventory' => true]);

    $_SESSION = [];
    $cart = new Cart($products);
    $cart->add($widgetId, 2);

    $countRows = static fn(string $table): int => (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

    // ---- Successful checkout, no coupon, zero tax/shipping (phase 1 backward-compat baseline) ----
    $service = new CheckoutService($pdo, $products, $coupons, new PricingCalculator(0, 0, null));
    $result = $service->submit($cart->lines(), 'Ada Lovelace', 'ada@example.com', 'EUR');
    $check($result['order_id'] > 0 && $result['order_number'] !== '' && strlen($result['confirmation_token']) === 32, 'submit() returns order id, number, and a 32-char confirmation token');
    $check($countRows('payment_transactions') === 1, 'submit() writes exactly one payment_transactions row');
    $check($countRows('commerce_orders') === 1, 'submit() writes exactly one commerce_orders row');
    $check($countRows('commerce_order_items') === 1, 'submit() writes one commerce_order_items row for the single cart line');

    $order = $pdo->query('SELECT * FROM commerce_orders')->fetch(PDO::FETCH_ASSOC);
    $transaction = $pdo->query('SELECT * FROM payment_transactions')->fetch(PDO::FETCH_ASSOC);
    $check((int)$order['payment_transaction_id'] === (int)$transaction['id'], 'the order correctly links to its payment_transactions row');
    $check($transaction['provider'] === 'bank' && $transaction['status'] === 'pending', 'the payment_transactions row is provider=bank, status=pending');
    $check((int)$order['total_minor'] === 2000, 'order total matches the cart subtotal (2 x 1000) with zero tax/shipping/discount');
    $check($order['coupon_id'] === null && (int)$order['discount_minor'] === 0, 'no coupon means no discount and a null coupon_id');
    $check($order['status'] === 'pending', 'a fresh order starts pending');

    $item = $pdo->query('SELECT * FROM commerce_order_items')->fetch(PDO::FETCH_ASSOC);
    $check((int)$item['product_id'] === $widgetId && $item['product_name'] === 'Widget' && (int)$item['quantity'] === 2, 'the order item correctly snapshots product id, name, and quantity');

    // ---- Empty cart rejected, zero rows written ----
    $beforeTransactions = $countRows('payment_transactions');
    $beforeOrders = $countRows('commerce_orders');
    $rejected = false;
    try {
        $service->submit([], 'Nobody', 'nobody@example.com', 'EUR');
    } catch (RuntimeException $e) {
        $rejected = true;
    }
    $check($rejected, 'submit() rejects an empty cart');
    $check($countRows('payment_transactions') === $beforeTransactions && $countRows('commerce_orders') === $beforeOrders, 'an empty-cart rejection writes zero rows');

    // ---- Out-of-stock rejected, full rollback (never trusts the cart's cached copy) ----
    $cart2 = new Cart($products);
    $cart2->add($widgetId, 999); // far more than the 1 unit remaining (3 - 2 already ordered)
    $beforeTransactions = $countRows('payment_transactions');
    $beforeOrders = $countRows('commerce_orders');
    $beforeItems = $countRows('commerce_order_items');
    $stockRejected = false;
    try {
        $service->submit($cart2->lines(), 'Grace Hopper', 'grace@example.com', 'EUR');
    } catch (RuntimeException $e) {
        $stockRejected = str_contains($e->getMessage(), 'stock');
    }
    $check($stockRejected, 'submit() rejects a line exceeding live stock, not the cart\'s cached quantity');
    $check(
        $countRows('payment_transactions') === $beforeTransactions
        && $countRows('commerce_orders') === $beforeOrders
        && $countRows('commerce_order_items') === $beforeItems,
        'an out-of-stock rejection leaves zero new rows across all three tables (full rollback)'
    );
    $check((int)$products->find($widgetId)['stock_quantity'] === 3, 'stock is untouched by a rejected checkout - phase 1 only decrements at admin mark-paid, never at order placement');

    // ---- Coupon: a valid percent coupon reduces the total and records discount_minor/coupon_id/coupon_code ----
    $couponId = $coupons->create(['code' => 'save10', 'type' => 'percent', 'value' => 10, 'active' => true]);
    $_SESSION['commerce_cart'] = []; // each Cart instance shares one session key - reset between scenarios
    $cart3 = new Cart($products);
    $cart3->add($widgetId, 1); // subtotal 1000
    $result3 = $service->submit($cart3->lines(), 'Alan Turing', 'alan@example.com', 'EUR', 'save10');
    $order3 = $pdo->query('SELECT * FROM commerce_orders WHERE id='.(int)$result3['order_id'])->fetch(PDO::FETCH_ASSOC);
    $check((int)$order3['coupon_id'] === $couponId && $order3['coupon_code'] === 'SAVE10', 'a valid coupon is stored on the order by id and normalized (uppercased) code');
    $check((int)$order3['discount_minor'] === 100, 'a 10% coupon on a 1000-minor subtotal discounts exactly 100 minor units');
    $check((int)$order3['total_minor'] === 900, 'the order total reflects the discount (1000 - 100)');

    // ---- Coupon: shipping and tax are applied on top of the discounted subtotal ----
    $servicePriced = new CheckoutService($pdo, $products, $coupons, new PricingCalculator(2100, 500, null)); // 21% tax, 5.00 flat shipping
    $_SESSION['commerce_cart'] = [];
    $cart4 = new Cart($products);
    $cart4->add($widgetId, 1); // subtotal 1000
    $result4 = $servicePriced->submit($cart4->lines(), 'Grace Hopper', 'grace@example.com', 'EUR', 'save10');
    $order4 = $pdo->query('SELECT * FROM commerce_orders WHERE id='.(int)$result4['order_id'])->fetch(PDO::FETCH_ASSOC);
    // discounted = 1000 - 100 = 900; tax = round(900 * 2100/10000) = 189; shipping = 500; total = 900 + 189 + 500 = 1589
    $check((int)$order4['discount_minor'] === 100 && (int)$order4['shipping_minor'] === 500 && (int)$order4['tax_minor'] === 189, 'shipping and tax are computed correctly on the discounted subtotal');
    $check((int)$order4['total_minor'] === 1589, 'the order total correctly sums discounted subtotal + shipping + tax');

    // ---- Coupon: an invalid/expired/nonexistent code rejects the whole checkout, full rollback ----
    $beforeTransactions = $countRows('payment_transactions');
    $beforeOrders = $countRows('commerce_orders');
    $beforeItems = $countRows('commerce_order_items');
    $_SESSION['commerce_cart'] = [];
    $cart5 = new Cart($products);
    $cart5->add($widgetId, 1);
    $couponRejected = false;
    try {
        $service->submit($cart5->lines(), 'Nobody', 'nobody@example.com', 'EUR', 'DOES-NOT-EXIST');
    } catch (RuntimeException $e) {
        $couponRejected = str_contains($e->getMessage(), 'DOES-NOT-EXIST');
    }
    $check($couponRejected, 'submit() rejects the whole checkout for a coupon code that does not resolve');
    $check(
        $countRows('payment_transactions') === $beforeTransactions
        && $countRows('commerce_orders') === $beforeOrders
        && $countRows('commerce_order_items') === $beforeItems,
        'a bad-coupon rejection leaves zero new rows across all three tables (full rollback, matches the out-of-stock rejection shape)'
    );

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce CheckoutService test passed.\n");
        fwrite(STDOUT, "Validated a successful order's 3-table write with correct linkage, full-rollback rejection for an empty cart/out-of-stock line/bad coupon, and correct discount+shipping+tax computation.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
