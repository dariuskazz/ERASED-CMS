<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

/**
 * Session-based guest cart - no persistent cart-token/cookie pattern exists
 * anywhere in this codebase to imitate instead, and the native PHP session
 * is already the established idiom for CSRF/flash/login state. Line prices
 * are never cached: lines() always re-fetches the live product row, so a
 * price change or a product going unpublished/deleted between add-to-cart
 * and checkout is reflected automatically, with stale lines silently
 * dropped from the cart rather than left dangling.
 */
final class Cart
{
    private const SESSION_KEY = 'commerce_cart';
    private const COUPON_SESSION_KEY = 'commerce_cart_coupon';

    public function __construct(private readonly ProductRepository $products)
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    public function add(int $productId, int $quantity = 1): void
    {
        if ($productId <= 0 || $quantity <= 0) {
            return;
        }
        $current = (int)($_SESSION[self::SESSION_KEY][$productId] ?? 0);
        $_SESSION[self::SESSION_KEY][$productId] = $current + $quantity;
    }

    public function setQuantity(int $productId, int $quantity): void
    {
        if ($productId <= 0) {
            return;
        }
        if ($quantity <= 0) {
            $this->remove($productId);
            return;
        }
        $_SESSION[self::SESSION_KEY][$productId] = $quantity;
    }

    public function remove(int $productId): void
    {
        unset($_SESSION[self::SESSION_KEY][$productId]);
    }

    public function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
        unset($_SESSION[self::COUPON_SESSION_KEY]);
    }

    public function setCouponCode(?string $code): void
    {
        if ($code === null || trim($code) === '') {
            unset($_SESSION[self::COUPON_SESSION_KEY]);
            return;
        }
        $_SESSION[self::COUPON_SESSION_KEY] = strtoupper(trim($code));
    }

    public function couponCode(): ?string
    {
        $code = $_SESSION[self::COUPON_SESSION_KEY] ?? null;
        return is_string($code) && $code !== '' ? $code : null;
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /** @return list<array{product:array<string,mixed>,quantity:int,line_total_minor:int}> */
    public function lines(): array
    {
        $lines = [];
        $stale = [];
        foreach ($_SESSION[self::SESSION_KEY] as $productId => $quantity) {
            $product = $this->products->find((int)$productId);
            if ($product === null || $product['status'] !== 'published' || (int)$quantity <= 0) {
                $stale[] = $productId;
                continue;
            }
            $quantity = (int)$quantity;
            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'line_total_minor' => (int)$product['price_minor'] * $quantity,
            ];
        }
        foreach ($stale as $productId) {
            unset($_SESSION[self::SESSION_KEY][$productId]);
        }
        return $lines;
    }

    public function subtotalMinor(): int
    {
        $total = 0;
        foreach ($this->lines() as $line) {
            $total += $line['line_total_minor'];
        }
        return $total;
    }
}
