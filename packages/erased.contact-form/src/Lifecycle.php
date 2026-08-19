<?php
declare(strict_types=1);

namespace ErasedContactForm;

use Erased\Packages\PackageLifecycle;
use Erased\Packages\PackageManifest;

final class Lifecycle implements PackageLifecycle
{
    public function install(PackageManifest $manifest, string $packagePath): void {}
    public function enable(PackageManifest $manifest): void {}
    public function disable(PackageManifest $manifest): void {}
    public function upgrade(PackageManifest $manifest, string $fromVersion): void {}
    public function uninstall(PackageManifest $manifest, bool $removeData = false): void {}
}
