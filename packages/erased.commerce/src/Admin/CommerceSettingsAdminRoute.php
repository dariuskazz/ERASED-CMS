<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

require_once __DIR__ . '/../Domain/ShopFrontConfig.php';

use ErasedCommerce\Domain\ShopFrontConfig;

final class CommerceSettingsAdminRoute
{
    public function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();

            $taxPercentInput = trim((string)($_POST['tax_rate_percent'] ?? ''));
            $taxBps = is_numeric($taxPercentInput) ? max(0, (int)round((float)$taxPercentInput * 100)) : 0;
            set_setting('commerce_tax_rate_bps', (string)$taxBps);

            $shippingInput = trim((string)($_POST['shipping_flat'] ?? ''));
            $shippingMinor = is_numeric($shippingInput) ? max(0, (int)round((float)$shippingInput * 100)) : 0;
            set_setting('commerce_shipping_flat_minor', (string)$shippingMinor);

            $thresholdInput = trim((string)($_POST['shipping_free_threshold'] ?? ''));
            $thresholdMinor = $thresholdInput !== '' && is_numeric($thresholdInput) ? max(0, (int)round((float)$thresholdInput * 100)) : null;
            set_setting('commerce_shipping_free_threshold_minor', $thresholdMinor !== null ? (string)$thresholdMinor : '');

            set_setting('shop_hero_enabled', isset($_POST['shop_hero_enabled']) ? '1' : '0');
            set_setting('shop_hero_eyebrow', trim((string)($_POST['shop_hero_eyebrow'] ?? '')));
            set_setting('shop_hero_headline', trim((string)($_POST['shop_hero_headline'] ?? '')));
            set_setting('shop_hero_headline_emphasis', trim((string)($_POST['shop_hero_headline_emphasis'] ?? '')));
            set_setting('shop_hero_description', trim((string)($_POST['shop_hero_description'] ?? '')));
            set_setting('shop_hero_cta_primary_text', trim((string)($_POST['shop_hero_cta_primary_text'] ?? '')));
            set_setting('shop_hero_cta_primary_url', trim((string)($_POST['shop_hero_cta_primary_url'] ?? '')));
            set_setting('shop_hero_cta_secondary_text', trim((string)($_POST['shop_hero_cta_secondary_text'] ?? '')));
            set_setting('shop_hero_cta_secondary_url', trim((string)($_POST['shop_hero_cta_secondary_url'] ?? '')));
            set_setting('shop_hero_stat3_value', trim((string)($_POST['shop_hero_stat3_value'] ?? '')));
            set_setting('shop_hero_stat3_label', trim((string)($_POST['shop_hero_stat3_label'] ?? '')));

            set_setting('shop_trust_enabled', isset($_POST['shop_trust_enabled']) ? '1' : '0');
            for ($i = 1; $i <= 4; $i++) {
                set_setting('shop_trust_' . $i . '_icon', trim((string)($_POST['shop_trust_' . $i . '_icon'] ?? '')));
                set_setting('shop_trust_' . $i . '_title', trim((string)($_POST['shop_trust_' . $i . '_title'] ?? '')));
                set_setting('shop_trust_' . $i . '_subtitle', trim((string)($_POST['shop_trust_' . $i . '_subtitle'] ?? '')));
            }

            set_setting('shop_category_rail_enabled', isset($_POST['shop_category_rail_enabled']) ? '1' : '0');
            set_setting('shop_recommended_enabled', isset($_POST['shop_recommended_enabled']) ? '1' : '0');

            audit('commerce.settings.save', []);
            flash('success', 'Commerce settings saved.');
            redirect('/admin/commerce/settings');
        }

        $currency = setting('payment_currency', 'EUR');
        $taxPercent = number_format((int)setting('commerce_tax_rate_bps', '0') / 100, 2, '.', '');
        $shippingFlat = number_format((int)setting('commerce_shipping_flat_minor', '0') / 100, 2, '.', '');
        $threshold = setting('commerce_shipping_free_threshold_minor', '');
        $thresholdDisplay = $threshold !== '' ? number_format((int)$threshold / 100, 2, '.', '') : '';

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-04 &middot; COMMERCE</p><h1>Commerce Settings</h1>'
            .'<p>A single flat tax rate and shipping fee applied to every order &mdash; not real jurisdiction-based tax or carrier-rated shipping, since checkout collects no address to compute either against.</p></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Tax &amp; Shipping</h2></div><div class="panel-body">'
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">'
            .'<div class="fgrid three">'
            .'<div class="fslot"><label>Tax rate (%)<input type="number" step="0.01" min="0" name="tax_rate_percent" value="'.e($taxPercent).'"></label></div>'
            .'<div class="fslot"><label>Flat shipping fee ('.e($currency).')<input type="number" step="0.01" min="0" name="shipping_flat" value="'.e($shippingFlat).'"></label></div>'
            .'<div class="fslot"><label>Free shipping above ('.e($currency).', blank = no threshold)<input type="number" step="0.01" min="0" name="shipping_free_threshold" value="'.e($thresholdDisplay).'"></label></div>'
            .'</div>'
            .$this->shopFrontFieldsHtml()
            .'<button class="btn" type="submit" style="margin-top:14px">Save settings</button>'
            .'</form></div></div>';
        layout('Commerce Settings', $body, true);
    }

    private function shopFrontFieldsHtml(): string
    {
        $g = static fn (string $key): string => ShopFrontConfig::get($key);
        $checked = static fn (string $key): string => $g($key) === '1' ? ' checked' : '';

        $h = '<div class="panel-head" style="margin:28px -1px 0;border-top:1px solid var(--line);padding-top:20px"><h2>Shop Front &mdash; Hero</h2></div>'
            . '<p class="muted" style="margin-top:0">Controls the top of <a href="/shop" target="_blank">/shop</a>. Product count and category count in the stats row are always live; the third stat is yours to fill in (or leave blank to hide it).</p>'
            . '<label class="check"><input type="checkbox" name="shop_hero_enabled" value="1"' . $checked('shop_hero_enabled') . '> Show the hero section</label>'
            . '<div class="fgrid three" style="margin-top:10px">'
            . '<div class="fslot"><label>Eyebrow badge<input name="shop_hero_eyebrow" value="' . e($g('shop_hero_eyebrow')) . '"></label></div>'
            . '<div class="fslot"><label>Headline<input name="shop_hero_headline" value="' . e($g('shop_hero_headline')) . '"></label></div>'
            . '<div class="fslot"><label>Headline emphasis (must match a phrase in the headline exactly)<input name="shop_hero_headline_emphasis" value="' . e($g('shop_hero_headline_emphasis')) . '"></label></div>'
            . '</div>'
            . '<div class="fslot"><label>Description<textarea name="shop_hero_description" style="min-height:70px">' . e($g('shop_hero_description')) . '</textarea></label></div>'
            . '<div class="fgrid"><div class="fslot"><label>Primary button text<input name="shop_hero_cta_primary_text" value="' . e($g('shop_hero_cta_primary_text')) . '"></label></div>'
            . '<div class="fslot"><label>Primary button link<input name="shop_hero_cta_primary_url" value="' . e($g('shop_hero_cta_primary_url')) . '"></label></div></div>'
            . '<div class="fgrid"><div class="fslot"><label>Secondary button text<input name="shop_hero_cta_secondary_text" value="' . e($g('shop_hero_cta_secondary_text')) . '"></label></div>'
            . '<div class="fslot"><label>Secondary button link<input name="shop_hero_cta_secondary_url" value="' . e($g('shop_hero_cta_secondary_url')) . '"></label></div></div>'
            . '<div class="fgrid"><div class="fslot"><label>Third stat value (e.g. "4.9★", blank = hidden)<input name="shop_hero_stat3_value" value="' . e($g('shop_hero_stat3_value')) . '"></label></div>'
            . '<div class="fslot"><label>Third stat label (e.g. "Avg. rating")<input name="shop_hero_stat3_label" value="' . e($g('shop_hero_stat3_label')) . '"></label></div></div>';

        $h .= '<div class="panel-head" style="margin:28px -1px 0;border-top:1px solid var(--line);padding-top:20px"><h2>Shop Front &mdash; Trust Strip</h2></div>'
            . '<label class="check"><input type="checkbox" name="shop_trust_enabled" value="1"' . $checked('shop_trust_enabled') . '> Show the trust strip</label>'
            . '<div class="fgrid" style="margin-top:10px">';
        $iconOptions = '';
        foreach (ShopFrontConfig::trustIconOptions() as $value => $label) {
            $iconOptions .= '<option value="' . e($value) . '">' . e($label) . '</option>';
        }
        for ($i = 1; $i <= 4; $i++) {
            $iconKey = $g('shop_trust_' . $i . '_icon');
            $options = '';
            foreach (ShopFrontConfig::trustIconOptions() as $value => $label) {
                $options .= '<option value="' . e($value) . '"' . ($iconKey === $value ? ' selected' : '') . '>' . e($label) . '</option>';
            }
            $h .= '<div class="fslot" style="border:1px solid var(--line);border-radius:8px;padding:12px"><label>Item ' . $i . ' icon<select name="shop_trust_' . $i . '_icon">' . $options . '</select></label>'
                . '<label style="margin-top:8px;display:block">Title<input name="shop_trust_' . $i . '_title" value="' . e($g('shop_trust_' . $i . '_title')) . '"></label>'
                . '<label style="margin-top:8px;display:block">Subtitle<input name="shop_trust_' . $i . '_subtitle" value="' . e($g('shop_trust_' . $i . '_subtitle')) . '"></label></div>';
        }
        $h .= '</div>';

        $h .= '<div class="panel-head" style="margin:28px -1px 0;border-top:1px solid var(--line);padding-top:20px"><h2>Shop Front &mdash; Other Sections</h2></div>'
            . '<label class="check"><input type="checkbox" name="shop_category_rail_enabled" value="1"' . $checked('shop_category_rail_enabled') . '> Show the category rail</label>'
            . '<label class="check"><input type="checkbox" name="shop_recommended_enabled" value="1"' . $checked('shop_recommended_enabled') . '> Show the Recommended carousel</label>';

        return $h;
    }
}
