<?php
declare(strict_types=1);

namespace Erased\Admin;

use InvalidArgumentException;

final class PluginAdminMenuEntry
{
    private readonly ?string $sheetCode;

    public function __construct(
        private readonly string $packageId,
        private readonly string $label,
        private readonly string $path,
        private readonly string $permission,
        private readonly string $group,
        ?string $sheetCode = null,
    ) {
        foreach (['package id' => $packageId, 'label' => $label, 'path' => $path, 'permission' => $permission, 'group' => $group] as $fieldLabel => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Plugin admin menu entry {$fieldLabel} cannot be empty.");
            }
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $permission) !== 1) {
            throw new InvalidArgumentException("Plugin admin menu entry permission '{$permission}' is invalid.");
        }
        if (!str_starts_with($path, '/admin/') || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException("Plugin admin menu entry path '{$path}' must start with /admin/ and contain no .. or // segments.");
        }
        $this->sheetCode = ($sheetCode !== null && trim($sheetCode) !== '') ? $sheetCode : null;
    }

    public function packageId(): string { return $this->packageId; }
    public function label(): string { return $this->label; }
    public function path(): string { return $this->path; }
    public function permission(): string { return $this->permission; }
    public function group(): string { return $this->group; }
    public function sheetCode(): ?string { return $this->sheetCode; }
}
