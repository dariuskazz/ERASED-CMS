<?php
declare(strict_types=1);

use Erased\Homepage\BlockPlacement;
use Erased\Homepage\HomepagePlacementRepository;

require_once dirname(__DIR__).'/app/Homepage/BlockPlacement.php';
require_once dirname(__DIR__).'/app/Homepage/HomepagePlacementRepository.php';

$table = 'homepage_placement_test_'.bin2hex(random_bytes(6));
$pdo = null;
$driver = '';

try {
    if (extension_loaded('pdo_sqlite')) {
        $driver = 'sqlite';
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = 'CREATE TABLE `'.$table.'` ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            .'instance_id TEXT NOT NULL UNIQUE,'
            .'profile_id TEXT NOT NULL,'
            .'region TEXT NOT NULL,'
            .'block_id TEXT NOT NULL,'
            .'position_index INTEGER NOT NULL DEFAULT 0,'
            .'visible INTEGER NOT NULL DEFAULT 1,'
            .'settings_json TEXT NOT NULL,'
            .'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            .')';
    } else {
        $driver = 'mysql';
        require_once dirname(__DIR__).'/app/bootstrap.php';
        $pdo = db();
        $schema = 'CREATE TABLE `'.$table.'` ('
            .'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            .'instance_id VARCHAR(190) NOT NULL UNIQUE,'
            .'profile_id VARCHAR(100) NOT NULL,'
            .'region VARCHAR(100) NOT NULL,'
            .'block_id VARCHAR(190) NOT NULL,'
            .'position_index INT UNSIGNED NOT NULL DEFAULT 0,'
            .'visible TINYINT(1) NOT NULL DEFAULT 1,'
            .'settings_json LONGTEXT NOT NULL,'
            .'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            .'INDEX profile_region_position (profile_id, region, position_index)'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    $pdo->exec($schema);
    $repository = new HomepagePlacementRepository($pdo, $table);

    $repository->save(new BlockPlacement('news-latest-1', 'news', 'center', 'latest-posts', 0, true, ['limit' => 10]));
    $repository->save(new BlockPlacement('news-latest-2', 'news', 'center', 'latest-posts', 1, false, ['limit' => 4]));
    $repository->save(new BlockPlacement('news-categories', 'news', 'left', 'categories', 0));
    $repository->save(new BlockPlacement('blog-latest', 'blog', 'center', 'latest-posts', 0, true, ['limit' => 6]));

    assert(count($repository->forProfile('news')) === 3);
    assert(count($repository->forProfile('blog')) === 1);
    assert(count($repository->forProfile('news', 'center')) === 2);
    assert(count($repository->forProfile('news', 'center', true)) === 1);
    assert($repository->find('news-latest-1')?->settings()['limit'] === 10);

    $placement = $repository->find('news-latest-2');
    assert($placement instanceof BlockPlacement);
    $repository->save($placement->withVisibility(true)->withSettings(['limit' => 8]));
    assert(count($repository->forProfile('news', 'center', true)) === 2);
    assert($repository->find('news-latest-2')?->settings()['limit'] === 8);

    $repository->reorder('news', 'center', ['news-latest-2', 'news-latest-1']);
    $center = $repository->forProfile('news', 'center');
    assert($center[0]->instanceId() === 'news-latest-2');
    assert($center[0]->position() === 0);
    assert($center[1]->instanceId() === 'news-latest-1');
    assert($center[1]->position() === 1);

    $invalidReorderRejected = false;
    try {
        $repository->reorder('news', 'center', ['news-latest-1']);
    } catch (RuntimeException $error) {
        $invalidReorderRejected = str_contains($error->getMessage(), 'every region instance exactly once');
    }
    assert($invalidReorderRejected === true);
    assert($repository->forProfile('news', 'center')[0]->instanceId() === 'news-latest-2');

    $repository->remove('news-categories');
    assert($repository->find('news-categories') === null);

    $repository->clearProfile('news');
    assert($repository->forProfile('news') === []);
    assert(count($repository->forProfile('blog')) === 1);

    fwrite(STDOUT, "Homepage placement repository smoke test passed.\n");
    fwrite(STDOUT, "Validated profile isolation, duplicate instances, visibility, settings, ordering, rollback, and cleanup using {$driver}.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    if ($pdo instanceof PDO && $driver === 'mysql') {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
        } catch (Throwable) {
        }
    }
}
