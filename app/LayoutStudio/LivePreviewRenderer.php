<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\BlockPlacement;

final class LivePreviewRenderer
{
    /** @param ?callable(BlockPlacement):?string $pluginBlockResolver mirrors PublishedHomepageRenderer::render()'s param - see its docblock */
    public function render(LivePreviewDocument $document, string $device = 'desktop', ?callable $pluginBlockResolver = null): string
    {
        $device = in_array($device, ['desktop', 'tablet', 'mobile', 'ultrawide'], true) ? $device : 'desktop';

        $availableBlocks = function_exists('homepage_studio_public_blocks') ? homepage_studio_public_blocks() : [];
        $studioConfig = function_exists('homepage_studio_config') ? homepage_studio_config() : [];

        // Mirrors PublishedHomepageRenderer's container grouping (see its
        // docblock) so 50/50 and 3x33% column layouts preview correctly
        // too, not just the final published output.
        /** @var array<string,list<array{container_id:?string,items:list<string>,hide_mobile:bool,hide_desktop:bool,style_attr:string}>> */
        $regionGroups = ['left' => [], 'center' => [], 'right' => []];
        foreach ($document->placements() as $placement) {
            if (!$placement->visible()) {
                continue;
            }
            $settings = $placement->settings();
            if (function_exists('erased_placement_within_schedule') && !erased_placement_within_schedule($settings)) {
                continue;
            }
            $blockId = $placement->blockId();
            $region = $placement->region();
            if (!in_array($region, ['left', 'center', 'right'], true)) {
                $region = 'center';
            }
            if (isset($availableBlocks[$blockId])) {
                $html = $availableBlocks[$blockId];
            } elseif ($pluginBlockResolver !== null) {
                try {
                    $resolved = $pluginBlockResolver($placement);
                } catch (\Throwable) {
                    $resolved = null;
                }
                if (!is_string($resolved) || trim($resolved) === '') {
                    continue;
                }
                $html = $resolved;
            } else {
                continue;
            }

            $containerId = isset($settings['container_id']) && is_string($settings['container_id']) ? $settings['container_id'] : null;

            $lastIndex = count($regionGroups[$region]) - 1;
            if ($containerId !== null && $lastIndex >= 0 && ($regionGroups[$region][$lastIndex]['container_id'] ?? null) === $containerId) {
                $regionGroups[$region][$lastIndex]['items'][] = $html;
            } else {
                $styleAttr = function_exists('erased_placement_style_attr') ? erased_placement_style_attr($settings) : '';
                $regionGroups[$region][] = ['container_id' => $containerId, 'items' => [$html], 'hide_mobile' => !empty($settings['hide_mobile']), 'hide_desktop' => !empty($settings['hide_desktop']), 'style_attr' => $styleAttr];
            }
        }

        $regions = ['left' => '', 'center' => '', 'right' => ''];
        foreach ($regionGroups as $region => $groups) {
            foreach ($groups as $group) {
                $count = count($group['items']);
                $deviceClass = ($group['hide_mobile'] ? ' hp-hide-mobile' : '') . ($group['hide_desktop'] ? ' hp-hide-desktop' : '');
                if ($count > 1) {
                    $regions[$region] .= '<div class="homepage-container-grid homepage-container-grid-' . min(4, $count) . $deviceClass . '"' . $group['style_attr'] . '>' . implode('', $group['items']) . '</div>';
                } elseif ($deviceClass !== '' || $group['style_attr'] !== '') {
                    $regions[$region] .= '<div class="homepage-section-wrap' . $deviceClass . '"' . $group['style_attr'] . '>' . ($group['items'][0] ?? '') . '</div>';
                } else {
                    $regions[$region] .= $group['items'][0] ?? '';
                }
            }
        }

        $activeRegions = $studioConfig['active_regions'] ?? ['left', 'center', 'right'];

        if (trim($regions['center']) === '') {
            $regions['center'] = '<section class="card"><p class="homepage-empty">No homepage content is enabled.</p></section>';
        }

        $markup = '';
        $visibleRegions = [];
        foreach ($activeRegions as $r) {
            $html = trim($regions[$r]);
            if ($html === '' && !($studioConfig['show_empty'] ?? false)) {
                continue;
            }
            if ($html === '') {
                $html = '<section class="card"><p class="homepage-empty">Empty region</p></section>';
            }
            $tag = $r === 'center' ? 'div' : 'aside';
            $role = $r === 'center' ? ' role="main"' : '';
            $markup .= '<' . $tag . ' class="homepage-region homepage-' . $r . '"' . $role . '>' . $html . '</' . $tag . '>';
            $visibleRegions[] = $r;
        }

        $classCount = max(1, count($visibleRegions));
        $regionClasses = '';
        foreach ($visibleRegions as $visRegion) {
            $regionClasses .= ' homepage-has-' . preg_replace('/[^a-z0-9_-]/', '', (string)$visRegion);
        }

        $widthStyle = function_exists('homepage_studio_width_style')
            ? homepage_studio_width_style($studioConfig, $visibleRegions)
            : '';
        $publicCss = function_exists('homepage_studio_public_css') ? homepage_studio_public_css() : '';

        $bodyContent = $publicCss
            . '<div class="homepage-shell">'
            . '<section class="homepage-layout-three homepage-columns-' . $classCount . $regionClasses . '" style="' . htmlspecialchars($widthStyle, ENT_QUOTES, 'UTF-8') . '" data-live-preview-grid data-preview-revision="' . $document->revision() . '">'
            . $markup
            . '</section></div>';

        if (function_exists('layout')) {
            ob_start();
            $pageTitle = function_exists('tr') ? tr('home') : 'Home';
            layout($pageTitle, $bodyContent, false);
            $fullHtml = (string)ob_get_clean();

            $syncScript = $this->clientSyncScript($document);
            if (str_contains($fullHtml, '</body>')) {
                $fullHtml = str_replace('</body>', $syncScript . '</body>', $fullHtml);
            } else {
                $fullHtml .= $syncScript;
            }
            if (str_contains($fullHtml, '</head>')) {
                $fullHtml = str_replace('</head>', '<meta name="robots" content="noindex,nofollow"></head>', $fullHtml);
            }
            if (str_contains($fullHtml, '<body')) {
                $fullHtml = preg_replace('/<body([^>]*)>/i', '<body$1 data-preview-device="' . htmlspecialchars($device, ENT_QUOTES, 'UTF-8') . '" data-profile-id="' . htmlspecialchars($document->profileId(), ENT_QUOTES, 'UTF-8') . '">', $fullHtml, 1);
            }
            return $fullHtml;
        }

        return $bodyContent;
    }

    private function clientSyncScript(LivePreviewDocument $doc): string
    {
        return '<style>
body[data-preview-device] .frontend-adminbar { display: none !important; }
body[data-preview-device] a, body[data-preview-device] form button { pointer-events: none !important; cursor: default !important; }
</style>
<script>
(function(){
  document.addEventListener("click", function(e){
    var elem = e.target.closest("a, button, input[type=\'submit\']");
    if(elem){
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);
  document.addEventListener("submit", function(e){
    e.preventDefault();
    e.stopPropagation();
  }, true);
  window.addEventListener("message", function(e){
    if(!e.data || typeof e.data !== "object") return;
    if(e.data.type === "composition:changed" || e.data.type === "layout:changed"){
      window.location.reload();
    }
  });
  var readyMessage = { type: "erased:preview-ready", revision: ' . $doc->revision() . ' };
  if(window.opener) window.opener.postMessage(readyMessage, location.origin);
  if(window.parent && window.parent !== window) window.parent.postMessage(readyMessage, location.origin);
})();
</script>';
    }
}
