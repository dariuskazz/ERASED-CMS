<?php
declare(strict_types=1);

namespace Erased\Admin;

use InvalidArgumentException;

final class PluginAdminRoute
{
    public function __construct(
        private readonly string $id,
        private readonly string $packageId,
        private readonly string $path,
        private readonly string $permission,
        private readonly string $serviceId,
    ) {
        foreach (['id' => $id, 'package id' => $packageId, 'path' => $path, 'permission' => $permission, 'service id' => $serviceId] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Plugin admin route {$label} cannot be empty.");
            }
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) !== 1) {
            throw new InvalidArgumentException("Plugin admin route id '{$id}' is invalid.");
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $permission) !== 1) {
            throw new InvalidArgumentException("Plugin admin route permission '{$permission}' is invalid.");
        }
        if (!str_starts_with($path, '/admin/') || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException("Plugin admin route path '{$path}' must start with /admin/ and contain no .. or // segments.");
        }
    }

    public function id(): string { return $this->id; }
    public function packageId(): string { return $this->packageId; }
    public function path(): string { return $this->path; }
    public function permission(): string { return $this->permission; }
    public function serviceId(): string { return $this->serviceId; }
}
