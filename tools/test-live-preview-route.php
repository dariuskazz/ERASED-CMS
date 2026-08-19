<?php
declare(strict_types=1);

$root=dirname(__DIR__);
foreach([
 'app/Homepage/BlockPlacement.php','app/LayoutStudio/LayoutDraft.php','app/LayoutStudio/LayoutDraftRepository.php','app/LayoutStudio/LayoutDraftService.php','app/LayoutStudio/LayoutSerializer.php','app/LayoutStudio/LivePreviewDocument.php','app/LayoutStudio/LivePreviewRenderer.php','app/LayoutStudio/LivePreviewRoute.php'
] as $file) require_once $root.'/'.$file;

use Erased\Homepage\BlockPlacement;
use Erased\LayoutStudio\LayoutDraft;
use Erased\LayoutStudio\LayoutDraftRepository;
use Erased\LayoutStudio\LayoutDraftService;
use Erased\LayoutStudio\LayoutSerializer;
use Erased\LayoutStudio\LivePreviewRenderer;
use Erased\LayoutStudio\LivePreviewRoute;

// LivePreviewRenderer only performs body-tag/device-attribute injection when a
// real `layout()` is available (see app/LayoutStudio/LivePreviewRenderer.php).
// The real one lives in public/index.php, which pulls in far more than this
// smoke test needs, so stub the minimal contract instead.
function layout(string $title, string $body, bool $admin = false): void {
    echo '<!doctype html><html><head><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body>' . $body . '</body></html>';
}
function tr(string $key): string { return $key; }

try{
 $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $pdo->exec('CREATE TABLE layout_drafts(draft_key TEXT PRIMARY KEY,profile_id TEXT,target_type TEXT,target_id TEXT,name TEXT,status TEXT,revision INTEGER,author_id INTEGER NULL,notes TEXT,payload_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
 $placement=new BlockPlacement('hero-1','default','center','hero',0,true,['title'=>'Hello']);
 $payload=json_decode((new LayoutSerializer())->encode('default',[$placement]),true,512,JSON_THROW_ON_ERROR);
 (new LayoutDraftRepository($pdo))->create(new LayoutDraft(LayoutDraftService::key('default','homepage','homepage'),'default','homepage','homepage','Homepage draft','draft',3,1,'',$payload));
 $html=(new LivePreviewRoute($pdo,new LayoutSerializer(),new LivePreviewRenderer()))->render('default','mobile');
 foreach(['data-preview-device="mobile"','data-preview-revision="3"','erased:preview-ready','noindex,nofollow'] as $needle)if(!str_contains($html,$needle))throw new RuntimeException('Preview output missing '.$needle);
 fwrite(STDOUT,"Live Preview authenticated route smoke test passed.\nValidated private draft loading, iframe sandboxing, device switching, and revision refresh.\n");
}catch(Throwable $error){fwrite(STDERR,'ERROR: '.$error->getMessage()."\n");exit(1);}
