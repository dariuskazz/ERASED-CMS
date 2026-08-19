<?php
declare(strict_types=1);

/**
 * v0.4: which homepage layout profile_id should be edited/rendered right
 * now. Deliberately reads the active website profile (Website Type
 * Foundation) rather than a hardcoded 'default', so each website profile
 * can carry its own homepage layout. Falls back to 'default' whenever no
 * website profile system is available or none is active, so a fresh
 * install with only the legacy single-homepage setup keeps working
 * unchanged.
 */
function erased_active_homepage_profile_id(): string
{
    $root = defined('ROOT') ? ROOT : dirname(__DIR__);
    if (!class_exists('\\Erased\\Website\\WebsiteProfileRepository') && is_file($root.'/app/Website/WebsiteProfileRepository.php')) {
        require_once $root.'/app/Website/WebsiteProfileRepository.php';
    }
    if (!class_exists('\\Erased\\Website\\WebsiteProfileRepository') || !function_exists('db')) {
        return 'default';
    }
    try {
        $active = (new \Erased\Website\WebsiteProfileRepository(db()))->findActive();
    } catch (\Throwable $error) {
        return 'default';
    }
    return $active !== null ? (string)$active['id'] : 'default';
}

/**
 * v0.4: starter homepage structure per website type - which of the 12
 * built-in section types appear, in which region, in what order. This
 * controls structure only, not copy: rich block text (hero headline, CTA
 * button label, etc.) lives in the global homepage_widget_options setting,
 * shared across every profile regardless of type - giving each type its
 * own wording as well would mean making that setting per-profile too,
 * which is a real, separate architectural change and not what this covers.
 * Structure alone is still a genuine difference: a Web Shop profile starts
 * with pricing/stats/testimonials in play and a Documentation profile
 * doesn't, which is the actual thing "preset" means here.
 *
 * Returns null for any type_id with no dedicated preset (community,
 * documentation, landing, custom, or an unrecognized id) - those fall back
 * to the existing clone-from-'default' behaviour, unchanged.
 *
 * @return null|list<array{block_id:string,region:string}>
 */
function erased_homepage_type_preset(string $typeId): ?array
{
    $presets = [
        // News Portal: fast-moving content discovery, active discussion visible up top.
        'news' => [
            ['block_id' => 'featured', 'region' => 'center'],
            ['block_id' => 'latest_posts', 'region' => 'center'],
            ['block_id' => 'categories', 'region' => 'left'],
            ['block_id' => 'popular_tags', 'region' => 'right'],
            ['block_id' => 'latest_comments', 'region' => 'right'],
        ],
        // Blog: a personal introduction before the writing itself.
        'blog' => [
            ['block_id' => 'featured', 'region' => 'center'],
            ['block_id' => 'latest_posts', 'region' => 'center'],
            ['block_id' => 'categories', 'region' => 'left'],
            ['block_id' => 'popular_tags', 'region' => 'right'],
        ],
        // Business: single-column marketing funnel (registry.json describes
        // this type as "single-column" - the preset honours that literally).
        // hero/stats/testimonials/cta removed along with the block types
        // themselves (2026-08-16) - 'features' is the only block left here.
        'business' => [
            ['block_id' => 'features', 'region' => 'center'],
        ],
        // Portfolio: intro, work/skills (features repurposed), social proof, contact.
        // hero/testimonials/cta removed along with the block types
        // themselves (2026-08-16) - 'features' is the only block left here.
        'portfolio' => [
            ['block_id' => 'features', 'region' => 'center'],
        ],
        // Gallery: no true gallery-grid block exists in the renderer yet
        // (a pre-existing, separately-flagged gap - see the "silently
        // dropped" fix earlier in docs/STATUS.md), so this leans on
        // featured/latest content.
        'gallery' => [
            ['block_id' => 'featured', 'region' => 'center'],
            ['block_id' => 'latest_posts', 'region' => 'center'],
        ],
        // Video: same shape as Gallery for the same reason (no dedicated
        // video-grid block yet) - featured/latest surface uploads.
        'video' => [
            ['block_id' => 'featured', 'region' => 'center'],
            ['block_id' => 'latest_posts', 'region' => 'center'],
        ],
        // Web Shop: full commerce-style landing funnel.
        // hero/pricing/stats/testimonials/cta removed along with the block
        // types themselves (2026-08-16) - 'features' is the only block left
        // here.
        'shop' => [
            ['block_id' => 'features', 'region' => 'center'],
        ],
    ];
    return $presets[$typeId] ?? null;
}

/**
 * First-use bootstrap for a website profile's homepage layout: if this
 * profile_id has never been published (no rows in
 * homepage_block_placements), seed it - from a dedicated starter preset if
 * its website type has one (erased_homepage_type_preset()), otherwise by
 * cloning 'default's current published layout - so the public site never
 * goes visibly empty just because a profile was activated. Idempotent and
 * safe to call on every request - the emptiness check makes the seed
 * itself a one-time event per profile. Best-effort: any failure here must
 * never break homepage rendering.
 */
function erased_ensure_homepage_layout_seeded(string $profileId): void
{
    if ($profileId === 'default' || $profileId === '') {
        return;
    }
    $root = defined('ROOT') ? ROOT : dirname(__DIR__);
    foreach (['/app/Homepage/BlockPlacement.php', '/app/Homepage/HomepagePlacementRepository.php'] as $file) {
        if (!class_exists('\\Erased\\Homepage\\HomepagePlacementRepository') && is_file($root.$file)) {
            require_once $root.$file;
        }
    }
    if (!class_exists('\\Erased\\Homepage\\HomepagePlacementRepository') || !function_exists('db')) {
        return;
    }
    try {
        $repo = new \Erased\Homepage\HomepagePlacementRepository(db());
        if ($repo->forProfile($profileId) !== []) {
            return;
        }

        $preset = null;
        if (ctype_digit($profileId) && class_exists('\\Erased\\Website\\WebsiteProfileRepository')) {
            $profileRow = (new \Erased\Website\WebsiteProfileRepository(db()))->find((int)$profileId);
            if ($profileRow !== null) {
                $preset = erased_homepage_type_preset((string)($profileRow['type_id'] ?? ''));
            }
        }

        if ($preset !== null) {
            foreach ($preset as $position => $entry) {
                $repo->save(new \Erased\Homepage\BlockPlacement(
                    $profileId.'-'.substr(bin2hex(random_bytes(6)), 0, 12),
                    $profileId,
                    $entry['region'],
                    $entry['block_id'],
                    $position,
                    true,
                    [],
                ));
            }
            return;
        }

        foreach ($repo->forProfile('default') as $placement) {
            $repo->save(new \Erased\Homepage\BlockPlacement(
                $profileId.'-'.substr(bin2hex(random_bytes(6)), 0, 12),
                $profileId,
                $placement->region(),
                $placement->blockId(),
                $placement->position(),
                $placement->visible(),
                $placement->settings(),
            ));
        }
    } catch (\Throwable $error) {
        // Best-effort seed only - never let a seeding failure break the page.
    }
}

/**
 * Section scheduling: a placement's settings may carry schedule_start/
 * schedule_end (strtotime-compatible strings, same convention as the
 * announcement bar's announcement_start/announcement_end). Empty or absent
 * bounds mean no restriction on that side. An unparsable date is treated as
 * no restriction on that side too, rather than hiding content over a typo.
 * @param array<string,mixed> $settings
 */
function erased_placement_within_schedule(array $settings): bool
{
    $start = trim((string)($settings['schedule_start'] ?? ''));
    $end = trim((string)($settings['schedule_end'] ?? ''));
    if ($start === '' && $end === '') return true;
    $now = time();
    if ($start !== '') {
        $startTs = strtotime($start);
        if ($startTs !== false && $now < $startTs) return false;
    }
    if ($end !== '') {
        $endTs = strtotime($end);
        if ($endTs !== false && $now > $endTs) return false;
    }
    return true;
}

/**
 * Container-level background colour and padding, entered by an
 * authenticated admin in Layout Studio's Container Options modal - still
 * validated here at render time (not trusted-and-passed-through), matching
 * how homepage_studio_style_attrs() already validates the same shapes for
 * the 7 rich block types. Returns a ready-to-use ' style="..."' HTML
 * fragment (empty string if neither is set or both are invalid).
 * @param array<string,mixed> $settings
 */
function erased_placement_style_attr(array $settings): string
{
    $style = '';
    $bgColor = trim((string)($settings['bg_color'] ?? ''));
    if ($bgColor !== '' && preg_match('/^#[0-9a-f]{3,8}$/i', $bgColor) === 1) {
        $style .= 'background:' . $bgColor . ';';
    }
    $padding = trim((string)($settings['padding'] ?? ''));
    if ($padding !== '' && preg_match('/^[0-9]{1,4}(?:px|%|rem|em)?(?:\s+[0-9]{1,4}(?:px|%|rem|em)?){0,3}$/', $padding) === 1) {
        $style .= 'padding:' . $padding . ';';
    }
    $widthMode = trim((string)($settings['width_mode'] ?? ''));
    if ($widthMode === 'narrow') {
        $widthValue = trim((string)($settings['width_value'] ?? ''));
        if ($widthValue !== '' && preg_match('/^[0-9]{1,4}(?:px|%|rem|em)$/', $widthValue) === 1) {
            // min-width:0 overrides the flex-item default of min-width:auto -
            // without it, a wrapper whose content wants more room than the
            // region's own column track can refuse to shrink and overflow
            // into the neighbouring column, since a flex item's own max-width
            // doesn't override that default on its own. Also cap with min()
            // so the requested width can never exceed the track itself.
            $style .= 'max-width:min(' . $widthValue . ',100%);min-width:0;margin-left:auto;margin-right:auto;';
        }
    } elseif ($widthMode === 'full-bleed') {
        // Standard CSS "breakout" technique: vw units resolve against the
        // viewport regardless of nesting depth, so this escapes the section's
        // grid column track and the shell's max-width without needing to
        // restructure the region/grid model itself. Real, disclosed
        // limitation: this does not push sibling left/right sidebar content
        // out of the way, so it can visually overlap a sidebar that still has
        // content at the same scroll position - surfaced in the editor's own
        // Container Options copy, not just here.
        $style .= 'width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);';
    }
    return $style === '' ? '' : ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
}

function homepage_studio_blocks(): array
{
    return [
        'featured' => ['Featured content', 'Highlighted article', 'center', '★'],
        'latest_posts' => ['Latest posts', 'Newest published posts', 'center', '▤'],
        'categories' => ['Categories', 'Category navigation', 'left', '▰'],
        'popular_tags' => ['Popular tags', 'Most used tags', 'left', '#'],
        'latest_comments' => ['Latest comments', 'Recent approved comments', 'right', '●'],
        'features' => ['Feature Grid', '3-column service or feature cards', 'center', '❖'],
    ];
}

function homepage_studio_new_block_defaults(): array
{
    // bg_style, align, min_height, padding, css_class, html_id are uniform
    // across every rich block type - homepage_studio_public_blocks() applies
    // them generically to whichever section is being rendered.
    $style = ['bg_style'=>'gradient','align'=>'left','min_height'=>'','padding'=>'','css_class'=>'','html_id'=>''];
    return [
        'features' => ['title'=>'Everything you need','subtitle'=>'Present your services or product benefits in a clear grid.','button_text'=>'','button_url'=>'','image_url'=>'','primary_color'=>'#16a34a','secondary_color'=>'#0f172a','items'=>"Fast setup|Launch quickly with a simple workflow.\nSecure by design|Use sensible defaults and clear controls.\nEasy to extend|Add more content as your website grows."] + $style,
    ];
}

function homepage_studio_block_options(string $key, array $options): array
{
    $defaults = homepage_studio_new_block_defaults()[$key] ?? [];
    return array_merge($defaults, is_array($options[$key] ?? null) ? $options[$key] : []);
}

function homepage_studio_safe_color(string $value, string $fallback): string
{
    $value = trim($value);
    return preg_match('/^#[0-9a-f]{3,8}$/i', $value) ? $value : $fallback;
}

function homepage_studio_items(string $value): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\\R/', $value) ?: []), static fn(string $line): bool => $line !== ''));
}

/**
 * Turns the universal style fields (bg_style, align, min_height, padding,
 * css_class, html_id) into HTML fragments a block can drop into its markup.
 * A block only needs bgImage when its bg_style is 'image' and it has one.
 * @param array<string,mixed> $o
 * @return array{sectionStyle:string,headingStyle:string,extraClass:string,idAttr:string}
 */
function homepage_studio_style_attrs(array $o, string $bgImage = ''): array
{
    $sectionStyle = '';
    $bgStyle = (string)($o['bg_style'] ?? 'gradient');
    if ($bgStyle === 'solid') {
        $sectionStyle .= 'background:var(--block-primary);';
    } elseif ($bgStyle === 'image' && $bgImage !== '') {
        $sectionStyle .= "background-image:url('".$bgImage."');background-size:cover;background-position:center;";
    }

    $minHeight = (int)($o['min_height'] ?? 0);
    if ($minHeight > 0) $sectionStyle .= 'min-height:'.min(4000, $minHeight).'px;';

    $padding = trim((string)($o['padding'] ?? ''));
    if ($padding !== '' && preg_match('/^[0-9]{1,4}(?:px|%|rem|em)?(?:\s+[0-9]{1,4}(?:px|%|rem|em)?){0,3}$/', $padding)) {
        $sectionStyle .= 'padding:'.$padding.';';
    }

    $align = (string)($o['align'] ?? 'left');
    $headingStyle = in_array($align, ['left', 'center', 'right', 'justify'], true) ? 'text-align:'.$align.';' : '';

    $cssClass = trim((string)($o['css_class'] ?? ''));
    $extraClass = preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $cssClass) === 1 ? ' '.$cssClass : '';

    $htmlId = trim((string)($o['html_id'] ?? ''));
    $idAttr = preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,60}$/', $htmlId) === 1 ? ' id="'.$htmlId.'"' : '';

    return ['sectionStyle' => $sectionStyle, 'headingStyle' => $headingStyle, 'extraClass' => $extraClass, 'idAttr' => $idAttr];
}

function homepage_studio_config(): array
{
    $preset = setting('homepage_layout_preset', 'three');
    if (!in_array($preset, ['one', 'two-left', 'two-right', 'three'], true)) $preset = 'three';
    $left = max(0, min(45, (int) setting('homepage_left_width', '20')));
    $right = max(0, min(45, (int) setting('homepage_right_width', '20')));
    if ($preset === 'one') {$left = 0; $right = 0;}
    elseif ($preset === 'two-left') $right = 0;
    elseif ($preset === 'two-right') $left = 0;
    $regions = json_decode(setting('homepage_block_regions', '{}'), true); if (!is_array($regions)) $regions = [];
    $enabled = json_decode(setting('homepage_enabled_blocks', '[]'), true); if (!is_array($enabled) || !$enabled) $enabled = array_keys(homepage_studio_blocks());
    $order = json_decode(setting('homepage_block_order', '[]'), true); if (!is_array($order) || !$order) $order = array_keys(homepage_studio_blocks());
    $options = json_decode(setting('homepage_widget_options', '{}'), true); if (!is_array($options)) $options = [];
    return [
        'preset'=>$preset,'left'=>$left,'right'=>$right,
        'gap'=>max(0,min(80,(int)setting('homepage_column_gap','24'))),
        'max_width'=>max(900,min(2400,(int)setting('homepage_max_width','1600'))),
        'widget_gap'=>max(0,min(80,(int)setting('homepage_widget_gap','16'))),
        'breakpoint'=>max(480,min(1200,(int)setting('homepage_mobile_breakpoint','760'))),
        'sticky_offset'=>max(0,min(200,(int)setting('homepage_sidebar_top','18'))),
        'sticky'=>setting('homepage_sticky_sidebars','0')==='1',
        'equal'=>setting('homepage_equal_widget_height','0')==='1',
        'show_empty'=>setting('homepage_show_empty_regions','0')==='1',
        'regions'=>$regions,'enabled'=>$enabled,'order'=>$order,'options'=>$options,
        'active_regions'=>match($preset){'one'=>['center'],'two-left'=>['left','center'],'two-right'=>['center','right'],default=>['left','center','right']},
    ];
}

function homepage_studio_width_style(?array $cfg=null, ?array $visibleRegions=null): string
{
    $cfg=$cfg?:homepage_studio_config();
    $left=(int)$cfg['left'];
    $right=(int)$cfg['right'];
    $center=max(10,100-$left-$right);
    $visibleRegions=$visibleRegions??($cfg['active_regions']??['center']);
    $visibleRegions=array_values(array_intersect(['left','center','right'],array_unique($visibleRegions)));

    $columns=match(implode(',', $visibleRegions)){
        'left,center,right'=>'minmax(0,'.$left.'fr) minmax(0,'.$center.'fr) minmax(0,'.$right.'fr)',
        'left,center'=>'minmax(0,'.$left.'fr) minmax(0,'.max(10,100-$left).'fr)',
        'center,right'=>'minmax(0,'.max(10,100-$right).'fr) minmax(0,'.$right.'fr)',
        'left,right'=>'minmax(0,'.max(1,$left).'fr) minmax(0,'.max(1,$right).'fr)',
        default=>'minmax(0,1fr)',
    };
    return '--homepage-columns:'.$columns.';--homepage-gap:'.(int)$cfg['gap'].'px;--homepage-max-width:'.(int)$cfg['max_width'].'px;--homepage-widget-gap:'.(int)$cfg['widget_gap'].'px;--homepage-sticky-offset:'.(int)$cfg['sticky_offset'].'px';
}

function homepage_studio_apply(array $cfg): array
{
    $studio = homepage_studio_config();
    $activeRegions = $studio['active_regions'];
    $configuredRegions = array_merge($cfg['regions'] ?? [], $studio['regions']);
    $resolvedRegions = [];

    // Resolve every block to an active region. This is essential for one- and
    // two-column presets: blocks assigned to a disabled sidebar must move into
    // the main content region instead of silently creating a third column.
    foreach (homepage_studio_blocks() as $block => $meta) {
        $region = (string) ($configuredRegions[$block] ?? $meta[2] ?? 'center');
        if (!in_array($region, ['left', 'center', 'right'], true)) $region = 'center';
        if (!in_array($region, $activeRegions, true)) $region = 'center';
        $resolvedRegions[$block] = $region;
    }

    $cfg['preset'] = $studio['preset'];
    $cfg['left'] = $studio['left'];
    $cfg['right'] = $studio['right'];
    $cfg['active_regions'] = $activeRegions;
    $cfg['regions'] = $resolvedRegions;
    $cfg['enabled'] = $studio['enabled'];
    $cfg['order'] = $studio['order'];
    $cfg['options'] = $studio['options'];
    return $cfg;
}

function homepage_studio_public_css(): string
{
    $c=homepage_studio_config(); $bp=(int)$c['breakpoint'];
    $sticky='.homepage-layout-three .homepage-left,.homepage-layout-three .homepage-right{position:static;top:auto;align-self:start}';
    $equal=$c['equal']?'.homepage-layout-three .homepage-column>*{min-height:100%}':'';
    return '<style>.homepage-shell{width:100%;max-width:var(--homepage-max-width);margin-inline:auto}.homepage-layout-three{display:grid!important;grid-template-columns:var(--homepage-columns)!important;grid-auto-rows:min-content!important;gap:var(--homepage-gap)!important;max-width:var(--homepage-max-width)!important;width:100%!important;align-items:start!important;align-content:start!important}.homepage-layout-three>.homepage-region,.homepage-layout-three .homepage-left,.homepage-layout-three .homepage-center,.homepage-layout-three .homepage-right{display:flex!important;flex-direction:column!important;align-content:start!important;justify-content:flex-start!important;align-items:stretch!important;align-self:start!important;justify-self:stretch!important;gap:var(--homepage-widget-gap)!important;min-width:0!important;min-height:0!important;height:auto!important;max-height:none!important;margin-top:0!important;margin-bottom:0!important;overflow-x:hidden!important}.homepage-layout-three>.homepage-region>*{flex:0 0 auto!important;align-self:stretch!important;margin-top:0!important}.homepage-layout-three .homepage-right{place-content:start stretch!important}.homepage-layout-three .homepage-left>:first-child,.homepage-layout-three .homepage-center>:first-child,.homepage-layout-three .homepage-right>:first-child{margin-top:0!important}.homepage-layout-three.homepage-columns-1{grid-template-columns:minmax(0,1fr)!important}.homepage-layout-three.homepage-columns-2{grid-template-columns:var(--homepage-columns)!important}'.$sticky.$equal.'
.homepage-rich-block{--block-primary:#16a34a;--block-secondary:#0f172a;container-type:inline-size;position:relative;overflow:hidden;border:1px solid color-mix(in srgb,var(--block-primary) 20%,var(--line,#d1d5db));border-radius:clamp(16px,2vw,28px);background:color-mix(in srgb,var(--panel,#fff) 94%,var(--block-primary) 6%);color:var(--text,inherit);padding:clamp(24px,5vw,64px)}.homepage-rich-block h2,.homepage-rich-block h3,.homepage-rich-block p{margin-top:0}.homepage-rich-heading{max-width:850px;margin-inline:auto;text-align:center}.homepage-rich-heading h2{font-size:clamp(1.8rem,4vw,3.4rem);line-height:1.06}.homepage-rich-heading p{max-width:720px;margin-inline:auto;color:var(--muted,currentColor);font-size:clamp(1rem,1.8vw,1.2rem)}.homepage-rich-button{display:inline-flex;max-width:100%;box-sizing:border-box;align-items:center;justify-content:center;min-height:46px;padding:11px 20px;border-radius:999px;background:var(--block-primary);color:#fff!important;text-decoration:none;font-weight:800;box-shadow:0 10px 28px color-mix(in srgb,var(--block-primary) 28%,transparent)}.homepage-rich-button:hover{filter:brightness(1.08);transform:translateY(-1px)}.homepage-rich-grid{display:grid;grid-template-columns:1fr;gap:18px;margin-top:32px}@container (min-width:440px){.homepage-rich-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@container (min-width:640px){.homepage-rich-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}.homepage-rich-card{min-width:0;padding:22px;border:1px solid color-mix(in srgb,var(--block-primary) 15%,var(--line,#d1d5db));border-radius:18px;background:color-mix(in srgb,var(--panel,#fff) 96%,var(--block-primary) 4%)}.homepage-rich-card h3{font-size:1.1rem}.homepage-rich-card p,.homepage-rich-card small{color:var(--muted,currentColor)}.homepage-feature-icon{display:grid;place-items:center;width:42px;height:42px;margin-bottom:16px;border-radius:12px;background:color-mix(in srgb,var(--block-primary) 15%,transparent);color:var(--block-primary);font-weight:900}.homepage-rich-actions{margin-top:28px;text-align:center}@media(max-width:'.$bp.'px){.homepage-layout-three{grid-template-columns:minmax(0,1fr)!important}.homepage-layout-three .homepage-left,.homepage-layout-three .homepage-center,.homepage-layout-three .homepage-right{position:static!important;top:auto!important}.homepage-rich-block{padding:24px}}
.appearance-block{grid-template-columns:38px minmax(0,1fr) minmax(120px,150px)}.appearance-block.is-enabled{border-color:color-mix(in srgb,var(--green) 42%,var(--line));background:color-mix(in srgb,var(--green) 5%,var(--panel))}.appearance-block.is-disabled{opacity:.68}.appearance-drag{display:grid;place-items:center;width:38px;height:38px;padding:0;border:1px solid var(--line);border-radius:9px;background:var(--panel);font-weight:800}.appearance-block-toggle{display:grid!important;grid-template-columns:auto minmax(0,1fr);align-items:center!important;gap:10px!important;margin:0!important;cursor:pointer}.appearance-block-toggle>input{position:absolute;opacity:0}.appearance-switch{position:relative;display:block!important;width:42px;height:24px;border-radius:999px;background:var(--line)}.appearance-switch::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:var(--panel);transition:transform .16s ease}.appearance-block-toggle input:checked+.appearance-switch{background:var(--green)}.appearance-block-toggle input:checked+.appearance-switch::after{transform:translateX(18px)}.appearance-block-copy{display:grid!important;gap:3px!important}.appearance-region{display:grid!important;gap:4px!important;margin:0!important}.appearance-region>span{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase}.appearance-block-toolbar{display:flex;justify-content:space-between;gap:12px;align-items:end;margin-bottom:12px;padding:10px;border:1px solid var(--line);border-radius:11px}.appearance-block-tools{display:flex;gap:8px;flex-wrap:wrap}.appearance-block-filter{display:grid;gap:4px;min-width:150px;margin:0}.appearance-count{padding:6px 9px;border-radius:999px;background:color-mix(in srgb,var(--green) 12%,var(--panel));white-space:nowrap}.appearance-block-empty{text-align:center;padding:20px;border:1px dashed var(--line);border-radius:12px}@media(max-width:680px){.appearance-block-heading,.appearance-block-toolbar{align-items:stretch;flex-direction:column}.appearance-block{grid-template-columns:38px minmax(0,1fr)}.appearance-region{grid-column:2}}
.homepage-container-grid{display:grid;gap:var(--homepage-widget-gap,16px);align-items:start}.homepage-container-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.homepage-container-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.homepage-container-grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}.homepage-section-wrap{display:block}@media(max-width:'.$bp.'px){.homepage-container-grid-2,.homepage-container-grid-3,.homepage-container-grid-4{grid-template-columns:minmax(0,1fr)}.hp-hide-mobile{display:none!important}}@media(min-width:'.($bp+1).'px){.hp-hide-desktop{display:none!important}}
</style>';
}

function homepage_studio_preview_widget(string $key,array $meta,array $options=[]): string
{
    $o=homepage_studio_block_options($key,[$key=>$options]);
    $title=e((string)($o['title']??$meta[0])); $icon=e((string)$meta[3]);
    $subtitle=e((string)($o['subtitle']??''));
    $items=homepage_studio_items((string)($o['items']??''));
    $body=match($key){
        'featured'=>'<div class="ls-feature"><div class="ls-feature-image"></div><div><span class="ls-kicker">Featured</span><h3>Building a private Linux workspace</h3><p>A practical guide to a fast, secure and self-hosted setup.</p></div></div>',
        'latest_posts'=>'<div class="ls-post"><b></b><div><strong>Linux security checklist</strong><small>Today · 6 min read</small><p>Simple steps that improve a self-hosted server.</p></div></div><div class="ls-post"><b></b><div><strong>Private AI at home</strong><small>Yesterday · 8 min read</small><p>Run useful models without sending data away.</p></div></div>',
        'categories'=>'<ul class="ls-list"><li><span>Linux</span><em>24</em></li><li><span>AI</span><em>18</em></li><li><span>Privacy</span><em>15</em></li><li><span>Security</span><em>12</em></li></ul>',
        'popular_tags'=>'<div class="ls-tags"><span>#linux</span><span>#php</span><span>#security</span><span>#privacy</span><span>#selfhosting</span></div>',
        'latest_comments'=>'<div class="ls-comment"><b>DK</b><div><strong>Darius</strong><small>Great Linux guide. The Podman section was useful.</small></div></div>',
        'features'=>'<div class="ls-rich"><h3>'.$title.'</h3><p>'.$subtitle.'</p><div class="ls-rich-mini">'.implode('',array_map(static fn(string $line): string=>'<i>'.e(trim(explode('|',$line)[0]??$line)).'</i>',array_slice($items,0,4))).'</div></div>',
        default=>'<p>Widget preview</p>'};
    return '<article class="ls-widget" draggable="true" tabindex="0" data-key="'.e($key).'"><div class="ls-widget-toolbar"><span class="ls-drag">⋮⋮</span><strong><i>'.$icon.'</i>'.$title.'</strong><button type="button" class="ls-widget-menu">•••</button></div><div class="ls-widget-content">'.$body.'</div></article>';
}

function homepage_studio_public_blocks(): array
{
    $config = homepage_studio_config();
    $widgetOptions = $config['options'];
    $featuredPosts = homepage_featured_posts();
    $featuredHtml = homepage_featured_html($featuredPosts);

    $cats = db()->query("SELECT category,COUNT(*) total FROM content WHERE status='published' AND category IS NOT NULL AND category<>'' GROUP BY category ORDER BY total DESC,category LIMIT 50")->fetchAll();
    $cats = array_slice($cats, 0, max(1, min(50, (int) setting('categories_widget_limit', '12'))));
    $catLinks = '';
    foreach ($cats as $cat) $catLinks .= '<a href="/posts?category='.rawurlencode((string)$cat['category']).'"><span>'.e((string)$cat['category']).'</span><small>'.(int)$cat['total'].'</small></a>';
    if ($catLinks === '') $catLinks = '<span class="homepage-empty">No categories yet.</span>';

    $tagRows = db()->query("SELECT tags FROM content WHERE status='published' AND tags IS NOT NULL AND tags<>'' ORDER BY updated_at DESC LIMIT 50")->fetchAll();
    $tagCounts=[]; foreach($tagRows as $row) foreach(array_filter(array_map('trim',explode(',',(string)$row['tags']))) as $tag) $tagCounts[$tag]=($tagCounts[$tag]??0)+1;
    arsort($tagCounts); $tagLinks='';
    foreach(array_slice($tagCounts,0,max(1,min(50,(int)setting('tags_widget_limit','12'))),true) as $tag=>$count) $tagLinks.='<a href="/posts?tag='.rawurlencode((string)$tag).'">'.e((string)$tag).'</a>';
    if($tagLinks==='')$tagLinks='<span class="homepage-empty">No tags yet.</span>';

    $comments=''; try{$rows=db()->query("SELECT comments.author_name,comments.body,content.title,content.slug FROM comments JOIN content ON content.id=comments.content_id WHERE comments.status='approved' ORDER BY comments.created_at DESC LIMIT 5")->fetchAll();foreach($rows as $comment){$body=trim(strip_tags((string)$comment['body']));if(mb_strlen($body)>70)$body=mb_substr($body,0,67).'…';$comments.='<div class="homepage-comment"><div><strong>'.e((string)($comment['author_name']?:'Reader')).'</strong> on <a href="/'.rawurlencode((string)$comment['slug']).'#comments">'.e((string)$comment['title']).'</a><br><small>'.e($body).'</small></div></div>';}}catch(Throwable $e){} if($comments==='')$comments='<p class="homepage-empty">No comments yet.</p>';

    $rich=[];
    foreach(homepage_studio_new_block_defaults() as $key=>$defaults){
        $o=homepage_studio_block_options($key,$widgetOptions); $primary=homepage_studio_safe_color((string)$o['primary_color'],'#16a34a'); $secondary=homepage_studio_safe_color((string)$o['secondary_color'],'#0f172a');
        $style='--block-primary:'.e($primary).';--block-secondary:'.e($secondary); $title=e((string)$o['title']); $subtitle=e((string)$o['subtitle']); $buttonText=e((string)$o['button_text']); $buttonUrl=e((string)($o['button_url']?:'#')); $image=e((string)$o['image_url']); $items=homepage_studio_items((string)$o['items']);
        $attrs=homepage_studio_style_attrs($o,$image); $sectionStyle=$style.$attrs['sectionStyle']; $headingStyle=$attrs['headingStyle']!==''?' style="'.$attrs['headingStyle'].'"':'';
        $heading='<div class="homepage-rich-heading"'.$headingStyle.'><h2>'.$title.'</h2>'.($subtitle!==''?'<p>'.$subtitle.'</p>':'').'</div>'; $button=$buttonText!==''?'<a class="homepage-rich-button" href="'.$buttonUrl.'">'.$buttonText.'</a>':'';
        $cards=''; foreach($items as $index=>$line){$parts=array_map('trim',explode('|',$line));
            if($key==='features'){$cards.='<article class="homepage-rich-card"><span class="homepage-feature-icon">'.($index+1).'</span><h3>'.e($parts[0]??'Feature').'</h3><p>'.e($parts[1]??'Describe this feature.').'</p></article>';}
        }
        // $button was computed above from the CTA Button label/URL fields but
        // never referenced here - those fields still showed as live/editable
        // in the Spec Sheet and saved successfully with no error, silently
        // doing nothing on the published page. Render it below the grid.
        $rich[$key]='<section class="homepage-rich-block homepage-'.$key.$attrs['extraClass'].'"'.$attrs['idAttr'].' style="'.$sectionStyle.'">'.$heading.'<div class="homepage-rich-grid">'.$cards.'</div>'.($button!==''?'<div class="homepage-rich-actions">'.$button.'</div>':'').'</section>';
    }

    return array_merge([
        'featured'=>$featuredHtml,'latest_posts'=>erased_latest_posts_widget(6,null),
        'categories'=>'<section class="card"><h2 class="homepage-section-title">'.e(setting('categories_widget_title','Categories')).'</h2><div class="homepage-categories">'.$catLinks.'</div></section>',
        'popular_tags'=>'<section class="card"><h2 class="homepage-section-title">'.e(setting('tags_widget_title','Popular tags')).'</h2><div class="homepage-tags">'.$tagLinks.'</div></section>',
        'latest_comments'=>'<section class="card"><h2 class="homepage-section-title">Latest comments</h2><div class="homepage-comments">'.$comments.'</div></section>',
    ],$rich);
}

/**
 * Renders a homepage block declared by an installed package's manifest
 * (app/Packages/PackageManifest.php::homepageBlocks()), for any placement
 * whose block id isn't one of the legacy built-ins already covered by
 * homepage_studio_public_blocks()'s flat map. Resolves the block's declared
 * service via service_runtime()'s container and calls its render(array
 * $settings): string method. Every failure mode (unknown id, unmet
 * capability, missing/broken service, a render() call that throws or
 * returns something invalid) returns null rather than propagating - a
 * broken plugin block must never be able to break the public homepage.
 */
function homepage_studio_plugin_block_resolver(): callable
{
    return static function (\Erased\Homepage\BlockPlacement $placement): ?string {
        static $registry = null;
        if ($registry === null) {
            $registry = new \Erased\Homepage\BlockDefinitionRegistry();
            try {
                (new \Erased\Homepage\InstalledBlockRuntime(new \Erased\Packages\InstalledPackageRepository(db()), $registry))->refresh();
            } catch (\Throwable) {
                // Keep whatever the registry already has (builtins only) rather than fail the whole homepage.
            }
        }

        $definition = $registry->find($placement->blockId());
        if ($definition === null || $definition->packageId() === 'erased.legacy-homepage') {
            return null;
        }

        $capabilities = function_exists('capability_runtime') ? capability_runtime()->resolver() : null;
        foreach ($definition->requiredCapabilities() as $capability) {
            if ($capabilities === null || !$capabilities->has($capability)) {
                return null; // hidden, not deleted - the placement stays saved
            }
        }

        try {
            $service = service_runtime()->container()->get($definition->serviceId());
            if (!method_exists($service, 'render')) {
                return null;
            }
            $html = $service->render($placement->settings());
            return is_string($html) ? $html : null;
        } catch (\Throwable) {
            return null;
        }
    };
}

function homepage_studio_render_public(): array
{
    $cfg = homepage_studio_config();
    $blocks = homepage_studio_public_blocks();
    $regions = ['left' => '', 'center' => '', 'right' => ''];
    $title = tr('posts');

    if (setting('homepage_mode', 'posts') === 'page') {
        $homeId = max(0, (int) setting('homepage_content_id', '0'));
        if ($homeId > 0) {
            $stmt = db()->prepare("SELECT * FROM content WHERE id=? AND status='published' AND (publish_at IS NULL OR publish_at<=NOW()) LIMIT 1");
            $stmt->execute([$homeId]);
            if ($page = $stmt->fetch()) {
                $title = (string) $page['title'];
                $regions['center'] = v100_page_template($page, public_article($page, true));
            }
        }
        unset($blocks['latest_posts'], $blocks['featured']);
    }

    $publishedHtml = null;
    $root = defined('ROOT') ? ROOT : dirname(__DIR__);
    if (is_file($root.'/app/Homepage/BlockPlacement.php')) require_once $root.'/app/Homepage/BlockPlacement.php';
    if (is_file($root.'/app/Homepage/HomepagePlacementRepository.php')) require_once $root.'/app/Homepage/HomepagePlacementRepository.php';
    if (is_file($root.'/app/Homepage/PublishedHomepageLayout.php')) require_once $root.'/app/Homepage/PublishedHomepageLayout.php';
    if (is_file($root.'/app/Homepage/PublishedHomepageRenderer.php')) require_once $root.'/app/Homepage/PublishedHomepageRenderer.php';
    if (class_exists('Erased\\Homepage\\PublishedHomepageLayout') && class_exists('Erased\\Homepage\\PublishedHomepageRenderer')) {
        try {
            $activeLayoutProfileId = erased_active_homepage_profile_id();
            erased_ensure_homepage_layout_seeded($activeLayoutProfileId);
            $placements=(new \Erased\Homepage\PublishedHomepageLayout(db()))->placements($activeLayoutProfileId);
            $publishedHtml=(new \Erased\Homepage\PublishedHomepageRenderer())->render($placements,$cfg,$blocks,homepage_studio_plugin_block_resolver());
        } catch (Throwable $error) {
            $publishedHtml=null;
        }
    }
    if (is_string($publishedHtml)) {
        return ['title'=>$title,'html'=>$publishedHtml];
    }

    foreach ($cfg['order'] as $block) {
        if (!in_array($block, $cfg['enabled'], true) || !array_key_exists($block, $blocks)) continue;
        $html = trim((string) $blocks[$block]);
        if ($html === '') continue;
        $region = (string) ($cfg['regions'][$block] ?? homepage_studio_blocks()[$block][2] ?? 'center');
        if (!in_array($region, $cfg['active_regions'], true)) $region = 'center';
        $regions[$region] .= $html;
    }

    if (trim($regions['center']) === '') {
        $regions['center'] = '<section class="card"><p class="homepage-empty">No homepage content is enabled.</p></section>';
    }

    $markup = '';
    $visibleRegions = [];
    foreach ($cfg['active_regions'] as $region) {
        $html = trim($regions[$region]);
        if ($html === '' && !$cfg['show_empty']) continue;
        if ($html === '') $html = '<section class="card"><p class="homepage-empty">Empty region</p></section>';
        $tag = $region === 'center' ? 'div' : 'aside';
        $role = $region === 'center' ? ' role="main"' : '';
        $markup .= '<'.$tag.' class="homepage-region homepage-'.$region.'"'.$role.'>'.$html.'</'.$tag.'>';
        $visibleRegions[] = $region;
    }

    $classCount = max(1, count($visibleRegions));
    $regionClasses = '';
    foreach ($visibleRegions as $visRegion) {
        $regionClasses .= ' homepage-has-'.preg_replace('/[^a-z0-9_-]/', '', (string) $visRegion);
    }
    $widthStyle = homepage_studio_width_style($cfg, $visibleRegions);

    $html = homepage_studio_public_css()
        .'<div class="homepage-shell">'
        .'<section class="homepage-layout-three homepage-columns-'.$classCount.$regionClasses.' website-style-'.e((string) ($cfg['style'] ?? 'standard')).'" style="'.e($widthStyle).'">'
        .$markup
        .'</section></div>';

    return ['title' => $title, 'html' => $html];
}

function homepage_studio_admin(): void
{
    require_permission('packages.manage');
    if (!class_exists('\\ERASED\\LayoutStudio\\LayoutStudioAdminScreen')) {
        $root = defined('ROOT') ? ROOT : dirname(__DIR__);
        if (is_file($root . '/app/Homepage/BlockDefinition.php')) require_once $root . '/app/Homepage/BlockDefinition.php';
        if (is_file($root . '/app/Homepage/BlockPlacement.php')) require_once $root . '/app/Homepage/BlockPlacement.php';
        if (is_file($root . '/app/LayoutStudio/LayoutCanvas.php')) require_once $root . '/app/LayoutStudio/LayoutCanvas.php';
        if (is_file($root . '/app/LayoutStudio/LayoutDocumentValidator.php')) require_once $root . '/app/LayoutStudio/LayoutDocumentValidator.php';
        if (is_file($root . '/app/LayoutStudio/LayoutSerializer.php')) require_once $root . '/app/LayoutStudio/LayoutSerializer.php';
        if (is_file($root . '/app/LayoutStudio/LayoutDraft.php')) require_once $root . '/app/LayoutStudio/LayoutDraft.php';
        if (is_file($root . '/app/LayoutStudio/LayoutDraftRepository.php')) require_once $root . '/app/LayoutStudio/LayoutDraftRepository.php';
        if (is_file($root . '/app/LayoutStudio/LayoutDraftService.php')) require_once $root . '/app/LayoutStudio/LayoutDraftService.php';
        if (is_file($root . '/app/LayoutStudio/LegacyLayoutDocumentValidator.php')) require_once $root . '/app/LayoutStudio/LegacyLayoutDocumentValidator.php';
        if (is_file($root . '/app/LayoutStudio/LayoutStudioAdminScreen.php')) require_once $root . '/app/LayoutStudio/LayoutStudioAdminScreen.php';
        if (is_file($root . '/app/Homepage/HomepagePlacementRepository.php')) require_once $root . '/app/Homepage/HomepagePlacementRepository.php';
        if (is_file($root . '/app/LayoutStudio/LayoutStudioAdminRoute.php')) require_once $root . '/app/LayoutStudio/LayoutStudioAdminRoute.php';
    }
    $route = new \Erased\LayoutStudio\LayoutStudioAdminRoute(new \Erased\LayoutStudio\LayoutStudioAdminScreen(), db());
    $activeLayoutProfileId = erased_requested_homepage_profile_id();
    erased_ensure_homepage_layout_seeded($activeLayoutProfileId);
    echo $route->render($activeLayoutProfileId);
    exit;
}

/**
 * v0.7-dev: which profile_id the *editing* screen should show, honoring an
 * explicit ?profile= override so Layout Studio (and ERASED Studio's Layout
 * tab, which embeds this same screen) can view/edit any website profile's
 * own draft, not only the currently active one - matching the "preview
 * inactive profiles without activation" promise the Website Profile system
 * already makes everywhere else. Falls back to the active profile, exactly
 * like before, whenever no override is given or it doesn't resolve to a
 * real profile. Deliberately separate from erased_active_homepage_profile_id()
 * - the public homepage renderer must always use the true active profile,
 * never a query-string override an anonymous visitor could otherwise abuse.
 */
function erased_requested_homepage_profile_id(): string
{
    $requested = strtolower(trim((string)($_GET['profile'] ?? '')));
    if ($requested !== '' && preg_match('/^[a-z0-9][a-z0-9._-]*$/', $requested) === 1 && erased_website_profile_id_exists($requested)) {
        return $requested;
    }
    return erased_active_homepage_profile_id();
}

function erased_website_profile_id_exists(string $profileId): bool
{
    if ($profileId === 'default') return true;
    if (!ctype_digit($profileId)) return false;
    $root = defined('ROOT') ? ROOT : dirname(__DIR__);
    if (!class_exists('\\Erased\\Website\\WebsiteProfileRepository') && is_file($root.'/app/Website/WebsiteProfileRepository.php')) {
        require_once $root.'/app/Website/WebsiteProfileRepository.php';
    }
    if (!class_exists('\\Erased\\Website\\WebsiteProfileRepository') || !function_exists('db')) return false;
    try {
        return (new \Erased\Website\WebsiteProfileRepository(db()))->find((int)$profileId) !== null;
    } catch (\Throwable $error) {
        return false;
    }
}

