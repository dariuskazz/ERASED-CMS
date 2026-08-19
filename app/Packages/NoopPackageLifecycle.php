<?php
declare(strict_types=1);

namespace Erased\Packages;

/**
 * Used for packages that have nothing to hook - today, only theme-type
 * packages with no declared lifecycle metadata (a pure CSS/asset theme
 * has no install/enable/disable logic to run). Every other package type
 * still requires real lifecycle metadata; see PackageLifecycleLoader.
 */
final class NoopPackageLifecycle implements PackageLifecycle
{
    public function install(PackageManifest $manifest, string $packagePath): void {}

    public function enable(PackageManifest $manifest): void {}

    public function disable(PackageManifest $manifest): void {}

    public function upgrade(PackageManifest $manifest, string $fromVersion): void {}

    public function uninstall(PackageManifest $manifest, bool $removeData = false): void {}
}
