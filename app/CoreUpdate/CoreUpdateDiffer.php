<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use Erased\Packages\PackageIntegrityChecker;

/**
 * Hash-based, memory-bounded diff between the live core codebase and a
 * staged update - purely path presence and hash equality, never content
 * diffing or loading full file contents (the codebase this compares is
 * ~90MB across a few thousand files). Reuses PackageIntegrityChecker's
 * existing computeManifest(), which already streams via hash_file() rather
 * than reading whole files into memory.
 */
final class CoreUpdateDiffer
{
    public function __construct(private readonly PackageIntegrityChecker $integrityChecker = new PackageIntegrityChecker())
    {
    }

    /**
     * @param list<string> $corePaths top-level paths (relative to root, e.g. "app", "routes") to compare
     * @return array{added:list<string>,changed:list<string>,removed:list<string>,counts:array{added:int,changed:int,removed:int,unchanged:int}}
     */
    public function diff(string $currentRoot, string $stagedRoot, array $corePaths): array
    {
        $current = $this->manifestForPaths(rtrim($currentRoot, '/'), $corePaths);
        $staged = $this->manifestForPaths(rtrim($stagedRoot, '/'), $corePaths);

        $added = array_values(array_diff(array_keys($staged), array_keys($current)));
        $removed = array_values(array_diff(array_keys($current), array_keys($staged)));

        $changed = [];
        $unchanged = 0;
        foreach (array_intersect_key($staged, $current) as $path => $hash) {
            if (hash_equals($current[$path], $hash)) {
                $unchanged++;
            } else {
                $changed[] = $path;
            }
        }

        sort($added);
        sort($changed);
        sort($removed);

        return [
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
            'counts' => [
                'added' => count($added),
                'changed' => count($changed),
                'removed' => count($removed),
                'unchanged' => $unchanged,
            ],
        ];
    }

    /**
     * @param list<string> $corePaths
     * @return array<string,string> "toppath/relative/file" => sha256, merged across every whitelisted top-level path present
     */
    private function manifestForPaths(string $root, array $corePaths): array
    {
        $merged = [];
        foreach ($corePaths as $topPath) {
            $fullPath = $root.'/'.$topPath;
            if (!is_dir($fullPath) && !is_file($fullPath)) {
                continue;
            }
            if (is_file($fullPath)) {
                // A whitelisted entry may be a single file (e.g. VERSION),
                // not a directory - PackageIntegrityChecker::computeManifest()
                // expects a directory, so hash a lone file directly.
                $hash = hash_file('sha256', $fullPath);
                if ($hash !== false) {
                    $merged[$topPath] = $hash;
                }
                continue;
            }
            foreach ($this->integrityChecker->computeManifest($fullPath) as $relative => $hash) {
                $merged[$topPath.'/'.$relative] = $hash;
            }
        }
        ksort($merged);
        return $merged;
    }
}
