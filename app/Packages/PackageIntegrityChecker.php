<?php
declare(strict_types=1);

namespace Erased\Packages;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * v0.8-dev roadmap: "Package signature and integrity checks." Scoped
 * deliberately to integrity, not cryptographic signature/authorship
 * verification - this codebase has no multi-publisher package ecosystem
 * yet (every installed package today is first-party: erased.commerce,
 * erased.payments, the 7 theme packages, the language packs), so building
 * a trust/public-key infrastructure now would be speculative scaffolding
 * for a use case that doesn't exist, the same reasoning this project
 * already applied to deferring a generic public_routes manifest field in
 * v0.6-dev. What genuinely is buildable and useful today: detect whether
 * an installed package's files have changed since the moment it was
 * installed or updated - corruption, a stray manual edit, or tampering by
 * anything with filesystem access to storage/plugins/installed/.
 *
 * A SHA-256 manifest (relative path => hash) is computed right after every
 * successful install/update (PackageInstallOrchestrator/
 * PackageUpdateOrchestrator) and persisted via
 * InstalledPackageRepository::recordIntegrityManifest(). verify() re-walks
 * the live directory and reports drift against that stored baseline -
 * exposed as an admin-triggerable "Verify integrity" action per package.
 */
final class PackageIntegrityChecker
{
    /** @return array<string,string> relative path (forward-slash) => sha256 hex digest, sorted by path */
    public function computeManifest(string $directory): array
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($directory)) {
            throw new RuntimeException("Package directory does not exist: {$directory}");
        }

        $manifest = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($path, strlen($directory))), '/');
            if ($relative === '') {
                continue;
            }

            if ($fileInfo->isLink()) {
                // A real package (staged through PackageArchiveStager, which
                // only ever writes plain files, never extractTo()) never
                // legitimately contains a symlink - encode its presence as
                // a distinct, deliberately-fake "hash" so it always shows up
                // as drift rather than being silently skipped, which would
                // create exactly the kind of filesystem-tamper blind spot
                // this whole feature exists to catch.
                $target = readlink($path);
                $manifest[$relative] = 'symlink:'.($target !== false ? $target : '?');
                continue;
            }

            if (!$fileInfo->isFile()) {
                continue;
            }

            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                throw new RuntimeException("Could not hash package file: {$relative}");
            }
            $manifest[$relative] = $hash;
        }

        ksort($manifest);
        return $manifest;
    }

    /**
     * @param array<string,string> $expected the manifest recorded at install/update time
     * @return array{status:string,mismatched:list<string>,missing:list<string>,unexpected:list<string>}
     */
    public function verify(string $directory, array $expected): array
    {
        $actual = $this->computeManifest($directory);

        $mismatched = [];
        $missing = [];
        foreach ($expected as $path => $hash) {
            if (!array_key_exists($path, $actual)) {
                $missing[] = $path;
                continue;
            }
            if (!hash_equals($hash, $actual[$path])) {
                $mismatched[] = $path;
            }
        }

        $unexpected = array_values(array_diff(array_keys($actual), array_keys($expected)));
        sort($mismatched);
        sort($missing);
        sort($unexpected);

        $status = ($mismatched === [] && $missing === [] && $unexpected === []) ? 'ok' : 'mismatch';

        return [
            'status' => $status,
            'mismatched' => $mismatched,
            'missing' => $missing,
            'unexpected' => $unexpected,
        ];
    }
}
