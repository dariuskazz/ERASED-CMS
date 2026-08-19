<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockDefinition;
use Erased\Homepage\BlockDefinitionRegistry;
use Erased\Homepage\BlockPlacement;
use Erased\Homepage\HomepagePlacementRepository;
use Erased\Homepage\InstalledBlockRuntime;
use Erased\Packages\InstalledPackageRepository;
use Erased\Website\WebsiteProfileRepository;
use PDO;
use RuntimeException;
use Throwable;

final class LayoutStudioAdminRoute
{
    private readonly BlockDefinitionRegistry $registry;

    public function __construct(
        private readonly LayoutStudioAdminScreen $screen,
        private readonly PDO $pdo,
    ) {
        $this->registry = new BlockDefinitionRegistry();
        $blocks = new InstalledBlockRuntime(new InstalledPackageRepository($this->pdo), $this->registry);
        $blocks->refresh();
    }

    public function render(string $profileId = 'default', string $query = '', ?int $authorId = null): string
    {
        [$definitions, $regions, $published, $palette, $registryBlocks] = $this->legacyState($profileId);
        $canvasModel = new LayoutCanvas($regions);
        $service = $this->draftService($this->registry->ids(), $regions);
        $draft = $service->createFromPublished($profileId, 'homepage', 'homepage', $published, $canvasModel, $authorId, 'Homepage draft');
        $placements = $this->reconcileRegions($service->placements($draft), $regions);
        $canvas = $canvasModel->arrange($placements);

        $html = $this->screen->render([
            'profile_id' => $profileId,
            'profiles' => $this->websiteProfiles(),
            'query' => $query,
            'regions' => $regions,
            'canvas' => $canvas,
            'palette' => $palette,
            'registry_blocks' => $registryBlocks,
            'mode' => 'draft',
        ]);

        $escapedProfile = htmlspecialchars($profileId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrfToken = function_exists('csrf') ? htmlspecialchars((string)csrf(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $html = str_replace(
            'data-layout-studio data-profile-id="'.$escapedProfile.'"',
            'data-layout-studio data-profile-id="'.$escapedProfile.'" data-layout-revision="'.$draft->revision().'" data-layout-csrf="'.$csrfToken.'"',
            $html,
        );
        $html = str_replace('data-layout-save disabled', 'data-layout-save', $html);
        return $html;
    }

    /** @param array<string,mixed> $input */
    public function save(string $profileId, array $input, ?int $authorId = null): LayoutDraft
    {
        [, $regions] = $this->legacyState($profileId);
        $rows = $input['placements'] ?? null;
        $revision = (int)($input['revision'] ?? 0);
        if (!is_array($rows) || $revision < 1) throw new RuntimeException('Layout draft request is incomplete.');

        $placements = [];
        foreach ($rows as $position => $row) {
            if (!is_array($row)) throw new RuntimeException('Layout placement payload is invalid.');
            $placements[] = new BlockPlacement(
                (string)($row['instance_id'] ?? ''),
                $profileId,
                (string)($row['region'] ?? ''),
                (string)($row['block_id'] ?? ''),
                (int)$position,
                (bool)($row['visible'] ?? true),
                is_array($row['settings'] ?? null) ? $row['settings'] : [],
            );
        }

        return $this->draftService($this->registry->ids(), $regions)->autosave(
            $profileId,
            'homepage',
            'homepage',
            $placements,
            new LayoutCanvas($regions),
            $revision,
            $authorId,
        );
    }

    /** @return array{revision:int,published:int} */
    public function publish(string $profileId='default'): array
    {
        return (new LayoutDraftPublisher(
            $this->pdo,
            new LayoutDraftRepository($this->pdo),
            new LayoutSerializer(),
            new HomepagePlacementRepository($this->pdo),
        ))->publish($profileId,'homepage','homepage');
    }

    /** @return array{0:array<string,array>,1:list<string>,2:list<BlockPlacement>,3:array<string,list<BlockDefinition>>,4:list<BlockDefinition>} */
    private function legacyState(string $profileId): array
    {
        if (!function_exists('homepage_studio_blocks') || !function_exists('homepage_studio_config')) {
            throw new RuntimeException('Legacy Homepage Studio helpers are unavailable.');
        }
        $definitions = homepage_studio_blocks();
        $config = homepage_studio_config();
        $regions = array_values(array_unique(array_map('strval', $config['active_regions'] ?? ['center'])));
        if ($regions === []) $regions = ['center'];
        $enabled = array_fill_keys(array_map('strval', $config['enabled'] ?? []), true);
        $order = array_values(array_map('strval', $config['order'] ?? array_keys($definitions)));
        $regionMap = is_array($config['regions'] ?? null) ? $config['regions'] : [];
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        $capabilities = function_exists('capability_runtime') ? capability_runtime()->resolver() : null;
        $palette = $this->registry->byCategory($capabilities);
        $registryBlocks = $this->registry->all();
        $published = [];
        foreach ($order as $position => $blockId) {
            if (!isset($definitions[$blockId])) continue;
            $metadata = $definitions[$blockId];
            $region = (string)($regionMap[$blockId] ?? $metadata[2] ?? 'center');
            if (!in_array($region, $regions, true)) $region = in_array('center', $regions, true) ? 'center' : $regions[0];
            $published[] = new BlockPlacement('legacy-'.$blockId, $profileId, $region, $blockId, (int)$position, isset($enabled[$blockId]), is_array($options[$blockId] ?? null) ? $options[$blockId] : []);
        }
        return [$definitions, $regions, $published, $palette, $registryBlocks];
    }

    /**
     * A saved draft can predate a later change to the active region set
     * (e.g. "Website Layout & Look" narrowed a 3-column preset to 'one',
     * dropping 'left'/'right') - createFromPublished() returns an existing
     * draft as-is with no re-validation against the *current* regions, so
     * without this, LayoutCanvas::arrange() hard-throws and fatals the
     * whole editing screen for that profile with no way to fix it through
     * the UI. Remaps any placement whose region no longer exists to
     * 'center' (or the first available region), mirroring the same
     * fallback legacyState() already uses for legacy block placements -
     * LayoutCanvas::arrange() itself stays strict (relied on by
     * tools/test-layout-studio-foundation.php to reject genuinely invalid
     * regions on the write path).
     * @param list<BlockPlacement> $placements @param list<string> $regions
     * @return list<BlockPlacement>
     */
    private function reconcileRegions(array $placements, array $regions): array
    {
        $fallback = in_array('center', $regions, true) ? 'center' : ($regions[0] ?? 'center');
        return array_map(
            static fn(BlockPlacement $p): BlockPlacement => in_array($p->region(), $regions, true) ? $p : $p->withRegion($fallback),
            $placements,
        );
    }

    /**
     * v0.7-dev: website profiles for the editing screen's profile switcher.
     * Defensive like erased_active_homepage_profile_id() - a fresh install
     * or a website-profiles-less environment just gets an empty list, which
     * the screen renders as no switcher at all rather than an error.
     * @return list<array{id:string,name:string,status:string}>
     */
    private function websiteProfiles(): array
    {
        if (!class_exists(WebsiteProfileRepository::class)) return [];
        try {
            $rows = (new WebsiteProfileRepository($this->pdo))->all();
        } catch (Throwable $error) {
            return [];
        }
        return array_map(static fn(array $row): array => [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'status' => (string)$row['status'],
        ], $rows);
    }

    /** @param list<string> $blockIds @param list<string> $regions */
    private function draftService(array $blockIds, array $regions): LayoutDraftService
    {
        return new LayoutDraftService(
            new LayoutDraftRepository($this->pdo),
            new LayoutSerializer(),
            new LegacyLayoutDocumentValidator($blockIds, $regions),
        );
    }

}
