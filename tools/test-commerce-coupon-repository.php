<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/CouponRepository.php';

use ErasedCommerce\Domain\CouponRepository;

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
    $pdo->exec('CREATE TABLE commerce_coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, type TEXT NOT NULL, value INTEGER NOT NULL,
        max_uses INTEGER NULL, used_count INTEGER NOT NULL DEFAULT 0, starts_at TEXT NULL, expires_at TEXT NULL,
        active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $coupons = new CouponRepository($pdo);

    // ---- CRUD ----
    $id = $coupons->create(['code' => 'welcome10', 'type' => 'percent', 'value' => 10, 'active' => true]);
    $check($id > 0, 'create() returns a new id');
    $found = $coupons->find($id);
    $check($found !== null && $found['code'] === 'WELCOME10', 'create() normalizes (uppercases) the code, find() returns it');

    $coupons->update($id, ['code' => 'welcome10', 'type' => 'fixed', 'value' => 500, 'active' => true]);
    $updated = $coupons->find($id);
    $check($updated['type'] === 'fixed' && (int)$updated['value'] === 500, 'update() changes type and value');

    $check(count($coupons->all()) === 1, 'all() returns every coupon');

    // ---- findValidByCode(): active/inactive ----
    $activeId = $coupons->create(['code' => 'ACTIVE', 'type' => 'percent', 'value' => 5, 'active' => true]);
    $inactiveId = $coupons->create(['code' => 'INACTIVE', 'type' => 'percent', 'value' => 5, 'active' => false]);
    $check($coupons->findValidByCode('active') !== null, 'findValidByCode() is case-insensitive and finds an active coupon');
    $check($coupons->findValidByCode('INACTIVE') === null, 'findValidByCode() excludes an inactive coupon');
    $check($coupons->findValidByCode('DOES-NOT-EXIST') === null, 'findValidByCode() returns null for a nonexistent code');
    $check($coupons->findValidByCode('') === null, 'findValidByCode() returns null for a blank code');

    // ---- findValidByCode(): date window ----
    $yesterday = (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s');
    $tomorrow = (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s');
    $notYetId = $coupons->create(['code' => 'FUTURE', 'type' => 'percent', 'value' => 5, 'active' => true, 'starts_at' => $tomorrow]);
    $check($coupons->findValidByCode('FUTURE') === null, 'findValidByCode() excludes a coupon that has not started yet');
    $expiredId = $coupons->create(['code' => 'EXPIRED', 'type' => 'percent', 'value' => 5, 'active' => true, 'expires_at' => $yesterday]);
    $check($coupons->findValidByCode('EXPIRED') === null, 'findValidByCode() excludes an expired coupon');
    $liveWindowId = $coupons->create(['code' => 'LIVEWINDOW', 'type' => 'percent', 'value' => 5, 'active' => true, 'starts_at' => $yesterday, 'expires_at' => $tomorrow]);
    $check($coupons->findValidByCode('LIVEWINDOW') !== null, 'findValidByCode() accepts a coupon within its start/expiry window');

    // ---- findValidByCode(): usage cap ----
    $cappedId = $coupons->create(['code' => 'CAPPED', 'type' => 'percent', 'value' => 5, 'active' => true, 'max_uses' => 2]);
    $check($coupons->findValidByCode('CAPPED') !== null, 'findValidByCode() accepts a coupon under its usage cap');
    $coupons->incrementUsage($cappedId);
    $coupons->incrementUsage($cappedId);
    $check($coupons->findValidByCode('CAPPED') === null, 'findValidByCode() excludes a coupon once used_count reaches max_uses');
    $unlimitedId = $coupons->create(['code' => 'UNLIMITED', 'type' => 'percent', 'value' => 5, 'active' => true]);
    for ($i = 0; $i < 50; $i++) {
        $coupons->incrementUsage($unlimitedId);
    }
    $check($coupons->findValidByCode('UNLIMITED') !== null, 'a null max_uses means unlimited usage, never excluded');

    // ---- discountFor() ----
    $percentCoupon = ['type' => 'percent', 'value' => 10];
    $check($coupons->discountFor($percentCoupon, 1000) === 100, 'discountFor() computes 10% of 1000 as 100');
    $check($coupons->discountFor($percentCoupon, 999) === 100, 'discountFor() rounds the percent computation');
    $fixedCoupon = ['type' => 'fixed', 'value' => 500];
    $check($coupons->discountFor($fixedCoupon, 1000) === 500, 'discountFor() applies the full fixed amount when it fits');
    $check($coupons->discountFor($fixedCoupon, 300) === 300, 'discountFor() caps a fixed discount at the subtotal - never a negative total');

    // ---- delete() ----
    $coupons->delete($id);
    $check($coupons->find($id) === null, 'delete() removes the coupon');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce CouponRepository test passed.\n");
        fwrite(STDOUT, "Validated CRUD, findValidByCode()'s active/date-window/usage-cap filtering (bound to a live PHP timestamp, not NOW()), and discountFor()'s percent/fixed math including the fixed-capped-at-subtotal case.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
