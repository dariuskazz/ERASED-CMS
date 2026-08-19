<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

require_once __DIR__.'/../Domain/ProductRepository.php';
require_once __DIR__.'/../Domain/CouponRepository.php';
require_once __DIR__.'/../Domain/OrderRepository.php';

use ErasedCommerce\Domain\CouponRepository;
use ErasedCommerce\Domain\OrderRepository;
use ErasedCommerce\Domain\ProductRepository;

final class OrdersAdminRoute
{
    private readonly OrderRepository $orders;

    public function __construct()
    {
        $pdo = db();
        $this->orders = new OrderRepository($pdo, new ProductRepository($pdo), new CouponRepository($pdo));
    }

    public function handle(string $id = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if ($path === '/admin/commerce/orders/calendar') {
            $this->calendar();
            return;
        }
        if ($id !== '') {
            $this->detail((int)$id);
            return;
        }
        $this->list();
    }

    private function list(): void
    {
        $dateFilter = trim((string)($_GET['date'] ?? ''));
        $isFiltered = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter) === 1;
        $rows = $isFiltered ? $this->orders->forDate($dateFilter) : $this->orders->all();
        $html = '';
        foreach ($rows as $row) {
            $pill = match ($row['status']) {
                'paid' => '<span class="stampword live">Paid</span>',
                'cancelled', 'refunded' => '<span class="stampword" style="color:var(--warn)">'.e(ucfirst((string)$row['status'])).'</span>',
                default => '<span class="stampword draft">Pending</span>',
            };
            $total = number_format((int)$row['total_minor'] / 100, 2).' '.e((string)$row['currency']);
            $html .= '<div class="admin-row"><div class="admin-row-body">'
                .'<div class="admin-row-title">'.e((string)$row['order_number']).' '.$pill.'</div>'
                .'<div class="admin-row-meta">'.$total.' &middot; '.e((string)$row['customer_name']).' &middot; '.e((string)$row['customer_email']).' &middot; '.e((string)$row['created_at']).'</div>'
                .'</div><div class="admin-row-actions"><a class="btn tertiary" href="/admin/commerce/orders/'.(int)$row['id'].'">View</a></div></div>';
        }
        $subtitle = $isFiltered
            ? 'Orders placed on '.e($dateFilter).'. <a href="/admin/commerce/orders">Clear filter</a>'
            : 'Manual bank transfer orders awaiting confirmation and their history.';
        $body = '<div class="title-row"><div><p class="kicker">SHEET C-02 &middot; COMMERCE</p><h1>Orders</h1><p>'.$subtitle.'</p></div><div class="actions"><a class="btn ghost" href="/admin/commerce/orders/calendar">Calendar</a></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>'.($isFiltered ? 'Orders on '.e($dateFilter) : 'All orders').'</h2></div><div class="panel-body"><div class="admin-row-list">'.($html ?: '<div class="admin-row-empty">No orders'.($isFiltered ? ' on this date.' : ' yet.').'</div>').'</div></div></div>';
        layout('Orders', $body, true);
    }

    private function calendar(): void
    {
        $monthInput = trim((string)($_GET['month'] ?? ''));
        $month = preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1 ? $monthInput.'-01' : date('Y-m-01');
        $monthStart = date('Y-m-01', strtotime($month));
        $monthEndExclusive = date('Y-m-01', strtotime($monthStart.' +1 month'));
        $prevMonth = date('Y-m', strtotime($monthStart.' -1 month'));
        $nextMonth = date('Y-m', strtotime($monthEndExclusive));
        $daysInMonth = (int)date('t', strtotime($monthStart));
        $firstWeekday = (int)date('N', strtotime($monthStart)); // 1=Mon..7=Sun

        $activity = [];
        foreach ($this->orders->activityByRange($monthStart, $monthEndExclusive) as $row) {
            $activity[$row['day']] = $row;
        }

        $currency = setting('payment_currency', 'EUR');
        $fmt = static fn(int $minor): string => number_format($minor / 100, 2).' '.e($currency);

        $cells = '';
        for ($blank = 1; $blank < $firstWeekday; $blank++) {
            $cells .= '<div class="commerce-cal-cell empty"></div>';
        }
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = date('Y-m-d', strtotime($monthStart.' +'.($day - 1).' days'));
            $stats = $activity[$dateStr] ?? null;
            $count = $stats !== null ? (int)$stats['order_count'] : 0;
            $inner = '<div class="commerce-cal-day">'.$day.'</div>';
            if ($count > 0) {
                $inner .= '<div class="commerce-cal-count">'.$count.' order'.($count === 1 ? '' : 's').'</div>';
                if ((int)$stats['paid_minor'] > 0) {
                    $inner .= '<div class="commerce-cal-revenue">'.$fmt((int)$stats['paid_minor']).'</div>';
                }
                $cells .= '<a class="commerce-cal-cell active" href="/admin/commerce/orders?date='.$dateStr.'">'.$inner.'</a>';
            } else {
                $cells .= '<div class="commerce-cal-cell">'.$inner.'</div>';
            }
        }

        $weekdayHeaders = '';
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label) {
            $weekdayHeaders .= '<div class="commerce-cal-weekday">'.$label.'</div>';
        }

        $style = '<style>'
            .'.commerce-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--line);border:1px solid var(--line);margin-top:12px}'
            .'.commerce-cal-weekday{background:var(--sheet-2);color:var(--ink-dim);font-family:var(--font-mono);font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;text-align:center;padding:6px 0}'
            .'.commerce-cal-cell{background:var(--sheet);min-height:78px;padding:6px;text-decoration:none;color:inherit;display:block}'
            .'.commerce-cal-cell.empty{background:var(--paper)}'
            .'.commerce-cal-cell.active{background:var(--accent-wash);cursor:pointer}'
            .'.commerce-cal-cell.active:hover{background:var(--good-wash)}'
            .'.commerce-cal-day{font-family:var(--font-mono);font-size:.8rem;color:var(--ink-dim)}'
            .'.commerce-cal-count{font-size:.75rem;font-weight:700;margin-top:4px;color:var(--ink)}'
            .'.commerce-cal-revenue{font-size:.72rem;color:var(--good);margin-top:2px}'
            .'</style>';

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-06 &middot; COMMERCE</p><h1>Order Calendar</h1><p>Sales activity by day. Order count is every status; the amount shown is realized revenue (paid orders only).</p></div><div class="actions"><a class="btn ghost" href="/admin/commerce/orders">All orders</a></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>'.e(date('F Y', strtotime($monthStart))).'</h2></div>'
            .'<div class="panel-body">'
            .'<div class="admin-row-actions"><a class="btn ghost" href="/admin/commerce/orders/calendar?month='.$prevMonth.'">&lsaquo; Previous</a><a class="btn ghost" href="/admin/commerce/orders/calendar?month='.$nextMonth.'">Next &rsaquo;</a></div>'
            .'<div class="commerce-cal-grid">'.$weekdayHeaders.$cells.'</div>'
            .'</div></div>'.$style;
        layout('Order Calendar', $body, true);
    }

    private function detail(int $id): void
    {
        $order = fetch_or_404('commerce_orders', $id);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'mark_paid') {
                try {
                    $warnings = $this->orders->markPaid($id);
                    audit('commerce.order.mark_paid', ['id' => $id]);
                    foreach ($warnings as $warning) {
                        flash('error', $warning);
                    }
                    if ($warnings === []) {
                        flash('success', 'Order marked as paid.');
                    }
                } catch (\Throwable $error) {
                    flash('error', $error->getMessage());
                }
            }
            redirect('/admin/commerce/orders/'.$id);
        }

        $items = $this->orders->items($id);
        $itemsHtml = '';
        foreach ($items as $item) {
            $lineTotal = number_format((int)$item['line_total_minor'] / 100, 2).' '.e((string)$item['currency']);
            $itemsHtml .= '<tr><td>'.e((string)$item['product_name']).'</td><td>'.(int)$item['quantity'].'</td><td>'.number_format((int)$item['unit_price_minor'] / 100, 2).'</td><td>'.$lineTotal.'</td></tr>';
        }

        $pill = match ($order['status']) {
            'paid' => '<span class="stampword live">Paid</span>',
            'cancelled', 'refunded' => '<span class="stampword" style="color:var(--warn)">'.e(ucfirst((string)$order['status'])).'</span>',
            default => '<span class="stampword draft">Pending</span>',
        };
        $currency = (string)$order['currency'];
        $fmt = static fn(int $minor): string => number_format($minor / 100, 2).' '.e($currency);

        $breakdown = '<p style="margin-top:12px">Subtotal: '.$fmt((int)$order['subtotal_minor']).'</p>';
        if ((int)$order['discount_minor'] > 0) {
            $breakdown .= '<p>Discount'.($order['coupon_code'] !== null ? ' ('.e((string)$order['coupon_code']).')' : '').': -'.$fmt((int)$order['discount_minor']).'</p>';
        }
        $breakdown .= '<p>Shipping: '.((int)$order['shipping_minor'] > 0 ? $fmt((int)$order['shipping_minor']) : 'Free').'</p>';
        if ((int)$order['tax_minor'] > 0) {
            $breakdown .= '<p>Tax: '.$fmt((int)$order['tax_minor']).'</p>';
        }
        $breakdown .= '<p><strong>Total: '.$fmt((int)$order['total_minor']).'</strong></p>';

        $markPaidForm = $order['status'] === 'pending'
            ? '<form method="post" onsubmit="return confirm(&quot;Mark this order as paid? This decrements stock for its line items.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="mark_paid"><button class="btn" type="submit">Mark as paid</button></form>'
            : '';

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-02 &middot; COMMERCE</p><h1>'.e((string)$order['order_number']).' '.$pill.'</h1></div><div class="actions"><a class="btn tertiary" href="/admin/commerce/orders">Back to orders</a></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Customer</h2></div><div class="panel-body"><p>'.e((string)$order['customer_name']).' &middot; '.e((string)$order['customer_email']).'</p><p class="muted">Placed '.e((string)$order['created_at']).'</p></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Items</h2></div><div class="panel-body"><table><thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead><tbody>'.$itemsHtml.'</tbody></table>'.$breakdown.'</div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Payment</h2></div><div class="panel-body"><p class="muted">Manual bank transfer. Confirm the transfer arrived before marking this order as paid.</p>'.$markPaidForm.'</div></div>';
        layout('Order '.$order['order_number'], $body, true);
    }
}
