<?php
declare(strict_types=1);

use Erased\Website\WebsiteProfileRepository;
use Erased\Website\WebsiteProfileService;
use Erased\Website\WebsiteTypeManager;

$root = dirname(__DIR__);
require_once $root.'/app/Website/WebsiteProfileRepository.php';
require_once $root.'/app/Website/WebsiteTypeManager.php';
require_once $root.'/app/Website/WebsiteProfileService.php';

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE website_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT, type_id TEXT NOT NULL, name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft', is_starter INTEGER NOT NULL DEFAULT 0,
        config_json TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');

    // Use the real registry.json - the actual list of 11 planned profiles this feature is meant to seed.
    $repository = new WebsiteProfileRepository($pdo);
    $types = new WebsiteTypeManager($root.'/website-types/registry.json');
    $service = new WebsiteProfileService($repository, $types, $pdo);

    // --- seedStarterProfiles() creates one starter per registry type, and is idempotent ---
    $seeded = $service->seedStarterProfiles();
    $typeCount = count($types->all());
    if (count($seeded) !== $typeCount) {
        throw new RuntimeException("Expected {$typeCount} starter profiles seeded, got ".count($seeded));
    }
    $againSeeded = $service->seedStarterProfiles();
    if ($againSeeded !== []) {
        throw new RuntimeException('seedStarterProfiles() re-created starters on a second call - not idempotent.');
    }
    if (count($repository->all()) !== $typeCount) {
        throw new RuntimeException('Re-running seedStarterProfiles() changed the total profile count.');
    }

    // --- activate() with no prior live config creates no snapshot ---
    $blog = null;
    foreach ($repository->byStatus('draft') as $profile) {
        if ($profile['type_id'] === 'blog') { $blog = $profile; break; }
    }
    if ($blog === null) {
        throw new RuntimeException('Expected a seeded "blog" starter profile.');
    }
    $firstSnapshot = $service->activate((int)$blog['id']);
    if ($firstSnapshot !== null) {
        throw new RuntimeException('First-ever activation should not create a snapshot (there was no prior live config).');
    }
    if ($repository->findActive() === null || $repository->findActive()['id'] !== $blog['id']) {
        throw new RuntimeException('activate() did not mark the target profile active.');
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute(['site_name']);
    if ($stmt->fetchColumn() !== 'Blog') {
        throw new RuntimeException('activate() did not write the profile config into the settings table.');
    }

    // --- activate() a second profile: snapshots the first, applies the second, and re-marks status ---
    $news = null;
    foreach ($repository->byStatus('draft') as $profile) {
        if ($profile['type_id'] === 'news') { $news = $profile; break; }
    }
    if ($news === null) {
        throw new RuntimeException('Expected a seeded "news" starter profile.');
    }
    $secondSnapshot = $service->activate((int)$news['id']);
    if ($secondSnapshot === null) {
        throw new RuntimeException('Activating a second profile over a live one should create a snapshot.');
    }
    $snapshotRow = $repository->find($secondSnapshot);
    if ($snapshotRow === null || $snapshotRow['status'] !== 'archived' || $snapshotRow['config']['site_name'] !== 'Blog') {
        throw new RuntimeException('The snapshot did not preserve the previously active configuration.');
    }
    if ($repository->find((int)$blog['id'])['status'] !== 'draft') {
        throw new RuntimeException('The previously active profile was not demoted back to draft.');
    }
    if ($repository->findActive()['id'] !== $news['id']) {
        throw new RuntimeException('activate() did not switch the active profile to the new target.');
    }
    $stmt->execute(['site_name']);
    if ($stmt->fetchColumn() !== 'News Portal') {
        throw new RuntimeException('activate() did not apply the second profile\'s config to live settings.');
    }

    // --- rollback = activating the snapshot again restores the original config ---
    $service->activate($secondSnapshot);
    $stmt->execute(['site_name']);
    if ($stmt->fetchColumn() !== 'Blog') {
        throw new RuntimeException('Activating a snapshot did not roll the live configuration back correctly.');
    }
    if ($repository->find($secondSnapshot)['status'] !== 'active') {
        throw new RuntimeException('The restored snapshot was not itself marked active.');
    }

    // --- activate() rejects an unknown profile id ---
    $rejected = false;
    try {
        $service->activate(999999);
    } catch (RuntimeException) {
        $rejected = true;
    }
    if (!$rejected) {
        throw new RuntimeException('activate() did not reject a non-existent profile id.');
    }

    fwrite(STDOUT, "Website profile service smoke test passed.\n");
    fwrite(STDOUT, "Validated idempotent starter seeding from the real registry, activation writing curated settings, automatic snapshotting, status demotion, and snapshot-based rollback.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
