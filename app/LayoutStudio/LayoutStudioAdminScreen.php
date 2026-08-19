<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockDefinition;
use JsonException;
use function admin_core_nav_button;
use function admin_plugin_extra_groups_html;
use function admin_plugin_menu_group_html;
use function admin_sheet_code;
use function csrf;
use function setting;
use function e;
use function can;
use function can_open_settings_hub;
use function tr;

final class LayoutStudioAdminScreen
{
    /** @param array<string,mixed> $state */
    public function render(array $state): string
    {
        $rawProfileId = (string)($state['profile_id'] ?? 'default');
        $profileId = $this->escape($rawProfileId);
        /** @var list<array{id:string,name:string,status:string}> */
        $profiles = is_array($state['profiles'] ?? null) ? $state['profiles'] : [];
        $currentProfile = null;
        foreach ($profiles as $p) {
            if ($p['id'] === $rawProfileId) { $currentProfile = $p; break; }
        }
        $profileSwitcherHtml = $this->renderProfileSwitcher($profiles, $rawProfileId, $currentProfile);
        $cfg = function_exists('homepage_studio_config') ? homepage_studio_config() : [
            'regions' => ['features' => 'center', 'latest_posts' => 'center', 'categories' => 'left'],
            'enabled' => ['features', 'latest_posts', 'categories'],
            'order' => ['features', 'latest_posts', 'categories'],
        ];
        $allBlocks = function_exists('homepage_studio_blocks') ? homepage_studio_blocks() : [];
        /** @var array<string,list<\Erased\Homepage\BlockPlacement>> */
        $canvas = is_array($state['canvas'] ?? null) ? $state['canvas'] : [];

        // Real registry data (built-in + installed-package blocks), threaded in
        // by LayoutStudioAdminRoute - the actual source of truth for the palette
        // and picker markup below, replacing what used to be static sample HTML
        // with fake ids that had no relation to what could actually be placed.
        /** @var array<string,list<BlockDefinition>> */
        $palette = is_array($state['palette'] ?? null) ? $state['palette'] : [];
        $paletteFlat = array_merge([], ...array_values($palette));

        /** @var list<BlockDefinition> */
        $registryBlocks = is_array($state['registry_blocks'] ?? null) ? $state['registry_blocks'] : [];
        $registryById = [];
        foreach ($registryBlocks as $definition) {
            if ($definition instanceof BlockDefinition) $registryById[$definition->id()] = $definition;
        }
        try {
            $knownBlockIdsJson = $this->escape(json_encode(array_keys($registryById), JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            $knownBlockIdsJson = '[]';
        }

        // Renders one region column's contents as a stack of .ls-container
        // cards from the *real* saved placements for this profile (not the
        // legacy global settings) - a "container" = one or more consecutive
        // placements sharing a container_id, grouped exactly the way
        // PublishedHomepageRenderer groups them for the live site, so what
        // this editor shows on load matches what was actually saved
        // (previously this always re-derived a flat, single-column view from
        // homepage_studio_config() regardless of what was really published).
        $renderRegionList = function(string $region) use ($canvas, $allBlocks, $registryById): string {
            $placements = $canvas[$region] ?? [];
            if (empty($placements)) {
                return '<div class="ls-region-empty">Drop containers or blocks here</div>';
            }

            $groups = [];
            foreach ($placements as $placement) {
                $settings = $placement->settings();
                $containerId = isset($settings['container_id']) && is_string($settings['container_id']) ? $settings['container_id'] : null;
                $lastIndex = count($groups) - 1;
                if ($containerId !== null && $lastIndex >= 0 && ($groups[$lastIndex]['container_id'] ?? null) === $containerId) {
                    $groups[$lastIndex]['items'][] = $placement;
                } else {
                    $groups[] = ['container_id' => $containerId, 'items' => [$placement]];
                }
            }

            $html = '';
            $containerCount = 1;
            foreach ($groups as $group) {
                $items = $group['items'];
                $columnCount = count($items);
                $layout = $columnCount >= 3 ? 'three' : ($columnCount === 2 ? 'two' : 'one');
                $pct = $columnCount >= 3 ? '33%' : ($columnCount === 2 ? '50%' : '100%');
                $cId = htmlspecialchars((string)($group['container_id'] ?? ('container-' . $region . '-' . $containerCount)), ENT_QUOTES, 'UTF-8');
                $hidden = !$items[0]->visible();
                $hiddenAttr = $hidden ? ' data-hidden="true"' : '';
                $hiddenClass = $hidden ? ' ls-container-hidden' : '';

                $groupSettings = $items[0]->settings();
                $scheduleStart = isset($groupSettings['schedule_start']) && is_string($groupSettings['schedule_start']) ? $groupSettings['schedule_start'] : '';
                $scheduleEnd = isset($groupSettings['schedule_end']) && is_string($groupSettings['schedule_end']) ? $groupSettings['schedule_end'] : '';
                $isScheduled = $scheduleStart !== '' || $scheduleEnd !== '';
                $scheduleAttr = ($scheduleStart !== '' ? ' data-schedule-start="' . htmlspecialchars($scheduleStart, ENT_QUOTES, 'UTF-8') . '"' : '')
                    . ($scheduleEnd !== '' ? ' data-schedule-end="' . htmlspecialchars($scheduleEnd, ENT_QUOTES, 'UTF-8') . '"' : '');
                $scheduleClass = $isScheduled ? ' ls-container-scheduled' : '';

                $hideMobile = !empty($groupSettings['hide_mobile']);
                $hideDesktop = !empty($groupSettings['hide_desktop']);
                $deviceLimited = $hideMobile || $hideDesktop;
                $deviceAttr = ($hideMobile ? ' data-hide-mobile="true"' : '') . ($hideDesktop ? ' data-hide-desktop="true"' : '');
                $deviceClass = $deviceLimited ? ' ls-container-device-limited' : '';

                $bgColor = isset($groupSettings['bg_color']) && is_string($groupSettings['bg_color']) && preg_match('/^#[0-9a-f]{3,8}$/i', $groupSettings['bg_color']) === 1 ? $groupSettings['bg_color'] : '';
                $padding = isset($groupSettings['padding']) && is_string($groupSettings['padding']) ? trim($groupSettings['padding']) : '';
                $styled = $bgColor !== '' || $padding !== '';
                $styleAttr = ($bgColor !== '' ? ' data-bg-color="' . htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8') . '"' : '')
                    . ($padding !== '' ? ' data-padding="' . htmlspecialchars($padding, ENT_QUOTES, 'UTF-8') . '"' : '');
                $styledClass = $styled ? ' ls-container-styled' : '';

                $widthMode = isset($groupSettings['width_mode']) && in_array($groupSettings['width_mode'], ['narrow', 'full-bleed'], true) ? $groupSettings['width_mode'] : '';
                $widthValue = isset($groupSettings['width_value']) && is_string($groupSettings['width_value']) ? trim($groupSettings['width_value']) : '';
                $widthAttr = ($widthMode !== '' ? ' data-width-mode="' . htmlspecialchars($widthMode, ENT_QUOTES, 'UTF-8') . '"' : '')
                    . ($widthMode === 'narrow' && $widthValue !== '' ? ' data-width-value="' . htmlspecialchars($widthValue, ENT_QUOTES, 'UTF-8') . '"' : '');
                $widthBadgeHtml = $widthMode !== '' ? '<span class="ls-container-width-badge">' . ($widthMode === 'full-bleed' ? 'FULL WIDTH' : 'NARROW') . '</span>' : '';

                $groupTitle = htmlspecialchars($this->resolveBlockTitle($items[0]->blockId(), $allBlocks, $registryById), ENT_QUOTES, 'UTF-8');

                $blocksHtml = '';
                foreach ($items as $colIndex => $placement) {
                    $blockId = $placement->blockId();
                    $title = htmlspecialchars($this->resolveBlockTitle($blockId, $allBlocks, $registryById), ENT_QUOTES, 'UTF-8');
                    $sId = 'slot-' . $region . '-' . $containerCount . '-' . ($colIndex + 1);
                    $blocksHtml .= '<div class="ls-block" data-slot-id="' . $sId . '" data-slot-index="' . $colIndex . '" data-block-id="' . htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8') . '" data-block-title="' . $title . '" data-category="content">
                      <span class="ls-block-num">#' . ($colIndex + 1) . '</span>
                      <strong class="ls-block-label">' . $title . '</strong>
                      <div class="ls-block-actions">
                        <button type="button" class="ls-container-gear" data-container-option="' . $cId . '" title="Container Options">⚙</button>
                      </div>
                      <div class="ls-block-dim"><span class="l"></span><span class="val">' . $pct . '</span><span class="l"></span></div>
                    </div>';
                }

                $html .= '<article class="ls-container' . $hiddenClass . $scheduleClass . $deviceClass . $styledClass . '" draggable="true" data-container-id="' . $cId . '" data-layout="' . $layout . '" data-region="' . $region . '"' . $hiddenAttr . $scheduleAttr . $deviceAttr . $styleAttr . $widthAttr . '>
                  <header class="ls-container-head">
                    <span class="ls-container-title">Container #' . $containerCount . ' (' . $groupTitle . ')' . $widthBadgeHtml . '</span>
                    <span class="ls-container-move"><button type="button" class="btn ghost ls-container-up" title="Move container up" aria-label="Move container up">&uarr;</button><button type="button" class="btn ghost ls-container-down" title="Move container down" aria-label="Move container down">&darr;</button></span>
                  </header>
                  <div class="ls-cols ' . $layout . '">' . $blocksHtml . '</div>
                </article>';
                $containerCount++;
            }

            return $html;
        };

        // The "rich" block types (just 'features' now - hero/cta/pricing/testimonials/
        // stats/team were removed 2026-08-16) are the ones homepage_studio_public_blocks()
        // actually renders from per-type content settings (title, subtitle, button
        // text/url, accent colour) via homepage_widget_options - the Spec Sheet panel
        // below edits exactly this, pre-merged with defaults so the fields always start
        // populated with what would actually render right now, not empty or purely
        // cosmetic values.
        $richBlockTypes = ['features'];
        $widgetOptionsRaw = function_exists('setting') ? json_decode((string)setting('homepage_widget_options', '{}'), true) : [];
        if (!is_array($widgetOptionsRaw)) $widgetOptionsRaw = [];
        $widgetOptions = [];
        foreach ($richBlockTypes as $richType) {
            $widgetOptions[$richType] = function_exists('homepage_studio_block_options')
                ? homepage_studio_block_options($richType, $widgetOptionsRaw)
                : [];
        }
        try {
            $widgetOptionsJson = $this->escape(json_encode($widgetOptions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException) {
            $widgetOptionsJson = '{}';
        }

        // The admin panel's theme is the admin_theme setting, rendered server-side
        // as a [data-theme] attribute (same as every other admin screen via
        // layout() in index.php). This screen renders as its own standalone
        // document (a fixed, full-height 3-panel workspace doesn't fit the
        // scrolling .canvas every other screen uses), so it duplicates the shared
        // .frame/.rail/.head shell markup and computes that same attribute itself
        // rather than going through layout().
        $adminTheme = function_exists('setting') ? setting('admin_theme', 'dark-green') : 'dark-green';
        $themeSlug = in_array($adminTheme, ['dark', 'dark-green', 'dark-grey', 'light-grey', 'ops-deck'], true) ? $adminTheme : 'dark-green';
        $bodyClass = 'admin-area';

        // ERASED Studio embeds this screen chrome-free inside an iframe
        // (/admin/studio's Layout tab, ?studio_embed=1) - matching the same
        // query flag index.php's own layout() already honors for every other
        // embedded tab (Media, Navigation, Theme). This screen never goes
        // through layout() (see the standalone-document comment above), so
        // it can't reuse that branch directly; instead the .frame keeps its
        // real structure (every data-* attribute below is a real JS hook)
        // and just gets one extra class that hides the rail/head via CSS -
        // safer than a second, structurally different minimal document.
        $isEmbedded = ($_GET['studio_embed'] ?? '') === '1';
        $frameClass = 'frame' . ($isEmbedded ? ' studio-embedded' : '');

        ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Layout — ERASED CMS</title>
<link rel="stylesheet" href="/assets/admin-design-system.css?v=8.58">
<style>
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0;height:100%}
</style>
</head>
<body class="<?= $this->escape($bodyClass) ?>" data-theme="<?= $this->escape($themeSlug) ?>">

<div class="<?= $this->escape($frameClass) ?>" id="frame" data-admin-studio-root data-layout-studio data-profile-id="<?= $profileId ?>" data-widget-options="<?= $widgetOptionsJson ?>" data-known-block-ids="<?= $knownBlockIdsJson ?>">
  <aside class="rail" data-system-nav aria-label="Sheet index">
    <a class="rail-mark" href="/admin"><span class="sq">E</span><span><?= e(tr('dashboard', 'admin')) ?></span></a>
    <nav class="rail-list">
      <p class="rail-group-label">Content</p>
      <?= admin_core_nav_button('posts', '/admin/appearance/homepage') ?>
      <?= admin_core_nav_button('pages', '/admin/appearance/homepage') ?>
      <?= admin_core_nav_button('publishing', '/admin/appearance/homepage') ?>
      <?= admin_core_nav_button('media', '/admin/appearance/homepage') . admin_core_nav_button('galleries', '/admin/appearance/homepage') ?>
      <?= admin_core_nav_button('comments', '/admin/appearance/homepage') ?>
      <?= function_exists('admin_plugin_menu_group_html') ? admin_plugin_menu_group_html('Content', '/admin/appearance/homepage') : '' ?>
      <p class="rail-group-label">Site</p>
      <?= admin_core_nav_button('users', '/admin/appearance/homepage') ?>
      <?= admin_core_nav_button('payments', '/admin/appearance/homepage') ?>
      <?= admin_core_nav_button('layout_studio', '/admin/appearance/homepage', true) . admin_core_nav_button('erased_studio', '/admin/appearance/homepage') ?>
      <?= function_exists('admin_plugin_menu_group_html') ? admin_plugin_menu_group_html('Site', '/admin/appearance/homepage') : '' ?>
      <p class="rail-group-label">System</p>
      <?= admin_core_nav_button('settings', '/admin/appearance/homepage') ?>
      <?= function_exists('admin_plugin_menu_group_html') ? admin_plugin_menu_group_html('System', '/admin/appearance/homepage') : '' ?>
      <?= function_exists('admin_plugin_extra_groups_html') ? admin_plugin_extra_groups_html('/admin/appearance/homepage') : '' ?>
    </nav>
    <div class="rail-foot"><span class="dot"></span><a href="/" target="_blank">View site</a><span class="sep">·</span><a href="/logout">Logout</a></div>
  </aside>

  <header class="head">
    <button type="button" class="rail-toggle" data-system-nav-toggle title="Hide sheet index" aria-label="Hide sheet index">
      <svg class="icon" viewBox="0 0 24 24" width="14" height="14"><rect x="3" y="4" width="18" height="16" rx="1"/><line x1="9" y1="4" x2="9" y2="20"/></svg>
    </button>
    <nav class="stamp" aria-label="Location"><span class="sheet-no mono"><?= e(admin_sheet_code('/admin/appearance/homepage')) ?></span><a href="/admin">Dashboard</a><span class="sep">/</span><b aria-current="page">Layout</b></nav>
    <form class="find" method="get" action="/admin/content">
      <span>FIND</span>
      <input type="search" placeholder="search content…">
    </form>
    <?php if (can('comments.manage')):
      $pendingCount = 0;
      try { $pendingCount = (int)db()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn(); } catch (\Throwable $e) {}
    ?>
    <button type="button" class="head-icon-btn" title="Pending Comments" onclick="window.location.href='/admin/comments?status=pending'">
      <svg class="icon" viewBox="0 0 24 24" width="16" height="16"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if ($pendingCount > 0): ?><span class="dot"><?= $pendingCount ?></span><?php endif; ?>
    </button>
    <?php endif; ?>
  </header>

  <main class="canvas"><div class="sheet-inner ls-sheet">

    <div class="title-row">
      <div>
        <h1>Layout</h1>
        <p><span class="stampword draft" data-layout-status>Draft · Unsaved changes</span></p>
        <?= $profileSwitcherHtml ?>
      </div>
      <div class="rule"></div>
      <div class="actions">
        <button type="button" class="btn ghost" data-layout-undo title="Undo last action">Undo</button>
        <button type="button" class="btn ghost" data-layout-redo title="Redo action">Redo</button>
        <button type="button" class="btn ghost" data-layout-discard title="Discard changes">Discard</button>
        <button type="button" class="btn ghost" data-layout-save title="Save draft">Save Draft</button>
        <button type="button" class="btn" data-layout-publish title="Publish live">Publish</button>
      </div>
    </div>

    <div class="ls-canvas-head">
      <div class="ls-viewtabs" role="tablist">
        <button type="button" class="ls-viewtab is-active" data-canvas-tab="map" role="tab" aria-selected="true">Map View</button>
        <button type="button" class="ls-viewtab" data-canvas-tab="website" role="tab" aria-selected="false">Live Preview</button>
      </div>
      <div class="ls-devices" role="group" aria-label="Device viewport">
        <button type="button" class="ls-devicebtn is-active" data-device-select="desktop">Desktop</button>
        <button type="button" class="ls-devicebtn" data-device-select="tablet">Tablet</button>
        <button type="button" class="ls-devicebtn" data-device-select="mobile">Mobile</button>
      </div>
      <button type="button" class="btn ghost" data-open-website-look-modal>Website Layout &amp; Look</button>
    </div>

    <div class="ls-shell">

      <!-- LEFT COLUMN: COMPONENT SCHEDULE (block library) — flat single-line
           list matching the sketch exactly: empty .sq, title, mono code
           number. No groups/descriptions/search - the sketch has none. -->
      <div class="ls-col" aria-label="Block palette">
        <div class="ls-col-head">Component Schedule</div>
        <?php foreach ($paletteFlat as $paletteIndex => $paletteBlock): echo $this->paletteItemMarkup($paletteBlock, $paletteIndex + 1); endforeach; ?>
      </div>

      <!-- CENTER COLUMN: THE SCHEMATIC -->
      <div class="ls-col ls-canvas" data-layout-canvas>

        <div class="ls-view is-active" data-canvas-view="map">
          <div class="ls-map" data-draft-map data-viewport-device="desktop">
            <div class="ls-frame-shell">

              <div class="ls-region header" data-structure-region="header">
                <span class="ls-region-tag">Global Header — Fixed</span>
                <div class="ls-container-list" data-region-list="header">
                  <article class="ls-container" data-container-id="container-header" data-layout="one" data-region="header" style="border-color:var(--accent)">
                    <header class="ls-container-head">
                      <span class="ls-container-title">Global Header Navigation &amp; Tools</span>
                    </header>
                    <div class="ls-cols one">
                      <div class="ls-block" data-slot-id="header-s1" data-slot-index="0" data-block-id="header-nav" data-block-title="Header Nav &amp; Search" data-category="navigation">
                        <span class="ls-block-num">TOP</span>
                        <strong class="ls-block-label">Header Nav Bar (Logo, Links, Search &amp; Plugins)</strong>
                        <div class="ls-block-dim"><span class="l"></span><span class="val">100%</span><span class="l"></span></div>
                      </div>
                    </div>
                  </article>
                </div>
              </div>

              <div class="ls-cols three" data-map-regions-grid>
                <section class="ls-region" data-structure-region="left">
                  <span class="ls-region-tag">Region — Left</span>
                  <button type="button" class="ls-region-add" data-map-add-container="left">+ Add</button>
                  <div class="ls-container-list" data-region-list="left"><?= $renderRegionList('left') ?></div>
                </section>

                <section class="ls-region" data-structure-region="center">
                  <span class="ls-region-tag">Region — Center</span>
                  <button type="button" class="ls-region-add" data-map-add-container="center">+ Add</button>
                  <div class="ls-container-list" data-region-list="center"><?= $renderRegionList('center') ?></div>
                </section>

                <section class="ls-region" data-structure-region="right">
                  <span class="ls-region-tag">Region — Right</span>
                  <button type="button" class="ls-region-add" data-map-add-container="right">+ Add</button>
                  <div class="ls-container-list" data-region-list="right"><?= $renderRegionList('right') ?></div>
                </section>
              </div>

              <div class="ls-region footer" data-structure-region="footer">
                <span class="ls-region-tag">Global Footer — Fixed</span>
                <div class="ls-container-list" data-region-list="footer">
                  <article class="ls-container" data-container-id="container-footer" data-layout="one" data-region="footer" style="border-color:var(--warn)">
                    <header class="ls-container-head">
                      <span class="ls-container-title">Global Footer Socials &amp; Subscribe</span>
                    </header>
                    <div class="ls-cols one">
                      <div class="ls-block" data-slot-id="footer-s1" data-slot-index="0" data-block-id="footer-links" data-block-title="Footer Socials &amp; Subscribe" data-category="navigation">
                        <span class="ls-block-num">BOTTOM</span>
                        <strong class="ls-block-label">Footer Bar (Social Links, Subscribe &amp; Copyright)</strong>
                        <div class="ls-block-dim"><span class="l"></span><span class="val">100%</span><span class="l"></span></div>
                      </div>
                    </div>
                  </article>
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="ls-view" data-canvas-view="website">
          <div class="ls-preview-wrap" data-preview-stage data-device="desktop">
            <iframe src="/admin/layout-studio/preview?profile=<?= $profileId ?>" title="Live Website Layout Preview" data-live-preview data-preview-frame class="ls-preview-frame"></iframe>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: SPEC SHEET (object inspector) -->
      <div class="ls-col" aria-label="Block inspector" data-layout-inspector>
        <div class="ls-col-head">Spec Sheet</div>

        <div class="ls-spec-identity" data-inspector-identity>
          <span class="ls-spec-icon" data-inspector-icon><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg></span>
          <span class="ls-spec-identity-text">
            <strong data-inspector-title>Feature Grid</strong>
            <small data-inspector-subtitle>Slot 1.1 · Center region</small>
          </span>
          <button type="button" class="btn ghost" data-inspector-replace>Replace</button>
        </div>

        <div class="ls-spectabs" role="tablist">
          <button type="button" class="ls-spectab is-active" data-inspector-tab="content" role="tab" aria-selected="true">Content</button>
          <button type="button" class="ls-spectab" data-inspector-tab="style" role="tab" aria-selected="false">Style</button>
          <button type="button" class="ls-spectab" data-inspector-tab="advanced" role="tab" aria-selected="false">Advanced</button>
        </div>

        <!-- TAB: CONTENT -->
        <div class="ls-specpane is-active" data-inspector-pane="content">

          <div data-inspector-group="header" hidden>
            <form method="post" action="/admin/appearance/homepage<?= $isEmbedded ? '?studio_embed=1' : '' ?>">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="action" value="save_header_settings">

              <details class="ls-spec-group" open>
                <summary>Header Navigation &amp; Layout</summary>
                <div class="spec-field row">
                  <label>Show Admin Link in Public Nav</label>
                  <input type="checkbox" name="nav_show_admin" value="1" <?= setting('nav_show_admin', '1') === '1' ? 'checked' : '' ?>>
                </div>
                <div class="spec-field">
                  <label>Admin Link Label</label>
                  <input type="text" name="nav_admin_label" value="<?= e(setting('nav_admin_label', 'Admin')) ?>">
                </div>
                <div class="spec-field">
                  <label>Header Layout Style</label>
                  <select name="header_layout">
                    <option value="standard" <?= setting('header_layout', 'standard') === 'standard' ? 'selected' : '' ?>>Standard (Logo Left, Links Right)</option>
                    <option value="centered" <?= setting('header_layout', 'standard') === 'centered' ? 'selected' : '' ?>>Centered (Logo Middle)</option>
                    <option value="compact" <?= setting('header_layout', 'standard') === 'compact' ? 'selected' : '' ?>>Compact Bar</option>
                  </select>
                </div>
              </details>

              <details class="ls-spec-group" open>
                <summary>Header Search &amp; Plugins</summary>
                <div class="spec-field row">
                  <label>Enable Header Search Bar</label>
                  <input type="checkbox" name="nav_show_search" value="1" <?= setting('nav_show_search', '1') === '1' ? 'checked' : '' ?>>
                </div>
                <div class="spec-field">
                  <label>Search Placeholder</label>
                  <input type="text" name="nav_search_placeholder" value="<?= e(setting('nav_search_placeholder', 'Search posts...')) ?>">
                </div>
                <div class="spec-field row">
                  <label>Enable Language Switcher</label>
                  <input type="checkbox" name="show_language_switcher" value="1" <?= (setting('show_language_switcher', '1') === '1' || setting('nav_show_language', '1') === '1') ? 'checked' : '' ?>>
                </div>
              </details>

              <div style="padding:12px 14px;"><button type="submit" class="btn" style="width:100%;justify-content:center">Save Header Settings</button></div>
            </form>
          </div>

          <div data-inspector-group="footer" hidden>
            <form method="post" action="/admin/appearance/homepage<?= $isEmbedded ? '?studio_embed=1' : '' ?>">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="action" value="save_footer_settings">

              <details class="ls-spec-group" open>
                <summary>Social Media Channels</summary>
                <small class="field-help social-help">Pick a platform and paste its URL.</small>
                <template id="social-icon-fallback"><?= social_icon_svg('') ?></template>
                <?php
                $socialPlatforms = erased_social_platforms();
                $socialRevealed = 0;
                for ($n = 1; $n <= 8; $n++):
                    $selectedPlatform = setting('social_link_' . $n . '_platform', '');
                    $selectedUrl = setting('social_link_' . $n . '_url', '');
                    $selectedLabel = $socialPlatforms[$selectedPlatform] ?? '';
                    $hasValue = $selectedPlatform !== '' || $selectedUrl !== '';
                    if ($hasValue) $socialRevealed++;
                ?>
                <div class="spec-field social-icon-picker-row" data-social-row<?= $hasValue ? '' : ' hidden' ?>>
                  <input type="hidden" name="social_link_<?= $n ?>_platform" value="<?= e($selectedPlatform) ?>" class="social-icon-picker-value">
                  <details class="social-icon-picker" data-social-picker>
                    <summary class="social-icon-picker-trigger" title="<?= $selectedLabel !== '' ? e($selectedLabel) : 'Choose a platform' ?>" aria-label="<?= $selectedLabel !== '' ? e($selectedLabel) : 'Choose a platform' ?>">
                      <span class="social-icon-picker-trigger-icon"><?= social_icon_svg($selectedPlatform) ?></span>
                    </summary>
                    <div class="social-icon-picker-list" role="listbox" aria-label="Platform">
                      <?php foreach ($socialPlatforms as $platformKey => $platformLabel): ?>
                      <button type="button" class="social-icon-choice<?= $selectedPlatform === $platformKey ? ' is-selected' : '' ?>" data-platform="<?= e($platformKey) ?>" data-label="<?= e($platformLabel) ?>" title="<?= e($platformLabel) ?>" role="option" aria-label="<?= e($platformLabel) ?>" aria-selected="<?= $selectedPlatform === $platformKey ? 'true' : 'false' ?>"><?= social_icon_svg($platformKey) ?></button>
                      <?php endforeach; ?>
                    </div>
                  </details>
                  <input type="text" name="social_link_<?= $n ?>_url" value="<?= e($selectedUrl) ?>" placeholder="https://...">
                  <button type="button" class="social-icon-row-remove" data-social-remove title="Remove this account" aria-label="Remove this account">&times;</button>
                </div>
                <?php endfor; ?>
                <div class="social-add-row">
                  <button type="button" class="btn ghost social-add-btn" data-social-add<?= $socialRevealed >= 8 ? ' hidden' : '' ?>>+ Add link</button>
                  <label class="check rss-toggle" title="Show RSS feed icon in the footer">
                    <input type="checkbox" name="footer_show_rss" value="1" <?= setting('footer_show_rss', '1') === '1' ? 'checked' : '' ?>>
                    <span class="rss-toggle-icon"><?= social_icon_svg('rss') ?></span>
                  </label>
                </div>
              </details>

              <details class="ls-spec-group" open>
                <summary>Newsletter Subscribe</summary>
                <div class="spec-field row newsletter-enable-row">
                  <label>Enable Subscribe Form</label>
                  <input type="checkbox" name="newsletter_enabled" value="1" <?= setting('newsletter_enabled', '1') === '1' ? 'checked' : '' ?>>
                </div>
                <div class="spec-field newsletter-label-row"><label>Button Label<input type="text" name="newsletter_button_text" value="<?= e(setting('newsletter_button_text', 'Subscribe')) ?>"></label></div>
              </details>

              <details class="ls-spec-group" open>
                <summary>Footer Copyright &amp; Legal</summary>
                <div class="spec-field footer-copyright-row"><label>Footer Copyright Text<input type="text" name="footer_text" value="<?= e(setting('footer_text', '© ERASED CMS. All rights reserved.')) ?>"></label></div>
              </details>

              <div style="padding:12px 14px;"><button type="submit" class="btn" style="width:100%;justify-content:center">Save Footer Settings</button></div>
            </form>
          </div>

          <div data-inspector-group="default">
            <details class="ls-spec-group" open>
              <summary>Block Content</summary>
              <div class="spec-field"><label>Headline text<input type="text" value="Welcome to our platform" data-prop-headline></label></div>
              <div class="spec-field"><label>Subheadline<textarea rows="3" data-prop-subheadline>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</textarea></label></div>
              <div class="spec-field"><label>CTA Button label<input type="text" value="Get Started" data-prop-cta-label></label></div>
              <div class="spec-field"><label>CTA Button URL<input type="text" value="/signup" data-prop-cta-url></label></div>
              <div class="spec-field" data-prop-items-field hidden>
                <label>List items</label>
                <textarea rows="6" data-prop-items placeholder="One item per line"></textarea>
                <small class="field-help" data-prop-items-hint></small>
              </div>
              <div class="spec-field row">
                <label>Show secondary CTA</label>
                <input type="checkbox" checked data-prop-secondary-cta>
              </div>
            </details>
          </div>

          <details class="ls-spec-group" open>
            <summary>Appearance</summary>
            <div class="spec-field">
              <label>Background style</label>
              <div class="ls-seg" data-prop-bg-segmented>
                <button type="button" class="ls-segbtn" data-val="solid">Solid</button>
                <button type="button" class="ls-segbtn is-active" data-val="gradient">Gradient</button>
                <button type="button" class="ls-segbtn" data-val="image">Image</button>
              </div>
            </div>
            <div class="spec-field">
              <label>Text alignment</label>
              <div class="ls-seg" data-prop-align-segmented>
                <button type="button" class="ls-segbtn" data-val="left" title="Align Left"><svg class="icon" viewBox="0 0 24 24"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg></button>
                <button type="button" class="ls-segbtn" data-val="center" title="Align Center"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg></button>
                <button type="button" class="ls-segbtn is-active" data-val="right" title="Align Right"><svg class="icon" viewBox="0 0 24 24"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg></button>
                <button type="button" class="ls-segbtn" data-val="justify" title="Justify"><svg class="icon" viewBox="0 0 24 24"><line x1="21" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="3" y2="18"/></svg></button>
              </div>
            </div>
            <div class="spec-field"><label>Minimum height (px)<input type="number" value="480" min="100" max="2000" data-prop-min-height></label></div>
          </details>
        </div>

        <!-- TAB: STYLE -->
        <div class="ls-specpane" data-inspector-pane="style">
          <details class="ls-spec-group" open>
            <summary>Styling &amp; Colors</summary>
            <div class="spec-field">
              <label>Primary accent color</label>
              <div style="display:flex;gap:6px;">
                <input type="color" value="#2dfc98" data-prop-accent-picker style="width:34px;height:30px;padding:2px;">
                <input type="text" value="#2dfc98" data-prop-accent-hex style="flex:1">
              </div>
            </div>
            <div class="spec-field"><label>Padding (top / bottom)<input type="text" value="60px 20px" data-prop-padding></label></div>
          </details>
        </div>

        <!-- TAB: ADVANCED -->
        <div class="ls-specpane" data-inspector-pane="advanced">
          <details class="ls-spec-group" open>
            <summary>Advanced</summary>
            <div class="spec-field"><label>CSS Class Name<input type="text" value="hero-section-custom" data-prop-css-class></label></div>
            <div class="spec-field"><label>HTML Container ID<input type="text" value="hero-section-1" data-prop-html-id></label></div>
            <div class="spec-field row"><label>Enable Lazy Loading<input type="checkbox" checked data-prop-lazy-load></label></div>
          </details>
        </div>

        <div class="ls-spec-footer">
          <button type="button" class="btn ghost" data-action-reset>Reset block</button>
          <button type="button" class="btn" data-action-save>Save changes</button>
        </div>
      </div>

    </div>
  </div></main>

<!-- CONTAINER OPTIONS MODAL -->
<div class="ls-modal" data-container-options-modal hidden>
  <div class="ls-modal-backdrop" data-container-options-close></div>
  <div class="ls-modal-dialog" style="width:min(90vw,420px)">
    <header class="ls-modal-head">
      <div><h3 data-container-options-title>Container Settings</h3><small>Modify region placement and slot layout</small></div>
      <button type="button" class="ls-modal-close" data-container-options-close>✕</button>
    </header>
    <div class="ls-modal-body">
      <div class="spec-field">
        <label>Region Placement</label>
        <select data-opt-region>
          <option value="left">Left Region</option>
          <option value="center" selected>Center Region</option>
          <option value="right">Right Region</option>
        </select>
      </div>
      <div class="spec-field">
        <label>Slot Layout Preset</label>
        <select data-opt-layout>
          <option value="one">100% (1 Slot)</option>
          <option value="two">50/50 (2 Slots)</option>
          <option value="three">3×33% (3 Slots)</option>
        </select>
      </div>
      <div class="spec-field row">
        <label>Visible on the live site</label>
        <input type="checkbox" data-opt-visible checked>
      </div>
      <div class="spec-field row">
        <label>Hide on mobile</label>
        <input type="checkbox" data-opt-hide-mobile>
      </div>
      <div class="spec-field row">
        <label>Hide on desktop</label>
        <input type="checkbox" data-opt-hide-desktop>
      </div>
      <div class="spec-field">
        <label>Background color (optional)</label>
        <input type="color" data-opt-bg-color>
        <button type="button" class="btn ghost" data-opt-bg-color-clear style="margin-top:6px">Use default background</button>
      </div>
      <div class="spec-field">
        <label>Padding (optional)</label>
        <input type="text" data-opt-padding placeholder="e.g. 40px 20px">
      </div>
      <div class="spec-field">
        <label>Section width</label>
        <select data-opt-width>
          <option value="">Fill column (default)</option>
          <option value="narrow">Narrower, centered</option>
          <option value="full-bleed">Full width (breaks out of the grid)</option>
        </select>
      </div>
      <div class="spec-field" data-opt-width-value-wrap hidden>
        <label>Width (e.g. 800px, 60%)</label>
        <input type="text" data-opt-width-value placeholder="800px">
      </div>
      <p class="muted" data-opt-width-warning style="font-size:11px;margin:-6px 0 10px;" hidden>Full width can visually overlap sidebar content placed at the same height - safest in a 1-column layout, or on the last section in a region.</p>
      <div class="spec-field">
        <label>Show from (optional)</label>
        <input type="datetime-local" data-opt-schedule-start>
      </div>
      <div class="spec-field">
        <label>Show until (optional)</label>
        <input type="datetime-local" data-opt-schedule-end>
        <small class="muted">Leave both empty to show at all times. Scheduling applies on top of the visibility toggle above.</small>
      </div>
      <div class="actions" style="margin-top:10px;">
        <button type="button" class="btn ghost" data-opt-duplicate style="flex:1;justify-content:center">Duplicate Container</button>
      </div>
      <div class="actions" style="margin-top:6px;">
        <button type="button" class="btn ghost" data-opt-delete style="flex:1;justify-content:center">Delete Container</button>
        <button type="button" class="btn" data-opt-apply style="flex:1;justify-content:center">Apply Settings</button>
      </div>
    </div>
  </div>
</div>

<!-- WEBSITE LAYOUT & LOOK MODAL -->
<div class="ls-modal" data-website-look-modal hidden>
  <div class="ls-modal-backdrop" data-website-look-close></div>
  <div class="ls-modal-dialog" style="width:min(92vw,520px);max-height:85vh;overflow-y:auto;">
    <header class="ls-modal-head">
      <div><h3>Website Layout &amp; Look Settings</h3><small>Column preset, sidebar widths, gaps &amp; max width</small></div>
      <button type="button" class="ls-modal-close" data-website-look-close>✕</button>
    </header>
    <form method="post" action="/admin/appearance/homepage<?= $isEmbedded ? '?studio_embed=1' : '' ?>" class="ls-modal-body">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save_website_look_settings">

      <div class="spec-field">
        <label>Column Layout Preset</label>
        <select name="homepage_layout_preset" data-wl-preset>
          <option value="three"<?= setting('homepage_layout_preset', 'three') === 'three' ? ' selected' : '' ?>>3 Columns (Left + Center + Right)</option>
          <option value="two-left"<?= setting('homepage_layout_preset', 'three') === 'two-left' ? ' selected' : '' ?>>2 Columns (Left + Center)</option>
          <option value="two-right"<?= setting('homepage_layout_preset', 'three') === 'two-right' ? ' selected' : '' ?>>2 Columns (Center + Right)</option>
          <option value="one"<?= setting('homepage_layout_preset', 'three') === 'one' ? ' selected' : '' ?>>1 Column (Full Width Center)</option>
        </select>
      </div>

      <div class="fgrid">
        <div class="fslot"><label>Left Sidebar Width (%)<input type="number" name="homepage_left_width" data-wl-left value="<?= (int)setting('homepage_left_width', '20') ?>" min="10" max="45"></label></div>
        <div class="fslot"><label>Right Sidebar Width (%)<input type="number" name="homepage_right_width" data-wl-right value="<?= (int)setting('homepage_right_width', '20') ?>" min="10" max="45"></label></div>
      </div>
      <p class="muted" style="font-size:11px;margin:6px 0 0;">A sidebar the current preset doesn't use is disabled here and always renders as 0% regardless of its stored value - picking a preset that uses it again fills in a sane width automatically if it's currently 0.</p>
      <div class="fgrid" style="margin-top:12px;">
        <div class="fslot"><label>Column Gap (px)<input type="number" name="homepage_column_gap" data-wl-gap value="<?= (int)setting('homepage_column_gap', '24') ?>" min="0" max="80"></label></div>
        <div class="fslot"><label>Max Page Width (px)<input type="number" name="homepage_max_width" data-wl-max-width value="<?= (int)setting('homepage_max_width', '1600') ?>" min="900" max="2400"></label></div>
      </div>
      <div class="fgrid" style="margin-top:12px;">
        <div class="fslot"><label>Widget Gap (px)<input type="number" name="homepage_widget_gap" data-wl-widget-gap value="<?= (int)setting('homepage_widget_gap', '16') ?>" min="0" max="80"></label></div>
        <label class="check" style="align-self:end;padding-bottom:6px;">
          <input type="checkbox" name="homepage_sticky_sidebars" data-wl-sticky value="1"<?= setting('homepage_sticky_sidebars', '0') === '1' ? ' checked' : '' ?>>
          Sticky Sidebars
        </label>
      </div>

      <div class="actions" style="justify-content:space-between;margin-top:16px;">
        <button type="button" class="btn ghost" data-website-look-reset>Reset to Standard</button>
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn ghost" data-website-look-close>Cancel</button>
          <button type="submit" class="btn">Save Website Look Settings</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- BLOCK PICKER MODAL -->
<div class="ls-modal" data-slot-popover hidden>
  <div class="ls-modal-backdrop" data-slot-popover-close></div>
  <div class="ls-modal-dialog" style="width:min(90vw,560px)" role="dialog" aria-modal="true" aria-label="Select Block">
    <header class="ls-modal-head">
      <div><h3 data-popover-target-title>Select Block for Slot</h3><small>Pick a block to insert into layout slot</small></div>
      <button type="button" class="ls-modal-close" data-slot-popover-close>✕</button>
    </header>
    <div class="ls-modal-body">
      <input type="search" placeholder="Search blocks..." class="ls-lib-search-input" data-popover-search style="margin-bottom:12px;">
      <div class="ls-lib-grid" style="grid-template-columns:1fr 1fr;">
        <?php foreach ($paletteFlat as $pickerBlock): echo $this->pickerCardMarkup($pickerBlock); endforeach; ?>
      </div>
    </div>
  </div>
</div>

</div><!-- /.frame -->

<script>
(()=>{
 const root = document.querySelector("[data-admin-studio-root]");
 if(!root) return;
 const navToggleBtn = root.querySelector("[data-system-nav-toggle]");
 const savedState = localStorage.getItem("erased_system_nav_collapsed");
 // See the matching comment in public/index.php's copy of this block - below
 // the 760px breakpoint the rail becomes a full-screen overlay, so a first
 // visit with no saved preference should default closed, not open. Opening
 // on a narrow viewport is also ephemeral (not persisted) for the same
 // reason documented there - .rail-item navigates via a real page load, so
 // persisting "open" would silently reopen the overlay on every subsequent
 // page after the first manual open.
 const prefersNarrow = window.matchMedia("(max-width:760px)").matches;
 if(savedState === "true" || (savedState === null && prefersNarrow)){ root.classList.add("rail-hidden"); }
 navToggleBtn?.addEventListener("click", event => {
  event.stopPropagation();
  const isHidden = root.classList.toggle("rail-hidden");
  if(!(prefersNarrow && !isHidden)){
   localStorage.setItem("erased_system_nav_collapsed", isHidden ? "true" : "false");
  }
 });
 if(prefersNarrow){
  root.querySelectorAll(".rail-item").forEach(item => {
   item.addEventListener("click", () => { root.classList.add("rail-hidden"); });
  });
 }
})();
</script>

<?= $this->script() ?>
</body>
</html>
<?php return trim((string)ob_get_clean());
    }

    private function escape(string $value): string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

    /**
     * v0.7-dev: lets this screen (and therefore ERASED Studio's Layout tab,
     * which embeds it) view/edit any website profile's own homepage draft,
     * not only the active one - "website-profile preview integration".
     * Reuses ?profile= (already understood by every save/publish/preview
     * endpoint this screen talks to) rather than inventing a new mechanism.
     * Renders nothing when there's zero or one profile - nothing to switch
     * between.
     * @param list<array{id:string,name:string,status:string}> $profiles
     * @param array{id:string,name:string,status:string}|null $current
     */
    private function renderProfileSwitcher(array $profiles, string $rawProfileId, ?array $current): string
    {
        if (count($profiles) < 2) return '';
        $options = '';
        foreach ($profiles as $p) {
            $label = $p['name'] . ($p['status'] === 'active' ? ' (Live)' : ($p['status'] === 'draft' ? ' (Draft)' : ' (Archived)'));
            $selected = $p['id'] === $rawProfileId ? ' selected' : '';
            $options .= '<option value="'.$this->escape($p['id']).'"'.$selected.'>'.$this->escape($label).'</option>';
        }
        $note = '';
        if ($current !== null && $current['status'] !== 'active') {
            $note = '<p class="ls-profile-note">Editing a '.($current['status'] === 'draft' ? 'draft' : 'archived').' profile - changes here don\'t reach the live site unless this profile is activated.</p>';
        }
        $onchange = "var u=new URL(window.location.href);u.searchParams.set('profile',this.value);window.location.href=u.toString();";
        return '<div class="ls-profile-switch"><label for="ls-profile-select">Editing profile</label>'
            .'<select id="ls-profile-select" onchange="'.$onchange.'">'.$options.'</select></div>'
            .$note;
    }

    /** @param array<string,array> $allBlocks @param array<string,BlockDefinition> $registryById */
    private function resolveBlockTitle(string $blockId, array $allBlocks, array $registryById): string
    {
        if (isset($registryById[$blockId])) return $registryById[$blockId]->title();
        if (isset($allBlocks[$blockId][0])) return (string)$allBlocks[$blockId][0];
        return ucfirst($blockId);
    }

    private function paletteItemMarkup(BlockDefinition $definition, int $code): string
    {
        $id = $this->escape($definition->id());
        $title = $this->escape($definition->title());
        $category = $this->escape($definition->category());
        return '<div class="comp-item" draggable="true" data-block-id="' . $id . '" data-library-name="' . $this->escape(strtolower($definition->title())) . '" data-library-category="' . $category . '" data-block-title="' . $title . '"><span class="sq"></span>' . $title . '<span class="code mono">' . str_pad((string)$code, 2, '0', STR_PAD_LEFT) . '</span></div>';
    }

    private function pickerCardMarkup(BlockDefinition $definition): string
    {
        $id = $this->escape($definition->id());
        $title = $this->escape($definition->title());
        $category = $this->escape($definition->category());
        $description = $this->escape($definition->description() !== '' ? $definition->description() : 'Homepage section block.');
        return '<div class="ls-picker-card" data-pick-block-id="' . $id . '" data-pick-title="' . $title . '" data-pick-category="' . $category . '">
          <strong>' . $title . '</strong><p>' . $description . '</p>
          <button type="button" class="btn">Select Block</button>
        </div>';
    }

    private function script(): string
    {
        return <<<'HTML'
<script>
(()=>{
 const root=document.querySelector('[data-layout-studio]');
 if(!root)return;

 // Rich block content (title/subtitle/CTA/accent colour) that the Spec Sheet
 // panel below actually edits - pre-merged server-side with defaults so
 // fields start populated with what would really render, not placeholder text.
 // Just 'features' now - hero/cta/pricing/testimonials/stats/team were
 // removed 2026-08-16.
 const richBlockTypes=['features'];
 let widgetOptions={};
 try{widgetOptions=JSON.parse(root.dataset.widgetOptions||'{}');}catch(e){widgetOptions={};}

 // Item-list format per rich type, matching homepage_studio_public_blocks()'s
 // pipe-delimited parsing exactly.
 const itemsFormatHints={
  features:'One per line: Label|Description',
 };

 // Keep the accent colour swatch and its hex text field in sync.
 root.addEventListener('input',event=>{
  if(event.target.matches('[data-prop-accent-picker]')){
   const hex=root.querySelector('[data-prop-accent-hex]');
   if(hex)hex.value=event.target.value;
  }else if(event.target.matches('[data-prop-accent-hex]')){
   const picker=root.querySelector('[data-prop-accent-picker]');
   if(picker&&/^#[0-9a-fA-F]{6}$/.test(event.target.value))picker.value=event.target.value;
  }
 });

 // Social link platform pickers: a <details> that behaves like a compact,
 // icon-only select - closed by default showing just the current icon
 // (platform name only as a tooltip/aria-label, not visible text), opens
 // to a small floating grid of every platform's icon, picking one writes
 // the platform key into the row's hidden input the form actually
 // submits, updates the closed-state icon to match, and collapses back
 // down. Clicking the already-selected option again clears the row back
 // to "none".
 const socialFallbackIcon=document.getElementById('social-icon-fallback')?.content.firstElementChild?.outerHTML||'';
 root.addEventListener('click',event=>{
  const choice=event.target.closest('.social-icon-choice');
  if(!choice)return;
  const picker=choice.closest('.social-icon-picker');
  const row=choice.closest('.social-icon-picker-row');
  const valueInput=row?.querySelector('.social-icon-picker-value');
  if(!picker||!valueInput)return;
  const platform=choice.dataset.platform||'';
  const nextValue=valueInput.value===platform?'':platform;
  valueInput.value=nextValue;
  picker.querySelectorAll('.social-icon-choice').forEach(btn=>{
   const selected=btn.dataset.platform===nextValue&&nextValue!=='';
   btn.classList.toggle('is-selected',selected);
   btn.setAttribute('aria-selected',selected?'true':'false');
  });
  const trigger=picker.querySelector('.social-icon-picker-trigger');
  const triggerIcon=picker.querySelector('.social-icon-picker-trigger-icon');
  const label=nextValue!==''?(choice.dataset.label||''):'Choose a platform';
  if(triggerIcon)triggerIcon.innerHTML=nextValue!==''?choice.innerHTML:socialFallbackIcon;
  if(trigger){trigger.title=label;trigger.setAttribute('aria-label',label);}
  picker.open=false;
 });
 // Floating panels close on outside click (native <details> only closes on
 // re-clicking its own summary), and opening one closes any other picker
 // already open so at most one floats at a time.
 root.addEventListener('toggle',event=>{
  const picker=event.target;
  if(!picker.classList?.contains('social-icon-picker')||!picker.open)return;
  root.querySelectorAll('.social-icon-picker[open]').forEach(other=>{
   if(other!==picker)other.open=false;
  });
 },true);
 document.addEventListener('click',event=>{
  root.querySelectorAll('.social-icon-picker[open]').forEach(picker=>{
   if(!picker.contains(event.target))picker.open=false;
  });
 });

 // Social rows start collapsed to whichever slots already have a value -
 // "+ Add social account" reveals the next empty one (saving the panel
 // space 8 permanently-visible rows cost when most sites use 2-3), and
 // each revealed row's "x" clears it back to empty and re-hides it,
 // freeing that slot up again. The underlying 8 numbered fields
 // (social_link_1..8) are unchanged - this is purely which of them show.
 const socialAddBtn=root.querySelector('[data-social-add]');
 const socialRowNodes=()=>Array.from(root.querySelectorAll('[data-social-row]'));
 const refreshSocialAddVisibility=()=>{
  if(socialAddBtn)socialAddBtn.hidden=socialRowNodes().every(row=>!row.hidden);
 };
 socialAddBtn?.addEventListener('click',()=>{
  const next=socialRowNodes().find(row=>row.hidden);
  if(!next)return;
  next.hidden=false;
  refreshSocialAddVisibility();
  next.querySelector('input[type="text"]')?.focus();
 });
 root.addEventListener('click',event=>{
  const removeBtn=event.target.closest('[data-social-remove]');
  if(!removeBtn)return;
  const row=removeBtn.closest('[data-social-row]');
  if(!row)return;
  const valueInput=row.querySelector('.social-icon-picker-value');
  const urlInput=row.querySelector('input[type="text"]');
  const trigger=row.querySelector('.social-icon-picker-trigger');
  const triggerIcon=row.querySelector('.social-icon-picker-trigger-icon');
  if(valueInput)valueInput.value='';
  if(urlInput)urlInput.value='';
  if(triggerIcon)triggerIcon.innerHTML=socialFallbackIcon;
  if(trigger){trigger.title='Choose a platform';trigger.setAttribute('aria-label','Choose a platform');}
  row.querySelectorAll('.social-icon-choice').forEach(btn=>{
   btn.classList.remove('is-selected');
   btn.setAttribute('aria-selected','false');
  });
  row.hidden=true;
  refreshSocialAddVisibility();
 });

 // State History Stack for Undo/Redo Header Actions
 const stateHistory=[];
 let historyPointer=-1;

 // Move-up/move-down container buttons are an unconditional alternative to
 // drag reorder (matching the Navigation Builder's own move buttons, same
 // reasoning: native HTML5 drag-and-drop has no touch equivalent, so
 // container reordering was previously impossible on any touch-only
 // device). Scoped per-region-list, since containers only reorder within
 // their own region here - moving a container to a *different* region is
 // already possible without drag, via the Container Options modal's region
 // select.
 const updateContainerMoveButtonStates=()=>{
  root.querySelectorAll('.ls-container-list[data-region-list]').forEach(list=>{
   const cards=Array.prototype.slice.call(list.querySelectorAll('.ls-container'));
   cards.forEach((card,index)=>{
    const upBtn=card.querySelector('.ls-container-up');
    const downBtn=card.querySelector('.ls-container-down');
    if(upBtn)upBtn.disabled=(index===0);
    if(downBtn)downBtn.disabled=(index===cards.length-1);
   });
  });
 };

 const saveStateToHistory=()=>{
  const mapData=root.querySelector('[data-map-regions-grid]')?.innerHTML||'';
  stateHistory.splice(historyPointer+1);
  stateHistory.push(mapData);
  historyPointer=stateHistory.length-1;
  updateUndoRedoButtons();
  updateContainerMoveButtonStates();
 };

 const updateUndoRedoButtons=()=>{
  const undoBtn=root.querySelector('[data-layout-undo]');
  const redoBtn=root.querySelector('[data-layout-redo]');
  if(undoBtn)undoBtn.disabled=historyPointer<=0;
  if(redoBtn)redoBtn.disabled=historyPointer>=stateHistory.length-1;
 };

 const blockKeyMap = {
   'article-body': 'latest_posts', 'latest_posts': 'latest_posts', 'articles': 'latest_posts',
   'featured-image': 'featured', 'featured': 'featured',
   'sidebar-widgets': 'categories', 'categories': 'categories',
   'feature-grid': 'features', 'features': 'features'
  };

  // Every block id the real registry currently knows about (built-in +
  // installed-package blocks), server-supplied via data-known-block-ids.
  // Anything else (e.g. a stale id from a since-disabled package) is
  // silently left out of what gets saved, matching how the public
  // homepage already ignores unknown block ids.
  let knownBlockIds = [];
  try { knownBlockIds = JSON.parse(root.dataset.knownBlockIds || '[]'); } catch (e) { knownBlockIds = []; }

  // Column layout (100% / 50-50 / 3x33%) is a container grouping several
  // slots side by side - it only ever lived in the editor's DOM structure.
  // BlockPlacement has no container/column concept of its own, so each
  // slot's settings carries container_id + column_count (how many slots
  // share its container) and column_index (its position within it); the
  // renderer groups by container_id and lays each group out in a matching
  // CSS grid instead of the flat vertical stack it used to fall back to.
  const buildPlacements = () => {
   const placements = [];
   const droppedBlocks = [];
   root.querySelectorAll('.ls-container-list[data-region-list]').forEach(list => {
    const region = list.dataset.regionList;
    if (!['left', 'center', 'right'].includes(region)) return;
    let index = 0;
    list.querySelectorAll('.ls-container[data-container-id]').forEach(card => {
     const slots = Array.from(card.querySelectorAll('.ls-block[data-block-id]'));
     const columnCount = slots.length;
     const containerId = card.dataset.containerId || ('container-'+region+'-'+index);
     const containerVisible = card.dataset.hidden !== 'true';
     const scheduleStart = card.dataset.scheduleStart || '';
     const scheduleEnd = card.dataset.scheduleEnd || '';
     const hideMobile = card.dataset.hideMobile === 'true';
     const hideDesktop = card.dataset.hideDesktop === 'true';
     const bgColor = card.dataset.bgColor || '';
     const padding = card.dataset.padding || '';
     const widthMode = card.dataset.widthMode || '';
     const widthValue = card.dataset.widthValue || '';
     slots.forEach((slot, columnIndex) => {
      const rawId = slot.dataset.blockId;
      const key = blockKeyMap[rawId] || rawId;
      if (!knownBlockIds.includes(key)) {
       const title = slot.dataset.blockTitle || rawId;
       if (!droppedBlocks.includes(title)) droppedBlocks.push(title);
       return;
      }
      const slotSettings = columnCount > 1 ? {container_id: containerId, column_count: columnCount, column_index: columnIndex} : {};
      if (scheduleStart) slotSettings.schedule_start = scheduleStart;
      if (scheduleEnd) slotSettings.schedule_end = scheduleEnd;
      if (bgColor) slotSettings.bg_color = bgColor;
      if (padding) slotSettings.padding = padding;
      if (widthMode) {
       slotSettings.width_mode = widthMode;
       if (widthMode === 'narrow' && widthValue) slotSettings.width_value = widthValue;
      }
      if (hideMobile) slotSettings.hide_mobile = true;
      if (hideDesktop) slotSettings.hide_desktop = true;
      placements.push({
       instance_id: region + '-' + index,
       region: region,
       block_id: key,
       visible: containerVisible,
       settings: slotSettings
      });
      index++;
     });
    });
   });
   return { placements, droppedBlocks };
  };

  const layoutStudioRequest = (payload) => {
   const csrfInput = root.querySelector('input[name="csrf"]');
   const csrfVal = root.dataset.layoutCsrf || (csrfInput ? csrfInput.value : '');
   return fetch('/admin/layout-studio?profile=' + encodeURIComponent(root.dataset.profileId || 'default'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(Object.assign({ csrf: csrfVal }, payload))
   }).then(r => r.json().catch(() => ({})).then(data => {
    if (!r.ok || !data.ok) throw new Error(data.error || 'Layout Studio request failed.');
    return data;
   }));
  };

  const saveLayoutStudioComposition = (isPublish) => {
   const revision = parseInt(root.dataset.layoutRevision || '1', 10);
   const { placements, droppedBlocks } = buildPlacements();
   if (droppedBlocks.length) {
    alert('These blocks aren\'t supported by the homepage renderer yet, so they were not saved: ' + droppedBlocks.join(', ') + '. Everything else on the page was saved normally.');
   }
   return layoutStudioRequest({ action: 'save', revision: revision, placements: placements })
    .then(data => {
     root.dataset.layoutRevision = String(data.revision);
     if (!isPublish) return data;
     return layoutStudioRequest({ action: 'publish' });
    })
    .then(data => {
     const frame = root.querySelector('.ls-preview-frame');
     if (frame) frame.src = '/admin/layout-studio/preview?profile=' + encodeURIComponent(root.dataset.profileId||'default') + '&t=' + Date.now();
     return data;
    });
  };

 saveStateToHistory();

 root.querySelector('[data-layout-undo]')?.addEventListener('click', () => {
   if (historyPointer > 0) {
    historyPointer--;
    const grid = root.querySelector('[data-map-regions-grid]');
    if (grid) {
     grid.innerHTML = stateHistory[historyPointer];
     updateUndoRedoButtons();
    }
   }
  });

  root.querySelector('[data-layout-redo]')?.addEventListener('click', () => {
   if (historyPointer < stateHistory.length - 1) {
    historyPointer++;
    const grid = root.querySelector('[data-map-regions-grid]');
    if (grid) {
     grid.innerHTML = stateHistory[historyPointer];
     updateUndoRedoButtons();
    }
   }
  });

  // Header Action Buttons: Discard, Save Draft, Publish
  root.querySelector('[data-layout-discard]')?.addEventListener('click', () => {
   if (confirm('Discard all unsaved changes to this layout draft?')) {
    location.reload();
   }
  });

  root.querySelector('[data-layout-save]')?.addEventListener('click', () => {
   const btn = root.querySelector('[data-layout-save]');
   const status = root.querySelector('[data-layout-status]');
   if (btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = 'Saving…';
    saveLayoutStudioComposition(false).then(() => {
     btn.innerHTML = 'Saved!';
     if (status) { status.textContent = 'Draft · Saved'; status.classList.remove('live'); status.classList.add('draft'); }
     setTimeout(() => { btn.innerHTML = orig; }, 1800);
    }).catch((err) => {
     btn.innerHTML = orig;
     alert(err && err.message ? err.message : 'Error saving layout draft.');
    });
   }
  });

  root.querySelector('[data-layout-publish]')?.addEventListener('click', () => {
   const btn = root.querySelector('[data-layout-publish]');
   const status = root.querySelector('[data-layout-status]');
   if (btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = 'Publishing…';
    saveLayoutStudioComposition(true).then(() => {
     btn.innerHTML = 'Published!';
     if (status) { status.textContent = 'Published live'; status.classList.remove('draft'); status.classList.add('live'); }
     setTimeout(() => { btn.innerHTML = orig; }, 2000);
    }).catch((err) => {
     btn.innerHTML = orig;
     alert(err && err.message ? err.message : 'Error publishing layout.');
    });
   }
  });

 // Tab switching (Map vs Live Preview)
 root.querySelectorAll('.ls-viewtab').forEach(tab=>{
  tab.addEventListener('click',()=>{
   root.querySelectorAll('.ls-viewtab').forEach(t=>{t.classList.remove('is-active');t.setAttribute('aria-selected','false');});
   tab.classList.add('is-active');
   tab.setAttribute('aria-selected','true');
   const view=tab.dataset.canvasTab;
   root.querySelectorAll('.ls-view').forEach(v=>v.classList.remove('is-active'));
   root.querySelector('.ls-view[data-canvas-view="'+view+'"]')?.classList.add('is-active');
   if(view==='website'){
    const frame=root.querySelector('.ls-preview-frame');
    if(frame)frame.src='/admin/layout-studio/preview?profile='+encodeURIComponent(root.dataset.profileId||'default')+'&t='+Date.now();
   }
  });
 });

 // Device switcher - this previews a chosen target width for the PUBLIC
 // page being edited, unrelated to the admin panel's own viewport. It
 // still defaults to "desktop" markup-side (no server-side device
 // sniffing here either), so on an actual narrow screen it auto-switches
 // once on load to the device the frame's own overflow-x scroll would
 // otherwise silently hide behind.
 function lsSelectDevice(device){
  root.querySelectorAll('.ls-devicebtn').forEach(b=>b.classList.toggle('is-active',b.dataset.deviceSelect===device));
  const mapContainer=root.querySelector('[data-draft-map]');
  const wrapper=root.querySelector('.ls-preview-wrap');
  if(mapContainer)mapContainer.dataset.viewportDevice=device;
  if(wrapper)wrapper.dataset.device=device;
 }
 root.querySelectorAll('.ls-devicebtn').forEach(btn=>{
  btn.addEventListener('click',()=>lsSelectDevice(btn.dataset.deviceSelect||'desktop'));
 });
 if(window.matchMedia('(max-width:760px)').matches)lsSelectDevice('mobile');

 // SPEC SHEET TAB NAV: [Content] [Style] [Advanced]
 root.querySelectorAll('.ls-spectab').forEach(btn=>{
  btn.addEventListener('click',()=>{
   root.querySelectorAll('.ls-spectab').forEach(b=>{b.classList.remove('is-active');b.setAttribute('aria-selected','false');});
   btn.classList.add('is-active');
   btn.setAttribute('aria-selected','true');
   const tab=btn.dataset.inspectorTab;
   root.querySelectorAll('.ls-specpane').forEach(c=>c.classList.remove('is-active'));
   root.querySelector('.ls-specpane[data-inspector-pane="'+tab+'"]')?.classList.add('is-active');
  });
 });

 // SEGMENTED CONTROL BUTTON INTERACTIVITY
 root.querySelectorAll('.ls-seg').forEach(ctrl=>{
  ctrl.addEventListener('click',event=>{
   const btn=event.target.closest('.ls-segbtn');
   if(btn){
    ctrl.querySelectorAll('.ls-segbtn').forEach(b=>b.classList.remove('is-active'));
    btn.classList.add('is-active');
   }
  });
 });

 // The rich block type (hero/cta/features/pricing/testimonials/stats/team)
 // the Spec Sheet's content fields currently apply to, or null when the
 // selected block has no editable rich content (e.g. Latest Posts, header/
 // footer, or an unrecognized block).
 let activeRichType=null;
 const setSegmentedActive=(ctrl,val)=>{
  if(!ctrl)return;
  ctrl.querySelectorAll('.ls-segbtn').forEach(b=>b.classList.toggle('is-active',b.dataset.val===val));
 };

 // Update Spec Sheet values on slot selection
 const updateInspector=(blockId,blockTitle,category,containerId,slotIndex,region)=>{
  const titleElem=root.querySelector('[data-inspector-title]');
  const subElem=root.querySelector('[data-inspector-subtitle]');
  const headlineInput=root.querySelector('[data-prop-headline]');
  const subheadlineInput=root.querySelector('[data-prop-subheadline]');
  const ctaLabelInput=root.querySelector('[data-prop-cta-label]');
  const ctaUrlInput=root.querySelector('[data-prop-cta-url]');
  const accentPickerInput=root.querySelector('[data-prop-accent-picker]');
  const accentHexInput=root.querySelector('[data-prop-accent-hex]');
  const bgControl=root.querySelector('[data-prop-bg-segmented]');
  const alignControl=root.querySelector('[data-prop-align-segmented]');
  const minHeightInput=root.querySelector('[data-prop-min-height]');
  const paddingInput=root.querySelector('[data-prop-padding]');
  const cssClassInput=root.querySelector('[data-prop-css-class]');
  const htmlIdInput=root.querySelector('[data-prop-html-id]');
  const itemsField=root.querySelector('[data-prop-items-field]');
  const itemsInput=root.querySelector('[data-prop-items]');
  const itemsHint=root.querySelector('[data-prop-items-hint]');
  const contentFields=[headlineInput,subheadlineInput,ctaLabelInput,ctaUrlInput,accentPickerInput,accentHexInput,minHeightInput,paddingInput,cssClassInput,htmlIdInput,itemsInput];

  const containerNum=containerId.replace('container-','');
  const slotNum=(parseInt(slotIndex,10)+1);
  const regionName=region.charAt(0).toUpperCase()+region.slice(1);

  if(titleElem)titleElem.textContent=blockTitle;
  if(subElem)subElem.textContent=(region==='header'||region==='footer')?(regionName+' Container Region'):('Slot '+containerNum+'.'+slotNum+' · '+regionName+' region');

  const normalizedKey=blockKeyMap[blockId]||blockId;
  activeRichType=richBlockTypes.includes(normalizedKey)?normalizedKey:null;

  if(activeRichType){
   const o=widgetOptions[activeRichType]||{};
   contentFields.forEach(f=>{if(f)f.disabled=false;});
   if(bgControl)bgControl.querySelectorAll('.ls-segbtn').forEach(b=>b.disabled=false);
   if(alignControl)alignControl.querySelectorAll('.ls-segbtn').forEach(b=>b.disabled=false);
   if(headlineInput)headlineInput.value=o.title||'';
   if(subheadlineInput)subheadlineInput.value=o.subtitle||'';
   if(ctaLabelInput)ctaLabelInput.value=o.button_text||'';
   if(ctaUrlInput)ctaUrlInput.value=o.button_url||'';
   const accent=o.primary_color||'#2dfc98';
   if(accentPickerInput)accentPickerInput.value=/^#[0-9a-fA-F]{6}$/.test(accent)?accent:'#2dfc98';
   if(accentHexInput)accentHexInput.value=accent;
   setSegmentedActive(bgControl,o.bg_style||'gradient');
   setSegmentedActive(alignControl,o.align||'left');
   if(minHeightInput)minHeightInput.value=o.min_height||'';
   if(paddingInput)paddingInput.value=o.padding||'';
   if(cssClassInput)cssClassInput.value=o.css_class||'';
   if(htmlIdInput)htmlIdInput.value=o.html_id||'';
   const hasItems=Object.prototype.hasOwnProperty.call(itemsFormatHints,activeRichType);
   if(itemsField)itemsField.hidden=!hasItems;
   if(itemsInput){itemsInput.disabled=!hasItems;if(hasItems)itemsInput.value=o.items||'';}
   if(itemsHint)itemsHint.textContent=hasItems?itemsFormatHints[activeRichType]:'';
  }else{
   if(headlineInput)headlineInput.value=blockTitle+' Section';
   if(subheadlineInput)subheadlineInput.value='';
   if(ctaLabelInput)ctaLabelInput.value='';
   if(ctaUrlInput)ctaUrlInput.value='';
   if(accentPickerInput)accentPickerInput.value='#2dfc98';
   if(accentHexInput)accentHexInput.value='';
   if(minHeightInput)minHeightInput.value='';
   if(paddingInput)paddingInput.value='';
   if(cssClassInput)cssClassInput.value='';
   if(htmlIdInput)htmlIdInput.value='';
   if(itemsField)itemsField.hidden=true;
   if(itemsInput)itemsInput.value='';
   if(itemsHint)itemsHint.textContent='';
   setSegmentedActive(bgControl,'gradient');
   setSegmentedActive(alignControl,'left');
   // Not a rich-content block (e.g. Latest Posts, Categories) - these fields
   // have nothing to save to, so disable rather than let them look editable.
   contentFields.forEach(f=>{if(f)f.disabled=true;});
   if(bgControl)bgControl.querySelectorAll('.ls-segbtn').forEach(b=>b.disabled=true);
   if(alignControl)alignControl.querySelectorAll('.ls-segbtn').forEach(b=>b.disabled=true);
  }

  root.querySelectorAll('[data-inspector-group]').forEach(grp=>{ grp.hidden = true; });
  if(region==='header'||blockId==='header-nav'||containerId==='container-header'){
   const hGrp=root.querySelector('[data-inspector-group="header"]');
   if(hGrp)hGrp.hidden=false;
  } else if(region==='footer'||blockId==='footer-links'||containerId==='container-footer'){
   const fGrp=root.querySelector('[data-inspector-group="footer"]');
   if(fGrp)fGrp.hidden=false;
  } else {
   const dGrp=root.querySelector('[data-inspector-group="default"]');
   if(dGrp)dGrp.hidden=false;
  }
 };

 // Active slot tracking & selection
 let activeSlot=root.querySelector('.ls-block.selected')||root.querySelector('.ls-block');

 const selectSlot=slot=>{
  if(!slot)return;
  root.querySelectorAll('.ls-block').forEach(s=>s.classList.remove('selected'));
  slot.classList.add('selected');
  activeSlot=slot;

  const blockId=slot.dataset.blockId||'features';
  const blockTitle=slot.dataset.blockTitle||'Feature Grid';
  const category=slot.dataset.category||'content';
  const card=slot.closest('.ls-container');
  const containerId=card?.dataset.containerId||'container-1';
  const slotIndex=slot.dataset.slotIndex||'0';
  const region=card?.dataset.region||'center';

  updateInspector(blockId,blockTitle,category,containerId,slotIndex,region);
 };

 root.addEventListener('click',event=>{
  const slot=event.target.closest('.ls-block');
  if(slot&&!event.target.closest('.ls-container-gear')){
   selectSlot(slot);
   // Below 760px the Spec Sheet is stacked underneath the whole canvas
   // (not a side column), so tapping a block gave no visible feedback
   // unless the user scrolled down and found it themselves. Only on a
   // real tap, not on the initial page-load selection above.
   if(window.matchMedia('(max-width:760px)').matches){
    root.querySelector('[data-layout-inspector]')?.scrollIntoView({behavior:'smooth',block:'start'});
   }
  }
 });

 // Popover modal block picker logic via Replace button
 const popover=root.querySelector('[data-slot-popover]');

 root.querySelector('[data-inspector-replace]')?.addEventListener('click',()=>{
  if(activeSlot&&popover){
   popover.hidden=false;
  }
 });

 root.addEventListener('click',event=>{
  if(event.target.closest('[data-slot-popover-close]')){
   if(popover)popover.hidden=true;
  }
 });

 popover?.addEventListener('click',event=>{
  const card=event.target.closest('[data-pick-block-id]');
  if(card&&activeSlot){
   const newId=card.dataset.pickBlockId;
   const newTitle=card.dataset.pickTitle;
   const newCat=card.dataset.pickCategory;

   activeSlot.dataset.blockId=newId;
   activeSlot.dataset.blockTitle=newTitle;
   activeSlot.dataset.category=newCat;

   const titleElem=activeSlot.querySelector('.ls-block-label');
   if(titleElem)titleElem.textContent=newTitle;

   selectSlot(activeSlot);
   saveStateToHistory();
   popover.hidden=true;
  }
 });

 // Save Changes & Reset Block Buttons
 root.querySelector('[data-action-save]')?.addEventListener('click',()=>{
  const btn=root.querySelector('[data-action-save]');
  if(!btn)return;
  if(!activeRichType){
   alert('This block has no editable content here (its text comes from elsewhere, e.g. published posts). Nothing to save.');
   return;
  }
  const headlineInput=root.querySelector('[data-prop-headline]');
  const subheadlineInput=root.querySelector('[data-prop-subheadline]');
  const ctaLabelInput=root.querySelector('[data-prop-cta-label]');
  const ctaUrlInput=root.querySelector('[data-prop-cta-url]');
  const accentHexInput=root.querySelector('[data-prop-accent-hex]');
  const bgControl=root.querySelector('[data-prop-bg-segmented]');
  const alignControl=root.querySelector('[data-prop-align-segmented]');
  const minHeightInput=root.querySelector('[data-prop-min-height]');
  const paddingInput=root.querySelector('[data-prop-padding]');
  const cssClassInput=root.querySelector('[data-prop-css-class]');
  const htmlIdInput=root.querySelector('[data-prop-html-id]');
  const itemsInput=root.querySelector('[data-prop-items]');
  const activeVal=(ctrl,fallback)=>ctrl?.querySelector('.ls-segbtn.is-active')?.dataset.val||fallback;
  const fields={
   title:headlineInput?headlineInput.value:'',
   subtitle:subheadlineInput?subheadlineInput.value:'',
   button_text:ctaLabelInput?ctaLabelInput.value:'',
   button_url:ctaUrlInput?ctaUrlInput.value:'',
   primary_color:accentHexInput?accentHexInput.value:'',
   bg_style:activeVal(bgControl,'gradient'),
   align:activeVal(alignControl,'left'),
   min_height:minHeightInput?minHeightInput.value:'',
   padding:paddingInput?paddingInput.value:'',
   css_class:cssClassInput?cssClassInput.value:'',
   html_id:htmlIdInput?htmlIdInput.value:'',
  };
  if(Object.prototype.hasOwnProperty.call(itemsFormatHints,activeRichType)&&itemsInput){
   fields.items=itemsInput.value;
  }
  const orig=btn.textContent;
  btn.textContent='Saving…';
  layoutStudioRequest({action:'save_widget_options',block_type:activeRichType,fields:fields}).then(data=>{
   widgetOptions[activeRichType]=data.options||fields;
   btn.textContent='Saved!';
   setTimeout(()=>{ btn.textContent=orig; },1500);
  }).catch(err=>{
   btn.textContent=orig;
   alert(err&&err.message?err.message:'Error saving block content.');
  });
 });

 root.querySelector('[data-action-reset]')?.addEventListener('click',()=>{
  const headlineInput=root.querySelector('[data-prop-headline]');
  const subheadlineInput=root.querySelector('[data-prop-subheadline]');
  const ctaLabelInput=root.querySelector('[data-prop-cta-label]');
  const ctaUrlInput=root.querySelector('[data-prop-cta-url]');
  const accentPickerInput=root.querySelector('[data-prop-accent-picker]');
  const accentHexInput=root.querySelector('[data-prop-accent-hex]');
  if(headlineInput)headlineInput.value='Welcome to our platform';
  if(subheadlineInput)subheadlineInput.value='Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
  if(activeRichType){
   if(ctaLabelInput)ctaLabelInput.value='';
   if(ctaUrlInput)ctaUrlInput.value='';
   if(accentPickerInput)accentPickerInput.value='#2dfc98';
   if(accentHexInput)accentHexInput.value='';
   setSegmentedActive(root.querySelector('[data-prop-bg-segmented]'),'gradient');
   setSegmentedActive(root.querySelector('[data-prop-align-segmented]'),'left');
   const minHeightInput=root.querySelector('[data-prop-min-height]');
   const paddingInput=root.querySelector('[data-prop-padding]');
   const cssClassInput=root.querySelector('[data-prop-css-class]');
   const htmlIdInput=root.querySelector('[data-prop-html-id]');
   if(minHeightInput)minHeightInput.value='';
   if(paddingInput)paddingInput.value='';
   if(cssClassInput)cssClassInput.value='';
   if(htmlIdInput)htmlIdInput.value='';
  }
 });

 // Container Option ⚙ Modal Handler
 const containerModal=root.querySelector('[data-container-options-modal]');
 let activeOptionContainer=null;

 const updateWidthFieldsVisibility=()=>{
  const mode=root.querySelector('[data-opt-width]').value;
  root.querySelector('[data-opt-width-value-wrap]').hidden=mode!=='narrow';
  root.querySelector('[data-opt-width-warning]').hidden=mode!=='full-bleed';
 };
 root.querySelector('[data-opt-width]')?.addEventListener('change',updateWidthFieldsVisibility);

 root.addEventListener('click',event=>{
  const optBtn=event.target.closest('[data-container-option]');
  if(optBtn){
   const card=optBtn.closest('.ls-container');
   if(card&&containerModal){
    activeOptionContainer=card;
    const title=card.querySelector('.ls-container-title')?.textContent||'Container';
    const region=card.dataset.region||'center';
    const layout=card.dataset.layout||'two';

    root.querySelector('[data-container-options-title]').textContent=title;
    root.querySelector('[data-opt-region]').value=region;
    root.querySelector('[data-opt-layout]').value=layout;
    root.querySelector('[data-opt-visible]').checked=card.dataset.hidden!=='true';
    root.querySelector('[data-opt-hide-mobile]').checked=card.dataset.hideMobile==='true';
    root.querySelector('[data-opt-hide-desktop]').checked=card.dataset.hideDesktop==='true';
    root.querySelector('[data-opt-bg-color]').value=card.dataset.bgColor||'#000000';
    root.querySelector('[data-opt-bg-color]').dataset.enabled=card.dataset.bgColor?'true':'';
    root.querySelector('[data-opt-padding]').value=card.dataset.padding||'';
    root.querySelector('[data-opt-width]').value=card.dataset.widthMode||'';
    root.querySelector('[data-opt-width-value]').value=card.dataset.widthValue||'';
    updateWidthFieldsVisibility();
    root.querySelector('[data-opt-schedule-start]').value=card.dataset.scheduleStart||'';
    root.querySelector('[data-opt-schedule-end]').value=card.dataset.scheduleEnd||'';
    containerModal.hidden=false;
   }
  }
  if(event.target.closest('[data-container-options-close]')){
   if(containerModal)containerModal.hidden=true;
  }
 });

 // Apply Container Options Settings
 root.querySelector('[data-opt-apply]')?.addEventListener('click',()=>{
  if(!activeOptionContainer||!containerModal)return;
  const newRegion=root.querySelector('[data-opt-region]').value;
  const newLayout=root.querySelector('[data-opt-layout]').value;

  // Switching to fewer columns discards the trailing slot(s) outright (see
  // below) - confirm first, listing what would be removed, so a real,
  // already-configured block never disappears silently just because the
  // user picked a smaller layout. Checked before any mutation so canceling
  // leaves the container completely untouched, including its region.
  const existingSlotsGrid=activeOptionContainer.querySelector('.ls-cols');
  const targetColumnCount=newLayout==='three'?3:(newLayout==='two'?2:1);
  if(existingSlotsGrid&&existingSlotsGrid.children.length>targetColumnCount){
   const removedTitles=Array.from(existingSlotsGrid.children).slice(targetColumnCount)
    .map(el=>el.querySelector('.ls-block-label')?.textContent||'a block').join(', ');
   if(!confirm('Switching to '+targetColumnCount+' column'+(targetColumnCount===1?'':'s')+' will remove: '+removedTitles+'. Continue?')){
    return;
   }
  }

  activeOptionContainer.dataset.region=newRegion;
  activeOptionContainer.dataset.layout=newLayout;

  const targetList=root.querySelector('[data-region-list="'+newRegion+'"]');
  if(targetList)targetList.appendChild(activeOptionContainer);

  const slotsGrid=activeOptionContainer.querySelector('.ls-cols');
  const badge=activeOptionContainer.querySelector('.badge');
  const containerId=activeOptionContainer.dataset.containerId;

  // The id/title/label below must all agree with each other and with the
  // real registry entry (see blockKeyMap above) - a mismatched placeholder
  // here (e.g. labeled "Article Body" while actually saving as a different
  // block id) would let the Studio show one thing while the published
  // homepage renders another, with no warning anywhere in the save path.
  const makeSlot=(idx,pct)=>{
   const s=document.createElement('div');
   s.className='ls-block';
   s.dataset.slotId='c-new-s'+idx;
   s.dataset.slotIndex=String(idx-1);
   s.dataset.blockId='latest_posts';
   s.dataset.blockTitle='Latest posts';
   s.dataset.category='content';
   s.innerHTML='<span class="ls-block-num">#'+idx+'</span><strong class="ls-block-label">Latest posts</strong><div class="ls-block-actions"><button type="button" class="ls-container-gear" data-container-option="'+containerId+'" title="Container Options">⚙</button></div><div class="ls-block-dim"><span class="l"></span><span class="val">'+pct+'</span><span class="l"></span></div>';
   return s;
  };

  if(newLayout==='three'){
   if(slotsGrid){
    slotsGrid.className='ls-cols three';
    while(slotsGrid.children.length<3){
     slotsGrid.appendChild(makeSlot(slotsGrid.children.length+1,'33%'));
    }
    slotsGrid.querySelectorAll('.ls-block-dim .val').forEach(v=>v.textContent='33%');
   }
   if(badge)badge.textContent='3×33%';
  } else if(newLayout==='two'){
   if(slotsGrid){
    slotsGrid.className='ls-cols two';
    while(slotsGrid.children.length>2)slotsGrid.lastElementChild?.remove();
    if(slotsGrid.children.length===1){
     slotsGrid.appendChild(makeSlot(2,'50%'));
    }
    slotsGrid.querySelectorAll('.ls-block-dim .val').forEach(v=>v.textContent='50%');
   }
   if(badge)badge.textContent='50/50';
  } else {
   if(slotsGrid){
    slotsGrid.className='ls-cols one';
    while(slotsGrid.children.length>1)slotsGrid.lastElementChild?.remove();
    slotsGrid.querySelectorAll('.ls-block-dim .val').forEach(v=>v.textContent='100%');
   }
   if(badge)badge.textContent='100%';
  }

  const isVisible=root.querySelector('[data-opt-visible]').checked;
  activeOptionContainer.classList.toggle('ls-container-hidden',!isVisible);
  if(isVisible){activeOptionContainer.removeAttribute('data-hidden');}else{activeOptionContainer.dataset.hidden='true';}

  const hideMobile=root.querySelector('[data-opt-hide-mobile]').checked;
  const hideDesktop=root.querySelector('[data-opt-hide-desktop]').checked;
  if(hideMobile){activeOptionContainer.dataset.hideMobile='true';}else{delete activeOptionContainer.dataset.hideMobile;}
  if(hideDesktop){activeOptionContainer.dataset.hideDesktop='true';}else{delete activeOptionContainer.dataset.hideDesktop;}
  activeOptionContainer.classList.toggle('ls-container-device-limited',hideMobile||hideDesktop);

  const bgColorInput=root.querySelector('[data-opt-bg-color]');
  const bgColorEnabled=bgColorInput.dataset.enabled==='true';
  if(bgColorEnabled){activeOptionContainer.dataset.bgColor=bgColorInput.value;}else{delete activeOptionContainer.dataset.bgColor;}
  const padding=root.querySelector('[data-opt-padding]').value.trim();
  if(padding){activeOptionContainer.dataset.padding=padding;}else{delete activeOptionContainer.dataset.padding;}
  activeOptionContainer.classList.toggle('ls-container-styled',bgColorEnabled||!!padding);

  const widthMode=root.querySelector('[data-opt-width]').value;
  const widthValue=root.querySelector('[data-opt-width-value]').value.trim();
  if(widthMode){activeOptionContainer.dataset.widthMode=widthMode;}else{delete activeOptionContainer.dataset.widthMode;}
  if(widthMode==='narrow'&&widthValue){activeOptionContainer.dataset.widthValue=widthValue;}else{delete activeOptionContainer.dataset.widthValue;}
  const widthBadge=activeOptionContainer.querySelector('.ls-container-width-badge');
  if(widthBadge)widthBadge.remove();
  if(widthMode){
   const span=document.createElement('span');
   span.className='ls-container-width-badge';
   span.textContent=widthMode==='full-bleed'?'FULL WIDTH':'NARROW';
   activeOptionContainer.querySelector('.ls-container-title')?.appendChild(span);
  }

  const scheduleStart=root.querySelector('[data-opt-schedule-start]').value;
  const scheduleEnd=root.querySelector('[data-opt-schedule-end]').value;
  const isScheduled=!!(scheduleStart||scheduleEnd);
  if(scheduleStart){activeOptionContainer.dataset.scheduleStart=scheduleStart;}else{delete activeOptionContainer.dataset.scheduleStart;}
  if(scheduleEnd){activeOptionContainer.dataset.scheduleEnd=scheduleEnd;}else{delete activeOptionContainer.dataset.scheduleEnd;}
  activeOptionContainer.classList.toggle('ls-container-scheduled',isScheduled);

  saveStateToHistory();
  containerModal.hidden=true;
 });

 root.querySelector('[data-opt-bg-color]')?.addEventListener('input',event=>{
  event.target.dataset.enabled='true';
 });
 root.querySelector('[data-opt-bg-color-clear]')?.addEventListener('click',()=>{
  const colorInput=root.querySelector('[data-opt-bg-color]');
  colorInput.dataset.enabled='';
  colorInput.value='#000000';
 });

 // Duplicate Container - clones the whole section (all its columns) right
 // after itself in the same region, with a fresh container id so the clone
 // groups independently from the original when saved.
 root.querySelector('[data-opt-duplicate]')?.addEventListener('click',()=>{
  if(!activeOptionContainer||!containerModal)return;
  const clone=activeOptionContainer.cloneNode(true);
  const newContainerId='container-dup-'+Math.random().toString(36).slice(2,10);
  clone.dataset.containerId=newContainerId;
  clone.querySelectorAll('[data-container-option]').forEach(gear=>{gear.dataset.containerOption=newContainerId;});
  const titleEl=clone.querySelector('.ls-container-title');
  if(titleEl)titleEl.textContent=titleEl.textContent+' (Copy)';
  activeOptionContainer.after(clone);
  saveStateToHistory();
  containerModal.hidden=true;
 });

 // Delete Container
 root.querySelector('[data-opt-delete]')?.addEventListener('click',()=>{
  if(activeOptionContainer){
   activeOptionContainer.remove();
   saveStateToHistory();
   if(containerModal)containerModal.hidden=true;
  }
 });

 // Block Library Card Drag to Canvas Slot
 let draggedBlockData=null;
 // Container Drag Between Regions
 let draggedContainer=null;

 // The Header and Footer containers are fixed, single-purpose global chrome
 // (no "+ Add" button, unlike Left/Center/Right) - they must never accept
 // drops. Without this check a drag visually "succeeds" there but the save
 // payload silently excludes it (buildPlacements() only reads left/center/
 // right), so the change looks accepted and then reverts with no warning
 // on the next page load.
 const isFixedRegion=(name)=>name==='header'||name==='footer';

 root.addEventListener('click',event=>{
  const upBtn=event.target.closest('.ls-container-up');
  const downBtn=event.target.closest('.ls-container-down');
  if(!upBtn&&!downBtn)return;
  const card=event.target.closest('.ls-container');
  if(!card||isFixedRegion(card.dataset.region))return;
  const list=card.closest('.ls-container-list[data-region-list]');
  if(!list)return;
  if(upBtn){
   const prev=card.previousElementSibling;
   if(prev&&prev.classList.contains('ls-container'))list.insertBefore(card,prev);
  }else{
   const next=card.nextElementSibling;
   if(next&&next.classList.contains('ls-container'))list.insertBefore(next,card);
  }
  saveStateToHistory();
 });

 root.addEventListener('dragstart',event=>{
  const blockCard=event.target.closest('.comp-item');
  if(blockCard){
   draggedBlockData={
    id:blockCard.dataset.blockId,
    title:blockCard.dataset.blockTitle,
    category:blockCard.dataset.blockCategory||blockCard.dataset.libraryCategory
   };
   blockCard.classList.add('is-dragging');
   return;
  }
  const containerCard=event.target.closest('.ls-container');
  if(containerCard&&!isFixedRegion(containerCard.dataset.region)){
   draggedContainer=containerCard;
   containerCard.classList.add('is-dragging');
  }
 });

 root.addEventListener('dragend',event=>{
  const blockCard=event.target.closest('.comp-item');
  if(blockCard)blockCard.classList.remove('is-dragging');
  if(draggedContainer){
   draggedContainer.classList.remove('is-dragging');
   draggedContainer=null;
  }
  root.querySelectorAll('.ls-container-list.drag-over').forEach(l=>l.classList.remove('drag-over'));
 });

 root.addEventListener('dragover',event=>{
  const slot=event.target.closest('.ls-block');
  if(slot&&draggedBlockData&&!isFixedRegion(slot.closest('.ls-container-list[data-region-list]')?.dataset.regionList)){
   event.preventDefault();
   slot.classList.add('drag-over');
   return;
  }
  const list=event.target.closest('.ls-container-list[data-region-list]');
  if(list&&draggedContainer&&!isFixedRegion(list.dataset.regionList)){
   event.preventDefault();
   list.classList.add('drag-over');
  }
 });

 root.addEventListener('dragleave',event=>{
  const slot=event.target.closest('.ls-block');
  if(slot)slot.classList.remove('drag-over');
  const list=event.target.closest('.ls-container-list[data-region-list]');
  if(list)list.classList.remove('drag-over');
 });

 root.addEventListener('drop',event=>{
  const slot=event.target.closest('.ls-block');
  if(slot&&draggedBlockData){
   if(isFixedRegion(slot.closest('.ls-container-list[data-region-list]')?.dataset.regionList)){
    draggedBlockData=null;
    return;
   }
   event.preventDefault();
   slot.classList.remove('drag-over');

   slot.dataset.blockId=draggedBlockData.id;
   slot.dataset.blockTitle=draggedBlockData.title;
   slot.dataset.category=draggedBlockData.category;

   const titleElem=slot.querySelector('.ls-block-label');
   if(titleElem)titleElem.textContent=draggedBlockData.title;

   selectSlot(slot);
   saveStateToHistory();
   draggedBlockData=null;
   return;
  }

  const list=event.target.closest('.ls-container-list[data-region-list]');
  if(list&&draggedContainer&&!isFixedRegion(list.dataset.regionList)){
   event.preventDefault();
   list.classList.remove('drag-over');

   const newRegion=list.dataset.regionList;
   draggedContainer.dataset.region=newRegion;

   const placeholder=list.querySelector('.ls-region-empty');
   if(placeholder)placeholder.remove();

   const beforeCard=event.target.closest('.ls-container');
   if(beforeCard&&beforeCard!==draggedContainer&&list.contains(beforeCard)){
    list.insertBefore(draggedContainer,beforeCard);
   }else{
    list.appendChild(draggedContainer);
   }

   const originList=root.querySelectorAll('.ls-container-list[data-region-list]');
   originList.forEach(l=>{
    if(!l.querySelector('.ls-container')&&!l.querySelector('.ls-region-empty')){
     l.innerHTML='<div class="ls-region-empty">Drop containers or blocks here</div>';
    }
   });

   saveStateToHistory();
   draggedContainer=null;
  }
 });

 // Add new container button for specific region
 root.addEventListener('click',event=>{
  const addBtn=event.target.closest('[data-map-add-container]');
  if(addBtn){
   const region=addBtn.dataset.mapAddContainer||'center';
   const list=root.querySelector('[data-region-list="'+region+'"]');
   if(!list)return;
   const count=root.querySelectorAll('.ls-container').length+1;
   const card=document.createElement('article');
   card.className='ls-container';
   card.draggable=true;
   card.dataset.containerId='container-'+count;
   card.dataset.region=region;
   card.dataset.layout='one';
   card.innerHTML='<header class="ls-container-head"><span class="ls-container-title">Container #'+count+' (Custom Section)</span><span class="ls-container-move"><button type="button" class="btn ghost ls-container-up" title="Move container up" aria-label="Move container up">&uarr;</button><button type="button" class="btn ghost ls-container-down" title="Move container down" aria-label="Move container down">&darr;</button></span></header><div class="ls-cols one"><div class="ls-block" data-slot-id="c'+count+'-s1" data-slot-index="0" data-block-id="latest_posts" data-block-title="Latest posts" data-category="content"><span class="ls-block-num">#1</span><strong class="ls-block-label">Latest posts</strong><div class="ls-block-actions"><button type="button" class="ls-container-gear" data-container-option="container-'+count+'" title="Container Options">⚙</button></div><div class="ls-block-dim"><span class="l"></span><span class="val">100%</span><span class="l"></span></div></div></div>';

   const placeholder=list.querySelector('.ls-region-empty');
   if(placeholder)placeholder.remove();
   list.appendChild(card);
   saveStateToHistory();
  }
 });

 document.addEventListener('click', event => {
  if(event.target.closest('[data-open-website-look-modal]')){
   const m = document.querySelector('[data-website-look-modal]');
   if(m) m.hidden = false;
  }
  if(event.target.closest('[data-website-look-close]')){
   const m = document.querySelector('[data-website-look-modal]');
   if(m) m.hidden = true;
  }
 });

 // Website Layout & Look modal - preset-driven sidebar defaults. Server-side,
 // homepage_studio_config() already forces the unused side(s) of a 2/1-column
 // preset to 0 regardless of what's stored, so disabling them here just makes
 // that reality visible instead of leaving a live-looking input that quietly
 // does nothing. The bug this closes: a sidebar left at 0% while a real block
 // is still assigned to it used to render invisibly (previously it visually
 // overlapped other columns) with no indication why - backfilling a sane
 // width the moment that side becomes relevant again prevents landing back in
 // that state by accident. Never touches a side that already has a real
 // (non-zero) value - manual edits always win over this convenience fill.
 const WL_STANDARD = {preset:'three', left:20, right:20, gap:24, maxWidth:1600, widgetGap:16, sticky:false};
 const wlApplyPreset = () => {
  const presetSel = document.querySelector('[data-wl-preset]');
  const leftInput = document.querySelector('[data-wl-left]');
  const rightInput = document.querySelector('[data-wl-right]');
  if(!presetSel||!leftInput||!rightInput) return;
  const preset = presetSel.value;
  const usesLeft = preset==='three'||preset==='two-left';
  const usesRight = preset==='three'||preset==='two-right';
  leftInput.disabled = !usesLeft;
  rightInput.disabled = !usesRight;
  if(usesLeft && Number(leftInput.value)===0) leftInput.value = String(WL_STANDARD.left);
  if(usesRight && Number(rightInput.value)===0) rightInput.value = String(WL_STANDARD.right);
 };
 document.querySelector('[data-wl-preset]')?.addEventListener('change', wlApplyPreset);
 document.querySelector('[data-open-website-look-modal]')?.addEventListener('click', wlApplyPreset);

 document.querySelector('[data-website-look-reset]')?.addEventListener('click', () => {
  const presetSel = document.querySelector('[data-wl-preset]');
  if(presetSel) presetSel.value = WL_STANDARD.preset;
  const gapInput = document.querySelector('[data-wl-gap]');
  if(gapInput) gapInput.value = String(WL_STANDARD.gap);
  const maxWidthInput = document.querySelector('[data-wl-max-width]');
  if(maxWidthInput) maxWidthInput.value = String(WL_STANDARD.maxWidth);
  const widgetGapInput = document.querySelector('[data-wl-widget-gap]');
  if(widgetGapInput) widgetGapInput.value = String(WL_STANDARD.widgetGap);
  const stickyInput = document.querySelector('[data-wl-sticky]');
  if(stickyInput) stickyInput.checked = WL_STANDARD.sticky;
  const leftInput = document.querySelector('[data-wl-left]');
  if(leftInput) leftInput.value = String(WL_STANDARD.left);
  const rightInput = document.querySelector('[data-wl-right]');
  if(rightInput) rightInput.value = String(WL_STANDARD.right);
  wlApplyPreset();
 });
})();
</script>
HTML;
    }
}
