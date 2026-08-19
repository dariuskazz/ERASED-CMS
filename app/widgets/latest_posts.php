<?php
declare(strict_types=1);
if (!function_exists('erased_latest_posts_widget')) {
function erased_latest_posts_widget(int $defaultLimit=6, $excludeIds=null): string {
 $showAll=$defaultLimit===0;$limit=$showAll?0:max(1,min(100,(int)setting('latest_posts_limit',(string)$defaultLimit)));
 $showThumb=setting('latest_posts_show_thumbnails','1')==='1';
 $thumbSize=(int)setting('latest_posts_thumbnail_size','48'); if(!in_array($thumbSize,[32,48,64],true))$thumbSize=48;
 $showDate=setting('latest_posts_show_date','1')==='1'; $showAuthor=setting('latest_posts_show_author','0')==='1'; $showExcerpt=setting('latest_posts_show_excerpt','0')==='1';
 $style=setting('latest_posts_style','compact'); if(!in_array($style,['compact','cards','minimal'],true))$style='compact';
 $sql="SELECT content.*,users.display_name AS author_name,users.username AS author_username FROM content LEFT JOIN users ON users.id=content.author_id WHERE content.type='post' AND content.status='published' AND (content.publish_at IS NULL OR content.publish_at<=NOW())"; $args=[];
 $excludeList=is_array($excludeIds)?array_values(array_filter(array_map('intval',$excludeIds),fn($v)=>$v>0)):((int)$excludeIds>0?[(int)$excludeIds]:[]);if($excludeList){$sql.=' AND content.id NOT IN ('.implode(',',array_fill(0,count($excludeList),'?')).')';$args=array_merge($args,$excludeList);} $sql.=' ORDER BY COALESCE(content.publish_at,content.created_at,content.updated_at) DESC,content.updated_at DESC';if(!$showAll)$sql.=' LIMIT '.(int)$limit;
 $q=db()->prepare($sql);$q->execute($args);$rows=$q->fetchAll();$items='';
 foreach($rows as $row){$thumb='';if($showThumb&&$style!=='minimal'){if(!empty($row['featured_media_id'])&&($m=media_by_id((int)$row['featured_media_id']))&&!str_starts_with((string)$m['mime_type'],'video/'))$thumb='<img class="latest-posts-widget-thumb" style="--latest-thumb:'.$thumbSize.'px" src="'.media_url($m).'" alt="'.e((string)($m['alt_text']??'')).'" loading="lazy">';else $thumb='<span class="latest-posts-widget-thumb latest-posts-widget-thumb-empty" style="--latest-thumb:'.$thumbSize.'px" aria-hidden="true"></span>';}
  $meta=[];if($showDate){$d=!empty($row['publish_at'])?$row['publish_at']:($row['created_at']??$row['updated_at']??'now');$meta[]=e(date(setting('date_format','Y-m-d'),strtotime((string)$d)));}if($showAuthor){$a=trim((string)($row['author_name']??''))?:trim((string)($row['author_username']??''));if($a!=='')$meta[]=e($a);} $metaHtml=$meta?'<p class="latest-posts-widget-meta">'.implode(' · ',$meta).'</p>':'';
  $excerptHtml='';if($showExcerpt&&$style!=='minimal'){$x=trim((string)($row['excerpt']??''));if($x==='')$x=trim(strip_tags((string)($row['body']??'')));if(mb_strlen($x)>110)$x=mb_substr($x,0,107).'…';if($x!=='')$excerptHtml='<p class="latest-posts-widget-excerpt">'.e($x).'</p>';}
  $items.='<article class="latest-posts-widget-item">'.$thumb.'<div class="latest-posts-widget-copy"><h3><a href="/'.rawurlencode((string)$row['slug']).'">'.e((string)$row['title']).'</a></h3>'.$metaHtml.$excerptHtml.'</div></article>';
 }
 if($items==='')$items='<p class="homepage-empty">No posts yet.</p>';
 return '<section class="card latest-posts-widget latest-posts-style-'.e($style).'"><h2 class="homepage-section-title">Latest posts</h2><div class="latest-posts-widget-list">'.$items.'</div></section>';
}}
