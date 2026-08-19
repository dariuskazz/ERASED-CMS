<?php
declare(strict_types=1);

use Erased\LayoutStudio\LayoutDraft;
use Erased\LayoutStudio\LayoutDraftRepository;

$root=dirname(__DIR__);
require_once $root.'/app/LayoutStudio/LayoutDraft.php';
require_once $root.'/app/LayoutStudio/LayoutDraftRepository.php';

$table='layout_drafts_test_'.bin2hex(random_bytes(5));
$pdo=null;$driver='';
try{
 if(extension_loaded('pdo_sqlite')){
  $driver='sqlite';$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $pdo->exec('CREATE TABLE `'.$table.'` (id INTEGER PRIMARY KEY AUTOINCREMENT,draft_key TEXT NOT NULL UNIQUE,profile_id TEXT NOT NULL,target_type TEXT NOT NULL,target_id TEXT NOT NULL,name TEXT NOT NULL,status TEXT NOT NULL,revision INTEGER NOT NULL,author_id INTEGER NULL,notes TEXT NULL,payload_json TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
 }else{
  $driver='mysql';require_once $root.'/app/bootstrap.php';$pdo=db();
  $pdo->exec('CREATE TABLE `'.$table.'` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,draft_key VARCHAR(190) NOT NULL UNIQUE,profile_id VARCHAR(190) NOT NULL,target_type VARCHAR(80) NOT NULL,target_id VARCHAR(190) NOT NULL,name VARCHAR(190) NOT NULL,status VARCHAR(40) NOT NULL,revision INT UNSIGNED NOT NULL,author_id BIGINT UNSIGNED NULL,notes TEXT NULL,payload_json LONGTEXT NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 }
 $repo=new LayoutDraftRepository($pdo,$table);
 $draft=new LayoutDraft('homepage-news','news','homepage','default','News homepage','draft',1,7,'First draft',['regions'=>['center'=>[['block_id'=>'latest-posts']]]]);
 $repo->create($draft);
 $loaded=$repo->find('homepage-news');
 assert($loaded instanceof LayoutDraft);
 assert($loaded->revision()===1);
 assert($loaded->authorId()===7);
 assert($loaded->payload()['regions']['center'][0]['block_id']==='latest-posts');

 $changed=new LayoutDraft('homepage-news','news','homepage','default','News homepage','draft',1,7,'Moved block',['regions'=>['left'=>[['block_id'=>'latest-posts']]]]);
 $saved=$repo->save($changed,1);
 assert($saved->revision()===2);
 assert($repo->find('homepage-news')?->revision()===2);
 assert($repo->find('homepage-news')?->notes()==='Moved block');

 $stale=false;
 try{$repo->save($changed,1);}catch(RuntimeException $e){$stale=str_contains($e->getMessage(),'another session');}
 assert($stale===true);

 $repo->delete('homepage-news');
 assert($repo->find('homepage-news')===null);
 fwrite(STDOUT,"Layout draft repository smoke test passed.\n");
 fwrite(STDOUT,"Validated create, JSON round-trip, metadata, optimistic revisions, stale-write rejection, and deletion using {$driver}.\n");
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}finally{if($pdo instanceof PDO&&$driver==='mysql'){try{$pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');}catch(Throwable){}}}
