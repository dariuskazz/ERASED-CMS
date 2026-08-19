<?php
declare(strict_types=1);

/**
 * Dev/deploy helper: builds a real core-update release ZIP from the current
 * working tree, for testing (or actually shipping) the core-update feature.
 *
 * A staged update is a *whole-directory swap* per top-level path
 * (CoreCodeInstaller renames the entire "app", "database", etc. directory
 * into place, it does not merge) - so the ZIP must contain the *complete*
 * contents of every whitelisted path it includes, not just the files that
 * changed. This mirrors how a real release archive works: it ships the full
 * state of the codebase at that version, not a diff.
 *
 * Usage:
 *   php tools/build-core-update-zip.php <target-version> <output.zip> [--add-migration=name]
 *
 * --add-migration=NAME writes a trivial timestamped smoke-test migration
 * file into database/migrations/ before zipping, purely for exercising the
 * "pending migrations actually run" path during manual verification - omit
 * it when building a real release.
 */

$root = dirname(__DIR__);
require_once $root.'/app/CoreUpdate/CoreCodeInstaller.php';

$targetVersion = $argv[1] ?? null;
$outputZip = $argv[2] ?? null;
if ($targetVersion === null || $outputZip === null) {
    fwrite(STDERR, "Usage: php tools/build-core-update-zip.php <target-version> <output.zip> [--add-migration=name] [--requires=version]\n");
    exit(1);
}

$addMigrationName = null;
$requires = '0.1.0';
foreach (array_slice($argv, 3) as $arg) {
    if (str_starts_with($arg, '--add-migration=')) {
        $addMigrationName = substr($arg, strlen('--add-migration='));
    } elseif (str_starts_with($arg, '--requires=')) {
        $requires = substr($arg, strlen('--requires='));
    }
}

$workspace = sys_get_temp_dir().'/erased-build-update-'.bin2hex(random_bytes(6));
mkdir($workspace, 0750, true);

$remove = static function (string $path) use (&$remove): void {
    if (!file_exists($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') $remove($path.'/'.$item);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
};

$copyTree = static function (string $from, string $to) use (&$copyTree): void {
    if (is_file($from)) {
        @mkdir(dirname($to), 0750, true);
        copy($from, $to);
        return;
    }
    if (!is_dir($from)) {
        return;
    }
    mkdir($to, 0750, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($from) + 1);
        $dest = $to.'/'.$relative;
        if ($item->isDir()) {
            @mkdir($dest, 0750, true);
        } else {
            @mkdir(dirname($dest), 0750, true);
            copy($item->getPathname(), $dest);
        }
    }
};

try {
    echo "Copying whitelisted core paths into a working tree...\n";
    foreach (\Erased\CoreUpdate\CoreCodeInstaller::CORE_UPDATE_PATHS as $path) {
        $from = $root.'/'.$path;
        if (!file_exists($from)) {
            continue;
        }
        $copyTree($from, $workspace.'/'.$path);
        echo "  copied {$path}\n";
    }

    // VERSION is always overwritten to the target version regardless of
    // what was copied above.
    file_put_contents($workspace.'/VERSION', $targetVersion);

    if ($addMigrationName !== null) {
        $migrationFile = $workspace.'/database/migrations/'.date('YmdHis').'_'.$addMigrationName.'.sql';
        @mkdir(dirname($migrationFile), 0750, true);
        file_put_contents(
            $migrationFile,
            "CREATE TABLE IF NOT EXISTS {$addMigrationName} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
        );
        echo "  added smoke-test migration: ".basename($migrationFile)."\n";
    }

    file_put_contents($workspace.'/update.json', json_encode([
        'version' => $targetVersion,
        'requires' => $requires,
        'name' => "ERASED CMS {$targetVersion}",
        'notes' => 'Built by tools/build-core-update-zip.php',
    ], JSON_PRETTY_PRINT));

    echo "Zipping...\n";
    if (is_file($outputZip)) {
        unlink($outputZip);
    }
    $zip = new ZipArchive();
    if ($zip->open($outputZip, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Could not create {$outputZip}");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = 'update/'.substr($item->getPathname(), strlen($workspace) + 1);
        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($item->getPathname(), $relative);
        }
    }
    $zip->close();

    $size = filesize($outputZip);
    echo "Wrote {$outputZip} (".number_format($size / 1024 / 1024, 1)." MB)\n";
} finally {
    $remove($workspace);
}
