<?php
declare(strict_types=1);

namespace Erased\Admin;

use Erased\Core\Router;
use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;
use Throwable;

/**
 * Registers each enabled installed package's declared admin_routes,
 * admin_menu, and permissions into one shared Router plus lookup tables,
 * mirroring InstalledBlockRuntime's exact shape (same source data, same
 * per-package try/catch isolation - one broken package must never take
 * down another enabled package's admin surface, or the whole /admin panel).
 *
 * Combined into one class rather than three separate runtimes: routes,
 * menu, and permissions all come from the same InstalledPackageRepository
 * loop over the same enabled packages, so three classes would mean three
 * DB queries and three failure surfaces for no real benefit.
 */
final class InstalledPluginAdminSurface
{
    private Router $router;

    /** @var array<string,list<PluginAdminMenuEntry>> group => entries */
    private array $menuByGroup = [];

    /** @var list<string> */
    private array $permissionIds = [];

    /** @var array<string,string> package id => error message */
    private array $errors = [];

    public function __construct(private readonly InstalledPackageRepository $packages)
    {
        $this->router = new Router();
    }

    public function refresh(): void
    {
        $this->router = new Router();
        $this->menuByGroup = [];
        $this->permissionIds = [];
        $this->errors = [];

        /** @var array<string,string> path => owning package id, across all packages this refresh */
        $claimedPaths = [];

        foreach ($this->packages->all() as $row) {
            if (($row['enabled'] ?? false) !== true) {
                continue;
            }

            $manifestData = $row['manifest'] ?? null;
            if (!is_array($manifestData)) {
                continue;
            }

            $packageId = (string)($row['package_id'] ?? 'unknown');

            try {
                $manifest = new PackageManifest($manifestData);
                $this->registerPermissions($manifest);
                $this->registerRoutes($manifest, $claimedPaths);
                $this->registerMenu($manifest);
            } catch (Throwable $error) {
                $this->errors[$packageId] = $error->getMessage();
            }
        }
    }

    public function router(): Router
    {
        return $this->router;
    }

    /** @return list<PluginAdminMenuEntry> */
    public function menuEntries(string $group): array
    {
        return $this->menuByGroup[$group] ?? [];
    }

    /** @return list<string> menu group names outside the 3 built-in groups, in first-seen order */
    public function extraGroups(): array
    {
        return array_values(array_diff(array_keys($this->menuByGroup), ['Content', 'Site', 'System']));
    }

    /** @return list<string> */
    public function permissionIds(): array
    {
        return array_values(array_unique($this->permissionIds));
    }

    /** @return array<string,string> package id => error message */
    public function errors(): array
    {
        return $this->errors;
    }

    private function registerPermissions(PackageManifest $manifest): void
    {
        foreach ($manifest->declaredPermissions() as $permission) {
            if (!is_array($permission)) {
                continue;
            }
            $id = (string)($permission['id'] ?? '');
            if ($id !== '' && preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) === 1) {
                $this->permissionIds[] = $id;
            }
        }
    }

    /** @param array<string,string> $claimedPaths */
    private function registerRoutes(PackageManifest $manifest, array &$claimedPaths): void
    {
        foreach ($manifest->adminRoutes() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            try {
                $route = new PluginAdminRoute(
                    (string)($entry['id'] ?? ''),
                    $manifest->id(),
                    (string)($entry['path'] ?? ''),
                    (string)($entry['permission'] ?? ''),
                    (string)($entry['service_id'] ?? ''),
                );
            } catch (Throwable $error) {
                $this->errors[$manifest->id()] = $error->getMessage();
                continue;
            }

            if (!function_exists('service_runtime') || !service_runtime()->container()->has($route->serviceId())) {
                $this->errors[$manifest->id()] = "Admin route '{$route->path()}' references unknown service '{$route->serviceId()}'.";
                continue;
            }

            if (isset($claimedPaths[$route->path()]) && $claimedPaths[$route->path()] !== $manifest->id()) {
                $this->errors[$manifest->id()] = "Admin route '{$route->path()}' is already claimed by package '{$claimedPaths[$route->path()]}'.";
                continue;
            }
            $claimedPaths[$route->path()] = $manifest->id();

            $handler = static function (string ...$params) use ($route): void {
                require_permission($route->permission());
                $service = service_runtime()->container()->get($route->serviceId());
                if (!method_exists($service, 'handle')) {
                    http_response_code(500);
                    layout('Plugin unavailable', '<div class="card"><h1>This page is unavailable</h1><p>The plugin providing this page is misconfigured.</p></div>', true);
                    return;
                }
                $service->handle(...$params);
            };
            $this->router->get($route->path(), $handler);
            $this->router->post($route->path(), $handler);
        }
    }

    private function registerMenu(PackageManifest $manifest): void
    {
        foreach ($manifest->adminMenu() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            try {
                $menuEntry = new PluginAdminMenuEntry(
                    $manifest->id(),
                    (string)($entry['label'] ?? ''),
                    (string)($entry['path'] ?? ''),
                    (string)($entry['permission'] ?? ''),
                    (string)($entry['group'] ?? ''),
                    isset($entry['sheet_code']) ? (string)$entry['sheet_code'] : null,
                );
            } catch (Throwable $error) {
                $this->errors[$manifest->id()] = $error->getMessage();
                continue;
            }

            $this->menuByGroup[$menuEntry->group()][] = $menuEntry;
        }
    }
}
