<?php
declare(strict_types=1);

/**
 * InstalledPluginAdminSurface's route registration checks
 * service_runtime()->container()->has($serviceId) - the real global
 * singleton, matching how homepage_studio_plugin_block_resolver() reuses
 * it. The real service_runtime() lives in app/bootstrap.php, which
 * requires a real MySQL connection via db() with no sqlite fallback (see
 * bootstrap.php's db()) - unavailable in this local test environment (only
 * pdo_sqlite is installed here). So this test stubs a minimal
 * service_runtime() instead, matching tools/test-live-preview-route.php's
 * approach of stubbing just enough of the real app to run standalone,
 * and uses sqlite in-memory for InstalledPackageRepository directly.
 *
 * Not covered here (needs the real bootstrap.php + a real DB, so it's
 * verified live on the podman container instead, per docs/STATUS.md):
 * role_permissions()'s real admin-only merge of plugin_admin_surface()'s
 * permissionIds() - this test only proves permissionIds() itself is
 * correct in isolation (Part 3 below), which is what that merge consumes.
 */

require_once dirname(__DIR__).'/app/Admin/PluginAdminRoute.php';
require_once dirname(__DIR__).'/app/Admin/PluginAdminMenuEntry.php';
require_once dirname(__DIR__).'/app/Admin/InstalledPluginAdminSurface.php';
require_once dirname(__DIR__).'/app/Core/Router.php';
require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';

use Erased\Admin\InstalledPluginAdminSurface;
use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;

/** Minimal stand-in for service_runtime()->container()->has($id) - refresh() never calls anything else on it. */
final class FakeServiceRuntimeContainer
{
    /** @param list<string> $knownServiceIds */
    public function __construct(private readonly array $knownServiceIds) {}
    public function has(string $id): bool { return in_array($id, $this->knownServiceIds, true); }
}
final class FakeServiceRuntime
{
    public function __construct(private readonly FakeServiceRuntimeContainer $container) {}
    public function container(): FakeServiceRuntimeContainer { return $this->container; }
}
$GLOBALS['__fakeServiceRuntime'] = new FakeServiceRuntime(new FakeServiceRuntimeContainer([
    'testplugin.alpha.route', 'testplugin.beta.route', 'testplugin.gamma.route',
]));
function service_runtime(): FakeServiceRuntime { return $GLOBALS['__fakeServiceRuntime']; }

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
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE installed_packages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        package_id TEXT NOT NULL UNIQUE,
        package_type TEXT NOT NULL,
        name TEXT NOT NULL,
        version TEXT NOT NULL,
        enabled INTEGER NOT NULL DEFAULT 0,
        health_status TEXT NOT NULL DEFAULT \'ok\',
        last_error TEXT NULL,
        last_error_at TEXT NULL,
        installed_path TEXT NOT NULL,
        manifest_json TEXT NOT NULL,
        integrity_manifest_json TEXT NULL,
        integrity_status TEXT NOT NULL DEFAULT \'unknown\',
        integrity_checked_at TEXT NULL,
        installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $repository = new InstalledPackageRepository($pdo);

    // ---- Part 1: an enabled package's route is reachable, a disabled package's is not ----
    $alpha = new PackageManifest([
        'id' => 'erased.test-plugin-admin-alpha', 'type' => 'module', 'name' => 'Alpha Plugin Admin', 'version' => '1.0.0',
        'requires' => '0.3.0', 'author' => 'ERASED CMS', 'description' => 'Plugin admin surface test fixture.',
        'permissions' => [['id' => 'testplugin.alpha.manage', 'label' => 'Manage Alpha']],
        'services' => ['testplugin.alpha.route' => ['file' => 'AlphaAdminRoute.php', 'class' => 'ErasedTest\\AlphaAdminRoute', 'shared' => true]],
        'admin_routes' => [['id' => 'testplugin.alpha.index', 'path' => '/admin/testplugin-alpha', 'permission' => 'testplugin.alpha.manage', 'service_id' => 'testplugin.alpha.route']],
        'admin_menu' => [['label' => 'Alpha Plugin', 'path' => '/admin/testplugin-alpha', 'permission' => 'testplugin.alpha.manage', 'group' => 'Content']],
    ]);
    $repository->save($alpha, '/tmp/does-not-matter-alpha', true);

    $beta = new PackageManifest([
        'id' => 'erased.test-plugin-admin-beta', 'type' => 'module', 'name' => 'Beta Plugin Admin (disabled)', 'version' => '1.0.0',
        'requires' => '0.3.0', 'author' => 'ERASED CMS', 'description' => 'Disabled plugin admin surface test fixture.',
        'permissions' => [['id' => 'testplugin.beta.manage', 'label' => 'Manage Beta']],
        'services' => ['testplugin.beta.route' => ['file' => 'BetaAdminRoute.php', 'class' => 'ErasedTest\\BetaAdminRoute', 'shared' => true]],
        'admin_routes' => [['id' => 'testplugin.beta.index', 'path' => '/admin/testplugin-beta', 'permission' => 'testplugin.beta.manage', 'service_id' => 'testplugin.beta.route']],
        'admin_menu' => [['label' => 'Beta Plugin', 'path' => '/admin/testplugin-beta', 'permission' => 'testplugin.beta.manage', 'group' => 'ExtraGroup']],
    ]);
    $repository->save($beta, '/tmp/does-not-matter-beta', false); // disabled

    $surface = new InstalledPluginAdminSurface($repository);
    $surface->refresh();

    $check($surface->router()->match('GET', '/admin/testplugin-alpha') !== null, 'enabled package route is reachable via GET');
    $check($surface->router()->match('POST', '/admin/testplugin-alpha') !== null, 'enabled package route is reachable via POST');
    $check($surface->router()->match('GET', '/admin/testplugin-beta') === null, 'disabled package route is not reachable');

    // ---- Part 2: menu groups ----
    $contentEntries = $surface->menuEntries('Content');
    $check(count($contentEntries) === 1 && $contentEntries[0]->label() === 'Alpha Plugin', 'menuEntries() returns the enabled package entry in its declared group');
    $check($surface->menuEntries('ExtraGroup') === [], 'menuEntries() is empty for a disabled package\'s group');
    $check($surface->extraGroups() === [], 'extraGroups() excludes groups with zero registered entries (beta is disabled)');

    // ---- Part 3: permissions, from enabled packages only, deduped ----
    $check(in_array('testplugin.alpha.manage', $surface->permissionIds(), true), 'permissionIds() includes the enabled package\'s declared permission');
    $check(!in_array('testplugin.beta.manage', $surface->permissionIds(), true), 'permissionIds() excludes the disabled package\'s permission');

    // ---- Part 4: two packages claiming the same path - first (by InstalledPackageRepository's real ORDER BY name) wins ----
    $gamma = new PackageManifest([
        'id' => 'erased.test-plugin-admin-gamma-second-claim', 'type' => 'module', 'name' => 'Zzz Second Claimant', 'version' => '1.0.0',
        'requires' => '0.3.0', 'author' => 'ERASED CMS', 'description' => 'Declares the same path as alpha - sorts after it by name.',
        'services' => ['testplugin.gamma.route' => ['file' => 'GammaAdminRoute.php', 'class' => 'ErasedTest\\GammaAdminRoute', 'shared' => true]],
        'admin_routes' => [['id' => 'testplugin.gamma.index', 'path' => '/admin/testplugin-alpha', 'permission' => 'testplugin.alpha.manage', 'service_id' => 'testplugin.gamma.route']],
    ]);
    $repository->save($gamma, '/tmp/does-not-matter-gamma', true);

    $surface->refresh();
    // Not calling router()->call() here: the registered handler closure
    // itself calls require_permission()/require_login(), which would try
    // to redirect and exit this CLI test process outright. match() alone
    // (returning route metadata, not invoking anything) is enough to prove
    // "first registered wins" via the collision being recorded below.
    $check($surface->router()->match('GET', '/admin/testplugin-alpha') !== null, 'the first-registered package\'s route still matches after the collision attempt');
    $check(isset($surface->errors()['erased.test-plugin-admin-gamma-second-claim']), 'path collision is recorded as an error against the second-registered package');

    // ---- Part 5: a malformed package does not break a different enabled package's admin surface ----
    // Directly insert a row with an invalid admin_routes shape (a string,
    // not an array) bypassing PackageManifest's own constructor validation
    // - simulates data that became malformed after being stored, exactly
    // the failure mode the outer per-package try/catch guards.
    $brokenId = 'erased.test-plugin-admin-broken';
    $brokenManifestJson = json_encode([
        'id' => $brokenId, 'type' => 'module', 'name' => 'Broken Plugin', 'version' => '1.0.0',
        'requires' => '0.3.0', 'author' => 'ERASED CMS', 'description' => 'Deliberately malformed.',
        'admin_routes' => 'not-an-array',
    ], JSON_THROW_ON_ERROR);
    $insert = $pdo->prepare('INSERT INTO installed_packages (package_id, package_type, name, version, enabled, installed_path, manifest_json) VALUES (?,?,?,?,?,?,?)');
    $insert->execute([$brokenId, 'module', 'Broken Plugin', '1.0.0', 1, '/tmp/does-not-matter-broken', $brokenManifestJson]);

    $surface->refresh();
    $check(isset($surface->errors()[$brokenId]), 'the malformed package is recorded in errors()');
    $check($surface->router()->match('GET', '/admin/testplugin-alpha') !== null, 'a different enabled package\'s route still registers despite the malformed package');
    $check(in_array('testplugin.alpha.manage', $surface->permissionIds(), true), 'a different enabled package\'s permission still registers despite the malformed package');

    if ($fail === 0) {
        fwrite(STDOUT, "Plugin admin surface test passed.\n");
        fwrite(STDOUT, "Validated route reachability by enabled state, menu grouping, permission collection, path-collision handling, and per-package failure isolation.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
