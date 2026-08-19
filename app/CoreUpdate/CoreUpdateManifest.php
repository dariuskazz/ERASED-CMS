<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use InvalidArgumentException;

/**
 * Value object for a core-update release's `update.json` - deliberately not
 * built on PackageManifest, which carries fields (theme_scope, pricing,
 * dependencies, PackageManifest::TYPES) that don't apply to a CMS core
 * update and no `core` package type to shoehorn one into.
 */
final class CoreUpdateManifest
{
    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
        foreach (['version', 'requires'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InvalidArgumentException("Core update manifest field '{$field}' is required.");
            }
            if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $data[$field])) {
                throw new InvalidArgumentException("Core update manifest field '{$field}' must use semantic versioning.");
            }
        }

        foreach (['name', 'notes'] as $optionalField) {
            if (isset($data[$optionalField]) && !is_string($data[$optionalField])) {
                throw new InvalidArgumentException("Core update manifest field '{$optionalField}' must be a string.");
            }
        }
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Core update manifest must decode to an object.');
        }

        return new self($decoded);
    }

    /** Target version this update brings the CMS to. */
    public function version(): string
    {
        return $this->data['version'];
    }

    /** Minimum current CMS version this update may be applied on top of. */
    public function requires(): string
    {
        return $this->data['requires'];
    }

    public function name(): ?string
    {
        return isset($this->data['name']) ? (string)$this->data['name'] : null;
    }

    public function notes(): ?string
    {
        return isset($this->data['notes']) ? (string)$this->data['notes'] : null;
    }
}
