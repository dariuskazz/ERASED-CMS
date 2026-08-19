<?php
declare(strict_types=1);

namespace Erased\Packages;

use InvalidArgumentException;

final class PackageManifest
{
    public const TYPES = [
        'module',
        'theme',
        'language',
        'website-type',
        'homepage-preset',
        'widget',
    ];

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
        foreach (['id', 'type', 'name', 'version', 'requires', 'author', 'description'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InvalidArgumentException("Package manifest field '{$field}' is required.");
            }
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $data['id'])) {
            throw new InvalidArgumentException('Package id contains unsupported characters.');
        }

        if (!in_array($data['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported package type: '.$data['type']);
        }

        foreach (['version', 'requires'] as $versionField) {
            if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $data[$versionField])) {
                throw new InvalidArgumentException("{$versionField} must use semantic versioning.");
            }
        }

        foreach (['provides', 'requires_capabilities'] as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            if (!is_array($data[$field])) {
                throw new InvalidArgumentException("Package manifest field '{$field}' must be an array.");
            }
            foreach ($data[$field] as $capability) {
                if (!is_string($capability) || !preg_match('/^[a-z0-9][a-z0-9._-]*$/', $capability)) {
                    throw new InvalidArgumentException("Package manifest field '{$field}' contains an invalid capability.");
                }
            }
        }

        foreach (['homepage_blocks', 'admin_routes', 'admin_menu', 'permissions', 'marketplace'] as $arrayField) {
            if (isset($data[$arrayField]) && !is_array($data[$arrayField])) {
                throw new InvalidArgumentException("Package manifest field '{$arrayField}' must be an array.");
            }
        }

        if (isset($data['pricing'])) {
            if (!is_array($data['pricing'])) {
                throw new InvalidArgumentException("Package manifest field 'pricing' must be an array.");
            }
            $model = $data['pricing']['model'] ?? null;
            if (!in_array($model, ['free', 'paid'], true)) {
                throw new InvalidArgumentException("Package manifest field 'pricing.model' must be 'free' or 'paid'.");
            }
            if ($model === 'paid') {
                $priceMinor = $data['pricing']['price_minor'] ?? null;
                if (!is_int($priceMinor) || $priceMinor < 0) {
                    throw new InvalidArgumentException("Paid packages must declare 'pricing.price_minor' as a non-negative integer.");
                }
                $currency = $data['pricing']['currency'] ?? null;
                if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/', $currency)) {
                    throw new InvalidArgumentException("Paid packages must declare 'pricing.currency' as a 3-letter uppercase currency code.");
                }
            }
        }

        if ($data['type'] === 'theme') {
            if (!isset($data['theme_scope']) || !in_array($data['theme_scope'], ['admin', 'website'], true)) {
                throw new InvalidArgumentException("Theme packages must declare theme_scope as 'admin' or 'website'.");
            }
            if (
                !isset($data['assets']) || !is_string($data['assets'])
                || !preg_match('/^[a-zA-Z0-9._-]+\.css$/', $data['assets'])
            ) {
                throw new InvalidArgumentException('Theme packages must declare assets as a plain "*.css" filename (no subdirectories) - it is served directly via /theme-asset/.');
            }
            if (
                isset($data['scripts'])
                && (!is_string($data['scripts']) || !preg_match('/^[a-zA-Z0-9._-]+\.js$/', $data['scripts']))
            ) {
                throw new InvalidArgumentException('Theme packages\' optional "scripts" field must be a plain "*.js" filename (no subdirectories) if present - it is served directly via /theme-asset/.');
            }
        }

        if ($data['type'] === 'language') {
            if (
                !isset($data['language_code']) || !is_string($data['language_code'])
                || !preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/i', $data['language_code'])
            ) {
                throw new InvalidArgumentException("Language packages must declare a valid 'language_code' (e.g. 'nb', 'pt-br').");
            }
            if (!isset($data['language_name']) || !is_string($data['language_name']) || trim($data['language_name']) === '') {
                throw new InvalidArgumentException("Language packages must declare 'language_name' (English display name).");
            }
            if (!isset($data['language_native_name']) || !is_string($data['language_native_name']) || trim($data['language_native_name']) === '') {
                throw new InvalidArgumentException("Language packages must declare 'language_native_name'.");
            }
            if (isset($data['language_rtl']) && !is_bool($data['language_rtl'])) {
                throw new InvalidArgumentException("Package field 'language_rtl' must be a boolean if present.");
            }
        }
    }

    public function themeScope(): ?string
    {
        $scope = $this->data['theme_scope'] ?? null;
        return is_string($scope) ? $scope : null;
    }

    public function assetsPath(): ?string
    {
        $assets = $this->data['assets'] ?? null;
        return is_string($assets) ? $assets : null;
    }

    public function scriptsPath(): ?string
    {
        $scripts = $this->data['scripts'] ?? null;
        return is_string($scripts) ? $scripts : null;
    }

    public function languageCode(): ?string
    {
        $code = $this->data['language_code'] ?? null;
        return is_string($code) ? strtolower($code) : null;
    }

    public function languageName(): ?string
    {
        $name = $this->data['language_name'] ?? null;
        return is_string($name) ? $name : null;
    }

    public function languageNativeName(): ?string
    {
        $name = $this->data['language_native_name'] ?? null;
        return is_string($name) ? $name : null;
    }

    public function languageIsRtl(): bool
    {
        return (bool)($this->data['language_rtl'] ?? false);
    }

    public function pricingModel(): string
    {
        $pricing = $this->data['pricing'] ?? null;
        if (!is_array($pricing)) {
            return 'free';
        }
        $model = $pricing['model'] ?? 'free';
        return $model === 'paid' ? 'paid' : 'free';
    }

    public function isPaid(): bool
    {
        return $this->pricingModel() === 'paid';
    }

    public function priceMinor(): ?int
    {
        $pricing = $this->data['pricing'] ?? null;
        if (!is_array($pricing) || !isset($pricing['price_minor']) || !is_int($pricing['price_minor'])) {
            return null;
        }
        return $pricing['price_minor'];
    }

    public function priceCurrency(): ?string
    {
        $pricing = $this->data['pricing'] ?? null;
        if (!is_array($pricing) || !isset($pricing['currency']) || !is_string($pricing['currency'])) {
            return null;
        }
        return $pricing['currency'];
    }

    /** @return array<string,mixed> */
    public function marketplace(): array
    {
        $marketplace = $this->data['marketplace'] ?? [];
        return is_array($marketplace) ? $marketplace : [];
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Package manifest must decode to an object.');
        }

        return new self($decoded);
    }

    public function id(): string { return $this->data['id']; }
    public function type(): string { return $this->data['type']; }
    public function name(): string { return $this->data['name']; }
    public function version(): string { return $this->data['version']; }
    public function requires(): string { return $this->data['requires']; }
    public function author(): string { return $this->data['author']; }
    public function description(): string { return $this->data['description']; }

    /** @return array<int,string> */
    public function dependencies(): array
    {
        return $this->stringList('dependencies');
    }

    /** @return array<int,string> */
    public function provides(): array
    {
        return $this->stringList('provides');
    }

    /** @return array<int,string> */
    public function requiredCapabilities(): array
    {
        return $this->stringList('requires_capabilities');
    }

    /** @return list<array<string,mixed>> */
    public function homepageBlocks(): array
    {
        $blocks = $this->data['homepage_blocks'] ?? [];
        return is_array($blocks) ? array_values($blocks) : [];
    }

    /** @return list<array<string,mixed>> */
    public function adminRoutes(): array
    {
        $routes = $this->data['admin_routes'] ?? [];
        return is_array($routes) ? array_values($routes) : [];
    }

    /** @return list<array<string,mixed>> */
    public function adminMenu(): array
    {
        $menu = $this->data['admin_menu'] ?? [];
        return is_array($menu) ? array_values($menu) : [];
    }

    /** @return list<array<string,mixed>> */
    public function declaredPermissions(): array
    {
        $permissions = $this->data['permissions'] ?? [];
        return is_array($permissions) ? array_values($permissions) : [];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** @return array<int,string> */
    private function stringList(string $field): array
    {
        $values = $this->data[$field] ?? [];
        return is_array($values)
            ? array_values(array_unique(array_filter($values, 'is_string')))
            : [];
    }
}
