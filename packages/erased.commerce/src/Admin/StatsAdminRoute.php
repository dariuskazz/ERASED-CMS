<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

require_once __DIR__ . '/../Domain/StatsRepository.php';
require_once __DIR__ . '/../Domain/ProductViewRepository.php';
require_once __DIR__ . '/../Domain/OrderRepository.php';
require_once __DIR__ . '/../Domain/ProductRepository.php';
require_once __DIR__ . '/../Domain/CouponRepository.php';

use ErasedCommerce\Domain\CouponRepository;
use ErasedCommerce\Domain\OrderRepository;
use ErasedCommerce\Domain\ProductRepository;
use ErasedCommerce\Domain\ProductViewRepository;
use ErasedCommerce\Domain\StatsRepository;

/**
 * Statistics screen combining what WooCommerce Analytics, Shopify
 * Analytics, and GA4's ecommerce reporting each surface in some form
 * (revenue trend, order-status/cancellation rate, best sellers, most
 * viewed, category performance, low-stock alerts, coupon performance) -
 * reimplemented from scratch against this app's own StatsRepository/
 * ProductViewRepository, no code or markup from any of them.
 */
final class StatsAdminRoute
{
    private readonly StatsRepository $stats;
    private readonly ProductViewRepository $productViews;
    private readonly OrderRepository $orders;

    public function __construct()
    {
        $pdo = db();
        $this->stats = new StatsRepository($pdo);
        $this->productViews = new ProductViewRepository($pdo);
        $this->orders = new OrderRepository($pdo, new ProductRepository($pdo), new CouponRepository($pdo));
    }

    public function handle(): void
    {
        $corners = '<div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';

        $h = '<div class="title-row"><div><p class="kicker">SHEET C-08 &middot; COMMERCE</p><h1>Statistics</h1><p>Revenue, best sellers, visitor interest, and stock alerts across the whole catalog.</p></div></div>';
        $h .= $this->overviewHtml($corners);
        $h .= $this->trendHtml($corners);
        $h .= $this->twoColumnRow($this->topSellingHtml($corners), $this->mostViewedHtml($corners));
        $h .= $this->twoColumnRow($this->topCategoriesHtml($corners), $this->stockAlertsHtml($corners));
        $h .= $this->couponPerformanceHtml($corners);
        $h .= $this->styles();

        layout('Statistics', $h, true);
    }

    private function overviewHtml(string $corners): string
    {
        $revenue = $this->stats->revenueSummary();
        $breakdown = $this->stats->orderStatusBreakdown();
        $totalOrders = array_sum(array_column($breakdown, 'count'));
        $cancelled = $breakdown['cancelled']['count'] + $breakdown['refunded']['count'];
        $cancellationRate = $totalOrders > 0 ? round($cancelled / $totalOrders * 100, 1) : 0.0;
        $currency = setting('payment_currency', 'EUR');

        $tile = static fn (string $label, string $value, string $detail, bool $warn = false): string => '<div class="stat'.($warn ? ' warn' : '').'"><div class="stat-label">'.e($label).'</div><div class="stat-value">'.$value.'</div><div class="stat-detail"><span class="track">'.e($detail).'</span></div></div>';

        return '<section class="stats" style="margin:18px 0">'
            . $tile('Revenue (paid)', number_format($revenue['revenue_minor'] / 100, 2).' '.e($currency), $revenue['orders'].' paid orders')
            . $tile('Average Order Value', number_format($revenue['average_order_value_minor'] / 100, 2).' '.e($currency), 'per paid order')
            . $tile('Cancellation / Refund Rate', $cancellationRate.'%', $cancelled.' of '.$totalOrders.' orders', $cancellationRate > 10)
            . $tile('Orders Pending', (string)$breakdown['pending']['count'], 'awaiting payment', $breakdown['pending']['count'] > 0)
            . '</section>';
    }

    private function trendHtml(string $corners): string
    {
        $days = 30;
        $rows = $this->orders->activityByRange(date('Y-m-d', strtotime("-{$days} days")), date('Y-m-d', strtotime('+1 day')));
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row;
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $series[$day] = $byDay[$day] ?? ['order_count' => 0, 'total_minor' => 0, 'paid_minor' => 0];
        }
        $max = max(1, ...array_column($series, 'paid_minor'));

        $bars = '';
        foreach ($series as $day => $stat) {
            $heightPct = max(3, (int)round((int)$stat['paid_minor'] / $max * 100));
            $bars .= '<div class="commerce-stat-bar" style="height:'.$heightPct.'%" title="'.e($day).': '.number_format((int)$stat['paid_minor'] / 100, 2).' paid, '.(int)$stat['order_count'].' orders"></div>';
        }

        return '<div class="panel">'.$corners.'<div class="panel-head"><h2>Paid Revenue &mdash; Last 30 Days</h2></div><div class="panel-body">'
            . '<div class="commerce-stat-trend">'.$bars.'</div>'
            . '<div class="commerce-stat-trend-labels"><span>'.e(date('M j', strtotime(array_key_first($series)))).'</span><span>'.e(date('M j', strtotime(array_key_last($series)))).'</span></div>'
            . '</div></div>';
    }

    private function topSellingHtml(string $corners): string
    {
        $rows = $this->stats->topSellingProducts(10);
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr><td>'.e((string)$row['product_name']).'</td><td>'.number_format((int)$row['quantity']).'</td><td>'.number_format((int)$row['revenue_minor'] / 100, 2).'</td></tr>';
        }
        return '<div class="panel">'.$corners.'<div class="panel-head"><h2>Best Sellers</h2></div><div class="panel-body"><div style="overflow-x:auto"><table><thead><tr><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead><tbody>'
            . ($body !== '' ? $body : '<tr><td colspan="3" class="admin-row-empty">No paid orders yet.</td></tr>')
            . '</tbody></table></div></div></div>';
    }

    private function mostViewedHtml(string $corners): string
    {
        $viewed = $this->productViews->mostViewed(10, 30);
        $sold = $this->stats->topSellingProducts(1000);
        $soldByProduct = [];
        foreach ($sold as $row) {
            if ($row['product_id'] !== null) {
                $soldByProduct[(int)$row['product_id']] = (int)$row['quantity'];
            }
        }
        $body = '';
        foreach ($viewed as $row) {
            $views = (int)$row['total_views'];
            $purchases = $soldByProduct[(int)$row['id']] ?? 0;
            $conversion = $views > 0 ? round($purchases / $views * 100, 1) : 0.0;
            $body .= '<tr><td><a href="/shop/'.e((string)$row['slug']).'" target="_blank">'.e((string)$row['name']).'</a></td>'
                . '<td>'.number_format($views).'</td><td>'.number_format($purchases).'</td><td>'.$conversion.'%</td></tr>';
        }
        return '<div class="panel">'.$corners.'<div class="panel-head"><h2>Most Viewed (30 Days)</h2></div><div class="panel-body"><div style="overflow-x:auto"><table><thead><tr><th>Product</th><th>Views</th><th>Purchases</th><th>Conversion</th></tr></thead><tbody>'
            . ($body !== '' ? $body : '<tr><td colspan="4" class="admin-row-empty">No product views recorded yet.</td></tr>')
            . '</tbody></table></div></div></div>';
    }

    private function topCategoriesHtml(string $corners): string
    {
        $rows = $this->stats->topCategories(10);
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr><td>'.e((string)$row['category']).'</td><td>'.number_format((int)$row['quantity']).'</td><td>'.number_format((int)$row['revenue_minor'] / 100, 2).'</td></tr>';
        }
        return '<div class="panel">'.$corners.'<div class="panel-head"><h2>Revenue by Category</h2></div><div class="panel-body"><div style="overflow-x:auto"><table><thead><tr><th>Category</th><th>Units</th><th>Revenue</th></tr></thead><tbody>'
            . ($body !== '' ? $body : '<tr><td colspan="3" class="admin-row-empty">No paid orders yet.</td></tr>')
            . '</tbody></table></div></div></div>';
    }

    private function stockAlertsHtml(string $corners): string
    {
        $alerts = $this->stats->stockAlerts(5);
        $rows = '';
        foreach ($alerts['out'] as $row) {
            $rows .= '<tr><td>'.e((string)$row['name']).'</td><td><span class="commerce-stock-pill commerce-stock-out">Out of stock</span></td></tr>';
        }
        foreach ($alerts['low'] as $row) {
            $rows .= '<tr><td>'.e((string)$row['name']).'</td><td><span class="commerce-stock-pill commerce-stock-low">'.(int)$row['stock_quantity'].' left</span></td></tr>';
        }
        return '<div class="panel">'.$corners.'<div class="panel-head"><h2>Stock Alerts</h2></div><div class="panel-body"><div style="overflow-x:auto"><table><thead><tr><th>Product</th><th>Status</th></tr></thead><tbody>'
            . ($rows !== '' ? $rows : '<tr><td colspan="2" class="admin-row-empty">Everything is well stocked.</td></tr>')
            . '</tbody></table></div></div></div>';
    }

    private function couponPerformanceHtml(string $corners): string
    {
        $rows = $this->stats->couponPerformance();
        if ($rows === []) {
            return '';
        }
        $body = '';
        foreach ($rows as $row) {
            $valueLabel = $row['type'] === 'percent' ? (int)$row['value'].'% off' : number_format((int)$row['value'] / 100, 2).' off';
            $statusPill = (int)$row['active'] === 1 ? '<span class="stampword live">Active</span>' : '<span class="stampword draft">Inactive</span>';
            $usage = (int)$row['used_count'].($row['max_uses'] !== null ? ' / '.(int)$row['max_uses'] : '').' used';
            $body .= '<tr><td>'.e((string)$row['code']).' '.$statusPill.'</td><td>'.e($valueLabel).'</td><td>'.e($usage).'</td></tr>';
        }
        return '<div class="panel">'.$corners.'<div class="panel-head"><h2>Coupon Performance</h2></div><div class="panel-body"><div style="overflow-x:auto"><table><thead><tr><th>Code</th><th>Discount</th><th>Usage</th></tr></thead><tbody>'.$body.'</tbody></table></div></div></div>';
    }

    private function twoColumnRow(string $left, string $right): string
    {
        return '<div class="commerce-stats-two-col">'.$left.$right.'</div>';
    }

    private function styles(): string
    {
        return '<style>
.commerce-stats-two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:900px){.commerce-stats-two-col{grid-template-columns:1fr}}
.commerce-stat-trend{display:flex;align-items:flex-end;gap:3px;height:120px;padding-top:8px}
.commerce-stat-bar{flex:1 1 0;background:var(--accent);border-radius:2px 2px 0 0;min-height:3px;opacity:.85;transition:opacity .12s ease}
.commerce-stat-bar:hover{opacity:1}
.commerce-stat-trend-labels{display:flex;justify-content:space-between;margin-top:6px;font-family:var(--font-mono);font-size:10.5px;color:var(--ink-faint)}
.commerce-stock-pill{font-size:.72rem;font-weight:800;padding:3px 8px;border-radius:999px}
.commerce-stock-out{background:color-mix(in srgb,var(--danger,#ff7777) 25%,transparent);color:var(--danger,#ff7777)}
.commerce-stock-low{background:color-mix(in srgb,var(--warn,#d69e2e) 25%,transparent);color:var(--warn,#d69e2e)}
</style>';
    }
}
