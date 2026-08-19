<?php
declare(strict_types=1);

namespace ErasedNewsletter;

use PDO;
use Throwable;

final class SubscribersAdminRoute
{
    private readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function handle(?string $id = null): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if (str_ends_with($path, '/export')) {
            $this->exportCsv();
            return;
        }

        if (($id !== null && $id !== '') || preg_match('#^/admin/newsletter/subscribers/(\d+)/(toggle|delete)$#', $path, $matches)) {
            $subId = (int)($id ?? ($matches[1] ?? 0));
            if (str_ends_with($path, '/toggle')) {
                $this->toggleStatus($subId);
                return;
            }
            if (str_ends_with($path, '/delete')) {
                $this->deleteSubscriber($subId);
                return;
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->addSubscriber();
            return;
        }

        $this->renderList();
    }

    private function renderList(): void
    {
        $search = trim((string)($_GET['q'] ?? ''));
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));

        // Gather statistics
        $totalCount = (int)$this->pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
        $activeCount = (int)$this->pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'")->fetchColumn();
        $unsubscribedCount = (int)$this->pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'unsubscribed'")->fetchColumn();
        $todayCount = (int)$this->pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE subscribed_at >= CURDATE()")->fetchColumn();

        // Build query for list
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "email LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        if (in_array($statusFilter, ['active', 'unsubscribed'], true)) {
            $where[] = "status = :status";
            $params[':status'] = $statusFilter;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscribers {$whereSql} ORDER BY subscribed_at DESC, id DESC LIMIT 500");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Build HTML with tr() translations
        $html = '<div class="title-row"><div><p class="kicker">SHEET NL-01 &middot; MARKETING</p><h1>' . e(tr('newsletter_subscribers', 'admin')) . '</h1><p>Manage, add, filter, and export newsletter subscription records.</p></div>'
            . '<div class="actions"><a class="btn tertiary" href="/admin/newsletter/subscribers/export">' . e(tr('export_csv', 'admin')) . ' &rarr;</a></div></div>';

        // Stats grid
        $html .= '<div class="fgrid four" style="margin-bottom:20px;">'
            . '<div class="fslot"><label>' . e(tr('total_subscribers', 'admin')) . '<div style="font-size:1.6rem;font-weight:700;">' . $totalCount . '</div></label></div>'
            . '<div class="fslot"><label>' . e(tr('active_subscribers', 'admin')) . '<div style="font-size:1.6rem;font-weight:700;color:var(--success, #2dfc98);">' . $activeCount . '</div></label></div>'
            . '<div class="fslot"><label>' . e(tr('unsubscribed_subscribers', 'admin')) . '<div style="font-size:1.6rem;font-weight:700;color:var(--muted, #888);">' . $unsubscribedCount . '</div></label></div>'
            . '<div class="fslot"><label>' . e(tr('new_today', 'admin')) . '<div style="font-size:1.6rem;font-weight:700;">' . $todayCount . '</div></label></div>'
            . '</div>';

        // Search & Add panel
        $html .= '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Filter &amp; Add Subscriber</h2></div><div class="panel-body">'
            . '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;">'
            . '<form method="get" action="/admin/newsletter/subscribers" style="display:flex;gap:10px;flex:1;min-width:280px;align-items:flex-end;">'
            . '<div class="fslot" style="flex:1;"><label>' . e(tr('search', 'admin')) . '<input type="search" name="q" value="' . e($search) . '" placeholder="Search email address..."></label></div>'
            . '<div class="fslot" style="width:160px;"><label>' . e(tr('status', 'admin')) . '<select name="status">'
            . '<option value="all"' . ($statusFilter === 'all' ? ' selected' : '') . '>All Statuses</option>'
            . '<option value="active"' . ($statusFilter === 'active' ? ' selected' : '') . '>' . e(tr('account_active', 'admin')) . '</option>'
            . '<option value="unsubscribed"' . ($statusFilter === 'unsubscribed' ? ' selected' : '') . '>' . e(tr('unsubscribed_subscribers', 'admin')) . '</option>'
            . '</select></label></div>'
            . '<button type="submit" class="btn ghost" style="margin-bottom:2px;">' . e(tr('filter', 'admin')) . '</button>'
            . ($search !== '' || $statusFilter !== 'all' ? '<a href="/admin/newsletter/subscribers" class="btn ghost" style="margin-bottom:2px;">Clear</a>' : '')
            . '</form>'
            . '<div style="border-left:1px solid var(--line);padding-left:20px;min-width:280px;flex:1;">'
            . '<form method="post" action="/admin/newsletter/subscribers" style="display:flex;gap:10px;align-items:flex-end;"><input type="hidden" name="csrf" value="' . csrf() . '">'
            . '<div class="fslot" style="flex:1;"><label>Add Email<input type="email" name="email" required placeholder="user@example.com"></label></div>'
            . '<button type="submit" class="btn" style="margin-bottom:2px;">+ ' . e(tr('add_subscriber', 'admin')) . '</button>'
            . '</form></div>'
            . '</div>'
            . '</div></div>';

        // Subscribers table
        $html .= '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            . '<div class="panel-head"><h2>Subscriber Records (' . count($rows) . ')</h2></div><div class="panel-body">';

        if ($rows === []) {
            $html .= '<div class="admin-row-empty">No subscribers found matching your criteria.</div>';
        } else {
            $html .= '<div class="admin-row-list">';
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $email = (string)$row['email'];
                $status = (string)$row['status'];
                $subscribedAt = (string)$row['subscribed_at'];
                $unsubscribedAt = $row['unsubscribed_at'] ? (string)$row['unsubscribed_at'] : null;

                $statusBadge = $status === 'active'
                    ? '<span class="badge success" style="background:rgba(45,252,152,0.15);color:#2dfc98;padding:2px 8px;border-radius:6px;font-size:0.8rem;font-weight:600;">' . e(tr('account_active', 'admin')) . '</span>'
                    : '<span class="badge" style="background:rgba(128,128,128,0.2);color:#aaa;padding:2px 8px;border-radius:6px;font-size:0.8rem;">' . e(tr('unsubscribed_subscribers', 'admin')) . '</span>';

                $metaInfo = 'Subscribed: ' . e($subscribedAt);
                if ($unsubscribedAt) {
                    $metaInfo .= ' &middot; Unsubscribed: ' . e($unsubscribedAt);
                }

                $toggleLabel = $status === 'active' ? tr('mark_unsubscribed', 'admin') : tr('reactivate', 'admin');
                $toggleConfirm = $status === 'active' ? "Unsubscribe {$email}?" : "Reactivate {$email}?";

                $html .= '<div class="admin-row"><div class="admin-row-body">'
                    . '<div class="admin-row-title" style="display:flex;align-items:center;gap:12px;">'
                    . '<strong>' . e($email) . '</strong> ' . $statusBadge . '</div>'
                    . '<div class="admin-row-meta">' . $metaInfo . '</div>'
                    . '</div><div class="admin-row-actions" style="display:flex;gap:8px;">'
                    . '<form method="post" action="/admin/newsletter/subscribers/' . $id . '/toggle" style="margin:0;" onsubmit="return confirm(' . e(json_encode($toggleConfirm)) . ')"><input type="hidden" name="csrf" value="' . csrf() . '"><button class="btn ghost" style="padding:4px 8px;font-size:0.8rem;">' . e($toggleLabel) . '</button></form>'
                    . '<form method="post" action="/admin/newsletter/subscribers/' . $id . '/delete" style="margin:0;" onsubmit="return confirm(\'Permanently delete ' . e($email) . '?\')"><input type="hidden" name="csrf" value="' . csrf() . '"><button class="btn danger" style="padding:4px 8px;font-size:0.8rem;">Delete</button></form>'
                    . '</div></div>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';

        layout(tr('newsletter_subscribers', 'admin'), $html, true);
    }

    private function addSubscriber(): void
    {
        verify_csrf();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('/admin/newsletter/subscribers');
        }

        try {
            $token = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare("INSERT INTO newsletter_subscribers(email, status, token, subscribed_at, unsubscribed_at) VALUES (?, 'active', ?, NOW(), NULL) ON DUPLICATE KEY UPDATE status = 'active', token = VALUES(token), subscribed_at = NOW(), unsubscribed_at = NULL");
            $stmt->execute([$email, $token]);

            audit('newsletter.subscriber.add', ['email' => $email]);
            flash('success', "Subscriber {$email} added and activated.");
        } catch (Throwable $e) {
            flash('error', 'Could not add subscriber: ' . $e->getMessage());
        }

        redirect('/admin/newsletter/subscribers');
    }

    private function toggleStatus(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/newsletter/subscribers');
        }
        verify_csrf();

        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscribers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('error', 'Subscriber record not found.');
            redirect('/admin/newsletter/subscribers');
        }

        $newStatus = $row['status'] === 'active' ? 'unsubscribed' : 'active';
        $unsubAt = $newStatus === 'unsubscribed' ? date('Y-m-d H:i:s') : null;

        $update = $this->pdo->prepare("UPDATE newsletter_subscribers SET status = ?, unsubscribed_at = ? WHERE id = ?");
        $update->execute([$newStatus, $unsubAt, $id]);

        audit('newsletter.subscriber.toggle', ['id' => $id, 'email' => $row['email'], 'status' => $newStatus]);
        flash('success', "Subscriber {$row['email']} status changed to {$newStatus}.");

        redirect('/admin/newsletter/subscribers');
    }

    private function deleteSubscriber(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/newsletter/subscribers');
        }
        verify_csrf();

        $stmt = $this->pdo->prepare("SELECT email FROM newsletter_subscribers WHERE id = ?");
        $stmt->execute([$id]);
        $email = (string)$stmt->fetchColumn();

        if ($email !== '') {
            $del = $this->pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
            $del->execute([$id]);

            audit('newsletter.subscriber.delete', ['id' => $id, 'email' => $email]);
            flash('success', "Subscriber {$email} removed.");
        } else {
            flash('error', 'Subscriber record not found.');
        }

        redirect('/admin/newsletter/subscribers');
    }

    private function exportCsv(): void
    {
        $stmt = $this->pdo->query("SELECT id, email, status, subscribed_at, unsubscribed_at FROM newsletter_subscribers ORDER BY id ASC");
        $rows = $stmt->fetchAll();

        $filename = 'newsletter_subscribers_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Email', 'Status', 'Subscribed At', 'Unsubscribed At']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'],
                $row['email'],
                $row['status'],
                $row['subscribed_at'],
                $row['unsubscribed_at'] ?? ''
            ]);
        }

        fclose($out);
        exit;
    }
}
