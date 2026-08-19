<?php
/**
 * ERASED CMS admin UI components.
 *
 * Phase 3.1 centralizes shared settings navigation and small presentation
 * helpers without changing route handlers or permission checks.
 */

declare(strict_types=1);

if (!function_exists('settings_hub_cards')) {
function settings_hub_cards(): string
{
    return '';
}
}

if (!function_exists('admin_nav')) {
function admin_nav(string $class, string $label, array $items, string $current): string
{
    if ($items === []) {
        return '';
    }

    $html = '<nav class="'.e($class).'" aria-label="'.e($label).'">';
    foreach ($items as $item) {
        [$key, $href, $text] = $item;
        $active = $current === $key || $current === $href;
        $html .= '<a class="'.($active ? 'active' : '').'" href="'.e($href).'"'.($active ? ' aria-current="page"' : '').'>'.e($text).'</a>';
    }

    return $html.'</nav>';
}
}

if (!function_exists('settings_subnav')) {
function settings_subnav(string $group, string $current): string
{
    $definitions = $group === 'packages'
        ? [
            ['/admin/packages', '/admin/packages', 'Packages & Add-ons', 'packages.manage'],
            ['/admin/backups', '/admin/backups', 'Database Backups', 'backups.manage'],
            ['/admin/core-update', '/admin/core-update', 'Core Update', 'core.update'],
        ]
        : [
            ['/admin/security', '/admin/security', 'Security', 'security.manage'],
        ];

    $items = [];
    foreach ($definitions as [$key, $href, $label, $permission]) {
        if (can($permission)) {
            $items[] = [$key, $href, $label];
        }
    }

    return admin_nav('settings-subnav', ucfirst($group), $items, $current);
}
}

if (!function_exists('admin_page_intro')) {
function admin_page_intro(string $title, string $description = '', string $actions = ''): string
{
    $html = '<div class="admin-page-intro"><div><h1>'.e($title).'</h1>';
    if ($description !== '') {
        $html .= '<p>'.e($description).'</p>';
    }
    $html .= '</div>';
    if ($actions !== '') {
        $html .= '<div class="admin-page-actions">'.$actions.'</div>';
    }
    return $html.'</div>';
}
}
