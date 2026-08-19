<?php
declare(strict_types=1);

/**
 * erased.payments is the Package Engine's first real paid package (every
 * other pricing.model=paid manifest in this codebase is a synthetic test
 * fixture, per tools/test-package-manifest-pricing.php) and the second
 * real exercise of admin_routes/admin_menu after erased.commerce - this
 * loads the real on-disk package.json rather than a fixture, so a typo in
 * the shipped file itself (not just PackageManifest's validation logic)
 * fails the suite.
 */

use Erased\Packages\PackageManifest;

$root = dirname(__DIR__);
require_once $root.'/app/Packages/PackageManifest.php';

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

try {
    $manifestPath = $root.'/packages/erased.payments/package.json';
    $raw = file_get_contents($manifestPath);
    if ($raw === false) {
        throw new RuntimeException('Could not read packages/erased.payments/package.json.');
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $manifest = new PackageManifest($data);

    $check($manifest->id() === 'erased.payments', 'id is erased.payments');
    $check($manifest->type() === 'module', 'type is module');

    // --- Paid package: the manifest itself declares real pricing, not a fixture default ---
    $check($manifest->isPaid() === true, 'isPaid() is true');
    $check($manifest->pricingModel() === 'paid', 'pricingModel() is paid');
    $check(is_int($manifest->priceMinor()) && $manifest->priceMinor() > 0, 'priceMinor() is a positive integer');
    $check($manifest->priceCurrency() !== null && preg_match('/^[A-Z]{3}$/', $manifest->priceCurrency()) === 1, 'priceCurrency() is a 3-letter uppercase code');

    // --- Declares payments.manage, the same permission core already grants admin (app/bootstrap.php role_permissions()) ---
    $permissionIds = array_map(static fn(array $p) => (string)($p['id'] ?? ''), $manifest->declaredPermissions());
    $check(in_array('payments.manage', $permissionIds, true), 'declares the payments.manage permission');

    // --- Serves /admin/payments via the generic admin_routes mechanism (InstalledPluginAdminSurface), not a core-hardcoded route ---
    $routes = $manifest->adminRoutes();
    $check(count($routes) === 1, 'declares exactly one admin route');
    $route = $routes[0] ?? [];
    $check(($route['path'] ?? null) === '/admin/payments', 'admin route path is /admin/payments');
    $check(($route['permission'] ?? null) === 'payments.manage', 'admin route requires payments.manage');
    $serviceId = (string)($route['service_id'] ?? '');
    $check($serviceId !== '', 'admin route declares a service_id');

    // --- The declared service_id resolves to a real, loadable file+class pair ---
    $services = $data['services'] ?? [];
    $check(isset($services[$serviceId]), 'admin route service_id matches a declared service');
    $serviceFile = (string)($services[$serviceId]['file'] ?? '');
    $serviceClass = (string)($services[$serviceId]['class'] ?? '');
    $serviceFullPath = dirname($manifestPath).'/'.$serviceFile;
    $check(is_file($serviceFullPath), 'the service file exists on disk: '.$serviceFile);
    require_once $serviceFullPath;
    $check(class_exists($serviceClass), 'the service class is loadable: '.$serviceClass);
    $check(method_exists($serviceClass, 'handle'), 'the service class has a handle() method');

    // --- Listed in its own "Plugins" admin_menu group, deliberately separate from Commerce's own
    // admin_routes-driven "Ecommerce" dropdown, so Payments doesn't fold into that dropdown's fixed item list ---
    $menu = $manifest->adminMenu();
    $check(count($menu) === 1 && ($menu[0]['group'] ?? null) === 'Plugins', 'admin menu entry is in the Plugins group');
    $check(($menu[0]['path'] ?? null) === '/admin/payments', 'admin menu entry links to /admin/payments');

    if ($fail === 0) {
        fwrite(STDOUT, "erased.payments manifest test passed.\n");
        fwrite(STDOUT, "Validated the real on-disk package.json: paid pricing, payments.manage permission, /admin/payments admin route, loadable service class, Plugins menu group.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
