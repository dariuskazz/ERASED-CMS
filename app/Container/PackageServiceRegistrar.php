<?php
declare(strict_types=1);

namespace Erased\Container;

use Erased\Packages\PackageManifest;
use ReflectionClass;
use RuntimeException;

final class PackageServiceRegistrar
{
    public function register(ServiceContainer $container, PackageManifest $manifest, string $packagePath): void
    {
        $services = $manifest->all()['services'] ?? [];
        if ($services === []) {
            return;
        }
        if (!is_array($services)) {
            throw new RuntimeException("Package '{$manifest->id()}' services declaration must be an object.");
        }

        $root = realpath($packagePath);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException("Package '{$manifest->id()}' installed path is invalid.");
        }

        foreach ($services as $serviceId => $definition) {
            if (!is_string($serviceId) || trim($serviceId) === '') {
                throw new RuntimeException("Package '{$manifest->id()}' contains an invalid service id.");
            }
            if (!is_array($definition)) {
                throw new RuntimeException("Service '{$serviceId}' declaration must be an object.");
            }

            $file = $definition['file'] ?? null;
            $class = $definition['class'] ?? null;
            $shared = $definition['shared'] ?? true;

            if (!is_string($file) || !$this->isSafeRelativePath($file)) {
                throw new RuntimeException("Service '{$serviceId}' file path is invalid.");
            }
            if (!is_string($class) || !$this->isValidClassName($class)) {
                throw new RuntimeException("Service '{$serviceId}' class name is invalid.");
            }
            if (!is_bool($shared)) {
                throw new RuntimeException("Service '{$serviceId}' shared flag must be boolean.");
            }

            $fullPath = realpath($root.DIRECTORY_SEPARATOR.$file);
            if ($fullPath === false || !is_file($fullPath) || !$this->isInside($fullPath, $root)) {
                throw new RuntimeException("Service '{$serviceId}' file does not exist inside its package.");
            }

            $container->set(
                $serviceId,
                static function (ServiceContainer $container) use ($fullPath, $class, $serviceId): object {
                    require_once $fullPath;
                    if (!class_exists($class)) {
                        throw new RuntimeException("Service class '{$class}' was not loaded for '{$serviceId}'.");
                    }
                    $reflection = new ReflectionClass($class);
                    if (!$reflection->isInstantiable()) {
                        throw new RuntimeException("Service class '{$class}' is not instantiable.");
                    }
                    $constructor = $reflection->getConstructor();
                    if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                        return $reflection->newInstance();
                    }
                    if ($constructor->getNumberOfRequiredParameters() === 1) {
                        $parameter = $constructor->getParameters()[0];
                        $type = $parameter->getType();
                        if ($type !== null && (string)$type === ServiceContainer::class) {
                            return $reflection->newInstance($container);
                        }
                    }
                    throw new RuntimeException(
                        "Service class '{$class}' constructor must require no arguments or one ServiceContainer.",
                    );
                },
                $shared,
            );
        }
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return false;
        }
        foreach (preg_split('~[\\\\/]~', $path) ?: [] as $segment) {
            if ($segment === '..') {
                return false;
            }
        }
        return true;
    }

    private function isInside(string $path, string $root): bool
    {
        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix);
    }

    private function isValidClassName(string $class): bool
    {
        $class = ltrim($class, '\\');
        if ($class === '') {
            return false;
        }
        foreach (explode('\\', $class) as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return false;
            }
        }
        return true;
    }
}
