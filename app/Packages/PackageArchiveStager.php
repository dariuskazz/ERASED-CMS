<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;

final class PackageArchiveStager
{
    private PackageZipExtractor $extractor;

    public function __construct(
        private readonly PackageArchiveInspector $inspector,
        private readonly PackageValidator $validator,
    ) {
        $this->extractor = new PackageZipExtractor();
    }

    /** @return array{directory:string,manifest:PackageManifest,files:int,uncompressed_bytes:int} */
    public function stage(string $archivePath, string $stagingRoot): array
    {
        $extraction = $this->extractor->extract($archivePath, $stagingRoot, $this->inspector);
        $stageDirectory = $extraction['directory'];

        $validation = $this->validator->validateDirectory($stageDirectory);
        if (!$validation['valid'] || !$validation['manifest'] instanceof PackageManifest) {
            $errors = implode('; ', $validation['errors']);
            $this->extractor->removeDirectory($stageDirectory);
            throw new RuntimeException('Staged package validation failed: '.$errors);
        }

        return [
            'directory' => $stageDirectory,
            'manifest' => $validation['manifest'],
            'files' => $extraction['files'],
            'uncompressed_bytes' => $extraction['uncompressed_bytes'],
        ];
    }

    public function discard(string $stageDirectory, string $stagingRoot): void
    {
        $this->extractor->discard($stageDirectory, $stagingRoot);
    }
}
