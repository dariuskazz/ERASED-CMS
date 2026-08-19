<?php
declare(strict_types=1);

use Erased\Packages\PackageLifecycle;
use Erased\Packages\PackageLifecycleLoader;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycle.php';
require_once dirname(__DIR__).'/app/Packages/PackageLifecycleLoader.php';

$root = sys_get_temp_dir().'/erased-lifecycle-loader-'.bin2hex(random_bytes(8));
$package = $root.'/package';
mkdir($package.'/src', 0750, true);

try {
    $class = 'ErasedTestLifecycle'.bin2hex(random_bytes(4));
    $source = "<?php\ndeclare(strict_types=1);\n"
        ."final class {$class} implements \\Erased\\Packages\\PackageLifecycle\n{\n"
        ."    public function install(\\Erased\\Packages\\PackageManifest \$manifest, string \$packagePath): void {}\n"
        ."    public function enable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function disable(\\Erased\\Packages\\PackageManifest \$manifest): void {}\n"
        ."    public function upgrade(\\Erased\\Packages\\PackageManifest \$manifest, string \$fromVersion): void {}\n"
        ."    public function uninstall(\\Erased\\Packages\\PackageManifest \$manifest, bool \$removeData = false): void {}\n"
        ."}\n";
    file_put_contents($package.'/src/Lifecycle.php', $source);

    $manifest = new PackageManifest([
        'id' => 'erased.test-lifecycle-loader',
        'type' => 'module',
        'name' => 'Lifecycle Loader Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Lifecycle loader smoke-test fixture.',
        'dependencies' => [],
        'lifecycle' => [
            'file' => 'src/Lifecycle.php',
            'class' => $class,
        ],
    ]);

    $loader = new PackageLifecycleLoader();
    $instance = $loader->load($manifest, $package);
    assert($instance instanceof PackageLifecycle);

    $unsafe = new PackageManifest(array_replace($manifest->all(), [
        'id' => 'erased.test-lifecycle-unsafe',
        'lifecycle' => ['file' => '../outside.php', 'class' => $class],
    ]));
    $unsafeRejected = false;
    try {
        $loader->load($unsafe, $package);
    } catch (RuntimeException $error) {
        $unsafeRejected = str_contains($error->getMessage(), 'unsafe');
    }
    assert($unsafeRejected === true);

    file_put_contents($package.'/src/Invalid.php', "<?php\nfinal class NotALifecycle {}\n");
    $invalid = new PackageManifest(array_replace($manifest->all(), [
        'id' => 'erased.test-lifecycle-invalid',
        'lifecycle' => ['file' => 'src/Invalid.php', 'class' => 'NotALifecycle'],
    ]));
    $invalidRejected = false;
    try {
        $loader->load($invalid, $package);
    } catch (RuntimeException $error) {
        $invalidRejected = str_contains($error->getMessage(), 'must implement');
    }
    assert($invalidRejected === true);

    fwrite(STDOUT, "Package lifecycle loader smoke test passed.\n");
    fwrite(STDOUT, "Validated safe lifecycle loading and rejection of unsafe or invalid lifecycle declarations.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    $remove = static function (string $directory) use (&$remove): void {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) && !is_link($path) ? $remove($path) : @unlink($path);
        }
        @rmdir($directory);
    };
    $remove($root);
}
