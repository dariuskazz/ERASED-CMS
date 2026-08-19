<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

require_once __DIR__.'/../Domain/CouponRepository.php';

use ErasedCommerce\Domain\CouponRepository;

final class CouponsAdminRoute
{
    private readonly CouponRepository $coupons;

    public function __construct()
    {
        $this->coupons = new CouponRepository(db());
    }

    public function handle(string $id = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if ($id !== '' && str_ends_with($path, '/delete')) {
            $this->delete((int)$id);
            return;
        }
        if ($id !== '') {
            $this->edit((int)$id);
            return;
        }
        if ($path === '/admin/commerce/coupons/new') {
            $this->create();
            return;
        }
        $this->list();
    }

    private function list(): void
    {
        $rows = $this->coupons->all();
        $html = '';
        foreach ($rows as $row) {
            $statusPill = (int)$row['active'] === 1 ? '<span class="stampword live">Active</span>' : '<span class="stampword draft">Inactive</span>';
            $valueLabel = $row['type'] === 'percent' ? (int)$row['value'].'% off' : number_format((int)$row['value'] / 100, 2).' off';
            $usage = (int)$row['used_count'].($row['max_uses'] !== null ? ' / '.(int)$row['max_uses'] : '').' used';
            $html .= '<div class="admin-row"><div class="admin-row-body">'
                .'<div class="admin-row-title">'.e((string)$row['code']).' '.$statusPill.'</div>'
                .'<div class="admin-row-meta">'.e($valueLabel).' &middot; '.e($usage).'</div>'
                .'</div><div class="admin-row-actions">'
                .'<a class="btn ghost" href="/admin/commerce/coupons/'.(int)$row['id'].'/edit">Edit</a>'
                .'<form method="post" action="/admin/commerce/coupons/'.(int)$row['id'].'/delete" onsubmit="return confirm(&quot;Delete this coupon?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Delete</button></form>'
                .'</div></div>';
        }
        $body = '<div class="title-row"><div><p class="kicker">SHEET C-03 &middot; COMMERCE</p><h1>Coupons</h1><p>Discount codes customers can apply at checkout.</p></div><div class="actions"><a class="btn" href="/admin/commerce/coupons/new">New coupon</a></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All coupons</h2></div><div class="panel-body"><div class="admin-row-list">'.($html ?: '<div class="admin-row-empty">No coupons yet.</div>').'</div></div></div>';
        layout('Coupons', $body, true);
    }

    private function create(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $data = $this->readFormInput();
            if ($data === null) {
                return;
            }
            $id = $this->coupons->create($data);
            audit('commerce.coupon.create', ['id' => $id]);
            flash('success', 'Coupon created.');
            redirect('/admin/commerce/coupons');
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-03 &middot; COMMERCE</p><h1>New coupon</h1></div></div>'.$this->couponForm([]);
        layout('New coupon', $body, true);
    }

    private function edit(int $id): void
    {
        $coupon = fetch_or_404('commerce_coupons', $id);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $data = $this->readFormInput();
            if ($data === null) {
                return;
            }
            $this->coupons->update($id, $data);
            audit('commerce.coupon.update', ['id' => $id]);
            flash('success', 'Coupon saved.');
            redirect('/admin/commerce/coupons/'.$id.'/edit');
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-03 &middot; COMMERCE</p><h1>Edit coupon</h1></div></div>'.$this->couponForm($coupon);
        layout('Edit coupon', $body, true);
    }

    private function delete(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/coupons');
        }
        verify_csrf();
        $this->coupons->delete($id);
        audit('commerce.coupon.delete', ['id' => $id]);
        flash('success', 'Coupon deleted.');
        redirect('/admin/commerce/coupons');
    }

    /** @return array<string,mixed>|null null when validation failed (flash set, caller should return without redirecting) */
    private function readFormInput(): ?array
    {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $type = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';

        if ($code === '') {
            flash('error', 'Enter a coupon code.');
            return null;
        }

        if ($type === 'percent') {
            $percent = (int)($_POST['value_percent'] ?? 0);
            if ($percent < 1 || $percent > 100) {
                flash('error', 'Enter a percentage between 1 and 100.');
                return null;
            }
            $value = $percent;
        } else {
            $fixedInput = trim((string)($_POST['value_fixed'] ?? ''));
            if (!is_numeric($fixedInput) || (float)$fixedInput <= 0) {
                flash('error', 'Enter a fixed discount amount greater than zero.');
                return null;
            }
            $value = (int)round((float)$fixedInput * 100);
        }

        return [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'max_uses' => trim((string)($_POST['max_uses'] ?? '')) !== '' ? (int)$_POST['max_uses'] : null,
            'starts_at' => $this->datetimeLocalToSql((string)($_POST['starts_at'] ?? '')),
            'expires_at' => $this->datetimeLocalToSql((string)($_POST['expires_at'] ?? '')),
            'active' => isset($_POST['active']),
        ];
    }

    private function datetimeLocalToSql(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return str_replace('T', ' ', $value).':00';
    }

    /** @param array<string,mixed> $coupon */
    private function couponForm(array $coupon): string
    {
        $type = $coupon['type'] ?? 'percent';
        $percentValue = $type === 'percent' ? (string)($coupon['value'] ?? '') : '';
        $fixedValue = $type === 'fixed' ? number_format((int)($coupon['value'] ?? 0) / 100, 2, '.', '') : '';
        $startsAt = isset($coupon['starts_at']) && $coupon['starts_at'] !== null ? str_replace(' ', 'T', substr((string)$coupon['starts_at'], 0, 16)) : '';
        $expiresAt = isset($coupon['expires_at']) && $coupon['expires_at'] !== null ? str_replace(' ', 'T', substr((string)$coupon['expires_at'], 0, 16)) : '';
        $active = !isset($coupon['active']) || (int)$coupon['active'] === 1;

        return '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body">'
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">'
            .'<div class="fgrid"><div class="fslot"><label>Code<input name="code" value="'.e((string)($coupon['code'] ?? '')).'" required style="text-transform:uppercase"></label></div>'
            .'<div class="fslot"><label>Type<select name="type"><option value="percent"'.($type === 'percent' ? ' selected' : '').'>Percentage off</option><option value="fixed"'.($type === 'fixed' ? ' selected' : '').'>Fixed amount off</option></select></label></div></div>'
            .'<div class="fgrid"><div class="fslot"><label>Percent off (%) &mdash; used when type is Percentage<input type="number" min="1" max="100" name="value_percent" value="'.e($percentValue).'"></label></div>'
            .'<div class="fslot"><label>Fixed amount off &mdash; used when type is Fixed<input type="number" step="0.01" min="0" name="value_fixed" value="'.e($fixedValue).'"></label></div></div>'
            .'<div class="fgrid three"><div class="fslot"><label>Max uses (blank = unlimited)<input type="number" min="1" name="max_uses" value="'.e((string)($coupon['max_uses'] ?? '')).'"></label></div>'
            .'<div class="fslot"><label>Starts at (optional)<input type="datetime-local" name="starts_at" value="'.e($startsAt).'"></label></div>'
            .'<div class="fslot"><label>Expires at (optional)<input type="datetime-local" name="expires_at" value="'.e($expiresAt).'"></label></div></div>'
            .'<label class="check"><input type="checkbox" name="active" value="1"'.($active ? ' checked' : '').'> Active</label>'
            .'<button class="btn" type="submit" style="margin-top:14px">Save coupon</button>'
            .'</form></div></div>';
    }
}
