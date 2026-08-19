<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

final class SubscriptionsAdminRoute
{
    public function handle(string $id = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if ($id !== '' && str_ends_with($path, '/cancel')) {
            $this->cancel((int)$id);
            return;
        }
        $this->list();
    }

    private function cancel(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/subscriptions');
        }
        verify_csrf();
        $statement = db()->prepare("UPDATE commerce_subscriptions SET status='cancelled' WHERE id=? AND status='active'");
        $statement->execute([$id]);
        audit('commerce.subscription.cancel', ['id' => $id]);
        flash('success', 'Subscription cancelled.');
        redirect('/admin/commerce/subscriptions');
    }

    private function list(): void
    {
        $rows = db()->query('SELECT * FROM commerce_subscriptions ORDER BY created_at DESC')->fetchAll();
        $html = '';
        foreach ($rows as $row) {
            $pill = match ($row['status']) {
                'active' => '<span class="stampword live">Active</span>',
                'cancelled' => '<span class="stampword" style="color:var(--warn)">Cancelled</span>',
                default => '<span class="stampword draft">Expired</span>',
            };
            $cancelForm = $row['status'] === 'active'
                ? '<form method="post" action="/admin/commerce/subscriptions/'.(int)$row['id'].'/cancel" onsubmit="return confirm(&quot;Cancel this subscription?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn tertiary">Cancel</button></form>'
                : '';
            $html .= '<div class="admin-row"><div class="admin-row-body">'
                .'<div class="admin-row-title">'.e((string)$row['product_name']).' '.$pill.'</div>'
                .'<div class="admin-row-meta">'.e((string)$row['customer_email']).' &middot; '.e(ucfirst((string)$row['interval_name'])).'ly &middot; period ends '.e((string)$row['current_period_end']).'</div>'
                .'</div><div class="admin-row-actions">'.$cancelForm.'</div></div>';
        }
        $body = '<div class="title-row"><div><p class="kicker">SHEET C-05 &middot; COMMERCE</p><h1>Subscriptions</h1><p>Admin-managed subscription periods. Renewal is manual - marking a renewal order as paid extends the period automatically; no payment gateway re-charges a customer.</p></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All subscriptions</h2></div><div class="panel-body"><div class="admin-row-list">'.($html ?: '<div class="admin-row-empty">No subscriptions yet.</div>').'</div></div></div>';
        layout('Subscriptions', $body, true);
    }
}
