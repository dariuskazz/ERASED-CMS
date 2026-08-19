<?php
declare(strict_types=1);

namespace Erased\CoreUpdate;

use Erased\Packages\PackageArchiveInspector;
use Erased\Packages\PackageZipExtractor;
use RuntimeException;

/**
 * Mirrors PackageArchiveStager's shape but roots on `update.json` instead of
 * `package.json` and validates into a CoreUpdateManifest instead of running
 * PackageValidator (which checks package-specific fields that don't apply
 * here). Reuses the exact same hardened, zip-slip-safe, decompression-bomb-
 * safe PackageZipExtractor the package system already relies on.
 */
final class CoreUpdateStager
{
    private PackageZipExtractor $extractor;
    private PackageArchiveInspector $inspector;

    public function __construct(?PackageArchiveInspector $inspector = null)
    {
        $this->extractor = new PackageZipExtractor();
        // A real core release ZIP is the whole codebase minus node_modules/
        // .git/storage - on this project that's ~90MB across a few thousand
        // files, comfortably larger than the package-system defaults
        // (2000 files / 100MB / 25MB-per-file) tuned for individual plugins.
        $this->inspector = $inspector ?? new PackageArchiveInspector(
            maxFiles: 8000,
            maxUncompressedBytes: 300 * 1024 * 1024,
            maxSingleFileBytes: 50 * 1024 * 1024,
            manifestFilename: 'update.json',
        );
    }

    /** @return array{directory:string,manifest:CoreUpdateManifest,files:int,uncompressed_bytes:int} */
    public function stage(string $archivePath, string $stagingRoot): array
    {
        $extraction = $this->extractor->extract($archivePath, $stagingRoot, $this->inspector);
        $stageDirectory = $extraction['directory'];

        $manifestFile = $stageDirectory.DIRECTORY_SEPARATOR.'update.json';
        if (!is_file($manifestFile)) {
            $this->extractor->removeDirectory($stageDirectory);
            throw new RuntimeException('Staged update is missing update.json at its root.');
        }

        try {
            $json = file_get_contents($manifestFile);
            if ($json === false) {
                throw new RuntimeException('Could not read update.json.');
            }
            $manifest = CoreUpdateManifest::fromJson($json);
        } catch (\Throwable $error) {
            $this->extractor->removeDirectory($stageDirectory);
            throw new RuntimeException('Invalid update.json: '.$error->getMessage());
        }

        return [
            'directory' => $stageDirectory,
            'manifest' => $manifest,
            'files' => $extraction['files'],
            'uncompressed_bytes' => $extraction['uncompressed_bytes'],
        ];
    }

    public function discard(string $stageDirectory, string $stagingRoot): void
    {
        $this->extractor->discard($stageDirectory, $stagingRoot);
    }
}
