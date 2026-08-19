<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;
use ZipArchive;

final class PackageArchiveInspector
{
    public function __construct(
        private readonly int $maxFiles = 2000,
        private readonly int $maxUncompressedBytes = 104857600,
        private readonly int $maxSingleFileBytes = 26214400,
        private readonly string $manifestFilename = 'package.json',
    ) {}

    /**
     * v0.8-dev security audit: PackageArchiveStager re-enforces these same
     * two limits against bytes actually produced during extraction (not
     * just the declared ZIP metadata this class checks) - exposed here so
     * both checks share one configured source of truth instead of the
     * stager re-declaring its own, potentially drifting, copies of these
     * numbers.
     */
    public function maxUncompressedBytes(): int { return $this->maxUncompressedBytes; }
    public function maxSingleFileBytes(): int { return $this->maxSingleFileBytes; }

    /** @return array{files:int,uncompressed_bytes:int,manifest_path:string} */
    public function inspect(string $archivePath): array
    {
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new RuntimeException('Readable package ZIP is required.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Package ZIP could not be opened.');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > $this->maxFiles) {
                throw new RuntimeException('Package ZIP contains an invalid number of files.');
            }

            $total = 0;
            $manifestPaths = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !isset($stat['name'])) {
                    throw new RuntimeException('Package ZIP contains an unreadable entry.');
                }

                $name = (string)$stat['name'];
                $this->assertSafePath($name);

                $size = (int)($stat['size'] ?? 0);
                if ($size < 0 || $size > $this->maxSingleFileBytes) {
                    throw new RuntimeException("Package entry is too large: {$name}");
                }

                $total += $size;
                if ($total > $this->maxUncompressedBytes) {
                    throw new RuntimeException('Package ZIP exceeds the uncompressed size limit.');
                }

                if (basename(rtrim($name, '/')) === $this->manifestFilename) {
                    $manifestPaths[] = $name;
                }
            }

            if (count($manifestPaths) !== 1) {
                throw new RuntimeException("Package ZIP must contain exactly one {$this->manifestFilename}.");
            }

            return [
                'files' => $zip->numFiles,
                'uncompressed_bytes' => $total,
                'manifest_path' => $manifestPaths[0],
            ];
        } finally {
            $zip->close();
        }
    }

    private function assertSafePath(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (
            $normalized === '' ||
            str_starts_with($normalized, '/') ||
            preg_match('/^[A-Za-z]:\//', $normalized) === 1 ||
            str_contains($normalized, "\0")
        ) {
            throw new RuntimeException("Unsafe package entry path: {$path}");
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException("Path traversal detected in package entry: {$path}");
            }
        }
    }
}
