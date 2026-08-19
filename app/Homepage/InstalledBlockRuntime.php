<?php
declare(strict_types=1);

namespace Erased\Homepage;

use Erased\Packages\InstalledPackageRepository;
use Erased\Packages\PackageManifest;
use RuntimeException;
use Throwable;

/**
 * Registers each enabled installed package's declared homepage_blocks
 * (app/Packages/PackageManifest.php::homepageBlocks()) into a shared
 * BlockDefinitionRegistry, mirroring InstalledCapabilityRuntime and
 * InstalledServiceRuntime's exact shape. A package with an invalid block
 * declaration is skipped with its error recorded (not fatal for every other
 * package's blocks), since a plugin author's mistake shouldn't take down
 * Homepage Studio for everyone.
 */
final class InstalledBlockRuntime
{
    /** @var array<string,string> block id => owning package id */
    private array $owners = [];

    /** @var array<string,string> package id => error message, for packages whose declared blocks failed to register */
    private array $errors = [];

    public function __construct(
        private readonly InstalledPackageRepository $packages,
        private readonly BlockDefinitionRegistry $registry,
    ) {
    }

    public function refresh(): void
    {
        foreach (array_keys($this->owners) as $blockId) {
            $this->registry->unregister($blockId);
        }
        $this->owners = [];
        $this->errors = [];

        foreach ($this->packages->all() as $row) {
            if (($row['enabled'] ?? false) !== true) {
                continue;
            }

            $manifestData = $row['manifest'] ?? null;
            if (!is_array($manifestData)) {
                continue;
            }

            try {
                $manifest = new PackageManifest($manifestData);
                $this->registerBlocksFor($manifest);
            } catch (Throwable $error) {
                $this->errors[(string)($row['package_id'] ?? 'unknown')] = $error->getMessage();
            }
        }
    }

    public function registry(): BlockDefinitionRegistry
    {
        return $this->registry;
    }

    /** @return array<string,string> block id => owning package id */
    public function owners(): array
    {
        $owners = $this->owners;
        ksort($owners);
        return $owners;
    }

    /** @return array<string,string> package id => error message */
    public function errors(): array
    {
        return $this->errors;
    }

    private function registerBlocksFor(PackageManifest $manifest): void
    {
        foreach ($manifest->homepageBlocks() as $block) {
            if (!is_array($block)) {
                throw new RuntimeException("Package '{$manifest->id()}' declares a homepage block that isn't an object.");
            }

            $definition = new BlockDefinition(
                (string)($block['id'] ?? ''),
                $manifest->id(),
                (string)($block['title'] ?? ''),
                (string)($block['category'] ?? ''),
                (string)($block['service_id'] ?? ''),
                $this->stringList($block['requires_capabilities'] ?? []),
                (string)($block['description'] ?? ''),
            );

            $blockId = $definition->id();
            if (isset($this->owners[$blockId])) {
                throw new RuntimeException(
                    "Enabled packages '{$this->owners[$blockId]}' and '{$manifest->id()}' both declare homepage block '{$blockId}'.",
                );
            }

            $this->registry->register($definition);
            $this->owners[$blockId] = $manifest->id();
        }
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
