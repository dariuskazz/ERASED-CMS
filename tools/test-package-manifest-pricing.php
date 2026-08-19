<?php
declare(strict_types=1);

use Erased\Packages\PackageManifest;

$root = dirname(__DIR__);
require_once $root.'/app/Packages/PackageManifest.php';

function pricing_test_manifest_data(array $overrides = []): array
{
    return array_merge([
        'id' => 'erased.pricing-test',
        'type' => 'module',
        'name' => 'Pricing Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Manifest pricing/marketplace field test.',
    ], $overrides);
}

function assert_throws(callable $fn, string $expectedSubstring, string $label): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), $expectedSubstring)) {
            throw new RuntimeException("{$label}: threw, but message '{$error->getMessage()}' did not contain '{$expectedSubstring}'.");
        }
        return;
    }
    throw new RuntimeException("{$label}: expected InvalidArgumentException, none was thrown.");
}

try {
    // --- Absent pricing must default to free, fully backward compatible ---
    $noPricing = new PackageManifest(pricing_test_manifest_data());
    if ($noPricing->pricingModel() !== 'free') {
        throw new RuntimeException('Manifest with no pricing field did not default to free.');
    }
    if ($noPricing->isPaid() !== false) {
        throw new RuntimeException('Manifest with no pricing field reported isPaid() === true.');
    }
    if ($noPricing->priceMinor() !== null || $noPricing->priceCurrency() !== null) {
        throw new RuntimeException('Manifest with no pricing field returned non-null price accessors.');
    }
    if ($noPricing->marketplace() !== []) {
        throw new RuntimeException('Manifest with no marketplace field returned non-empty marketplace().');
    }

    // --- Explicit free model behaves the same as absent ---
    $explicitFree = new PackageManifest(pricing_test_manifest_data(['pricing' => ['model' => 'free']]));
    if ($explicitFree->isPaid() !== false) {
        throw new RuntimeException('Explicit pricing.model=free reported isPaid() === true.');
    }

    // --- Valid paid manifest round-trips ---
    $paid = new PackageManifest(pricing_test_manifest_data([
        'pricing' => ['model' => 'paid', 'price_minor' => 4900, 'currency' => 'EUR'],
        'marketplace' => ['homepage_url' => 'https://example.test', 'support_url' => 'https://example.test/support', 'tags' => ['commerce', 'payments']],
    ]));
    if ($paid->isPaid() !== true) {
        throw new RuntimeException('Valid paid manifest did not report isPaid() === true.');
    }
    if ($paid->priceMinor() !== 4900) {
        throw new RuntimeException('Valid paid manifest priceMinor() did not round-trip.');
    }
    if ($paid->priceCurrency() !== 'EUR') {
        throw new RuntimeException('Valid paid manifest priceCurrency() did not round-trip.');
    }
    $marketplace = $paid->marketplace();
    if (($marketplace['homepage_url'] ?? null) !== 'https://example.test' || ($marketplace['tags'] ?? null) !== ['commerce', 'payments']) {
        throw new RuntimeException('marketplace() did not round-trip the declared fields.');
    }

    // --- Invalid shapes must throw ---
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['pricing' => 'not-an-array'])),
        "'pricing' must be an array",
        'non-array pricing',
    );
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['pricing' => ['model' => 'bogus']])),
        "'pricing.model' must be",
        'invalid pricing.model',
    );
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['pricing' => ['model' => 'paid', 'currency' => 'EUR']])),
        "'pricing.price_minor'",
        'missing price_minor',
    );
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['pricing' => ['model' => 'paid', 'price_minor' => -1, 'currency' => 'EUR']])),
        "'pricing.price_minor'",
        'negative price_minor',
    );
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['pricing' => ['model' => 'paid', 'price_minor' => 100, 'currency' => 'eur']])),
        "'pricing.currency'",
        'lowercase currency',
    );
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['pricing' => ['model' => 'paid', 'price_minor' => 100, 'currency' => 'EURO']])),
        "'pricing.currency'",
        '4-letter currency',
    );
    assert_throws(
        fn() => new PackageManifest(pricing_test_manifest_data(['marketplace' => 'not-an-array'])),
        "'marketplace' must be an array",
        'non-array marketplace',
    );

    fwrite(STDOUT, "Package manifest pricing/marketplace smoke test passed.\n");
    fwrite(STDOUT, "Validated backward-compatible defaults, valid paid manifest round-trip, and 7 invalid-shape rejections.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
