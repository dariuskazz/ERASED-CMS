<?php
declare(strict_types=1);

namespace Erased\Homepage;

use InvalidArgumentException;

final class BlockDefinition
{
    /** @param list<string> $requiredCapabilities */
    public function __construct(
        private readonly string $id,
        private readonly string $packageId,
        private readonly string $title,
        private readonly string $category,
        private readonly string $serviceId,
        private readonly array $requiredCapabilities = [],
        private readonly string $description = '',
    ) {
        foreach (['id' => $id, 'package id' => $packageId, 'title' => $title, 'category' => $category, 'service id' => $serviceId] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Homepage block {$label} cannot be empty.");
            }
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) !== 1) {
            throw new InvalidArgumentException("Homepage block id '{$id}' is invalid.");
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $category) !== 1) {
            throw new InvalidArgumentException("Homepage block category '{$category}' is invalid.");
        }
        foreach ($requiredCapabilities as $capability) {
            if (!is_string($capability) || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $capability) !== 1) {
                throw new InvalidArgumentException("Homepage block '{$id}' contains an invalid required capability.");
            }
        }
    }

    public function id(): string { return $this->id; }
    public function packageId(): string { return $this->packageId; }
    public function title(): string { return $this->title; }
    public function category(): string { return $this->category; }
    public function serviceId(): string { return $this->serviceId; }
    public function description(): string { return $this->description; }

    /** @return list<string> */
    public function requiredCapabilities(): array
    {
        return array_values(array_unique($this->requiredCapabilities));
    }
}
