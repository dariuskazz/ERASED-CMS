<?php
declare(strict_types=1);

use Erased\Packages\PackageDependencyResolver;
use Erased\Packages\PackageManifest;
use Erased\Packages\PackageMigrationRunner;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Packages/PackageDependencyResolver.php';
require_once dirname(__DIR__).'/app/Packages/PackageMigrationRunner.php';

try {
    // --- PackageDependencyResolver::describeDependencies() ---
    $target = new PackageManifest([
        'id' => 'erased.details-view-test',
        'type' => 'module',
        'name' => 'Details View Test',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Package details/dependency viewer smoke test.',
        'dependencies' => ['erased.satisfied-dep >=1.0.0', 'erased.mismatched-dep ^2.0.0', 'erased.missing-dep'],
    ]);

    $resolver = new PackageDependencyResolver();
    $described = $resolver->describeDependencies($target, [
        'erased.satisfied-dep' => '1.2.0',
        'erased.mismatched-dep' => '1.0.0',
    ]);

    $byId = [];
    foreach ($described as $entry) {
        $byId[$entry['id']] = $entry;
    }

    if (count($described) !== 3) {
        throw new RuntimeException('Expected 3 described dependencies, got '.count($described));
    }
    if ($byId['erased.satisfied-dep']['status'] !== 'satisfied' || $byId['erased.satisfied-dep']['installed_version'] !== '1.2.0') {
        throw new RuntimeException('A satisfied dependency was not described as satisfied.');
    }
    if ($byId['erased.mismatched-dep']['status'] !== 'version-mismatch' || $byId['erased.mismatched-dep']['installed_version'] !== '1.0.0') {
        throw new RuntimeException('An installed-but-mismatched dependency was not flagged as a version mismatch.');
    }
    if ($byId['erased.missing-dep']['status'] !== 'missing' || $byId['erased.missing-dep']['installed_version'] !== null) {
        throw new RuntimeException('A not-installed dependency was not described as missing.');
    }

    $noDeps = new PackageManifest([
        'id' => 'erased.details-view-no-deps',
        'type' => 'module',
        'name' => 'No Deps',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Has no dependencies.',
    ]);
    if ($resolver->describeDependencies($noDeps, []) !== []) {
        throw new RuntimeException('A package with no dependencies should describe none.');
    }

    // --- PackageMigrationRunner::appliedMigrationsWithTimestamps() ---
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $workspace = sys_get_temp_dir().'/erased-package-details-view-'.bin2hex(random_bytes(6));
    $migrationsDir = $workspace.'/migrations';
    mkdir($migrationsDir, 0750, true);
    file_put_contents($migrationsDir.'/20260101_001_first.sql', 'CREATE TABLE first_table (id INTEGER PRIMARY KEY);');
    file_put_contents($migrationsDir.'/20260102_002_second.sql', 'CREATE TABLE second_table (id INTEGER PRIMARY KEY);');

    $runner = new PackageMigrationRunner($pdo);

    if ($runner->appliedMigrationsWithTimestamps('erased.details-view-test') !== []) {
        throw new RuntimeException('A package with no applied migrations should report none.');
    }

    $runner->run('erased.details-view-test', $workspace);
    $applied = $runner->appliedMigrationsWithTimestamps('erased.details-view-test');

    if (count($applied) !== 2) {
        throw new RuntimeException('Expected 2 applied migrations, got '.count($applied));
    }
    if ($applied[0]['migration'] !== '20260101_001_first.sql' || $applied[1]['migration'] !== '20260102_002_second.sql') {
        throw new RuntimeException('Applied migrations were not returned in order.');
    }
    foreach ($applied as $entry) {
        if (!isset($entry['applied_at']) || trim((string)$entry['applied_at']) === '') {
            throw new RuntimeException('An applied migration is missing its timestamp.');
        }
    }

    // Isolated per package id, matching PackageMigrationRunner::run()'s own guarantee.
    if ($runner->appliedMigrationsWithTimestamps('erased.some-other-package') !== []) {
        throw new RuntimeException('Applied migrations leaked across package ids.');
    }

    fwrite(STDOUT, "Package details/dependency viewer smoke test passed.\n");
    fwrite(STDOUT, "Validated dependency status classification (satisfied, version-mismatch, missing) and applied-migration history with timestamps, ordered and per-package isolated.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    if (isset($workspace) && is_dir($workspace)) {
        exec('rm -rf '.escapeshellarg($workspace));
    }
}
