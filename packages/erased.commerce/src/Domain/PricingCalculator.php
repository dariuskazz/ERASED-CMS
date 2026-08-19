<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

/**
 * A single flat, global tax rate and a single flat/free-threshold shipping
 * fee - explicitly not real jurisdiction/nexus-aware tax computation or a
 * real shipping-carrier integration, since checkout collects no shipping
 * address to compute either against. Tax rate is stored in basis points
 * (2100 = 21%), not whole percent, to represent fractional VAT rates
 * without floats. Deliberately does not read setting() itself, so it stays
 * plain-constructor testable like every other domain class in this package
 * - the caller reads settings and passes plain values in.
 */
final class PricingCalculator
{
    public function __construct(
        private readonly int $taxRateBps,
        private readonly int $shippingFlatMinor,
        private readonly ?int $shippingFreeThresholdMinor,
    ) {
    }

    /** @return array{shipping_minor:int,tax_minor:int,total_minor:int} */
    public function calculate(int $subtotalMinor, int $discountMinor): array
    {
        $discounted = max(0, $subtotalMinor - $discountMinor);

        $shippingMinor = $this->shippingFlatMinor;
        if ($this->shippingFreeThresholdMinor !== null && $discounted >= $this->shippingFreeThresholdMinor) {
            $shippingMinor = 0;
        }

        $taxMinor = (int)round($discounted * $this->taxRateBps / 10000);

        return [
            'shipping_minor' => $shippingMinor,
            'tax_minor' => $taxMinor,
            'total_minor' => $discounted + $shippingMinor + $taxMinor,
        ];
    }
}
