<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/PricingCalculator.php';

use ErasedCommerce\Domain\PricingCalculator;

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
    // ---- Zero tax, zero shipping: total equals the discounted subtotal ----
    $free = new PricingCalculator(0, 0, null);
    $result = $free->calculate(1000, 0);
    $check($result['shipping_minor'] === 0 && $result['tax_minor'] === 0 && $result['total_minor'] === 1000, 'zero tax/shipping leaves the subtotal untouched');

    // ---- Flat shipping is added on top ----
    $flatShipping = new PricingCalculator(0, 500, null);
    $result = $flatShipping->calculate(1000, 0);
    $check($result['shipping_minor'] === 500 && $result['total_minor'] === 1500, 'a flat shipping fee is added to the total');

    // ---- Free-shipping threshold waives the flat fee once reached ----
    $withThreshold = new PricingCalculator(0, 500, 2000);
    $below = $withThreshold->calculate(1000, 0);
    $check($below['shipping_minor'] === 500, 'shipping still applies below the free-shipping threshold');
    $atThreshold = $withThreshold->calculate(2000, 0);
    $check($atThreshold['shipping_minor'] === 0, 'shipping is waived exactly at the free-shipping threshold');
    $above = $withThreshold->calculate(3000, 0);
    $check($above['shipping_minor'] === 0, 'shipping is waived above the free-shipping threshold');

    // ---- The threshold is checked against the discounted subtotal, not the raw subtotal ----
    $discountAware = new PricingCalculator(0, 500, 2000);
    $result = $discountAware->calculate(2500, 600); // discounted = 1900, below the 2000 threshold
    $check($result['shipping_minor'] === 500, 'the free-shipping threshold is evaluated after discount, not before it');

    // ---- Tax is computed on the discounted subtotal in basis points ----
    $taxed = new PricingCalculator(2100, 0, null); // 21%
    $result = $taxed->calculate(1000, 0);
    $check($result['tax_minor'] === 210, '2100 bps (21%) of 1000 is 210');
    $result = $taxed->calculate(1000, 100); // discounted to 900
    $check($result['tax_minor'] === 189, 'tax is computed on the discounted subtotal (21% of 900 = 189), not the raw subtotal');

    // ---- Tax rounds to the nearest minor unit ----
    $oddRate = new PricingCalculator(875, 0, null); // 8.75%
    $result = $oddRate->calculate(999, 0);
    $check($result['tax_minor'] === (int)round(999 * 875 / 10000), 'tax rounds fractional basis-point results to the nearest minor unit');

    // ---- Total sums discounted subtotal + shipping + tax, discount never produces a negative subtotal ----
    $combined = new PricingCalculator(2000, 300, null); // 20% tax, 3.00 flat shipping
    $result = $combined->calculate(1000, 1000); // fully discounted subtotal (e.g. a 100%-off coupon)
    $check($result['tax_minor'] === 0 && $result['total_minor'] === 300, 'a fully-discounted subtotal never goes negative and still gets shipping/tax applied to zero');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce PricingCalculator test passed.\n");
        fwrite(STDOUT, "Validated flat shipping, free-shipping-threshold waiving (evaluated post-discount), basis-point tax computation and rounding, and the never-negative discounted-subtotal floor.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
