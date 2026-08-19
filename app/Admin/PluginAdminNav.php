<?php
/**
 * Plain admin-nav helper functions (like Components.php) rather than an
 * autoloaded class - these render directly into the two hand-written nav
 * copies (public/index.php's layout(), LayoutStudioAdminScreen::render()),
 * matching the style of the functions already sitting alongside them.
 */

declare(strict_types=1);

function admin_plugin_menu_group_html(string $group, string $requestPath): string
{
    if (!function_exists('plugin_admin_surface')) return '';
    $html = '';
    foreach (plugin_admin_surface()->menuEntries($group) as $entry) {
        if (!can($entry->permission())) continue;
        $active = $requestPath === $entry->path() || str_starts_with($requestPath, rtrim($entry->path(), '/').'/');
        $code = $entry->sheetCode() ?? ('P-'.strtoupper(substr(md5($entry->path()), 0, 2)));
        $html .= '<button type="button" class="rail-item '.($active ? 'is-active' : '').'" onclick="location.href=\''.e_js_str($entry->path()).'\'"><span class="rail-item-code">'.e($code).'</span>'.e($entry->label()).'</button>';
    }
    return $html;
}

function admin_plugin_extra_groups_html(string $requestPath): string
{
    if (!function_exists('plugin_admin_surface')) return '';
    $groups = plugin_admin_surface()->extraGroups();

    // Commerce's group collapses behind one "Ecommerce" toggle instead of a
    // flat list - it's the only extra group with enough items (up to 6) to
    // need it. A native <details> keeps this open/closed for free with no
    // JS, matching the "Reply"/translation-section collapse convention
    // already used elsewhere in this admin - and it works identically in
    // both hand-written nav copies (public/index.php, LayoutStudioAdminScreen)
    // since both already call this same function. It's nested under the
    // "Plugins" label rather than getting its own top-level section, since
    // that's the only other extra group today and Commerce is itself just
    // another installed package's contribution, not a first-class rail area.
    $commerceItemsHtml = admin_plugin_menu_group_html('Commerce', $requestPath);
    $ecommerceDropdownHtml = '';
    if ($commerceItemsHtml !== '') {
        $open = str_starts_with($requestPath, '/admin/commerce') ? ' open' : '';
        $ecommerceDropdownHtml = '<details class="rail-dropdown"'.$open.'><summary class="rail-item rail-dropdown-toggle"><span class="rail-item-code">PL-01</span>Ecommerce</summary><div class="rail-dropdown-panel">'.$commerceItemsHtml.'</div></details>';
    }

    $html = '';
    $ecommercePlaced = $ecommerceDropdownHtml === '';
    foreach ($groups as $group) {
        if ($group === 'Commerce') continue; // rendered inline under "Plugins" below, not as its own section
        $groupHtml = admin_plugin_menu_group_html($group, $requestPath);
        if ($groupHtml === '') continue;
        if ($group === 'Plugins') {
            $html .= '<p class="rail-group-label">Plugins</p>'.$ecommerceDropdownHtml.$groupHtml;
            $ecommercePlaced = true;
        } else {
            $html .= '<p class="rail-group-label">'.e($group).'</p>'.$groupHtml;
        }
    }
    // No "Plugins" group exists to nest Ecommerce under (e.g. erased.payments
    // isn't installed) - fall back to its own entry so Commerce nav isn't
    // silently dropped rather than only ever reachable through Plugins.
    if (!$ecommercePlaced) $html .= $ecommerceDropdownHtml;

    return $html;
}
