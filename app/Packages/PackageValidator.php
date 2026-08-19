<?php
declare(strict_types=1);

namespace Erased\Packages;

use JsonException;
use Throwable;

final class PackageValidator
{
    /** @return array{valid:bool,errors:array<int,string>,manifest:?PackageManifest} */
    public function validateDirectory(string $packageDirectory): array
    {
        $errors = [];
        $manifest = null;
        $manifestPath = rtrim($packageDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'package.json';

        if (!is_dir($packageDirectory)) {
            return ['valid' => false, 'errors' => ['Package directory does not exist.'], 'manifest' => null];
        }

        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            return ['valid' => false, 'errors' => ['Readable package.json is required.'], 'manifest' => null];
        }

        try {
            $json = file_get_contents($manifestPath);
            if ($json === false) {
                throw new JsonException('Could not read package.json.');
            }
            $manifest = PackageManifest::fromJson($json);
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
        }

        if ($manifest !== null) {
            foreach ($this->declaredPaths($manifest) as $relativePath) {
                if (!$this->isSafeRelativePath($relativePath)) {
                    $errors[] = "Unsafe package path declared: {$relativePath}";
                    continue;
                }

                $fullPath = $packageDirectory.DIRECTORY_SEPARATOR.$relativePath;
                if (!file_exists($fullPath)) {
                    $errors[] = "Declared package path is missing: {$relativePath}";
                }
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'manifest' => $manifest];
    }

    /** @return array<int,string> */
    private function declaredPaths(PackageManifest $manifest): array
    {
        $paths = [];
        $data = $manifest->all();
        foreach (['routes', 'migrations', 'permissions', 'admin', 'assets', 'widgets'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $paths[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && $item !== '') {
                        $paths[] = $item;
                    }
                }
            }
        }

        $lifecycle = $data['lifecycle'] ?? null;
        if (is_array($lifecycle) && isset($lifecycle['file']) && is_string($lifecycle['file'])) {
            $paths[] = $lifecycle['file'];
        }

        if (($data['type'] ?? null) === 'language') {
            $paths[] = 'site.json';
            $paths[] = 'admin.json';
        }

        return array_values(array_unique($paths));
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
