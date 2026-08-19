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

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-04 &middot; COMMERCE</p><h1>'.e(tr('commerce_settings', 'admin')).'</h1>'
            .'<p>'.e(tr('commerce_settings_desc', 'admin')).'</p></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>'.e(tr('tax_and_shipping', 'admin')).'</h2></div><div class="panel-body">'
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">'
            .'<div class="fgrid three">'
            .'<div class="fslot"><label>'.e(tr('tax_rate_percent', 'admin')).'<input type="number" step="0.01" min="0" name="tax_rate_percent" value="'.e($taxPercent).'"></label></div>'
            .'<div class="fslot"><label>'.e(str_replace('{currency}', $currency, tr('flat_shipping_fee', 'admin'))).'<input type="number" step="0.01" min="0" name="shipping_flat" value="'.e($shippingFlat).'"></label></div>'
            .'<div class="fslot"><label>'.e(str_replace('{currency}', $currency, tr('free_shipping_above', 'admin'))).'<input type="number" step="0.01" min="0" name="shipping_free_threshold" value="'.e($thresholdDisplay).'"></label></div>'
            .'</div>'
            .$this->shopFrontFieldsHtml()
            .'<button class="btn" type="submit" style="margin-top:14px">'.e(tr('save_settings', 'admin')).'</button>'
            .'</form></div></div>';
        layout(tr('commerce_settings', 'admin'), $body, true);
    }

    private function shopFrontFieldsHtml(): string
    {
        $g = static fn (string $key): string => ShopFrontConfig::get($key);
        $checked = static fn (string $key): string => $g($key) === '1' ? ' checked' : '';

        $h = '<div class="panel-head" style="margin:28px -1px 0;border-top:1px solid var(--line);padding-top:20px"><h2>'.e(tr('shop_front_hero', 'admin')).'</h2></div>'
            . '<p class="muted" style="margin-top:0">'.e(tr('shop_front_hero_desc', 'admin')).'</p>'
            . '<label class="check"><input type="checkbox" name="shop_hero_enabled" value="1"' . $checked('shop_hero_enabled') . '> '.e(tr('show_hero_section', 'admin')).'</label>'
            . '<div class="fgrid three" style="margin-top:10px">'
            . '<div class="fslot"><label>'.e(tr('eyebrow_badge', 'admin')).'<input name="shop_hero_eyebrow" value="' . e($g('shop_hero_eyebrow')) . '"></label></div>'
            . '<div class="fslot"><label>'.e(tr('headline', 'admin')).'<input name="shop_hero_headline" value="' . e($g('shop_hero_headline')) . '"></label></div>'
            . '<div class="fslot"><label>'.e(tr('headline_emphasis', 'admin')).'<input name="shop_hero_headline_emphasis" value="' . e($g('shop_hero_headline_emphasis')) . '"></label></div>'
            . '</div>'
            . '<div class="fslot"><label>'.e(tr('description', 'admin')).'<textarea name="shop_hero_description" style="min-height:70px">' . e($g('shop_hero_description')) . '</textarea></label></div>'
            . '<div class="fgrid"><div class="fslot"><label>'.e(tr('primary_button_text', 'admin')).'<input name="shop_hero_cta_primary_text" value="' . e($g('shop_hero_cta_primary_text')) . '"></label></div>'
            . '<div class="fslot"><label>'.e(tr('primary_button_link', 'admin')).'<input name="shop_hero_cta_primary_url" value="' . e($g('shop_hero_cta_primary_url')) . '"></label></div></div>'
            . '<div class="fgrid"><div class="fslot"><label>'.e(tr('secondary_button_text', 'admin')).'<input name="shop_hero_cta_secondary_text" value="' . e($g('shop_hero_cta_secondary_text')) . '"></label></div>'
            . '<div class="fslot"><label>'.e(tr('secondary_button_link', 'admin')).'<input name="shop_hero_cta_secondary_url" value="' . e($g('shop_hero_cta_secondary_url')) . '"></label></div></div>'
            . '<div class="fgrid"><div class="fslot"><label>'.e(tr('third_stat_value', 'admin')).'<input name="shop_hero_stat3_value" value="' . e($g('shop_hero_stat3_value')) . '"></label></div>'
            . '<div class="fslot"><label>'.e(tr('third_stat_label', 'admin')).'<input name="shop_hero_stat3_label" value="' . e($g('shop_hero_stat3_label')) . '"></label></div></div>';

        $h .= '<div class="panel-head" style="margin:28px -1px 0;border-top:1px solid var(--line);padding-top:20px"><h2>'.e(tr('shop_front_trust_strip', 'admin')).'</h2></div>'
            . '<label class="check"><input type="checkbox" name="shop_trust_enabled" value="1"' . $checked('shop_trust_enabled') . '> '.e(tr('show_trust_strip', 'admin')).'</label>'
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
            $itemLabel = str_replace('{n}', (string)$i, tr('item_icon', 'admin'));
            $h .= '<div class="fslot" style="border:1px solid var(--line);border-radius:8px;padding:12px"><label>'.e($itemLabel).'<select name="shop_trust_' . $i . '_icon">' . $options . '</select></label>'
                . '<label style="margin-top:8px;display:block">'.e(tr('title', 'admin')).'<input name="shop_trust_' . $i . '_title" value="' . e($g('shop_trust_' . $i . '_title')) . '"></label>'
                . '<label style="margin-top:8px;display:block">'.e(tr('subtitle', 'admin')).'<input name="shop_trust_' . $i . '_subtitle" value="' . e($g('shop_trust_' . $i . '_subtitle')) . '"></label></div>';
        }
        $h .= '</div>';

        $h .= '<div class="panel-head" style="margin:28px -1px 0;border-top:1px solid var(--line);padding-top:20px"><h2>'.e(tr('shop_front_other_sections', 'admin')).'</h2></div>'
            . '<label class="check"><input type="checkbox" name="shop_category_rail_enabled" value="1"' . $checked('shop_category_rail_enabled') . '> '.e(tr('show_category_rail', 'admin')).'</label>'
            . '<label class="check"><input type="checkbox" name="shop_recommended_enabled" value="1"' . $checked('shop_recommended_enabled') . '> '.e(tr('show_recommended_carousel', 'admin')).'</label>';

        return $h;
    }
}
