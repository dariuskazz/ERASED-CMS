<?php
declare(strict_types=1);

/**
 * Covers what tools/test-platform-foundation-wiring.php explicitly does not:
 * Layout Studio's palette/picker markup actually reading the real block
 * registry (not hardcoded fake ids), the save-path validator accepting a
 * real plugin block id, PublishedHomepageRenderer's plugin-block resolver
 * (success + failure isolation), and InstalledServiceRuntime::container().
 */

require_once dirname(__DIR__).'/app/Core/Autoloader.php';
\Erased\Core\Autoloader::register(dirname(__DIR__));

use Erased\Container\InstalledServiceRuntime;
use Erased\Container\PackageServiceRegistrar;
use Erased\Container\ServiceContainer;
use Erased\Homepage\BlockPlacement;
use Erased\Homepage\PublishedHomepageRenderer;
use Erased\LayoutStudio\LayoutStudioAdminRoute;
use Erased\LayoutStudio\LayoutStudioAdminScreen;
use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;

// Minimal global-function shims LayoutStudioAdminScreen::render() needs to
// render standalone, mirroring tools/test-live-preview-route.php's approach
// of stubbing just enough of the real public/index.php-hosted helpers
// instead of pulling in the full app bootstrap.
function csrf(): string { return 'test-csrf-token'; }
function setting(string $key, string $default = ''): string { return $default; }
function e(string|int|null $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function can(string $permission): bool { return false; }
function can_open_settings_hub(): bool { return false; }
function tr(string $key, string $group = 'site'): string { return $key; }
function admin_sheet_code(string $requestPath): string { return 'A-10'; }
function admin_core_nav_button(string $key, string $requestPath, ?bool $active = null): string { return ''; }
function erased_social_platforms(): array { return ['github' => 'GitHub', 'x' => 'X (Twitter)']; }
function social_icon_svg(string $name): string { return '<svg></svg>'; }
function homepage_studio_blocks(): array
{
    return ['hero' => ['Hero Banner', 'Large title with CTA button', 'center', '⚡']];
}
function homepage_studio_config(): array
{
    return [
        'active_regions' => ['left', 'center', 'right'],
        'enabled' => ['hero'],
        'order' => ['hero'],
        'regions' => ['hero' => 'center'],
        'options' => [],
    ];
}

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
    $pdo->exec('CREATE TABLE layout_drafts(draft_key TEXT PRIMARY KEY,profile_id TEXT,target_type TEXT,target_id TEXT,name TEXT,status TEXT,revision INTEGER,author_id INTEGER NULL,notes TEXT,payload_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

    $packages = new InstalledPackageRepository($pdo);
    $pluginManifest = new PackageManifest([
        'id' => 'erased.test-plugin-block-package',
        'type' => 'module',
        'name' => 'Test Plugin Block Package',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Layout Studio plugin-block wiring test fixture.',
        'homepage_blocks' => [[
            'id' => 'test.plugin-block',
            'title' => 'Test Plugin Block',
            'category' => 'other',
            'service_id' => 'test.plugin-block.render',
            'description' => 'A plugin-provided homepage block.',
        ]],
    ]);
    $packages->save($pluginManifest, '/tmp/does-not-matter', true);

    // ---- Part 1: palette/picker markup reads the real registry ----
    $route = new LayoutStudioAdminRoute(new LayoutStudioAdminScreen(), $pdo);
    $html = $route->render('default');
    $check(str_contains($html, 'data-block-id="test.plugin-block"'), 'palette renders the real plugin block id');
    $check(str_contains($html, 'data-pick-block-id="test.plugin-block"'), 'picker modal renders the real plugin block id');

    if (preg_match('/data-known-block-ids="([^"]*)"/', $html, $m)) {
        $decoded = json_decode(htmlspecialchars_decode($m[1], ENT_QUOTES), true);
        $check(is_array($decoded) && in_array('test.plugin-block', $decoded, true), 'known-block-ids JSON contains the plugin block id');
        $check(is_array($decoded) && in_array('hero', $decoded, true), 'known-block-ids JSON still contains legacy built-ins');
    } else {
        $check(false, 'data-known-block-ids attribute is present and parseable');
    }

    // ---- Part 2: save-path validator accepts a real plugin block id ----
    // (regression test for the fix - the validator used to only accept
    // array_keys(homepage_studio_blocks()), the legacy 12 only)
    $saveInput = [
        'revision' => 1,
        'placements' => [
            ['instance_id' => 'inst-1', 'region' => 'center', 'block_id' => 'test.plugin-block', 'visible' => true, 'settings' => []],
        ],
    ];
    $rejected = null;
    try {
        $route->save('default', $saveInput, null);
    } catch (RuntimeException $e) {
        $rejected = $e->getMessage();
    }
    $check($rejected === null, 'save() accepts a placement referencing a real plugin block id'.($rejected !== null ? " (rejected: {$rejected})" : ''));

    // ---- Part 3: PublishedHomepageRenderer's plugin-block resolver ----
    $placement = new BlockPlacement('inst-1', 'default', 'center', 'test.plugin-block', 0, true, ['foo' => 'bar']);

    $rendererOk = (new PublishedHomepageRenderer())->render(
        [$placement],
        ['active_regions' => ['center']],
        [],
        static fn(BlockPlacement $p): ?string => '<div data-resolved="'.$p->blockId().'"></div>',
    );
    $check(is_string($rendererOk) && str_contains($rendererOk, 'data-resolved="test.plugin-block"'), 'resolver-returned HTML appears in PublishedHomepageRenderer output');

    $rendererIsolated = (new PublishedHomepageRenderer())->render(
        [$placement],
        ['active_regions' => ['center']],
        [],
        static function (BlockPlacement $p): ?string { throw new RuntimeException('boom'); },
    );
    $check(is_string($rendererIsolated), 'a throwing resolver does not break render() - it still returns a string');

    // ---- Part 4: InstalledServiceRuntime::container() ----
    $container = new ServiceContainer();
    $runtime = new InstalledServiceRuntime($packages, $container, new PackageServiceRegistrar());
    $check($runtime->container() === $container, 'InstalledServiceRuntime::container() returns the exact injected ServiceContainer');

    if ($fail === 0) {
        fwrite(STDOUT, "Layout Studio plugin-block wiring test passed.\n");
        fwrite(STDOUT, "Validated real registry-driven palette/picker markup, save-path acceptance for plugin block ids, resolver-based rendering with failure isolation, and the service container accessor.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
