<?php
declare(strict_types=1);

namespace Erased\Packages;

use ReflectionClass;
use RuntimeException;

final class PackageLifecycleLoader
{
    public function load(PackageManifest $manifest, string $packagePath): PackageLifecycle
    {
        $definition = $manifest->all()['lifecycle'] ?? null;
        if (!is_array($definition)) {
            if ($manifest->type() === 'theme') {
                return new NoopPackageLifecycle();
            }
            if ($manifest->type() === 'language') {
                return new LanguagePackLifecycle();
            }
            throw new RuntimeException("Package '{$manifest->id()}' does not declare lifecycle metadata.");
        }

        $file = $definition['file'] ?? null;
        $class = $definition['class'] ?? null;
        if (!is_string($file) || trim($file) === '') {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle file is required.");
        }
        if (!is_string($class) || trim($class) === '') {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class is required.");
        }
        if (!$this->isSafeRelativePath($file)) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle file path is unsafe.");
        }
        if (!$this->isValidClassName($class)) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class name is invalid.");
        }

        $root = realpath($packagePath);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException("Package '{$manifest->id()}' installation path is unavailable.");
        }

        $candidate = $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle file is missing or unreadable.");
        }
        if (!str_starts_with($resolved.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle file resolves outside the package directory.");
        }

        require_once $resolved;

        if (!class_exists($class, false)) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class '{$class}' was not loaded.");
        }
        if (!is_subclass_of($class, PackageLifecycle::class)) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class must implement PackageLifecycle.");
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class is not instantiable.");
        }
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class constructor must not require arguments.");
        }

        $instance = $reflection->newInstance();
        if (!$instance instanceof PackageLifecycle) {
            throw new RuntimeException("Package '{$manifest->id()}' lifecycle class could not be instantiated.");
        }

        return $instance;
    }

    private function isValidClassName(string $class): bool
    {
        $class = ltrim(trim($class), '\\');
        if ($class === '') {
            return false;
        }

        foreach (explode('\\', $class) as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..' || $segment === '') {
                return false;
            }
        }

        return !str_contains($normalized, "\0");
    }
}
