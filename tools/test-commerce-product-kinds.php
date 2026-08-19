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
    $pdo->exec('CREATE TABLE commerce_product_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, original_filename TEXT NOT NULL,
        stored_filename TEXT NOT NULL, size_bytes INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE commerce_downloads (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_item_id INTEGER NOT NULL, product_file_id INTEGER NOT NULL,
        token TEXT NOT NULL UNIQUE, download_count INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE commerce_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NULL, product_name TEXT NOT NULL,
        customer_email TEXT NOT NULL, order_id INTEGER NULL, status TEXT NOT NULL DEFAULT \'active\',
        interval_name TEXT NOT NULL DEFAULT \'month\', current_period_end TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $products = new ProductRepository($pdo);
    $coupons = new CouponRepository($pdo);
    $pricing = new PricingCalculator(0, 0, null);
    $orders = new OrderRepository($pdo, $products, $coupons);

    // ---- product_kind snapshot survives product deletion ----
    $digitalId = $products->create(['name' => 'Ebook', 'slug' => 'ebook', 'price_minor' => 1500, 'currency' => 'EUR', 'status' => 'published', 'track_inventory' => false, 'kind' => 'digital']);
    $pdo->exec('INSERT INTO commerce_product_files (product_id, original_filename, stored_filename, size_bytes) VALUES ('.$digitalId.', \'book.pdf\', \'abc123.pdf\', 1024)');
    $pdo->exec('INSERT INTO commerce_product_files (product_id, original_filename, stored_filename, size_bytes) VALUES ('.$digitalId.', \'bonus.pdf\', \'def456.pdf\', 2048)');

    $_SESSION = ['commerce_cart' => [$digitalId => 1]];
    $cart = new Cart($products);
    $checkout = new CheckoutService($pdo, $products, $coupons, $pricing);
    $placed = $checkout->submit($cart->lines(), 'Ada Lovelace', 'ada@example.com', 'EUR');

    $item = $orders->items((int)$placed['order_id'])[0];
    $check($item['product_kind'] === 'digital', 'CheckoutService snapshots product_kind onto commerce_order_items');

    $products->delete($digitalId);
    $itemAfterDelete = $orders->items((int)$placed['order_id'])[0];
    $check($itemAfterDelete['product_kind'] === 'digital', 'snapshotted product_kind survives the product being deleted');

    // ---- markPaid() issues one download token per attached file, and is idempotent ----
    $digitalId2 = $products->create(['name' => 'Ebook 2', 'slug' => 'ebook-2', 'price_minor' => 1000, 'currency' => 'EUR', 'status' => 'published', 'track_inventory' => false, 'kind' => 'digital']);
    $pdo->exec('INSERT INTO commerce_product_files (product_id, original_filename, stored_filename, size_bytes) VALUES ('.$digitalId2.', \'a.pdf\', \'aaa.pdf\', 10)');
    $pdo->exec('INSERT INTO commerce_product_files (product_id, original_filename, stored_filename, size_bytes) VALUES ('.$digitalId2.', \'b.pdf\', \'bbb.pdf\', 20)');
    $_SESSION['commerce_cart'] = [$digitalId2 => 1];
    $cart2 = new Cart($products);
    $placed2 = $checkout->submit($cart2->lines(), 'Grace Hopper', 'grace@example.com', 'EUR');
    $item2Id = (int)$orders->items((int)$placed2['order_id'])[0]['id'];

    // Pre-seed a download row for one of the two files, simulating a prior partial run - markPaid() must not duplicate it.
    $file1Id = (int)$pdo->query("SELECT id FROM commerce_product_files WHERE stored_filename='aaa.pdf'")->fetchColumn();
    $pdo->prepare('INSERT INTO commerce_downloads (order_item_id, product_file_id, token) VALUES (?, ?, ?)')->execute([$item2Id, $file1Id, str_repeat('a', 40)]);

    $orders->markPaid((int)$placed2['order_id']);
    $downloadCount = (int)$pdo->query('SELECT COUNT(*) FROM commerce_downloads WHERE order_item_id='.$item2Id)->fetchColumn();
    $check($downloadCount === 2, 'markPaid() issues exactly one download row per attached file (pre-existing row not duplicated)');
    $preSeededToken = $pdo->query('SELECT token FROM commerce_downloads WHERE order_item_id='.$item2Id.' AND product_file_id='.$file1Id)->fetchColumn();
    $check($preSeededToken === str_repeat('a', 40), 'markPaid() does not overwrite an already-issued download token');

    // ---- markPaid() subscription: creates on first paid order, extends (not duplicates) on renewal ----
    $subId = $products->create(['name' => 'Pro Plan', 'slug' => 'pro-plan', 'price_minor' => 500, 'currency' => 'EUR', 'status' => 'published', 'track_inventory' => false, 'kind' => 'subscription', 'subscription_interval' => 'month']);
    $_SESSION['commerce_cart'] = [$subId => 1];
    $cart3 = new Cart($products);
    $placedSub1 = $checkout->submit($cart3->lines(), 'Alan Turing', 'alan@example.com', 'EUR');
    $orders->markPaid((int)$placedSub1['order_id']);

    $subCount = (int)$pdo->query("SELECT COUNT(*) FROM commerce_subscriptions WHERE customer_email='alan@example.com'")->fetchColumn();
    $check($subCount === 1, 'markPaid() creates exactly one commerce_subscriptions row for a new subscriber');
    $firstPeriodEnd = $pdo->query("SELECT current_period_end FROM commerce_subscriptions WHERE customer_email='alan@example.com'")->fetchColumn();
    $check(is_string($firstPeriodEnd) && $firstPeriodEnd !== '', 'the new subscription row has a current_period_end');

    // A renewal order (same product, same email) must extend the existing row, not create a second one.
    $_SESSION['commerce_cart'] = [$subId => 1];
    $cart4 = new Cart($products);
    $placedSub2 = $checkout->submit($cart4->lines(), 'Alan Turing', 'alan@example.com', 'EUR');
    $orders->markPaid((int)$placedSub2['order_id']);

    $subCountAfterRenewal = (int)$pdo->query("SELECT COUNT(*) FROM commerce_subscriptions WHERE customer_email='alan@example.com'")->fetchColumn();
    $check($subCountAfterRenewal === 1, 'a renewal order extends the existing active subscription instead of creating a second row');
    $renewedOrderId = (int)$pdo->query("SELECT order_id FROM commerce_subscriptions WHERE customer_email='alan@example.com'")->fetchColumn();
    $check($renewedOrderId === (int)$placedSub2['order_id'], 'the subscription row now points at the renewal order, not the original one');

    // ---- markPaid() subscription for a since-deleted product must not crash, defaults to month ----
    $subId2 = $products->create(['name' => 'Temp Plan', 'slug' => 'temp-plan', 'price_minor' => 300, 'currency' => 'EUR', 'status' => 'published', 'track_inventory' => false, 'kind' => 'subscription', 'subscription_interval' => 'year']);
    $_SESSION['commerce_cart'] = [$subId2 => 1];
    $cart5 = new Cart($products);
    $placedSub3 = $checkout->submit($cart5->lines(), 'Katherine Johnson', 'katherine@example.com', 'EUR');
    $products->delete($subId2);
    $noCrash = true;
    try {
        $orders->markPaid((int)$placedSub3['order_id']);
    } catch (Throwable $e) {
        $noCrash = false;
    }
    $check($noCrash, 'markPaid() does not crash creating a subscription for a product deleted after the order was placed');
    $fallbackInterval = $pdo->query("SELECT interval_name FROM commerce_subscriptions WHERE customer_email='katherine@example.com'")->fetchColumn();
    $check($fallbackInterval === 'month', 'a deleted product falls back to a monthly interval rather than failing');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce product-kinds test passed.\n");
        fwrite(STDOUT, "Validated product_kind snapshotting, digital download token issuance and idempotency, and subscription create-vs-extend fulfillment at mark-paid time.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
