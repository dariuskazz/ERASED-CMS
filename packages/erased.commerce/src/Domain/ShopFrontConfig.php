<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

/**
 * Single source of truth for the shop front's editable-copy defaults, read
 * by both the admin settings form (CommerceSettingsAdminRoute) and the
 * public storefront (StorefrontRoute) via setting($key, default) - kept in
 * one place so the two can never drift into showing different fallback
 * text for a setting nobody has customized yet.
 */
final class ShopFrontConfig
{
    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'shop_hero_enabled' => '1',
            'shop_hero_eyebrow' => 'Fresh stock, every week',
            'shop_hero_headline' => 'Parts and gear that actually fit your build.',
            'shop_hero_headline_emphasis' => 'actually fit',
            'shop_hero_description' => 'From OEM-spec brake pads to everyday electronics — one catalog, real stock counts, and search that understands a part number, not just a product name.',
            'shop_hero_cta_primary_text' => 'Shop Automotive Parts →',
            'shop_hero_cta_primary_url' => '/shop/category/automotive-parts',
            'shop_hero_cta_secondary_text' => 'Browse everything',
            'shop_hero_cta_secondary_url' => '/shop',
            'shop_hero_stat3_value' => '',
            'shop_hero_stat3_label' => '',
            'shop_trust_enabled' => '1',
            'shop_trust_1_icon' => 'shipping',
            'shop_trust_1_title' => 'Free shipping',
            'shop_trust_1_subtitle' => 'On orders over 50 USD',
            'shop_trust_2_icon' => 'secure',
            'shop_trust_2_title' => 'Secure checkout',
            'shop_trust_2_subtitle' => 'Bank transfer, verified orders',
            'shop_trust_3_icon' => 'returns',
            'shop_trust_3_title' => 'Easy returns',
            'shop_trust_3_subtitle' => '30-day window',
            'shop_trust_4_icon' => 'stock',
            'shop_trust_4_title' => 'Real stock counts',
            'shop_trust_4_subtitle' => 'No surprise backorders',
            'shop_category_rail_enabled' => '1',
            'shop_recommended_enabled' => '1',
        ];
    }

    /** @return array<string,string> label => value, for the icon preset <select> */
    public static function trustIconOptions(): array
    {
        return [
            'shipping' => 'Shipping truck',
            'secure' => 'Shield (secure checkout)',
            'returns' => 'Returns / refresh',
            'stock' => 'Check circle (stock accuracy)',
        ];
    }

    public static function get(string $key): string
    {
        $defaults = self::defaults();
        return setting($key, $defaults[$key] ?? '');
    }
}
