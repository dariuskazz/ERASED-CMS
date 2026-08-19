<?php
declare(strict_types=1);

use Erased\Capabilities\CapabilityRegistry;
use Erased\Capabilities\CapabilityResolver;
use Erased\Capabilities\InstalledCapabilityRuntime;
use Erased\Events\EventDispatcher;
use Erased\Events\PackageEvent;
use Erased\Homepage\BlockDefinition;
use Erased\Homepage\BlockDefinitionRegistry;
use Erased\Homepage\InstalledBlockRuntime;
use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageLifecycleExecutor;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageStateManager;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycle.php';
require_once dirname(__DIR__).'/app/Packages/InstalledPackageRepository.php';
require_once dirname(__DIR__).'/app/Packages/PackageStateManager.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycleLoader.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycleExecutor.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityRegistry.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityResolver.php';
require_once dirname(__DIR__).'/app/Capabilities/InstalledCapabilityRuntime.php';
require_once dirname(__DIR__).'/app/Events/EventDispatcher.php';
require_once dirname(__DIR__).'/app/Events/PackageEvent.php';
require_once dirname(__DIR__).'/app/Homepage/BlockDefinition.php';
require_once dirname(__DIR__).'/app/Homepage/BlockDefinitionRegistry.php';
require_once dirname(__DIR__).'/app/Homepage/InstalledBlockRuntime.php';

$table = 'platform_foundation_wiring_test_'.bin2hex(random_bytes(6));
$tempRoot = sys_get_temp_dir().'/erased-platform-foundation-'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';
$fail = 0;

$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory.DIRECTORY_SEPARATOR.$item;
        is_dir($path) && !is_link($path) ? $removeDirectory($path) : @unlink($path);
    }
    @rmdir($directory);
};

/** Writes a real no-op PackageLifecycle class to disk - required for PackageLifecycleLoader::load() on non-theme packages. */
$writeLifecyclePackage = static function (string $directory, string $packageId, array $extra = []): PackageManifest {
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create test package directory.');
    }
    $class = 'ErasedTest\\Fixture'.substr(md5($packageId), 0, 8);
    $short = substr($class, (int)strrpos($class, '\\') + 1);
    $code = "<?php\ndeclare(strict_types=1);\nnamespace ErasedTest;\n"
        ."use Erased\\Packages\\PackageLifecycle;\nuse Erased\\Packages\\PackageManifest;\n"
        ."final class {$short} implements PackageLifecycle {\n"
        ."public function install(PackageManifest \$m, string \$p): void {}\n"
        ."public function enable(PackageManifest \$m): void {}\n"
        ."public function disable(PackageManifest \$m): void {}\n"
        ."public function upgrade(PackageManifest \$m, string \$f): void {}\n"
        ."public function uninstall(PackageManifest \$m, bool \$r = false): void {}\n"
        ."}\n";
    file_put_contents($directory.'/Lifecycle.php', $code);

    return new PackageManifest(array_merge([
        'id' => $packageId,
        'type' => 'module',
        'name' => 'Fixture '.$packageId,
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Platform Foundation wiring test fixture.',
        'lifecycle' => ['file' => 'Lifecycle.php', 'class' => $class],
    ], $extra));
};

try {
    if (extension_loaded('pdo_sqlite')) {
        $driver = 'sqlite';
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = 'CREATE TABLE `'.$table.'` ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            .'package_id TEXT NOT NULL UNIQUE,'
            .'package_type TEXT NOT NULL,'
            .'name TEXT NOT NULL,'
            .'version TEXT NOT NULL,'
            .'enabled INTEGER NOT NULL DEFAULT 0,'
            ."health_status TEXT NOT NULL DEFAULT 'ok',"
            .'last_error TEXT NULL,'
            .'last_error_at TEXT NULL,'
            .'installed_path TEXT NOT NULL,'
            .'manifest_json TEXT NOT NULL,'
            .'integrity_manifest_json TEXT NULL,'
            ."integrity_status TEXT NOT NULL DEFAULT 'unknown',"
            .'integrity_checked_at TEXT NULL,'
            .'installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            .')';
    } else {
        $driver = 'mysql';
        require_once dirname(__DIR__).'/app/bootstrap.php';
        $pdo = db();
        $schema = 'CREATE TABLE `'.$table.'` ('
            .'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            .'package_id VARCHAR(190) NOT NULL UNIQUE,'
            .'package_type VARCHAR(40) NOT NULL,'
            .'name VARCHAR(190) NOT NULL,'
            .'version VARCHAR(64) NOT NULL,'
            .'enabled TINYINT(1) NOT NULL DEFAULT 0,'
            ."health_status VARCHAR(20) NOT NULL DEFAULT 'ok',"
            .'last_error TEXT NULL,'
            .'last_error_at DATETIME NULL,'
            .'installed_path VARCHAR(500) NOT NULL,'
            .'manifest_json LONGTEXT NOT NULL,'
            .'integrity_manifest_json LONGTEXT NULL,'
            ."integrity_status VARCHAR(20) NOT NULL DEFAULT 'unknown',"
            .'integrity_checked_at DATETIME NULL,'
            .'installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
    $pdo->exec($schema);
    $repository = new InstalledPackageRepository($pdo, $table);

    // ---- Part 1: capability enforcement blocks enable() ----
    $needsWidgets = $writeLifecyclePackage($tempRoot.'/needs-widgets', 'erased.test-needs-widgets', ['requires_capabilities' => ['widgets']]);
    $repository->save($needsWidgets, $tempRoot.'/needs-widgets', false);

    $emptyRegistry = new CapabilityRegistry();
    $emptyResolver = new CapabilityResolver($emptyRegistry, []);
    $executor = new PackageLifecycleExecutor(
        new PackageStateManager($repository),
        new PackageLifecycleLoader(),
        null,
        $emptyResolver,
    );

    $rejected = false;
    try {
        $executor->enable('erased.test-needs-widgets');
    } catch (RuntimeException $e) {
        $rejected = str_contains($e->getMessage(), 'widgets');
    }
    $check($rejected, 'enable() rejects a package whose required capability has no active provider');
    $check($repository->find('erased.test-needs-widgets')['enabled'] === false, 'rejected package stays disabled');

    // ---- Part 2: events actually fire ----
    $provider = $writeLifecyclePackage($tempRoot.'/provider', 'erased.test-provider', ['provides' => ['widgets']]);
    $repository->save($provider, $tempRoot.'/provider', false);

    $events = new EventDispatcher();
    $fired = [];
    $events->listen('package.enabled', function (PackageEvent $e) use (&$fired) { $fired[] = ['package.enabled', $e->packageId()]; });
    $events->listen('capability.provider.changed', function (PackageEvent $e) use (&$fired) { $fired[] = ['capability.provider.changed', $e->packageId()]; });
    $events->listen('package.disabled', function (PackageEvent $e) use (&$fired) { $fired[] = ['package.disabled', $e->packageId()]; });

    $satisfiedRegistry = new CapabilityRegistry();
    $satisfiedRegistry->register($provider);
    $satisfiedResolver = new CapabilityResolver($satisfiedRegistry, ['erased.test-provider']);

    $eventExecutor = new PackageLifecycleExecutor(
        new PackageStateManager($repository),
        new PackageLifecycleLoader(),
        null,
        $satisfiedResolver,
        $events,
    );
    $eventExecutor->enable('erased.test-provider');
    $check(in_array(['package.enabled', 'erased.test-provider'], $fired, true), 'package.enabled fires with the correct package id');
    $check(in_array(['capability.provider.changed', 'erased.test-provider'], $fired, true), 'capability.provider.changed fires for a package that provides a capability');

    $eventExecutor->disable('erased.test-provider');
    $check(in_array(['package.disabled', 'erased.test-provider'], $fired, true), 'package.disabled fires with the correct package id');

    // ---- Part 3: homepage_blocks registration + capability-aware hiding ----
    $blocksRegistry = new BlockDefinitionRegistry();
    $blockRuntime = new InstalledBlockRuntime($repository, $blocksRegistry);

    $withBlock = new PackageManifest([
        'id' => 'erased.test-block-provider',
        'type' => 'module',
        'name' => 'Block Provider',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Declares one homepage block requiring a capability.',
        'homepage_blocks' => [[
            'id' => 'test.gated-block',
            'title' => 'Gated Block',
            'category' => 'other',
            'service_id' => 'test.gated-block.render',
            'requires_capabilities' => ['widgets'],
            'description' => 'Visible only when widgets is provided.',
        ]],
    ]);
    // Reuse the already-created directory (no lifecycle needed for InstalledBlockRuntime, it only reads the manifest).
    $repository->save($withBlock, $tempRoot.'/block-provider', true);
    $repository->setEnabled('erased.test-provider', false); // widgets NOT currently provided

    $blockRuntime->refresh();
    $check($blocksRegistry->exists('test.gated-block'), 'plugin-declared homepage block is registered after refresh()');
    $check($blockRuntime->errors() === [], 'no registration errors for a well-formed homepage_blocks entry');

    $capabilityRuntime = new InstalledCapabilityRuntime($repository);
    $unfilteredIds = array_map(fn(BlockDefinition $d) => $d->id(), $blocksRegistry->all());
    $check(in_array('test.gated-block', $unfilteredIds, true), 'unfiltered all() still lists the gated block (saved layout data is never deleted)');

    $filteredIds = array_map(fn(BlockDefinition $d) => $d->id(), $blocksRegistry->all($capabilityRuntime->resolver()));
    $check(!in_array('test.gated-block', $filteredIds, true), 'capability-filtered all() hides the block while its capability is unmet');

    $repository->setEnabled('erased.test-provider', true);
    $capabilityRuntime->refresh();
    $filteredIdsAfter = array_map(fn(BlockDefinition $d) => $d->id(), $blocksRegistry->all($capabilityRuntime->resolver()));
    $check(in_array('test.gated-block', $filteredIdsAfter, true), 'capability-filtered all() shows the block once its capability becomes satisfied');

    // refresh() must remove blocks for packages that got disabled, not just add new ones
    $repository->setEnabled('erased.test-block-provider', false);
    $blockRuntime->refresh();
    $check(!$blocksRegistry->exists('test.gated-block'), 'refresh() unregisters blocks belonging to a now-disabled package');

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} assertion(s) failed.\n");
        exit(1);
    }

    echo "\nPlatform Foundation wiring test passed ({$driver}).\n";
} finally {
    if ($pdo instanceof PDO && $driver === 'mysql') {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
        } catch (Throwable) {
        }
    }
    $removeDirectory($tempRoot);
}
