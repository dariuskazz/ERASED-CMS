<?php
declare(strict_types=1);

$root=dirname(__DIR__);
foreach([
    'app/Homepage/BlockPlacement.php',
    'app/Homepage/HomepagePlacementRepository.php',
    'app/LayoutStudio/LayoutSerializer.php',
    'app/LayoutStudio/LayoutDraft.php',
    'app/LayoutStudio/LayoutDraftRepository.php',
    'app/LayoutStudio/LayoutDraftService.php',
    'app/LayoutStudio/LayoutDraftPublisher.php',
] as $file) require_once $root.'/'.$file;

use Erased\Homepage\BlockPlacement;
use Erased\Homepage\HomepagePlacementRepository;
use Erased\LayoutStudio\LayoutDraft;
use Erased\LayoutStudio\LayoutDraftPublisher;
use Erased\LayoutStudio\LayoutDraftRepository;
use Erased\LayoutStudio\LayoutSerializer;

try {
    $pdo=new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE layout_drafts (draft_key TEXT PRIMARY KEY,profile_id TEXT NOT NULL,target_type TEXT NOT NULL,target_id TEXT NOT NULL,name TEXT NOT NULL,status TEXT NOT NULL,revision INTEGER NOT NULL,author_id INTEGER NULL,notes TEXT NOT NULL,payload_json TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE homepage_block_placements (id INTEGER PRIMARY KEY AUTOINCREMENT,instance_id TEXT NOT NULL UNIQUE,profile_id TEXT NOT NULL,region TEXT NOT NULL,block_id TEXT NOT NULL,position_index INTEGER NOT NULL DEFAULT 0,visible INTEGER NOT NULL DEFAULT 1,settings_json TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

    $serializer=new LayoutSerializer();
    $placements=[
        new BlockPlacement('hero-a','default','center','hero',0,true,['title'=>'Published hero']),
        new BlockPlacement('tags-a','default','left','popular_tags',1,false,[]),
        new BlockPlacement('cta-a','default','right','cta',2,true,['title'=>'Published CTA']),
    ];
    $payload=json_decode($serializer->encode('default',$placements),true,512,JSON_THROW_ON_ERROR);
    $draft=new LayoutDraft('default.homepage.homepage','default','homepage','homepage','Homepage draft','draft',4,null,'',$payload);
    (new LayoutDraftRepository($pdo))->create($draft);

    $published=new HomepagePlacementRepository($pdo);
    $published->save(new BlockPlacement('old','default','center','latest_posts',0,true,[]));

    $result=(new LayoutDraftPublisher($pdo,new LayoutDraftRepository($pdo),$serializer,$published))->publish('default');
    if($result!==['revision'=>4,'published'=>3]) throw new RuntimeException('Publish result metadata is incorrect.');

    $rows=$published->forProfile('default');
    if(count($rows)!==3) throw new RuntimeException('Published placement count is incorrect.');
    $byId=[];foreach($rows as $row)$byId[$row->instanceId()]=$row;
    if(isset($byId['old'])) throw new RuntimeException('Previous published placement was not replaced.');
    if(($byId['hero-a']??null)?->region()!=='center') throw new RuntimeException('Hero region was not published.');
    if(($byId['cta-a']??null)?->region()!=='right') throw new RuntimeException('CTA region was not published.');
    if(($byId['tags-a']??null)?->visible()!==false) throw new RuntimeException('Hidden state was not preserved.');
    if((($byId['hero-a']??null)?->settings()['title']??'')!=='Published hero') throw new RuntimeException('Placement settings were not preserved.');

    fwrite(STDOUT,"Layout Studio publish pipeline smoke test passed.\n");
    fwrite(STDOUT,"Validated draft promotion, replacement, regions, visibility, revision metadata, and settings persistence.\n");
} catch(Throwable $error){
    fwrite(STDERR,'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
