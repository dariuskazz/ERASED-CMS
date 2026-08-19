<?php
declare(strict_types=1);

namespace Erased\Website;

use RuntimeException;

final class WebsiteTypeManager
{
    public function __construct(
        private readonly string $registryPath,
    ) {
    }

    public function all(): array
    {
        $registry = $this->registry();
        return is_array($registry['types'] ?? null) ? $registry['types'] : [];
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $type) {
            if (($type['id'] ?? null) === $id) {
                return $type;
            }
        }

        return null;
    }

    public function defaultId(): string
    {
        $registry = $this->registry();
        $default = (string)($registry['default'] ?? 'blog');
        return $this->find($default) !== null ? $default : 'custom';
    }

    public function validateId(string $id): string
    {
        return $this->find($id) !== null ? $id : $this->defaultId();
    }

    private function registry(): array
    {
        if (!is_file($this->registryPath)) {
            throw new RuntimeException('Website type registry is missing.');
        }

        $json = file_get_contents($this->registryPath);
        if ($json === false) {
            throw new RuntimeException('Website type registry could not be read.');
        }

        $registry = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($registry)) {
            throw new RuntimeException('Website type registry is invalid.');
        }

        return $registry;
    }
}
