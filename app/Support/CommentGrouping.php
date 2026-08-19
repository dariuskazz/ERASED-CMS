<?php
declare(strict_types=1);
namespace Erased\Support;
final class CommentGrouping {
    /**
     * Groups already-fetched comment rows (each expected to carry content_id,
     * title, slug, created_at from the comments+content join) by their post,
     * for display only - never for the SQL selection/LIMIT itself, since
     * sorting by post before a row cap is applied could let one busy post's
     * older comments crowd out other posts' genuinely-recent ones.
     * @param list<array<string,mixed>> $rows already ordered created_at DESC
     * @return list<array{title:string,slug:?string,latest:string,rows:list<array<string,mixed>>}>
     */
    public static function byPost(array $rows): array {
        $groups=[];
        foreach($rows as $r){
            $orphan=empty($r['slug']);
            $key=$orphan?'__orphaned__':(string)$r['content_id'];
            if(!isset($groups[$key])){
                $groups[$key]=['title'=>$orphan?'Comments on deleted content':(string)$r['title'],'slug'=>$orphan?null:(string)$r['slug'],'latest'=>(string)$r['created_at'],'rows'=>[]];
            }
            $groups[$key]['rows'][]=$r;
        }
        usort($groups,fn($a,$b)=>$b['latest']<=>$a['latest']);
        return $groups;
    }
}
