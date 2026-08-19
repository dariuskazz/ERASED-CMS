<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/ProductRepository.php';
require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/Cart.php';

use ErasedCommerce\Domain\Cart;
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

    $repo = new ProductRepository($pdo);
    $widgetId = $repo->create(['name' => 'Widget', 'slug' => 'widget', 'price_minor' => 1000, 'currency' => 'EUR', 'stock_quantity' => 10, 'status' => 'published']);
    $gadgetId = $repo->create(['name' => 'Gadget', 'slug' => 'gadget', 'price_minor' => 2500, 'currency' => 'EUR', 'stock_quantity' => 5, 'status' => 'published']);
    $draftId = $repo->create(['name' => 'Draft Item', 'slug' => 'draft-item', 'price_minor' => 500, 'currency' => 'EUR', 'stock_quantity' => 5, 'status' => 'draft']);

    $_SESSION = [];
    $cart = new Cart($repo);

    $check($cart->isEmpty() === true, 'a fresh cart is empty');

    $cart->add($widgetId, 2);
    $check($cart->isEmpty() === false, 'cart is non-empty after add()');
    $lines = $cart->lines();
    $check(count($lines) === 1 && $lines[0]['quantity'] === 2, 'add() records the correct quantity');
    $check($lines[0]['line_total_minor'] === 2000, 'line_total_minor is price * quantity');

    $cart->add($widgetId, 3);
    $check($cart->lines()[0]['quantity'] === 5, 'a second add() for the same product accumulates quantity');

    $cart->add($gadgetId, 1);
    $check(count($cart->lines()) === 2, 'a different product becomes its own line');
    $check($cart->subtotalMinor() === (5 * 1000 + 1 * 2500), 'subtotalMinor() sums every line correctly');

    $cart->setQuantity($widgetId, 1);
    $widgetLine = null;
    foreach ($cart->lines() as $line) {
        if ((int)$line['product']['id'] === $widgetId) {
            $widgetLine = $line;
        }
    }
    $check($widgetLine !== null && $widgetLine['quantity'] === 1, 'setQuantity() overwrites (not adds to) the existing quantity');

    $cart->setQuantity($widgetId, 0);
    $check(count($cart->lines()) === 1, 'setQuantity() to zero removes the line entirely');

    $cart->remove($gadgetId);
    $check($cart->isEmpty() === true, 'remove() empties the cart of its last line');

    // Stale-line dropping: a draft (unpublished) product in the cart is
    // silently excluded from lines() and cleaned out of the session.
    $_SESSION['commerce_cart'] = [$draftId => 1];
    $cart2 = new Cart($repo);
    $check($cart2->lines() === [], 'lines() drops a cart entry for an unpublished product');
    $check(!isset($_SESSION['commerce_cart'][$draftId]), 'the stale unpublished-product entry is removed from the session');

    // Stale-line dropping: a deleted product.
    $_SESSION['commerce_cart'] = [999999 => 2];
    $cart3 = new Cart($repo);
    $check($cart3->lines() === [], 'lines() drops a cart entry for a product id that no longer exists');

    // ---- Coupon code storage ----
    $check($cart->couponCode() === null, 'a fresh cart has no coupon code');
    $cart->setCouponCode('save10');
    $check($cart->couponCode() === 'SAVE10', 'setCouponCode() normalizes (trims + uppercases) the stored code');
    $cart->setCouponCode('  ');
    $check($cart->couponCode() === null, 'setCouponCode() with a blank string clears the coupon');
    $cart->setCouponCode('WELCOME5');
    $cart->setCouponCode(null);
    $check($cart->couponCode() === null, 'setCouponCode(null) clears the coupon');

    // ---- clear() must also reset the coupon, not just the cart lines ----
    // (regression: a coupon that silently survived clear() would carry over
    // into the customer's next shopping session, applied without them
    // re-entering it.)
    $cart->add($widgetId, 1);
    $cart->setCouponCode('SAVE10');
    $cart->clear();
    $check($cart->isEmpty() === true, 'clear() empties the cart');
    $check($cart->couponCode() === null, 'clear() also clears any applied coupon code');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce Cart test passed.\n");
        fwrite(STDOUT, "Validated add/accumulate, setQuantity overwrite and zero-removal, remove, subtotal calculation, stale-line dropping for unpublished/deleted products, coupon-code storage/normalization, and clear() resetting the coupon too.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
