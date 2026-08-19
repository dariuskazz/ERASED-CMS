<?php
declare(strict_types=1);

namespace Erased\Homepage;

final class PublishedHomepageRenderer
{
    /**
     * Render published placements through the existing public block HTML map.
     * Returns null when no published placement set exists so legacy settings remain the fallback.
     *
     * @param list<BlockPlacement> $placements
     * @param ?callable(BlockPlacement):?string $pluginBlockResolver called for any
     *   placement whose block id isn't a key in $blocks (i.e. not one of the
     *   legacy built-ins) - returns rendered HTML for a package-provided
     *   block, or null to skip it (unknown id, capability gated, or the
     *   resolver's own render call failed). Never allowed to throw out of
     *   this method - a broken plugin block must not break the whole page.
     */
    public function render(array $placements,array $config,array $blocks,?callable $pluginBlockResolver=null): ?string
    {
        if($placements===[]) return null;

        // Column layout (e.g. two placements side by side) has no field of
        // its own on BlockPlacement - the editor records it as container_id/
        // column_count in settings() instead. Consecutive placements sharing
        // a container_id (they're always saved adjacently) get grouped and
        // wrapped in a matching CSS grid; anything without one renders in
        // the flat vertical stack exactly as before.
        /** @var array<string,list<array{container_id:?string,items:list<string>,hide_mobile:bool,hide_desktop:bool,style_attr:string}>> */
        $regionGroups=['left'=>[],'center'=>[],'right'=>[]];
        foreach($placements as $placement){
            if(!$placement->visible()) continue;
            $settings=$placement->settings();
            if(function_exists('erased_placement_within_schedule')&&!erased_placement_within_schedule($settings)) continue;
            $blockId=$placement->blockId();
            if(isset($blocks[$blockId])){
                $html=(string)$blocks[$blockId];
            }elseif($pluginBlockResolver!==null){
                try{$resolved=$pluginBlockResolver($placement);}catch(\Throwable){$resolved=null;}
                if(!is_string($resolved)||trim($resolved)==='') continue;
                $html=$resolved;
            }else{
                continue;
            }
            $region=$placement->region();
            if(!in_array($region,$config['active_regions']??['center'],true)) $region='center';

            $containerId=isset($settings['container_id'])&&is_string($settings['container_id'])?$settings['container_id']:null;

            $lastIndex=count($regionGroups[$region])-1;
            if($containerId!==null&&$lastIndex>=0&&($regionGroups[$region][$lastIndex]['container_id']??null)===$containerId){
                $regionGroups[$region][$lastIndex]['items'][]=$html;
            }else{
                $styleAttr=function_exists('erased_placement_style_attr')?erased_placement_style_attr($settings):'';
                $regionGroups[$region][]=['container_id'=>$containerId,'items'=>[$html],'hide_mobile'=>!empty($settings['hide_mobile']),'hide_desktop'=>!empty($settings['hide_desktop']),'style_attr'=>$styleAttr];
            }
        }

        $regions=['left'=>'','center'=>'','right'=>''];
        foreach($regionGroups as $region=>$groups){
            foreach($groups as $group){
                $count=count($group['items']);
                $deviceClass=($group['hide_mobile']?' hp-hide-mobile':'').($group['hide_desktop']?' hp-hide-desktop':'');
                if($count>1){
                    $regions[$region].='<div class="homepage-container-grid homepage-container-grid-'.min(4,$count).$deviceClass.'"'.$group['style_attr'].'>'.implode('',$group['items']).'</div>';
                }elseif($deviceClass!==''||$group['style_attr']!==''){
                    $regions[$region].='<div class="homepage-section-wrap'.$deviceClass.'"'.$group['style_attr'].'>'.($group['items'][0]??'').'</div>';
                }else{
                    $regions[$region].=$group['items'][0]??'';
                }
            }
        }

        if(trim($regions['center'])===''){
            $regions['center']='<section class="card"><p class="homepage-empty">No homepage content is enabled.</p></section>';
        }

        $markup='';$visibleRegions=[];
        foreach(($config['active_regions']??['center']) as $region){
            $html=trim($regions[$region]??'');
            if($html===''&&!($config['show_empty']??false)) continue;
            if($html==='') $html='<section class="card"><p class="homepage-empty">Empty region</p></section>';
            $tag=$region==='center'?'div':'aside';
            $role=$region==='center'?' role="main"':'';
            $markup.='<'.$tag.' class="homepage-region homepage-'.$region.'"'.$role.'>'.$html.'</'.$tag.'>';
            $visibleRegions[]=$region;
        }

        $classCount=max(1,count($visibleRegions));
        $regionClasses='';
        foreach($visibleRegions as $region){
            $regionClasses.=' homepage-has-'.preg_replace('/[^a-z0-9_-]/','',(string)$region);
        }
        $widthStyle=function_exists('homepage_studio_width_style')?homepage_studio_width_style($config,$visibleRegions):'';
        $publicCss=function_exists('homepage_studio_public_css')?homepage_studio_public_css():'';
        $escape=static fn(string $value): string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

        return $publicCss
            .'<div class="homepage-shell">'
            .'<section class="homepage-layout-three homepage-columns-'.$classCount.$regionClasses.' website-style-'.$escape((string)($config['style']??'standard')).'" style="'.$escape($widthStyle).'">'
            .$markup
            .'</section></div>';
    }
}
