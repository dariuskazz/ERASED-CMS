<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/ProductRepository.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/ProductImageRepository.php';
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
use ErasedCommerce\Domain\ProductImageRepository;
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
    $pdo->exec('CREATE TABLE media (
        id INTEGER PRIMARY KEY AUTOINCREMENT, original_name TEXT NOT NULL, stored_name TEXT NOT NULL UNIQUE,
        mime_type TEXT NOT NULL, size_bytes INTEGER NOT NULL, width INTEGER NULL, height INTEGER NULL,
        has_thumb INTEGER NOT NULL DEFAULT 0, alt_text TEXT NOT NULL DEFAULT \'\', caption TEXT NULL,
        uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE commerce_product_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, media_id INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $products = new ProductRepository($pdo);
    $coupons = new CouponRepository($pdo);
    $pricing = new PricingCalculator(0, 0, null);
    $orders = new OrderRepository($pdo, $products, $coupons);
    $images = new ProductImageRepository($pdo);

    // ---- category_id persists through create/update (real category tree tested separately in test-commerce-category-repository.php) ----
    $mugId = $products->create(['name' => 'Mug', 'slug' => 'mug', 'price_minor' => 1000, 'currency' => 'EUR', 'status' => 'published', 'category_id' => 10]);
    $mug = $products->find($mugId);
    $check((int)$mug['category_id'] === 10, 'create() persists category_id');
    $products->update($mugId, ['name' => 'Mug', 'slug' => 'mug', 'price_minor' => 1000, 'currency' => 'EUR', 'status' => 'published', 'category_id' => 11]);
    $check((int)$products->find($mugId)['category_id'] === 11, 'update() overwrites category_id');

    // ---- publishedInCategory() including descendants, and no-arg published() backward compatibility ----
    $products->create(['name' => 'Plate', 'slug' => 'plate', 'price_minor' => 800, 'currency' => 'EUR', 'status' => 'published', 'category_id' => 11]);
    $products->create(['name' => 'Novel', 'slug' => 'novel', 'price_minor' => 1500, 'currency' => 'EUR', 'status' => 'published', 'category_id' => 20]);
    $products->create(['name' => 'Draft Item', 'slug' => 'draft-item', 'price_minor' => 500, 'currency' => 'EUR', 'status' => 'draft', 'category_id' => 11]);

    $check(count($products->published()) === 3, 'published() with no arguments returns every published product regardless of category');
    $check(count($products->publishedInCategory(11)) === 2, 'publishedInCategory(id) filters to that category only, excluding drafts');
    $check(count($products->publishedInCategory(10, [11])) === 2, 'publishedInCategory(id, descendantIds) includes descendant categories too');
    $names = array_column($products->publishedInCategory(11), 'name');
    $check(in_array('Mug', $names, true) && in_array('Plate', $names, true), 'publishedInCategory() returns the right products');
    $check($products->publishedInCategory(999) === [], 'a category with no products returns an empty list, not an error');

    // ---- ProductImageRepository round-trip ----
    $pdo->exec("INSERT INTO media (original_name, stored_name, mime_type, size_bytes, alt_text) VALUES ('mug.jpg','abc.jpg','image/jpeg',1000,'A mug')");
    $mediaId = (int)$pdo->lastInsertId();
    $imageId = $images->attach($mugId, $mediaId);
    $check($images->find($imageId) !== null, 'attach() creates a findable row');
    $forProduct = $images->forProduct($mugId);
    $check(count($forProduct) === 1 && $forProduct[0]['stored_name'] === 'abc.jpg' && $forProduct[0]['alt_text'] === 'A mug', 'forProduct() joins media fields (stored_name, alt_text) live');
    $images->delete($imageId);
    $check($images->find($imageId) === null, 'delete() removes the row');
    $check($images->forProduct($mugId) === [], 'forProduct() reflects the deletion');

    // ---- OrderRepository::activityByRange()/forDate() ----
    $_SESSION = ['commerce_cart' => [$mugId => 1]];
    $cart = new Cart($products);
    $checkout = new CheckoutService($pdo, $products, $coupons, $pricing);
    $placedToday = $checkout->submit($cart->lines(), 'Ada Lovelace', 'ada@example.com', 'EUR');
    $orders->markPaid((int)$placedToday['order_id']);

    $_SESSION['commerce_cart'] = [$mugId => 2];
    $cart2 = new Cart($products);
    $placedToday2 = $checkout->submit($cart2->lines(), 'Grace Hopper', 'grace@example.com', 'EUR');
    // leave this second one pending, to confirm it counts toward activity but not paid revenue

    $todayIso = date('Y-m-d');
    $activity = $orders->activityByRange($todayIso.' 00:00:00', $todayIso.' 23:59:59');
    $check(count($activity) === 1, 'activityByRange() buckets same-day orders into exactly one row');
    $todayRow = $activity[0];
    $check($todayRow['order_count'] === 2, 'activityByRange() counts every order regardless of status');
    $check($todayRow['paid_minor'] === 1000, 'activityByRange() sums paid_minor from paid orders only (the pending second order is excluded)');
    $check($todayRow['total_minor'] === 3000, 'activityByRange() sums total_minor across every order regardless of status (1000 + 2000)');

    $dateOrders = $orders->forDate($todayIso);
    $check(count($dateOrders) === 2, 'forDate() returns every order placed that day');

    $noActivity = $orders->activityByRange('2000-01-01 00:00:00', '2000-01-02 00:00:00');
    $check($noActivity === [], 'activityByRange() returns an empty list for a date range with no orders');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce Phase B test passed.\n");
        fwrite(STDOUT, "Validated category_id persistence, publishedInCategory()'s direct+descendant filtering (with no-arg published() backward compatibility), ProductImageRepository's live media join, and OrderRepository's day-bucketed activity/revenue split and date filter.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
