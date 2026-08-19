<?php
declare(strict_types=1);

namespace ErasedCommerce\Storefront;

require_once __DIR__.'/../Domain/ProductRepository.php';
require_once __DIR__.'/../Domain/ProductFileRepository.php';
require_once __DIR__.'/../Domain/ProductImageRepository.php';
require_once __DIR__.'/../Domain/CategoryRepository.php';
require_once __DIR__.'/../Domain/ProductViewRepository.php';
require_once __DIR__.'/../Domain/Cart.php';
require_once __DIR__.'/../Domain/CouponRepository.php';
require_once __DIR__.'/../Domain/PricingCalculator.php';
require_once __DIR__.'/../Domain/CheckoutService.php';
require_once __DIR__.'/../Domain/OrderRepository.php';
require_once __DIR__.'/../Domain/ShopFrontConfig.php';

use ErasedCommerce\Domain\Cart;
use ErasedCommerce\Domain\CategoryRepository;
use ErasedCommerce\Domain\CheckoutService;
use ErasedCommerce\Domain\CouponRepository;
use ErasedCommerce\Domain\OrderRepository;
use ErasedCommerce\Domain\PricingCalculator;
use ErasedCommerce\Domain\ProductFileRepository;
use ErasedCommerce\Domain\ProductImageRepository;
use ErasedCommerce\Domain\ProductRepository;
use ErasedCommerce\Domain\ProductViewRepository;
use ErasedCommerce\Domain\ShopFrontConfig;
use finfo;
use RuntimeException;

/**
 * Owns everything under /shop, /cart, /checkout - resolved through the real
 * service container from one hardcoded dispatch stanza in public/index.php
 * (the Plugin API only covers admin routes today; a generic public-route
 * mechanism is real, separate platform work deferred until a second
 * package genuinely needs it - see docs/STATUS.md for the full reasoning).
 * dispatch() returns false only for a path shape it doesn't recognize at
 * all, letting it fall through to the real public 404; an unknown product
 * slug under a recognized shape renders the app's own 404 page instead.
 */
final class StorefrontRoute
{
    private readonly ProductRepository $products;
    private readonly Cart $cart;
    private readonly CouponRepository $coupons;
    private readonly ProductImageRepository $images;
    private readonly CategoryRepository $categories;
    private readonly ProductViewRepository $productViews;

    public function __construct()
    {
        $pdo = db();
        $this->products = new ProductRepository($pdo);
        $this->cart = new Cart($this->products);
        $this->coupons = new CouponRepository($pdo);
        $this->images = new ProductImageRepository($pdo);
        $this->categories = new CategoryRepository($pdo);
        $this->productViews = new ProductViewRepository($pdo);
    }

    private function pricing(): PricingCalculator
    {
        $threshold = trim(setting('commerce_shipping_free_threshold_minor', ''));
        return new PricingCalculator(
            (int)setting('commerce_tax_rate_bps', '0'),
            (int)setting('commerce_shipping_flat_minor', '0'),
            $threshold !== '' ? (int)$threshold : null,
        );
    }

    /** @return array{coupon:?array<string,mixed>,discount_minor:int,notice:?string} */
    private function resolveCoupon(int $subtotalMinor): array
    {
        $code = $this->cart->couponCode();
        if ($code === null) {
            return ['coupon' => null, 'discount_minor' => 0, 'notice' => null];
        }
        $coupon = $this->coupons->findValidByCode($code);
        if ($coupon === null) {
            return ['coupon' => null, 'discount_minor' => 0, 'notice' => "Coupon \"{$code}\" is no longer valid and was not applied."];
        }
        return ['coupon' => $coupon, 'discount_minor' => $this->coupons->discountFor($coupon, $subtotalMinor), 'notice' => null];
    }

    private function breakdownHtml(int $subtotalMinor, int $discountMinor, ?string $couponCode, int $shippingMinor, int $taxMinor, int $totalMinor, string $currency): string
    {
        $fmt = static fn(int $minor): string => number_format($minor / 100, 2).' '.e($currency);
        $html = '<p>'.e(tr('subtotal', 'site')).': '.$fmt($subtotalMinor).'</p>';
        if ($discountMinor > 0) {
            $html .= '<p>'.e(tr('discount', 'site')).($couponCode !== null ? ' ('.e($couponCode).')' : '').': -'.$fmt($discountMinor).'</p>';
        }
        $html .= '<p>'.e(tr('shipping', 'site')).': '.($shippingMinor > 0 ? $fmt($shippingMinor) : e(tr('free', 'site'))).'</p>';
        if ($taxMinor > 0) {
            $html .= '<p>'.e(tr('tax', 'site')).': '.$fmt($taxMinor).'</p>';
        }
        $html .= '<p><strong>'.e(tr('total', 'site')).': '.$fmt($totalMinor).'</strong></p>';
        return $html;
    }

    private function couponFormHtml(?string $appliedCode): string
    {
        if ($appliedCode !== null) {
            return '<form method="post" class="commerce-coupon-form"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="remove_coupon">'
                .'<span class="muted">'.e(tr('coupon_applied', 'site')).': <strong>'.e($appliedCode).'</strong></span>'
                .'<button class="btn ghost" type="submit">'.e(tr('remove', 'site')).'</button></form>';
        }
        return '<form method="post" class="commerce-coupon-form"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="apply_coupon">'
            .'<input name="coupon_code" placeholder="'.e(tr('coupon_code', 'site')).'">'
            .'<button class="btn ghost" type="submit">'.e(tr('apply_coupon', 'site')).'</button></form>';
    }

    public function dispatch(string $path): bool
    {
        if ($path === '/shop') {
            $this->listing();
            return true;
        }
        if (preg_match('#^/shop/category/([a-z0-9][a-z0-9-]*)$#', $path, $m) === 1) {
            $this->categoryPage($m[1]);
            return true;
        }
        if (preg_match('#^/shop/([a-z0-9][a-z0-9-]*)$#', $path, $m) === 1) {
            $this->detail($m[1]);
            return true;
        }
        if ($path === '/cart') {
            $this->cartView();
            return true;
        }
        if ($path === '/cart/add' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $this->cartAdd();
            return true;
        }
        if ($path === '/checkout') {
            $this->checkout();
            return true;
        }
        if (preg_match('#^/checkout/confirmation/(\d+)/([a-f0-9]{32})$#', $path, $m) === 1) {
            $this->confirmation((int)$m[1], $m[2]);
            return true;
        }
        if (preg_match('#^/commerce/download/(\d+)/([a-f0-9]{40})$#', $path, $m) === 1) {
            $this->download((int)$m[1], $m[2]);
            return true;
        }
        return false;
    }

    /**
     * Purchase-scoped digital delivery. Unlike media_stream_token()
     * (app/bootstrap.php) - a stable, non-expiring HMAC identical for every
     * visitor - this token is a random per-order-item secret stored in
     * commerce_downloads, only ever issued once the order is marked paid
     * (OrderRepository::markPaid()), so a guessed order item id alone never
     * grants access.
     */
    private function download(int $orderItemId, string $token): void
    {
        $pdo = db();
        $lookup = $pdo->prepare('SELECT * FROM commerce_downloads WHERE order_item_id=?');
        $lookup->execute([$orderItemId]);
        $download = null;
        foreach ($lookup->fetchAll() as $row) {
            if (hash_equals((string)$row['token'], $token)) {
                $download = $row;
                break;
            }
        }
        if ($download === null) {
            http_response_code(404);
            exit('Not found');
        }

        $files = new ProductFileRepository($pdo);
        $file = $files->find((int)$download['product_file_id']);
        if ($file === null) {
            http_response_code(404);
            exit('Not found');
        }
        $path = ProductFileRepository::directory().'/'.basename((string)$file['stored_filename']);
        if (!is_file($path)) {
            http_response_code(404);
            exit('Not found');
        }

        $pdo->prepare('UPDATE commerce_downloads SET download_count = download_count + 1 WHERE id=?')->execute([(int)$download['id']]);

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="'.addslashes(basename((string)$file['original_filename'])).'"');
        readfile($path);
        exit;
    }

    private function listing(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $filters = $this->readFilters();

        if ($query !== '') {
            $this->renderBrowse(null, $this->products->search($query), $filters, $query);
            return;
        }

        $products = $this->products->published($this->repositoryFilters($filters));
        $this->renderBrowse(null, $products, $filters, '');
    }

    private function categoryPage(string $slug): void
    {
        $category = $this->categories->findBySlug($slug);
        if ($category === null) {
            erased_public_not_found();
            return;
        }
        $filters = $this->readFilters();
        $descendantIds = $this->categories->descendantIds((int)$category['id']);
        $products = $this->products->publishedInCategory((int)$category['id'], $descendantIds, $this->repositoryFilters($filters));
        $this->renderBrowse($category, $products, $filters, '');
    }

    /** @return array{sort:string,in_stock:bool,featured:bool,min_price:?int,max_price:?int,brand:?string,view:string} */
    private function readFilters(): array
    {
        $minInput = trim((string)($_GET['min_price'] ?? ''));
        $maxInput = trim((string)($_GET['max_price'] ?? ''));
        $brand = trim((string)($_GET['brand'] ?? ''));
        return [
            'sort' => $this->validSort((string)($_GET['sort'] ?? 'featured')),
            'in_stock' => ($_GET['in_stock'] ?? '') === '1',
            'featured' => ($_GET['featured'] ?? '') === '1',
            'min_price' => $minInput !== '' && is_numeric($minInput) ? (int)round((float)$minInput * 100) : null,
            'max_price' => $maxInput !== '' && is_numeric($maxInput) ? (int)round((float)$maxInput * 100) : null,
            'brand' => $brand !== '' ? $brand : null,
            'view' => ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid',
        ];
    }

    /** @param array{sort:string,in_stock:bool,featured:bool,min_price:?int,max_price:?int,brand:?string,view:string} $filters */
    private function repositoryFilters(array $filters): array
    {
        return [
            'sort' => $filters['sort'],
            'in_stock_only' => $filters['in_stock'],
            'featured_only' => $filters['featured'],
            'min_price_minor' => $filters['min_price'],
            'max_price_minor' => $filters['max_price'],
            'brand' => $filters['brand'],
        ];
    }

    /**
     * Shared renderer for both /shop and every /shop/category/{slug} page -
     * the hero/trust-strip/category-rail/recommended-carousel are the
     * "landing" identity and only render for the un-filtered, un-searched
     * /shop root; the sidebar+grid "browsing" layer renders in both places,
     * exactly as the approved sketch showed both states in one continuous
     * page. $category is null on the /shop root.
     * @param array<string,mixed>|null $category
     * @param list<array<string,mixed>> $products
     * @param array{sort:string,in_stock:bool,featured:bool,min_price:?int,max_price:?int,brand:?string,view:string} $filters
     */
    private function renderBrowse(?array $category, array $products, array $filters, string $query): void
    {
        $isRoot = $category === null && $query === '';
        $actionPath = $category !== null ? '/shop/category/' . $category['slug'] : '/shop';

        $top = '';
        if ($isRoot) {
            $top .= ShopFrontConfig::get('shop_hero_enabled') === '1' ? $this->heroHtml() : '';
            $top .= ShopFrontConfig::get('shop_trust_enabled') === '1' ? $this->trustStripHtml() : '';
            $top .= ShopFrontConfig::get('shop_category_rail_enabled') === '1' ? $this->categoryRailHtml(null) : '';
            $top .= ShopFrontConfig::get('shop_recommended_enabled') === '1' ? $this->recommendedCarouselHtml() : '';
        } elseif ($category !== null) {
            $top .= $this->categoryRailHtml((int)$category['id']);
        }

        $heading = $category !== null ? (string)$category['name'] : ($query !== '' ? 'Search results' : 'All Products');
        $description = $category !== null ? (string)($category['description'] ?? '') : ($query !== '' ? 'for &ldquo;' . e($query) . '&rdquo; &middot; <a href="/shop">Clear search</a>' : '');

        $browse = '<div class="commerce-subnav-spacer"></div>'
            . $top
            . '<div class="wrap commerce-browse-wrap">'
            . '<div class="commerce-shop-layout">'
            . $this->sidebarFiltersHtml($category, $filters, $actionPath, $query)
            . '<div class="commerce-grid-col">'
            . $this->gridToolbarHtml($heading, $description, count($products), $filters, $actionPath, $query)
            . '<div class="commerce-product-grid' . ($filters['view'] === 'list' ? ' commerce-view-list' : '') . '">'
            . ($this->productGridHtml($products) ?: '<div class="card">No products found.</div>')
            . '</div></div></div></div>';

        $body = $this->subNavHtml($query) . $browse . $this->commerceStyles();
        layout($category !== null ? (string)$category['name'] : 'Shop', $body);
    }

    private function validSort(string $sort): string
    {
        return in_array($sort, ['featured', 'newest', 'price_asc', 'price_desc', 'name_asc'], true) ? $sort : 'featured';
    }

    /**
     * A second, sticky navigation bar rendered under the site's own main
     * header (not replacing it) - the site header/nav/search/language
     * switcher stay exactly as they are everywhere else on the site; this
     * bar is shop-specific chrome, matching the approved sketch's header
     * treatment (blurred sticky bar, pill search, cart badge).
     */
    private function subNavHtml(string $query): string
    {
        $topCategories = array_values(array_filter($this->categories->tree(), static fn ($row) => (int)$row['depth'] === 0));
        $quickLinks = '';
        foreach (array_slice($topCategories, 0, 5) as $row) {
            $quickLinks .= '<a href="/shop/category/' . e((string)$row['slug']) . '">' . e((string)$row['name']) . '</a>';
        }
        $cartCount = 0;
        foreach ($this->cart->lines() as $line) {
            $cartCount += (int)$line['quantity'];
        }

        return '<div class="commerce-subnav"><div class="wrap commerce-subnav-row">'
            . '<a class="commerce-subnav-brand" href="/shop"><span class="commerce-subnav-dot"></span>Shop</a>'
            . '<nav class="commerce-subnav-links">' . $quickLinks . '</nav>'
            . '<form class="commerce-subnav-search" method="get" action="/shop"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>'
            . '<input type="search" name="q" value="' . e($query) . '" placeholder="Search products, SKUs, part numbers...">'
            . '</form>'
            . '<a class="commerce-subnav-cart" href="/cart" aria-label="Cart"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>'
            . ($cartCount > 0 ? '<span class="commerce-cart-count">' . $cartCount . '</span>' : '') . '</a>'
            . '</div></div>';
    }

    /**
     * Every piece of copy here is admin-editable (Commerce Settings → Shop
     * Front) via ShopFrontConfig, matching the approved sketch's exact
     * layout/structure but with none of its copy hardcoded permanently -
     * "make all needed settings to manage this front page" was the second
     * half of the same request this section fulfills. Product/category
     * counts are always live; the third stat is free text since there is
     * no real ratings/orders-shipped system in this app to compute one
     * from, and fabricating one would mean showing fake data to visitors.
     */
    private function heroHtml(): string
    {
        $g = static fn (string $key): string => ShopFrontConfig::get($key);
        $headline = $g('shop_hero_headline');
        $emphasis = $g('shop_hero_headline_emphasis');
        if ($emphasis !== '' && str_contains($headline, $emphasis)) {
            $headline = str_replace($emphasis, '<em>' . e($emphasis) . '</em>', e($headline));
        } else {
            $headline = e($headline);
        }

        $productCount = count($this->products->published());
        $categoryCount = count($this->categories->all());
        $stat3Value = $g('shop_hero_stat3_value');
        $stat3Label = $g('shop_hero_stat3_label');

        $stats = '<div><b>' . number_format($productCount) . '</b><span>Products</span></div>'
            . '<div><b>' . number_format($categoryCount) . '</b><span>Categories</span></div>'
            . ($stat3Value !== '' ? '<div><b>' . e($stat3Value) . '</b><span>' . e($stat3Label) . '</span></div>' : '');

        $primaryText = $g('shop_hero_cta_primary_text');
        $primaryUrl = $g('shop_hero_cta_primary_url');
        $secondaryText = $g('shop_hero_cta_secondary_text');
        $secondaryUrl = $g('shop_hero_cta_secondary_url');
        $actions = '';
        if ($primaryText !== '') {
            $actions .= '<a class="commerce-btn commerce-btn-primary" href="' . e($primaryUrl !== '' ? $primaryUrl : '/shop') . '">' . e($primaryText) . '</a>';
        }
        if ($secondaryText !== '') {
            $actions .= '<a class="commerce-btn commerce-btn-ghost" href="' . e($secondaryUrl !== '' ? $secondaryUrl : '/shop') . '">' . e($secondaryText) . '</a>';
        }

        $eyebrow = $g('shop_hero_eyebrow');
        return '<div class="wrap"><div class="commerce-hero"><div class="commerce-hero-copy">'
            . ($eyebrow !== '' ? '<span class="commerce-hero-eyebrow">' . e($eyebrow) . '</span>' : '')
            . '<h1>' . $headline . '</h1>'
            . '<p>' . e($g('shop_hero_description')) . '</p>'
            . ($actions !== '' ? '<div class="commerce-hero-actions">' . $actions . '</div>' : '')
            . '<div class="commerce-hero-stats">' . $stats . '</div>'
            . '</div></div></div>';
    }

    private function trustStripHtml(): string
    {
        $icons = [
            'shipping' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h15v13H3z"/><path d="M16 8h4l3 3v5h-7"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="18.5" cy="18.5" r="1.5"/></svg>',
            'secure' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'returns' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>',
            'stock' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>',
        ];
        $items = '';
        for ($i = 1; $i <= 4; $i++) {
            $iconKey = ShopFrontConfig::get('shop_trust_' . $i . '_icon');
            $title = ShopFrontConfig::get('shop_trust_' . $i . '_title');
            $subtitle = ShopFrontConfig::get('shop_trust_' . $i . '_subtitle');
            if ($title === '') {
                continue;
            }
            $items .= '<div>' . ($icons[$iconKey] ?? $icons['stock']) . '<div><b>' . e($title) . '</b><span>' . e($subtitle) . '</span></div></div>';
        }
        if ($items === '') {
            return '';
        }
        return '<div class="wrap"><div class="commerce-trust">' . $items . '</div></div>';
    }

    /**
     * Real categories, real counts, and an admin-settable emoji icon
     * (falls back to the first letter, matching this package's existing
     * convention elsewhere) - matches the sketch's icon-pill rail exactly,
     * just never hardcoding fake category names.
     */
    private function categoryRailHtml(?int $activeCategoryId): string
    {
        $topLevel = array_values(array_filter($this->categories->tree(), static fn ($row) => (int)$row['depth'] === 0));
        if ($topLevel === []) {
            return '';
        }
        $counts = $this->categories->productCounts();
        $pills = '<a class="commerce-cat-pill' . ($activeCategoryId === null ? ' is-active' : '') . '" href="/shop">All</a>';
        foreach ($topLevel as $category) {
            $count = $counts[(int)$category['id']] ?? 0;
            $active = $activeCategoryId === (int)$category['id'] ? ' is-active' : '';
            $pills .= '<a class="commerce-cat-pill' . $active . '" href="/shop/category/' . e((string)$category['slug']) . '">'
                . e((string)$category['name']) . ' <span class="commerce-cat-count">' . $count . '</span></a>';
        }
        return '<div class="wrap"><div class="commerce-rail-section"><div class="commerce-section-head"><h2>Shop by category</h2></div>'
            . '<div class="commerce-rail-wrap">'
            . '<button type="button" class="commerce-rail-arrow commerce-rail-prev" aria-label="Scroll categories left"><svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><polygon points="10,0 0,5 10,10"/></svg></button>'
            . '<div class="commerce-cat-rail">' . $pills . '</div>'
            . '<button type="button" class="commerce-rail-arrow commerce-rail-next" aria-label="Scroll categories right"><svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><polygon points="0,0 10,5 0,10"/></svg></button>'
            . '</div></div></div>';
    }

    /**
     * The shop homepage's "Recommended" section - the first ~2 rows,
     * rendered as a horizontal-scroll carousel (per the approved sketch)
     * rather than a grid section, pulled from ProductRepository::
     * recommended() so it's never empty before an admin has flagged
     * anything as Featured.
     */
    private function recommendedCarouselHtml(): string
    {
        $products = $this->products->recommended(8);
        if ($products === []) {
            return '';
        }
        $cards = '';
        foreach ($products as $product) {
            $cards .= $this->productCardHtml($product, true, 'commerce-rec-card');
        }
        return '<div class="wrap"><div class="commerce-rail-section commerce-rail-section-recommended"><div class="commerce-section-head"><h2>Recommended for you</h2></div>'
            . '<div class="commerce-rail-wrap">'
            . '<button type="button" class="commerce-rail-arrow commerce-rail-prev" aria-label="Scroll recommended products left"><svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><polygon points="10,0 0,5 10,10"/></svg></button>'
            . '<div class="commerce-rec-rail">' . $cards . '</div>'
            . '<button type="button" class="commerce-rail-arrow commerce-rail-next" aria-label="Scroll recommended products right"><svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><polygon points="0,0 10,5 0,10"/></svg></button>'
            . '</div></div></div>';
    }

    /**
     * The persistent sidebar - category tree, a real dual-handle price
     * range (native range inputs layered to form one control, submitting
     * on release rather than every pixel of drag), in-stock/featured
     * checkboxes, and a "Brand fit" block that only renders when the
     * current browsing scope actually contains recognizable vehicle
     * makes in a product name (never a fixed, sometimes-empty checkbox
     * list).
     * @param array<string,mixed>|null $category
     * @param array{sort:string,in_stock:bool,featured:bool,min_price:?int,max_price:?int,brand:?string,view:string} $filters
     */
    private function sidebarFiltersHtml(?array $category, array $filters, string $actionPath, string $query): string
    {
        $counts = $this->categories->productCounts();
        $categoryTreeHtml = '<a class="' . ($category === null ? 'is-active' : '') . '" href="/shop">All Products</a>';
        $treeRows = $this->categories->tree();
        $activeId = $category !== null ? (int)$category['id'] : null;

        // Ancestor chain of the active category, computed from the flat
        // pre-order $treeRows list itself (no repository method for this
        // exists) - a stack of "current open ancestor id per depth" as we
        // walk the rows in order gives the active row's ancestor chain the
        // moment we reach it.
        $activeAncestorIds = [];
        if ($activeId !== null) {
            $ancestorStack = [];
            foreach ($treeRows as $row) {
                $depth = (int)$row['depth'];
                while (count($ancestorStack) > $depth) {
                    array_pop($ancestorStack);
                }
                if ((int)$row['id'] === $activeId) {
                    $activeAncestorIds = $ancestorStack;
                    break;
                }
                $ancestorStack[$depth] = (int)$row['id'];
            }
        }

        $openDepths = [];
        foreach ($treeRows as $i => $row) {
            $depth = (int)$row['depth'];
            $id = (int)$row['id'];
            while ($openDepths !== [] && end($openDepths) >= $depth) {
                array_pop($openDepths);
                $categoryTreeHtml .= '</div></details>';
            }
            $active = $activeId === $id ? ' is-active' : '';
            $hasChildren = isset($treeRows[$i + 1]) && (int)$treeRows[$i + 1]['depth'] > $depth;
            $count = $counts[$id] ?? 0;
            if ($hasChildren) {
                $isOpen = in_array($id, $activeAncestorIds, true) || $active !== '' ? ' open' : '';
                $categoryTreeHtml .= '<details class="commerce-cat-node"' . $isOpen . '><summary class="' . trim($active) . '">'
                    . e((string)$row['name']) . ' <span>' . $count . '</span></summary><div class="commerce-cat-children">';
                $openDepths[] = $depth;
            } else {
                $categoryTreeHtml .= '<a class="' . trim($active) . '" href="/shop/category/' . e((string)$row['slug']) . '">'
                    . e((string)$row['name']) . ' <span>' . $count . '</span></a>';
            }
        }
        while ($openDepths !== []) {
            array_pop($openDepths);
            $categoryTreeHtml .= '</div></details>';
        }

        $range = $this->products->priceRange();
        $minBound = (int)floor($range['min_minor'] / 100);
        $maxBound = (int)ceil($range['max_minor'] / 100);
        if ($maxBound <= $minBound) {
            $maxBound = $minBound + 1;
        }
        $currentMin = $filters['min_price'] !== null ? (int)round($filters['min_price'] / 100) : $minBound;
        $currentMax = $filters['max_price'] !== null ? (int)round($filters['max_price'] / 100) : $maxBound;

        $hiddenQ = $query !== '' ? '<input type="hidden" name="q" value="' . e($query) . '">' : '';

        $brandHtml = '';
        $categoryId = $category !== null ? (int)$category['id'] : null;
        $descendantIds = $categoryId !== null ? $this->categories->descendantIds($categoryId) : [];
        $brands = $this->products->distinctBrandsInCategory($categoryId, $descendantIds);
        if ($brands !== []) {
            $brandRows = '';
            foreach ($brands as $brand) {
                $checked = $filters['brand'] === $brand ? ' checked' : '';
                $brandRows .= '<label class="commerce-check-row"><input type="radio" name="brand" value="' . e($brand) . '"' . $checked . ' onchange="this.form.submit()"> ' . e($brand) . '</label>';
            }
            $brandHtml = '<div class="commerce-filter-block commerce-filter-block-brand"><b>Brand fit</b>'
                . ($filters['brand'] !== null ? '<label class="commerce-check-row"><input type="radio" name="brand" value=""' . ($filters['brand'] === null ? ' checked' : '') . ' onchange="this.form.submit()"> Any brand</label>' : '')
                . $brandRows . '</div>';
        }

        return '<aside class="commerce-filters"><form method="get" action="' . e($actionPath) . '" id="commerce-filter-form">' . $hiddenQ
            . '<input type="hidden" name="sort" value="' . e($filters['sort']) . '">'
            . '<input type="hidden" name="view" value="' . e($filters['view']) . '">'
            . '<div class="commerce-filter-block"><b>Category</b><div class="commerce-filter-list">' . $categoryTreeHtml . '</div></div>'
            . '<div class="commerce-filter-block"><b>Price</b>'
            . '<div class="commerce-price-slider" data-min-bound="' . $minBound . '" data-max-bound="' . $maxBound . '">'
            . '<div class="commerce-price-track"><div class="commerce-price-fill"></div></div>'
            . '<input type="range" class="commerce-range-min" min="' . $minBound . '" max="' . $maxBound . '" value="' . $currentMin . '" name="min_price" onchange="this.form.submit()">'
            . '<input type="range" class="commerce-range-max" min="' . $minBound . '" max="' . $maxBound . '" value="' . $currentMax . '" name="max_price" onchange="this.form.submit()">'
            . '</div><div class="commerce-price-labels"><span>$' . $currentMin . '</span><span>$' . $currentMax . '</span></div></div>'
            . '<div class="commerce-filter-block"><b>Availability</b>'
            . '<label class="commerce-check-row"><input type="checkbox" name="in_stock" value="1"' . ($filters['in_stock'] ? ' checked' : '') . ' onchange="this.form.submit()"> In stock only</label>'
            . '<label class="commerce-check-row"><input type="checkbox" name="featured" value="1"' . ($filters['featured'] ? ' checked' : '') . ' onchange="this.form.submit()"> Featured only</label></div>'
            . $brandHtml
            . '</form></aside>';
    }

    /**
     * @param array{sort:string,in_stock:bool,featured:bool,min_price:?int,max_price:?int,brand:?string,view:string} $filters
     */
    private function gridToolbarHtml(string $heading, string $description, int $resultCount, array $filters, string $actionPath, string $query): string
    {
        $sortOptions = [
            'featured' => 'Featured',
            'newest' => 'Newest',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            'name_asc' => 'Name: A to Z',
        ];
        $options = '';
        foreach ($sortOptions as $value => $label) {
            $options .= '<option value="' . e($value) . '"' . ($filters['sort'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        $hiddenQ = $query !== '' ? '<input type="hidden" name="q" value="' . e($query) . '">' : '';
        $preserved = static fn (string $key): string => isset($filters[$key]) && $filters[$key] !== null && $filters[$key] !== false
            ? '<input type="hidden" name="' . e($key) . '" value="' . e((string)$filters[$key]) . '">' : '';

        return '<div class="commerce-section-head"><h2>' . e($heading) . '</h2></div>'
            . ($description !== '' ? '<p class="muted" style="margin-top:-10px">' . $description . '</p>' : '')
            . '<div class="commerce-grid-toolbar">'
            . '<span class="commerce-result-count">' . number_format($resultCount) . ' result' . ($resultCount === 1 ? '' : 's') . '</span>'
            . '<div class="commerce-toolbar-right">'
            . '<form method="get" action="' . e($actionPath) . '" style="display:flex;align-items:center;gap:10px">' . $hiddenQ
            . $preserved('in_stock') . $preserved('featured') . $preserved('min_price') . $preserved('max_price') . $preserved('brand') . $preserved('view')
            . '<select name="sort" class="commerce-sort-select" onchange="this.form.submit()">' . $options . '</select></form>'
            . '<div class="commerce-view-seg">'
            . '<a href="' . e($this->withQuery($actionPath, $filters, $query, ['view' => 'grid'])) . '" class="' . ($filters['view'] === 'grid' ? 'is-active' : '') . '" aria-label="Grid view"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></a>'
            . '<a href="' . e($this->withQuery($actionPath, $filters, $query, ['view' => 'list'])) . '" class="' . ($filters['view'] === 'list' ? 'is-active' : '') . '" aria-label="List view"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></a>'
            . '</div></div></div>';
    }

    /** @param array{sort:string,in_stock:bool,featured:bool,min_price:?int,max_price:?int,brand:?string,view:string} $filters @param array<string,string> $overrides */
    private function withQuery(string $path, array $filters, string $query, array $overrides): string
    {
        $params = array_filter([
            'q' => $query !== '' ? $query : null,
            'sort' => $filters['sort'] !== 'featured' ? $filters['sort'] : null,
            'in_stock' => $filters['in_stock'] ? '1' : null,
            'featured' => $filters['featured'] ? '1' : null,
            'min_price' => $filters['min_price'] !== null ? (string)round($filters['min_price'] / 100) : null,
            'max_price' => $filters['max_price'] !== null ? (string)round($filters['max_price'] / 100) : null,
            'brand' => $filters['brand'],
            'view' => $filters['view'] !== 'grid' ? $filters['view'] : null,
        ], static fn ($v) => $v !== null);
        $params = array_merge($params, $overrides);
        return $path . ($params !== [] ? '?' . http_build_query($params) : '');
    }

    /** @param list<array<string,mixed>> $products */
    private function productGridHtml(array $products): string
    {
        $html = '';
        foreach ($products as $product) {
            $html .= $this->productCardHtml($product, false, 'commerce-p-card');
        }
        return $html;
    }

    /**
     * The outer element is a <div>, not an <a> - a quick "Add to cart"
     * button needs to sit in this card, and a <form>/<button> nested
     * inside an <a> is invalid HTML (and unreliable in practice: a click
     * on the button both submits the form and triggers the anchor's own
     * navigation). The image/title/price are wrapped in their own inner
     * <a> instead, so the card still behaves like one big click target
     * everywhere except the button/quick-view icon.
     * @param array<string,mixed> $product
     */
    private function productCardHtml(array $product, bool $isRecommended, string $cardClass): string
    {
        $price = number_format((int)$product['price_minor'] / 100, 2) . ' ' . e((string)$product['currency']);
        $trackInventory = (int)($product['track_inventory'] ?? 0) === 1;
        $stockQty = (int)($product['stock_quantity'] ?? 0);
        $inStock = !$trackInventory || $stockQty > 0;

        $stockDot = $inStock
            ? '<span class="commerce-stock-dot' . ($trackInventory && $stockQty <= 5 ? ' low' : '') . '"><i></i>' . ($trackInventory && $stockQty <= 5 ? $stockQty . ' left' : 'In stock') . '</span>'
            : '<span class="commerce-stock-dot out"><i></i>Out of stock</span>';
        $recBadge = $isRecommended ? '<div class="commerce-badge commerce-badge-featured">★ Recommended</div>' : '';

        $quickAdd = $inStock
            ? '<form method="post" action="/cart/add" class="commerce-quick-add"><input type="hidden" name="csrf" value="' . csrf() . '"><input type="hidden" name="product_id" value="' . (int)$product['id'] . '"><input type="hidden" name="quantity" value="1"><button type="submit">Add to cart</button></form>'
            : '';
        $quickView = '<a class="commerce-quick-icon" href="/shop/' . e((string)$product['slug']) . '" aria-label="View ' . e((string)$product['name']) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>';

        $sku = trim((string)($product['sku'] ?? ''));

        return '<div class="' . $cardClass . '">'
            . '<div class="commerce-p-media-wrap">'
            . $recBadge . $stockDot
            . '<a class="commerce-card-link" href="/shop/' . e((string)$product['slug']) . '">' . $this->productImage($product) . '</a>'
            . '<div class="commerce-p-quick">' . $quickAdd . $quickView . '</div>'
            . '</div>'
            . '<a class="commerce-card-link commerce-p-body" href="/shop/' . e((string)$product['slug']) . '">'
            . '<div class="commerce-p-title">' . e((string)$product['name']) . '</div>'
            . '<div class="commerce-p-price-row"><span class="commerce-p-price">' . $price . '</span>' . ($sku !== '' ? '<span class="commerce-p-sku">' . e($sku) . '</span>' : '') . '</div>'
            . '</a></div>';
    }

    /**
     * Package-scoped CSS, inlined on the page rather than added to the core
     * theme stylesheet - erased.commerce stays self-contained and the
     * public site theme doesn't need to know this package exists. Every
     * color reads from the active theme's own custom properties (--panel/
     * --panel-2/--line/--text/--muted/--green/--warning/--danger/--bg,
     * confirmed against public-site.css rather than assumed) instead of
     * the approved sketch's own placeholder palette, so this renders
     * correctly under every installed theme, not just the one the sketch
     * happened to preview in.
     */
    private function commerceStyles(): string
    {
        return '<style>
html,body{overflow-x:hidden}
.commerce-subnav{position:sticky;top:var(--commerce-header-h,64px);z-index:19;background:color-mix(in srgb,var(--panel) 55%,transparent);backdrop-filter:blur(16px) saturate(150%);-webkit-backdrop-filter:blur(16px) saturate(150%);border-bottom:1px solid var(--line);box-shadow:0 0 24px -4px rgba(0,0,0,.42);border-radius:18px}
.commerce-subnav-row{display:flex;align-items:center;gap:24px;padding:12px 20px}
.commerce-subnav-brand{display:flex;align-items:center;gap:8px;font-weight:800;white-space:nowrap;color:var(--text)}
.commerce-subnav-dot{width:8px;height:8px;border-radius:99px;background:var(--green)}
.commerce-subnav-links{display:flex;gap:18px;font-size:.86rem;font-weight:600;color:var(--muted)}
.commerce-subnav-links a{color:var(--muted)}
.commerce-subnav-links a:hover{color:var(--text)}
.commerce-subnav-search{flex:0 1 340px;margin-left:auto;position:relative;display:flex;align-items:center}
.commerce-subnav-search svg{position:absolute;left:13px;opacity:.5;pointer-events:none}
.commerce-subnav-search input{width:100%;padding:9px 12px 9px 36px;border-radius:99px;border:1px solid var(--line);background:var(--panel);color:var(--text);font-size:.86rem;margin:0}
.commerce-subnav-cart{position:relative;width:38px;height:38px;flex:0 0 auto;border-radius:99px;display:flex;align-items:center;justify-content:center;background:var(--panel);border:1px solid var(--line);color:var(--text)}
.commerce-cart-count{position:absolute;top:-4px;right:-4px;background:var(--green);color:var(--bg);font-size:.65rem;font-weight:800;width:17px;height:17px;border-radius:99px;display:flex;align-items:center;justify-content:center}
@media(max-width:900px){.commerce-subnav-links{display:none}}

.commerce-hero{position:relative;margin:24px 0;border-radius:20px;overflow:hidden;min-height:320px;display:flex;align-items:center;background:radial-gradient(900px 400px at 15% 20%,color-mix(in srgb,var(--green) 22%,transparent),transparent 60%),linear-gradient(135deg,var(--panel-2),var(--panel) 70%);border:1px solid var(--line)}
.commerce-hero-copy{padding:44px 48px;max-width:600px}
.commerce-hero-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--green);background:color-mix(in srgb,var(--green) 16%,transparent);padding:6px 12px;border-radius:99px;margin-bottom:16px}
.commerce-hero h1{font-size:clamp(1.9rem,3.8vw,2.9rem);line-height:1.06;margin:0 0 14px;letter-spacing:-.01em;font-weight:800;color:var(--text)}
.commerce-hero h1 em{font-style:normal;color:var(--green)}
.commerce-hero p{color:var(--muted);font-size:1rem;line-height:1.55;margin:0 0 24px;max-width:48ch}
.commerce-hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px}
.commerce-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:99px;font-weight:700;font-size:.88rem;border:0;cursor:pointer;text-decoration:none}
.commerce-btn-primary{background:var(--green);color:var(--btn-text,#052014)}
.commerce-btn-ghost{background:transparent;color:var(--text);border:1px solid var(--line)}
.commerce-hero-stats{display:flex;gap:30px}
.commerce-hero-stats div{display:flex;flex-direction:column;gap:2px}
.commerce-hero-stats b{font-size:1.2rem;font-weight:800;color:var(--text)}
.commerce-hero-stats span{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}

.commerce-trust{display:flex;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:36px;flex-wrap:wrap}
.commerce-trust>div{flex:1 1 200px;display:flex;align-items:center;gap:12px;padding:15px 18px;border-right:1px solid var(--line)}
.commerce-trust>div:last-child{border-right:0}
.commerce-trust svg{flex:0 0 auto;color:var(--green)}
.commerce-trust b{display:block;font-size:.82rem;color:var(--text)}
.commerce-trust span{display:block;font-size:.72rem;color:var(--muted)}

.commerce-section-head{margin-bottom:6px}
.commerce-section-head h2{font-size:1.2rem;margin:0;letter-spacing:-.01em;color:var(--text)}

.commerce-rail-section{margin-bottom:40px;padding:16px 30px 20px;background:color-mix(in srgb,var(--panel) 55%,transparent);backdrop-filter:blur(16px) saturate(150%);-webkit-backdrop-filter:blur(16px) saturate(150%);box-shadow:0 0 24px -4px rgba(0,0,0,.42);border-radius:18px}
.commerce-rail-wrap{position:relative;padding:0 40px}
.commerce-rail-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:2;width:38px;height:38px;border-radius:0;border:0;background:transparent;box-shadow:none;color:var(--text);font-size:1.3rem;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer}
.commerce-rail-arrow:hover{color:var(--green)}
.commerce-rail-prev{left:-8px}
.commerce-rail-next{right:-8px}
@media(max-width:640px){.commerce-rail-arrow{display:none}}

.commerce-cat-rail{display:flex;gap:16px;overflow-x:auto;padding-bottom:6px;scroll-behavior:smooth;scrollbar-width:none}
.commerce-cat-rail::-webkit-scrollbar{display:none}
.commerce-cat-pill{flex:0 0 auto;display:flex;align-items:center;gap:7px;padding:6px 14px;border-radius:99px;background:var(--panel);border:0;font-size:.78rem;font-weight:700;white-space:nowrap;color:var(--text)}
.commerce-cat-pill:hover{color:var(--green)}
.commerce-cat-count{opacity:.7}

.commerce-rec-rail{display:flex;gap:16px;overflow-x:auto;padding-bottom:10px;scroll-behavior:smooth;scroll-snap-type:x mandatory;scrollbar-width:none}
.commerce-rec-rail::-webkit-scrollbar{display:none}
.commerce-rec-card{scroll-snap-align:start;flex:0 0 260px}
.commerce-rec-card .commerce-p-media-wrap{aspect-ratio:4/3}

.commerce-browse-wrap{padding-top:8px}
.commerce-shop-layout{display:grid;grid-template-columns:240px 1fr;gap:32px;align-items:start;margin-bottom:40px}
main:has(.commerce-shop-layout),.public:has(.commerce-shop-layout){max-width:1600px}
main:has(.commerce-shop-layout){position:relative;background:var(--panel,#d6dfe9);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%);margin-top:0;padding-top:34px;padding-bottom:64px;border-radius:0 0 24px 24px;box-shadow:0 0 26px -4px rgba(0,0,0,.2)}
.commerce-filters{position:sticky;top:calc(var(--commerce-header-h,64px) + var(--commerce-subnav-h,64px) + 8px);display:flex;flex-direction:column;gap:22px;padding:20px 16px 40px;background:color-mix(in srgb,var(--panel) 55%,transparent);backdrop-filter:blur(16px) saturate(150%);-webkit-backdrop-filter:blur(16px) saturate(150%);box-shadow:0 0 24px -4px rgba(0,0,0,.42);border-radius:18px}
.commerce-grid-col{padding:18px 16px 24px;background:color-mix(in srgb,var(--panel) 55%,transparent);backdrop-filter:blur(16px) saturate(150%);-webkit-backdrop-filter:blur(16px) saturate(150%);box-shadow:0 0 24px -4px rgba(0,0,0,.42);border-radius:18px}
.commerce-filter-block b{display:block;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:10px}
.commerce-filter-list{display:flex;flex-direction:column;gap:1px;max-height:320px;overflow-y:auto;padding-top:6px;padding-bottom:6px;scrollbar-width:none}
.commerce-filter-list::-webkit-scrollbar{display:none}
.commerce-filters{scrollbar-width:none}
.commerce-filters::-webkit-scrollbar{display:none}
.commerce-filter-list a{display:flex;justify-content:space-between;padding:4px 8px;border-radius:6px;font-size:.79rem;line-height:1.5;color:var(--text);text-decoration:none}
.commerce-filter-list a:hover{background:var(--panel)}
.commerce-filter-list a.is-active{background:var(--panel-2);font-weight:700}
.commerce-filter-list a span{color:var(--muted);font-size:.76rem}
.commerce-price-slider{position:relative;height:20px;margin:6px 0 4px}
.commerce-price-track{position:absolute;left:11px;right:11px;top:50%;height:4px;border-radius:99px;background:var(--panel-2);transform:translateY(-50%);pointer-events:none}
.commerce-price-fill{position:absolute;height:100%;background:var(--green);border-radius:99px}
.commerce-price-slider input[type=range]{position:absolute;left:0;right:0;top:0;width:100%;margin:0;height:20px;background:transparent;-webkit-appearance:none;appearance:none;pointer-events:none}
.commerce-price-slider input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;pointer-events:auto;width:16px;height:16px;border-radius:99px;background:var(--text);border:3px solid var(--bg);cursor:pointer;margin-top:0}
.commerce-price-slider input[type=range]::-moz-range-thumb{pointer-events:auto;width:16px;height:16px;border-radius:99px;background:var(--text);border:3px solid var(--bg);cursor:pointer}
.commerce-price-slider input[type=range]::-webkit-slider-runnable-track{background:transparent}
.commerce-price-labels{display:flex;justify-content:space-between;font-size:.76rem;color:var(--muted);margin-bottom:8px}
.commerce-check-row{display:flex;align-items:center;gap:9px;font-size:.85rem;color:var(--text);padding:4px 0;margin:0}
.commerce-check-row input{width:16px;height:16px;accent-color:var(--green);margin:0}

.commerce-grid-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:14px;flex-wrap:wrap}
.commerce-result-count{font-size:.85rem;color:var(--muted)}
.commerce-toolbar-right{display:flex;gap:10px;align-items:center}
.commerce-sort-select{padding:8px 13px;border-radius:99px;border:1px solid var(--line);background:var(--panel);color:var(--text);font-size:.82rem;font-weight:600;margin:0}
.commerce-view-seg{display:flex;border:1px solid var(--line);border-radius:99px;padding:3px}
.commerce-view-seg a{border:0;background:transparent;color:var(--muted);width:30px;height:30px;border-radius:99px;display:flex;align-items:center;justify-content:center}
.commerce-view-seg a.is-active{background:var(--panel-2);color:var(--text)}

.commerce-product-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.commerce-p-card{background:var(--panel);border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column}
.commerce-p-card:hover{border-color:color-mix(in srgb,var(--green) 45%,var(--line))}
.commerce-p-media-wrap{aspect-ratio:1/1;position:relative;overflow:hidden;flex-shrink:0}
.commerce-pimg{width:100%;height:100%;object-fit:cover;display:block}
.commerce-pimg-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--panel-2);color:var(--muted)}
.commerce-card-link{display:block;color:inherit;text-decoration:none;height:100%}
.commerce-badge{position:absolute;top:10px;left:10px;z-index:2;font-size:.68rem;font-weight:800;padding:5px 10px;border-radius:99px}
.commerce-badge-featured{background:var(--green);color:var(--btn-text,#052014)}
.commerce-stock-dot{position:absolute;top:10px;right:10px;z-index:2;display:flex;align-items:center;gap:6px;font-size:.66rem;font-weight:800;padding:5px 10px 5px 8px;border-radius:99px;background:color-mix(in srgb,var(--bg) 70%,transparent);backdrop-filter:blur(6px);color:var(--text)}
.commerce-stock-dot i{width:6px;height:6px;border-radius:99px;background:var(--green)}
.commerce-stock-dot.low i{background:var(--warning,#d69e2e)}
.commerce-stock-dot.out i{background:var(--danger,#ff7777)}
.commerce-p-quick{position:absolute;bottom:10px;left:10px;right:10px;display:flex;gap:8px;opacity:0;transform:translateY(6px);transition:opacity .16s ease,transform .16s ease;z-index:2}
.commerce-p-card:hover .commerce-p-quick,.commerce-rec-card:hover .commerce-p-quick{opacity:1;transform:none}
.commerce-quick-add{flex:1;margin:0}
.commerce-quick-add button{width:100%;border:0;border-radius:10px;padding:9px;font-weight:700;font-size:.8rem;cursor:pointer;background:var(--text);color:var(--bg)}
.commerce-quick-icon{flex:0 0 auto;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--bg) 75%,transparent);color:var(--text);backdrop-filter:blur(6px)}
.commerce-p-body{padding:12px 14px 14px}
.commerce-p-title{font-size:.88rem;font-weight:700;margin:0 0 8px;line-height:1.3;color:var(--text);min-height:2.3em}
.commerce-p-price-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.commerce-p-price{font-weight:800;color:var(--text)}
.commerce-p-sku{font-size:.68rem;color:var(--muted);font-family:ui-monospace,monospace}

.commerce-view-list .commerce-product-grid,.commerce-view-list.commerce-product-grid{grid-template-columns:1fr}
.commerce-view-list .commerce-p-card,.commerce-product-grid.commerce-view-list .commerce-p-card{flex-direction:row}
.commerce-view-list .commerce-p-media-wrap{width:160px;aspect-ratio:1/1;flex:0 0 auto}
.commerce-view-list .commerce-p-body{flex:1}

@media(max-width:1080px){.commerce-shop-layout{grid-template-columns:1fr}.commerce-product-grid{grid-template-columns:repeat(3,1fr)}.commerce-filters{position:static;display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-height:none;overflow-y:visible}.commerce-trust>div{flex:1 1 50%}}
@media(max-width:640px){.commerce-product-grid{grid-template-columns:1fr 1fr}.commerce-hero-copy{padding:32px 24px}.commerce-filters{grid-template-columns:1fr}}

.commerce-cart-layout{display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;margin-top:18px}
.commerce-cart-lines{display:flex;flex-direction:column;gap:12px}
.commerce-cart-line{display:grid;grid-template-columns:64px 1fr auto auto auto;gap:16px;align-items:center;padding:14px;background:var(--panel);border:1px solid var(--line);border-radius:12px}
.commerce-cart-thumb{width:64px;height:64px;border-radius:8px;overflow:hidden;flex:0 0 auto;display:block}
.commerce-cart-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.commerce-cart-name{color:var(--text);font-weight:700;text-decoration:none;font-size:.92rem}
.commerce-cart-name:hover{color:var(--green)}
.commerce-cart-sku{font-size:.72rem;color:var(--muted);font-family:ui-monospace,monospace}
.commerce-cart-unit-price{font-size:.78rem;color:var(--muted)}
.commerce-qty-form{display:flex;align-items:center;border:1px solid var(--line);border-radius:8px;overflow:hidden}
.commerce-qty-btn{width:30px;height:32px;border:0;background:var(--panel-2);color:var(--text);font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center}
.commerce-qty-btn:hover{background:color-mix(in srgb,var(--panel-2) 70%,var(--green) 30%)}
.commerce-qty-input{width:42px;height:32px;margin:0;padding:0;border:0;border-left:1px solid var(--line);border-right:1px solid var(--line);text-align:center;background:var(--bg);color:var(--text);-moz-appearance:textfield}
.commerce-qty-input::-webkit-outer-spin-button,.commerce-qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.commerce-cart-line-total{font-weight:800;color:var(--text);white-space:nowrap}
.commerce-cart-remove{margin:0}
.commerce-cart-remove button{width:32px;height:32px;border:1px solid var(--line);border-radius:8px;background:transparent;color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer}
.commerce-cart-remove button:hover{border-color:var(--danger,#ff7777);color:var(--danger,#ff7777)}
.commerce-cart-summary{position:sticky;top:calc(var(--commerce-header-h,64px) + 16px);background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:18px}
.commerce-cart-summary .btn{width:100%;text-align:center;margin-top:14px}
.commerce-ship-progress{margin-bottom:16px}
.commerce-ship-bar{height:6px;border-radius:99px;background:var(--panel-2);overflow:hidden;margin-bottom:8px}
.commerce-ship-fill{height:100%;background:var(--green);border-radius:99px;transition:width .3s ease}
.commerce-ship-msg{font-size:.8rem;color:var(--muted);margin:0}
.commerce-ship-msg.is-unlocked{color:var(--green);font-weight:700}
.commerce-coupon-form{display:flex;gap:6px;align-items:center;margin-top:10px}
.commerce-coupon-form input,.commerce-cart-summary .commerce-coupon-form button{margin:0}
.commerce-coupon-form input{width:160px}
@media(max-width:800px){.commerce-cart-layout{grid-template-columns:1fr}.commerce-cart-summary{position:static}.commerce-cart-line{grid-template-columns:56px 1fr auto;grid-template-areas:"thumb info remove" "thumb qty total"}.commerce-cart-thumb{grid-area:thumb;width:56px;height:56px}.commerce-cart-info{grid-area:info}.commerce-qty-form{grid-area:qty}.commerce-cart-line-total{grid-area:total;justify-self:end}.commerce-cart-remove{grid-area:remove;justify-self:end}}
.commerce-subnav-cart svg,.commerce-view-seg a svg,.commerce-quick-icon svg,.commerce-cart-remove button svg,.commerce-rail-arrow svg{flex-shrink:0;width:auto;height:auto}

.commerce-detail-wrap{margin:24px auto 48px}
.commerce-breadcrumbs{font-size:.8rem;color:var(--muted);margin-bottom:20px;display:flex;gap:6px;flex-wrap:wrap}
.commerce-breadcrumbs a{color:var(--muted);text-decoration:none}
.commerce-breadcrumbs a:hover{color:var(--green)}
.commerce-breadcrumbs span{color:var(--text)}
.commerce-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:44px;align-items:start;margin-bottom:52px}
.commerce-detail-main-img{aspect-ratio:1/1;border-radius:16px;overflow:hidden;background:var(--panel);border:1px solid var(--line)}
.commerce-detail-main-img img,.commerce-detail-main-img .commerce-pimg-placeholder{width:100%;height:100%;object-fit:cover;display:block}
.commerce-detail-thumbs{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
.commerce-detail-thumb{width:72px;height:72px;border-radius:10px;overflow:hidden;border:2px solid var(--line);background:var(--panel);cursor:pointer;padding:0}
.commerce-detail-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.commerce-detail-thumb.is-active,.commerce-detail-thumb:hover{border-color:var(--green)}
.commerce-detail-info{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:28px}
.commerce-detail-info h1{font-size:1.7rem;margin:0 0 12px;color:var(--text);letter-spacing:-.01em}
.commerce-detail-badges{display:flex;gap:8px;margin-bottom:12px}
.commerce-detail-price-row{margin-bottom:14px}
.commerce-detail-price{font-size:1.6rem;font-weight:800;color:var(--text)}
.commerce-detail-stock{display:flex;align-items:center;gap:8px;font-size:.85rem;font-weight:700;margin-bottom:18px;color:var(--text)}
.commerce-detail-stock i{width:8px;height:8px;border-radius:99px;background:var(--green)}
.commerce-detail-stock.low i{background:var(--warning,#d69e2e)}
.commerce-detail-stock.out i{background:var(--danger,#ff7777)}
.commerce-detail-desc{color:var(--muted);line-height:1.6;margin-bottom:20px;white-space:normal}
.commerce-detail-specs{width:100%;border-collapse:collapse;margin-bottom:24px;font-size:.85rem}
.commerce-detail-specs tr{border-bottom:1px solid var(--line)}
.commerce-detail-specs tr:last-child{border-bottom:0}
.commerce-detail-specs td{padding:9px 4px;color:var(--text)}
.commerce-detail-specs td:first-child{color:var(--muted);width:40%}
.commerce-detail-form{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.commerce-detail-qty{display:flex;align-items:center;border:1px solid var(--line);border-radius:8px;overflow:hidden}
.commerce-detail-qty-btn{width:38px;height:42px;border:0;background:var(--panel-2);color:var(--text);font-size:1.1rem;font-weight:700;cursor:pointer}
.commerce-detail-qty-btn:hover{background:color-mix(in srgb,var(--panel-2) 70%,var(--green) 30%)}
.commerce-detail-qty-input{width:50px;height:42px;margin:0;padding:0;border:0;border-left:1px solid var(--line);border-right:1px solid var(--line);text-align:center;background:var(--bg);color:var(--text);-moz-appearance:textfield;font-size:.95rem}
.commerce-detail-qty-input::-webkit-outer-spin-button,.commerce-detail-qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.commerce-related{margin-top:8px}
@media(max-width:800px){.commerce-detail-grid{grid-template-columns:1fr;gap:26px}}
</style>
<script>(function(){
  var siteHeader = document.querySelector("header");
  var subNav = document.querySelector(".commerce-subnav");
  function syncHeaderOffset(){
    if(siteHeader) document.documentElement.style.setProperty("--commerce-header-h", siteHeader.getBoundingClientRect().height + "px");
    if(subNav) document.documentElement.style.setProperty("--commerce-subnav-h", subNav.getBoundingClientRect().height + "px");
  }
  syncHeaderOffset();
  window.addEventListener("resize", syncHeaderOffset);
  document.addEventListener("wheel", function(e){
    var rail = e.target.closest && e.target.closest(".commerce-cat-rail, .commerce-rec-rail");
    if (!rail) return;
    if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
    rail.scrollLeft += e.deltaY * 2;
    e.preventDefault();
  }, {passive:false});
  document.addEventListener("click", function(e){
    var btn = e.target.closest && e.target.closest(".commerce-rail-arrow");
    if (!btn) return;
    var rail = btn.parentElement.querySelector(".commerce-cat-rail, .commerce-rec-rail");
    if (!rail) return;
    var amount = rail.clientWidth * 0.8;
    rail.scrollBy({left: btn.classList.contains("commerce-rail-prev") ? -amount : amount, behavior: "smooth"});
  });
  document.querySelectorAll(".commerce-qty-btn").forEach(function(btn){
    btn.addEventListener("click", function(){
      var input = btn.parentElement.querySelector(".commerce-qty-input");
      var next = Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.step, 10));
      input.value = next;
      btn.form.submit();
    });
  });
  document.querySelectorAll(".commerce-qty-input").forEach(function(input){
    input.addEventListener("change", function(){ input.form.submit(); });
  });
  document.querySelectorAll(".commerce-detail-thumb").forEach(function(thumb){
    thumb.addEventListener("click", function(){
      var main = document.querySelector(".commerce-detail-main-img");
      if (!main) return;
      document.querySelectorAll(".commerce-detail-thumb").forEach(function(t){ t.classList.remove("is-active"); });
      thumb.classList.add("is-active");
      var img = document.createElement("img");
      img.src = thumb.dataset.full;
      img.alt = thumb.dataset.alt || "";
      main.innerHTML = "";
      main.appendChild(img);
    });
  });
  document.querySelectorAll(".commerce-detail-qty-btn").forEach(function(btn){
    btn.addEventListener("click", function(){
      var input = btn.parentElement.querySelector(".commerce-detail-qty-input");
      var next = Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.step, 10));
      input.value = next;
    });
  });
  document.querySelectorAll(".commerce-price-slider").forEach(function(slider){
    var minInput=slider.querySelector(".commerce-range-min"), maxInput=slider.querySelector(".commerce-range-max"), fill=slider.querySelector(".commerce-price-fill");
    var bound=parseFloat(slider.dataset.minBound), boundMax=parseFloat(slider.dataset.maxBound);
    function sync(){
      var lo=Math.min(parseFloat(minInput.value),parseFloat(maxInput.value)), hi=Math.max(parseFloat(minInput.value),parseFloat(maxInput.value));
      var range=(boundMax-bound)||1;
      var thumbHalf=8, trackWidth=slider.clientWidth, usable=Math.max(0,trackWidth-thumbHalf*2);
      var loPx=thumbHalf+((lo-bound)/range)*usable, hiPx=thumbHalf+((hi-bound)/range)*usable;
      fill.style.left=loPx+"px";
      fill.style.right=(trackWidth-hiPx)+"px";
      var labels=slider.parentElement.querySelector(".commerce-price-labels");
      if(labels){labels.children[0].textContent="$"+lo;labels.children[1].textContent="$"+hi;}
    }
    minInput.addEventListener("input",sync);
    maxInput.addEventListener("input",sync);
    sync();
  });
})();</script>';
    }

    /**
     * Real gallery (featured image + additional commerce_product_images
     * rows, swapped client-side - no server round-trip), a specs table
     * built only from columns that actually exist on commerce_products
     * (sku/category/subcategory/kind - no compare-at price or fabricated
     * attributes), and a "You might also like" rail sourced from
     * publishedInCategory() for products sharing this one's category.
     */
    private function detail(string $slug): void
    {
        $product = $this->products->findBySlug($slug);
        if ($product === null) {
            erased_public_not_found();
            return;
        }
        // Best-effort, day-bucketed - see ProductViewRepository's own
        // docblock for why this exists instead of relying on the separate,
        // optional erased.analytics-pro plugin. Never allowed to break the
        // page itself if it fails for any reason.
        try {
            $this->productViews->track((int)$product['id']);
        } catch (\Throwable) {
        }

        $price = number_format((int)$product['price_minor'] / 100, 2).' '.e((string)$product['currency']);
        $trackInventory = (int)($product['track_inventory'] ?? 0) === 1;
        $stockQty = (int)($product['stock_quantity'] ?? 0);
        $inStock = !$trackInventory || $stockQty > 0;
        $low = $trackInventory && $stockQty > 0 && $stockQty <= 5;
        $stockClass = !$inStock ? 'out' : ($low ? 'low' : '');
        $stockLabel = !$inStock ? 'Out of stock' : ($low ? $stockQty.' left in stock' : 'In stock');
        $stockHtml = '<div class="commerce-detail-stock '.$stockClass.'"><i></i>'.e($stockLabel).'</div>';
        $badges = (int)($product['featured'] ?? 0) === 1
            ? '<div class="commerce-detail-badges"><span class="commerce-badge commerce-badge-featured" style="position:static">&#9733; Recommended</span></div>'
            : '';

        // Gallery: the single featured image first (if any), then every
        // additional commerce_product_images row - same source data the
        // old stacked-<img> gallery used, just presented as a switchable
        // main image + thumbnail strip instead.
        $galleryImages = [];
        if (!empty($product['featured_media_id']) && function_exists('media_by_id') && ($media = media_by_id((int)$product['featured_media_id']))) {
            $galleryImages[] = ['url' => media_url($media), 'alt' => (string)$product['name']];
        }
        foreach ($this->images->forProduct((int)$product['id']) as $image) {
            if (!str_starts_with((string)$image['mime_type'], 'image/')) {
                continue;
            }
            $url = function_exists('media_url') ? media_url(['stored_name' => $image['stored_name'], 'mime_type' => $image['mime_type']]) : '';
            if ($url === '') {
                continue;
            }
            $galleryImages[] = ['url' => $url, 'alt' => (string)($image['alt_text'] ?? $product['name'])];
        }
        $mainImageHtml = $galleryImages !== []
            ? '<img src="'.e($galleryImages[0]['url']).'" alt="'.e($galleryImages[0]['alt']).'">'
            : $this->productImage($product);
        $thumbsHtml = '';
        if (count($galleryImages) > 1) {
            foreach ($galleryImages as $i => $image) {
                $thumbsHtml .= '<button type="button" class="commerce-detail-thumb'.($i === 0 ? ' is-active' : '').'" data-full="'.e($image['url']).'" data-alt="'.e($image['alt']).'"><img src="'.e($image['url']).'" alt="" loading="lazy"></button>';
            }
            $thumbsHtml = '<div class="commerce-detail-thumbs">'.$thumbsHtml.'</div>';
        }

        // Breadcrumb + specs table both key off the product's real
        // category_id (nullable - plenty of products have none).
        $category = !empty($product['category_id']) ? $this->categories->find((int)$product['category_id']) : null;
        $breadcrumb = '<a href="/shop">Shop</a>';
        if ($category !== null) {
            $breadcrumb .= ' <span>/</span> <a href="/shop/category/'.e((string)$category['slug']).'">'.e((string)$category['name']).'</a>';
        }
        $breadcrumb .= ' <span>/</span> <span>'.e((string)$product['name']).'</span>';

        $specRows = '';
        $sku = trim((string)($product['sku'] ?? ''));
        if ($sku !== '') {
            $specRows .= '<tr><td>SKU</td><td>'.e($sku).'</td></tr>';
        }
        if ($category !== null) {
            $categoryLabel = (string)$category['name'];
            $subcategory = trim((string)($product['subcategory'] ?? ''));
            if ($subcategory !== '' && strcasecmp($subcategory, $categoryLabel) !== 0) {
                $categoryLabel .= ' / '.$subcategory;
            }
            $specRows .= '<tr><td>Category</td><td>'.e($categoryLabel).'</td></tr>';
        }
        $kind = (string)($product['kind'] ?? 'physical');
        $kindLabel = ucfirst($kind);
        if ($kind === 'subscription' && !empty($product['subscription_interval'])) {
            $kindLabel .= ' (billed '.((string)$product['subscription_interval'] === 'year' ? 'yearly' : 'monthly').')';
        }
        $specRows .= '<tr><td>Type</td><td>'.e($kindLabel).'</td></tr>';
        $specsHtml = '<table class="commerce-detail-specs">'.$specRows.'</table>';

        $addToCart = $inStock
            ? '<form method="post" action="/cart/add" class="commerce-detail-form"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="product_id" value="'.(int)$product['id'].'">'
                .'<div class="commerce-detail-qty"><button type="button" class="commerce-detail-qty-btn" data-step="-1" aria-label="Decrease quantity">&minus;</button>'
                .'<input type="number" name="quantity" value="1" min="1" class="commerce-detail-qty-input"><button type="button" class="commerce-detail-qty-btn" data-step="1" aria-label="Increase quantity">+</button></div>'
                .'<button class="commerce-btn commerce-btn-primary" type="submit">Add to cart</button></form>'
            : '<p class="notice error">Out of stock.</p>';

        $body = '<div class="wrap commerce-detail-wrap">'
            .'<div class="commerce-breadcrumbs">'.$breadcrumb.'</div>'
            .'<div class="commerce-detail-grid">'
            .'<div class="commerce-detail-gallery"><div class="commerce-detail-main-img">'.$mainImageHtml.'</div>'.$thumbsHtml.'</div>'
            .'<div class="commerce-detail-info">'.$badges
            .'<h1>'.e((string)$product['name']).'</h1>'
            .'<div class="commerce-detail-price-row"><span class="commerce-detail-price">'.$price.'</span></div>'
            .$stockHtml
            .'<p class="commerce-detail-desc">'.nl2br(e((string)$product['description'])).'</p>'
            .$specsHtml
            .$addToCart
            .'</div></div>';

        if ($category !== null) {
            $related = array_values(array_filter(
                $this->products->publishedInCategory((int)$category['id']),
                static fn ($row) => (int)$row['id'] !== (int)$product['id']
            ));
            $related = array_slice($related, 0, 4);
            if ($related !== []) {
                $cards = '';
                foreach ($related as $row) {
                    $cards .= $this->productCardHtml($row, (int)($row['featured'] ?? 0) === 1, 'commerce-p-card');
                }
                $body .= '<div class="commerce-related"><div class="commerce-section-head"><h2>You might also like</h2></div><div class="commerce-product-grid">'.$cards.'</div></div>';
            }
        }

        $body .= '</div>'.$this->commerceStyles();
        layout((string)$product['name'], $body);
    }

    private function cartAdd(): void
    {
        verify_csrf();
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $this->cart->add($productId, $quantity);
        flash('success', 'Added to cart.');
        redirect('/cart');
    }

    private function cartView(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $action = (string)($_POST['action'] ?? '');
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($action === 'remove') {
                $this->cart->remove($productId);
            } elseif ($action === 'update') {
                $this->cart->setQuantity($productId, max(0, (int)($_POST['quantity'] ?? 0)));
            } elseif ($action === 'apply_coupon') {
                $this->cart->setCouponCode((string)($_POST['coupon_code'] ?? ''));
            } elseif ($action === 'remove_coupon') {
                $this->cart->setCouponCode(null);
            }
            redirect('/cart');
        }

        $lines = $this->cart->lines();
        $html = '';
        foreach ($lines as $line) {
            $product = $line['product'];
            $unitPrice = number_format((int)$product['price_minor'] / 100, 2).' '.e((string)$product['currency']);
            $lineTotal = number_format($line['line_total_minor'] / 100, 2).' '.e((string)$product['currency']);
            $sku = trim((string)($product['sku'] ?? ''));
            $qty = (int)$line['quantity'];
            $html .= '<div class="commerce-cart-line">'
                .'<a class="commerce-cart-thumb" href="/shop/'.e((string)$product['slug']).'">'.$this->productImage($product).'</a>'
                .'<div class="commerce-cart-info"><a class="commerce-cart-name" href="/shop/'.e((string)$product['slug']).'">'.e((string)$product['name']).'</a>'
                .($sku !== '' ? '<span class="commerce-cart-sku">'.e($sku).'</span>' : '')
                .'<span class="commerce-cart-unit-price">'.$unitPrice.' each</span></div>'
                .'<form method="post" class="commerce-qty-form"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="update"><input type="hidden" name="product_id" value="'.(int)$product['id'].'">'
                .'<button type="button" class="commerce-qty-btn" data-step="-1" aria-label="Decrease quantity">&minus;</button>'
                .'<input type="number" name="quantity" value="'.$qty.'" min="1" class="commerce-qty-input">'
                .'<button type="button" class="commerce-qty-btn" data-step="1" aria-label="Increase quantity">+</button>'
                .'</form>'
                .'<div class="commerce-cart-line-total">'.$lineTotal.'</div>'
                .'<form method="post" class="commerce-cart-remove"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="'.(int)$product['id'].'"><button type="submit" aria-label="Remove '.e((string)$product['name']).'"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></button></form>'
                .'</div>';
        }

        $currency = setting('payment_currency', 'EUR');
        $subtotalMinor = $this->cart->subtotalMinor();
        $resolved = $this->resolveCoupon($subtotalMinor);
        $pricing = $this->pricing()->calculate($subtotalMinor, $resolved['discount_minor']);
        $couponCode = $resolved['coupon'] !== null ? (string)$resolved['coupon']['code'] : null;
        $notice = $resolved['notice'] !== null ? '<p class="notice error">'.e($resolved['notice']).'</p>' : '';

        $summary = $lines !== []
            ? '<div class="commerce-cart-summary">'.$notice
                .$this->shippingProgressHtml($subtotalMinor, $currency)
                .$this->couponFormHtml($couponCode)
                .'<div style="margin-top:14px">'.$this->breakdownHtml($subtotalMinor, $resolved['discount_minor'], $couponCode, $pricing['shipping_minor'], $pricing['tax_minor'], $pricing['total_minor'], $currency).'</div>'
                .'<a class="btn" href="/checkout">Proceed to checkout</a></div>'
            : '';

        $body = '<div class="toolbar"><div><h1>Your cart</h1></div></div>'
            .($lines !== []
                ? '<div class="commerce-cart-layout"><div class="commerce-cart-lines">'.$html.'</div>'.$summary.'</div>'
                : '<div class="panel"><div class="panel-body"><p class="muted">Your cart is empty.</p><a class="btn secondary" href="/shop">Continue shopping</a></div></div>')
            .$this->commerceStyles();
        layout('Cart', $body);
    }

    /**
     * A real "you're X away from free shipping" bar, computed against the
     * actual configured threshold and the actual cart subtotal - not a
     * decorative always-90%-full bar. Renders nothing if no free-shipping
     * threshold is configured at all.
     */
    private function shippingProgressHtml(int $subtotalMinor, string $currency): string
    {
        $thresholdSetting = trim(setting('commerce_shipping_free_threshold_minor', ''));
        if ($thresholdSetting === '' || (int)$thresholdSetting <= 0) {
            return '';
        }
        $thresholdMinor = (int)$thresholdSetting;
        $percent = (int)min(100, round($subtotalMinor / $thresholdMinor * 100));
        if ($subtotalMinor >= $thresholdMinor) {
            return '<div class="commerce-ship-progress"><div class="commerce-ship-bar"><div class="commerce-ship-fill" style="width:100%"></div></div>'
                .'<p class="commerce-ship-msg is-unlocked">You\'ve unlocked free shipping.</p></div>';
        }
        $remaining = number_format(($thresholdMinor - $subtotalMinor) / 100, 2).' '.e($currency);
        return '<div class="commerce-ship-progress"><div class="commerce-ship-bar"><div class="commerce-ship-fill" style="width:'.$percent.'%"></div></div>'
            .'<p class="commerce-ship-msg">Add '.$remaining.' more for free shipping.</p></div>';
    }

    private function checkout(): void
    {
        $gateway = setting('payment_gateway', 'none');
        $gatewayLabels = ['none' => 'Disabled', 'stripe' => 'Stripe', 'paypal' => 'PayPal', 'vipps' => 'Vipps MobilePay', 'klarna' => 'Klarna', 'bank' => 'Manual bank transfer'];
        $gatewayLabel = $gatewayLabels[$gateway] ?? $gateway;

        if ($gateway !== 'bank') {
            layout('Checkout unavailable', '<div class="card"><h1>This payment method isn\'t wired up yet</h1>'
                .'<p>This site is configured to use "'.e($gatewayLabel).'" for payments. ERASED Commerce currently only processes orders for Manual Bank Transfer.</p>'
                .'<a class="btn secondary" href="/cart">Back to cart</a></div>');
            return;
        }
        if (trim(setting('payment_bank_account_name', '')) === '' || trim(setting('payment_bank_account_number', '')) === '') {
            layout('Checkout unavailable', '<div class="card"><h1>Checkout isn\'t ready yet</h1><p>Manual bank transfer is selected, but the account details haven\'t been configured yet. Please check back soon.</p><a class="btn secondary" href="/cart">Back to cart</a></div>');
            return;
        }

        $lines = $this->cart->lines();
        if ($lines === []) {
            redirect('/cart');
        }

        $currency = setting('payment_currency', 'EUR');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            try {
                $service = new CheckoutService(db(), $this->products, $this->coupons, $this->pricing());
                $result = $service->submit(
                    $lines,
                    trim((string)($_POST['customer_name'] ?? '')),
                    trim((string)($_POST['customer_email'] ?? '')),
                    $currency,
                    $this->cart->couponCode(),
                );
                $this->cart->clear();
                redirect('/checkout/confirmation/'.$result['order_id'].'/'.$result['confirmation_token']);
            } catch (RuntimeException $error) {
                flash('error', $error->getMessage());
                redirect('/checkout');
            }
        }

        $subtotalMinor = $this->cart->subtotalMinor();
        $resolved = $this->resolveCoupon($subtotalMinor);
        $pricing = $this->pricing()->calculate($subtotalMinor, $resolved['discount_minor']);
        $couponCode = $resolved['coupon'] !== null ? (string)$resolved['coupon']['code'] : null;
        $notice = $resolved['notice'] !== null ? '<p class="notice error">'.e($resolved['notice']).'</p>' : '';
        $breakdown = $this->breakdownHtml($subtotalMinor, $resolved['discount_minor'], $couponCode, $pricing['shipping_minor'], $pricing['tax_minor'], $pricing['total_minor'], $currency);

        $body = '<div class="toolbar"><div><h1>Checkout</h1></div></div>'
            .'<div class="card">'.$notice.$this->couponFormHtml($couponCode)
            .'<div style="margin-top:14px">'.$breakdown.'</div>'
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">'
            .'<label>Name<input name="customer_name" required></label>'
            .'<label>Email<input type="email" name="customer_email" required></label>'
            .'<div class="notice"><strong>Manual bank transfer</strong><p>'.nl2br(e(setting('payment_bank_instructions', 'Use the order number as the payment reference.'))).'</p>'
            .'<p>Account: '.e(setting('payment_bank_account_name', '')).' &middot; '.e(setting('payment_bank_account_number', '')).'</p></div>'
            .'<button class="btn" type="submit">Place order</button></form></div>';
        layout('Checkout', $body);
    }

    private function confirmation(int $orderId, string $token): void
    {
        $pdo = db();
        $orders = new OrderRepository($pdo, $this->products, $this->coupons);
        $order = $orders->findByToken($orderId, $token);
        if ($order === null) {
            erased_public_not_found();
            return;
        }
        $items = $orders->items($orderId);
        $isPaid = $order['status'] === 'paid';
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<li>'.(int)$item['quantity'].'&times; '.e((string)$item['product_name']).' &mdash; '.number_format((int)$item['line_total_minor'] / 100, 2).' '.e((string)$item['currency']);
            $itemsHtml .= $this->fulfillmentHtml($pdo, $item, $isPaid, $orderId);
            $itemsHtml .= '</li>';
        }
        $breakdown = $this->breakdownHtml(
            (int)$order['subtotal_minor'],
            (int)$order['discount_minor'],
            $order['coupon_code'] !== null ? (string)$order['coupon_code'] : null,
            (int)$order['shipping_minor'],
            (int)$order['tax_minor'],
            (int)$order['total_minor'],
            (string)$order['currency'],
        );
        $paymentNotice = $isPaid
            ? '<div class="notice"><strong>Payment confirmed</strong><p>Thanks - this order has been paid.</p></div>'
            : '<div class="notice"><strong>Next step: bank transfer</strong><p>'.nl2br(e(setting('payment_bank_instructions', 'Use the order number as the payment reference.'))).'</p>'
                .'<p>Account: '.e(setting('payment_bank_account_name', '')).' &middot; '.e(setting('payment_bank_account_number', '')).'</p>'
                .'<p>Reference: '.e((string)$order['order_number']).'</p></div>';
        $body = '<div class="card"><h1>Thank you, '.e((string)$order['customer_name']).'</h1>'
            .'<p>Your order <strong>'.e((string)$order['order_number']).'</strong> has been received'.($isPaid ? '.' : ' and is awaiting payment confirmation.').'</p>'
            .'<ul>'.$itemsHtml.'</ul>'.$breakdown
            .$paymentNotice.'</div>';
        layout('Order confirmed', $body);
    }

    /** @param array<string,mixed> $item */
    private function fulfillmentHtml(\PDO $pdo, array $item, bool $isPaid, int $orderId): string
    {
        $kind = (string)($item['product_kind'] ?? 'physical');
        if ($kind === 'digital') {
            if (!$isPaid) {
                return ' <span class="muted">(download link available once payment is confirmed)</span>';
            }
            $lookup = $pdo->prepare(
                'SELECT d.token, f.original_filename FROM commerce_downloads d '
                .'JOIN commerce_product_files f ON f.id = d.product_file_id WHERE d.order_item_id=?'
            );
            $lookup->execute([(int)$item['id']]);
            $links = '';
            foreach ($lookup->fetchAll() as $row) {
                $links .= ' <a href="/commerce/download/'.(int)$item['id'].'/'.e((string)$row['token']).'">Download '.e((string)$row['original_filename']).'</a>';
            }
            return $links !== '' ? '<br>'.$links : '';
        }
        if ($kind === 'subscription') {
            if (!$isPaid) {
                return ' <span class="muted">(subscription activates once payment is confirmed)</span>';
            }
            $lookup = $pdo->prepare("SELECT current_period_end FROM commerce_subscriptions WHERE order_id=? AND product_name=? LIMIT 1");
            $lookup->execute([$orderId, (string)$item['product_name']]);
            $row = $lookup->fetch();
            if (is_array($row)) {
                return '<br><span class="muted">Active until '.e((string)$row['current_period_end']).'</span>';
            }
        }
        return '';
    }

    /** @param array<string,mixed> $product */
    private function productImage(array $product): string
    {
        if (!empty($product['featured_media_id']) && function_exists('media_by_id') && ($media = media_by_id((int)$product['featured_media_id']))) {
            return '<img class="commerce-pimg" src="'.e(media_url($media)).'" alt="'.e((string)$product['name']).'" loading="lazy">';
        }
        return '<div class="commerce-pimg-placeholder" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 7.5 12 3 3 7.5m18 0-9 4.5m9-4.5v9L12 21m0-9L3 7.5m9 9L3 16.5v-9"/></svg></div>';
    }
}
