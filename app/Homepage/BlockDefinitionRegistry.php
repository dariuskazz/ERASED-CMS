<?php
declare(strict_types=1);

namespace Erased\Homepage;

use Erased\Capabilities\CapabilityResolver;

/**
 * Single source of truth for "what homepage section types exist." Built-in
 * sections are registered from homepage_studio_blocks() (title/description/
 * default region/icon still live there - that's presentation data this
 * registry doesn't need to duplicate), each wrapped in the same
 * BlockDefinition shape a plugin-provided section will eventually use
 * (packageId/serviceId/requiredCapabilities already exist on that class for
 * exactly this reason - see v0.6-dev's Plugin API in ROADMAP.md).
 *
 * v0.4 scope is registration and lookup for the built-in set only. Runtime
 * registration from installed packages is deliberately v0.6 work, not
 * guessed at here - this class exists so that later work has one real place
 * to call register() from, instead of every consumer hand-rolling its own
 * BlockDefinition list (which is exactly what LayoutStudioAdminRoute did
 * before this existed).
 */
final class BlockDefinitionRegistry
{
    private const BUILTIN_PACKAGE_ID = 'erased.legacy-homepage';

    /** @var array<string,BlockDefinition> */
    private array $definitions = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    public function register(BlockDefinition $definition): void
    {
        $this->definitions[$definition->id()] = $definition;
    }

    public function unregister(string $id): void
    {
        unset($this->definitions[$id]);
    }

    /**
     * @return list<BlockDefinition> every registered block, or - when a
     *   resolver is given - only those whose required capabilities are all
     *   currently satisfied. Filtering only: an unavailable block's saved
     *   placements in existing layouts are never touched by this, only its
     *   visibility in pickers that call this method.
     */
    public function all(?CapabilityResolver $capabilities = null): array
    {
        $definitions = array_values($this->definitions);
        if ($capabilities === null) {
            return $definitions;
        }
        return array_values(array_filter($definitions, fn(BlockDefinition $d): bool => $this->isAvailable($d, $capabilities)));
    }

    public function find(string $id): ?BlockDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    public function exists(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->definitions);
    }

    /** @return array<string,list<BlockDefinition>> grouped by category, in registration order */
    public function byCategory(?CapabilityResolver $capabilities = null): array
    {
        $grouped = [];
        foreach ($this->all($capabilities) as $definition) {
            $grouped[$definition->category()][] = $definition;
        }
        return $grouped;
    }

    private function isAvailable(BlockDefinition $definition, CapabilityResolver $capabilities): bool
    {
        foreach ($definition->requiredCapabilities() as $capability) {
            if (!$capabilities->has($capability)) {
                return false;
            }
        }
        return true;
    }

    private function registerBuiltins(): void
    {
        if (!function_exists('homepage_studio_blocks')) {
            return;
        }
        foreach (homepage_studio_blocks() as $blockId => $metadata) {
            $this->register(new BlockDefinition(
                (string) $blockId,
                self::BUILTIN_PACKAGE_ID,
                (string) ($metadata[0] ?? $blockId),
                self::builtinCategory((string) $blockId),
                'legacy.homepage.' . $blockId,
                [],
                (string) ($metadata[1] ?? ''),
            ));
        }
    }

    private static function builtinCategory(string $blockId): string
    {
        return match ($blockId) {
            'features' => 'marketing',
            'featured', 'latest_posts', 'categories', 'popular_tags', 'latest_comments' => 'content',
            default => 'other',
        };
    }
}
