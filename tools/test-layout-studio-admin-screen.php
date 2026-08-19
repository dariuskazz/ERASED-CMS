<?php
declare(strict_types=1);

use Erased\Homepage\BlockDefinition;
use Erased\Homepage\BlockPlacement;
use Erased\LayoutStudio\LayoutStudioAdminScreen;

$root=dirname(__DIR__);
require_once $root.'/app/Homepage/BlockDefinition.php';
require_once $root.'/app/Homepage/BlockPlacement.php';
require_once $root.'/app/LayoutStudio/LayoutStudioAdminScreen.php';

try{
 $screen=new LayoutStudioAdminScreen();
 $state=[
  'profile_id'=>'news','regions'=>['left','center','right'],
  'canvas'=>[
   'left'=>[new BlockPlacement('news-categories','news','left','categories',0,true,['limit'=>8])],
   'center'=>[new BlockPlacement('news-latest','news','center','latest-posts',0)],
   'right'=>[],
  ],
  'palette'=>['content'=>[
   new BlockDefinition('latest-posts','erased.posts','Latest Posts','content','homepage.latest-posts',[],'Newest published posts'),
   new BlockDefinition('categories','erased.posts','Categories','content','homepage.categories',[],'Category navigation'),
  ]],
 ];
 $html=$screen->render($state);
 foreach([
  'data-layout-studio','data-profile-id="news"','data-layout-search','data-layout-status',
  'data-layout-undo','data-layout-redo','Undo','Redo','Save draft','Draft loaded',
  'Detailed Structure Preview','Draft layout map','data-structure-frame','data-structure-viewport',
  'data-structure-device="desktop"','data-structure-device="tablet"','data-structure-device="mobile"',
  'data-structure-summary','layout-structure__legend','renderStructure','layout:restored',
  'data-layout-region="left"','data-layout-region="center"','data-layout-region="right"',
  'data-layout-dropzone="left"','data-layout-instance="news-categories"','data-layout-instance="news-latest"',
  'data-layout-region-name="left"','data-layout-visible="1"','data-layout-settings="{&quot;limit&quot;:8}"',
  'is-selected','is-dragging','is-drop-target','layout:changed',
  'Latest Posts','Categories','Inspector',
 ] as $needle){if(!str_contains($html,$needle))throw new RuntimeException("Rendered admin screen is missing expected marker: {$needle}");}
 if(str_contains($html,'<script>alert('))throw new RuntimeException('Rendered admin screen contains unsafe script content.');
 $escaped=$screen->render(['profile_id'=>'safe-profile','regions'=>['center'],'canvas'=>['center'=>[]],'palette'=>['content'=>[new BlockDefinition('safe-block','safe.package','<Unsafe>','content','safe.service',[],'"quoted"')]]]);
 if(!str_contains($escaped,'&lt;Unsafe&gt;')||!str_contains($escaped,'&quot;quoted&quot;'))throw new RuntimeException('Rendered admin screen did not escape block metadata.');
 fwrite(STDOUT,"Layout Studio detailed structure preview smoke test passed.\n");
 fwrite(STDOUT,"Validated device modes, region proportions, block depth hints, visibility legend, live structure updates, history controls, and HTML escaping.\n");
}catch(Throwable $error){fwrite(STDERR,'ERROR: '.$error->getMessage()."\n");exit(1);}
