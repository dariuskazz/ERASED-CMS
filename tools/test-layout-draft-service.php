<?php
declare(strict_types=1);

use Erased\Homepage\BlockPlacement;
use Erased\LayoutStudio\LayoutCanvas;
use Erased\LayoutStudio\LayoutDocumentValidator;
use Erased\LayoutStudio\LayoutDraftRepository;
use Erased\LayoutStudio\LayoutDraftService;
use Erased\LayoutStudio\LayoutSerializer;

$root = dirname(__DIR__);
require_once $root.'/app/Homepage/BlockPlacement.php';
require_once $root.'/app/LayoutStudio/LayoutCanvas.php';
require_once $root.'/app/LayoutStudio/LayoutSerializer.php';
require_once $root.'/app/LayoutStudio/LayoutDocumentValidator.php';
require_once $root.'/app/LayoutStudio/LayoutDraft.php';
require_once $root.'/app/LayoutStudio/LayoutDraftRepository.php';
require_once $root.'/app/LayoutStudio/LayoutDraftService.php';

try {
    if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite is required.');
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE layout_drafts (id INTEGER PRIMARY KEY AUTOINCREMENT,draft_key TEXT NOT NULL UNIQUE,profile_id TEXT NOT NULL,target_type TEXT NOT NULL,target_id TEXT NOT NULL,name TEXT NOT NULL,status TEXT NOT NULL,revision INTEGER NOT NULL,author_id INTEGER NULL,notes TEXT NOT NULL DEFAULT "",payload_json TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

    $validator = new class implements LayoutDocumentValidator {
        public int $calls = 0;
        public function assertValid(string $profileId, array $placements, LayoutCanvas $canvas): void {
            $this->calls++;
            foreach ($placements as $placement) {
                if (!$placement instanceof BlockPlacement || $placement->profileId() !== $profileId) throw new RuntimeException('Invalid placement.');
            }
            $canvas->arrange($placements);
        }
    };

    $service = new LayoutDraftService(new LayoutDraftRepository($pdo), new LayoutSerializer(), $validator);
    $canvas = new LayoutCanvas(['left','center','right']);
    $published = [new BlockPlacement('news-latest','news','center','latest-posts',0,true,['limit'=>6])];

    $draft = $service->createFromPublished('news','homepage','default',$published,$canvas,7,'News redesign','Initial draft');
    assert($draft->revision() === 1);
    assert($draft->key() === 'news.homepage.default');
    assert(count($service->placements($draft)) === 1);
    assert($service->createFromPublished('news','homepage','default',$published,$canvas)->revision() === 1);

    $changed = [new BlockPlacement('news-latest','news','right','latest-posts',0,true,['limit'=>10])];
    $saved = $service->autosave('news','homepage','default',$changed,$canvas,1,7,'News redesign','Moved right');
    assert($saved->revision() === 2);
    assert($service->placements($saved)[0]->region() === 'right');
    assert($service->load('news','homepage','default')?->notes() === 'Moved right');

    $stale = false;
    try { $service->autosave('news','homepage','default',$published,$canvas,1); }
    catch (RuntimeException $error) { $stale = str_contains($error->getMessage(),'another session'); }
    assert($stale === true);
    assert($validator->calls >= 3);

    $service->delete('news','homepage','default');
    assert($service->load('news','homepage','default') === null);

    fwrite(STDOUT, "Layout draft service smoke test passed.\n");
    fwrite(STDOUT, "Validated create-from-published, deterministic targets, validation, autosave revisions, stale-write rejection, round-trip loading, and deletion using sqlite.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
