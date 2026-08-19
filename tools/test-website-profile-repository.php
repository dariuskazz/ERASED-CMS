<?php
declare(strict_types=1);

use Erased\Website\WebsiteProfileRepository;

require_once dirname(__DIR__).'/app/Website/WebsiteProfileRepository.php';

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE website_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT, type_id TEXT NOT NULL, name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft', is_starter INTEGER NOT NULL DEFAULT 0,
        config_json TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");

    $repo = new WebsiteProfileRepository($pdo);

    // --- create() and find() round-trip the config array ---
    $id = $repo->create('blog', 'My Blog', ['site_name' => 'My Blog', 'theme_accent' => '#2dfc98'], isStarter: true, status: 'draft');
    $row = $repo->find($id);
    if ($row === null || $row['name'] !== 'My Blog' || $row['config']['site_name'] !== 'My Blog') {
        throw new RuntimeException('create()/find() did not round-trip name and config correctly.');
    }
    if ($row['is_starter'] !== true || $row['status'] !== 'draft') {
        throw new RuntimeException('create() did not persist is_starter/status correctly.');
    }

    // --- updateConfig() changes name and config, not status ---
    $repo->updateConfig($id, 'My Renamed Blog', ['site_name' => 'Renamed']);
    $row = $repo->find($id);
    if ($row['name'] !== 'My Renamed Blog' || $row['config']['site_name'] !== 'Renamed' || $row['status'] !== 'draft') {
        throw new RuntimeException('updateConfig() did not update as expected.');
    }

    // --- setStatus() and findActive() ---
    if ($repo->findActive() !== null) {
        throw new RuntimeException('findActive() should be null before any profile is active.');
    }
    $repo->setStatus($id, 'active');
    $active = $repo->findActive();
    if ($active === null || $active['id'] !== $id) {
        throw new RuntimeException('findActive() did not find the newly activated profile.');
    }

    // --- existsForType() only counts starters ---
    if (!$repo->existsForType('blog')) {
        throw new RuntimeException('existsForType() should be true for a starter profile of that type.');
    }
    $repo->create('news', 'Custom News Thing', [], isStarter: false, status: 'draft');
    if ($repo->existsForType('news')) {
        throw new RuntimeException('existsForType() should ignore non-starter profiles.');
    }

    // --- byStatus() filters correctly ---
    $repo->create('shop', 'Shop Snapshot', [], isStarter: false, status: 'archived');
    $drafts = $repo->byStatus('draft');
    $archived = $repo->byStatus('archived');
    if (count($drafts) !== 1 || count($archived) !== 1) {
        throw new RuntimeException('byStatus() did not correctly partition profiles: '.count($drafts).' drafts, '.count($archived).' archived.');
    }

    // --- all() returns every profile, active first ---
    $all = $repo->all();
    if (count($all) !== 3 || $all[0]['id'] !== $id) {
        throw new RuntimeException('all() did not return every profile with the active one first.');
    }

    fwrite(STDOUT, "Website profile repository smoke test passed.\n");
    fwrite(STDOUT, "Validated create/find/updateConfig round-tripping, status transitions, findActive, existsForType (starters only), and byStatus filtering.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
