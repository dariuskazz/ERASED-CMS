<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Package-agnostic ZIP extraction, split out of PackageArchiveStager so the
 * core-update feature can reuse the exact same zip-slip-safe,
 * decompression-bomb-safe extraction code (see copyBounded()'s doc comment)
 * against an `update.json`-rooted archive instead of a `package.json`-rooted
 * one, without forking this security-sensitive logic.
 */
final class PackageZipExtractor
{
    /** @return array{directory:string,manifest_path:string,files:int,uncompressed_bytes:int} */
    public function extract(string $archivePath, string $stagingRoot, PackageArchiveInspector $inspector): array
    {
        $inspection = $inspector->inspect($archivePath);
        $stagingRoot = rtrim($stagingRoot, DIRECTORY_SEPARATOR);
        $this->ensureDirectory($stagingRoot);

        $token = date('Ymd-His').'-'.bin2hex(random_bytes(8));
        $stageDirectory = $stagingRoot.DIRECTORY_SEPARATOR.$token;
        $this->ensureDirectory($stageDirectory);

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            $this->removeDirectory($stageDirectory);
            throw new RuntimeException('Archive could not be reopened for staging.');
        }

        try {
            $manifestPath = str_replace('\\', '/', $inspection['manifest_path']);
            $packagePrefix = dirname($manifestPath);
            $packagePrefix = $packagePrefix === '.' ? '' : rtrim($packagePrefix, '/').'/';
            $totalWritten = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !isset($stat['name'])) {
                    throw new RuntimeException('Archive contains an unreadable entry.');
                }

                $archiveName = str_replace('\\', '/', (string)$stat['name']);
                if ($packagePrefix !== '' && !str_starts_with($archiveName, $packagePrefix)) {
                    throw new RuntimeException('All archive files must be inside the manifest root directory.');
                }

                $relativeName = $packagePrefix === ''
                    ? $archiveName
                    : substr($archiveName, strlen($packagePrefix));

                if ($relativeName === '') {
                    continue;
                }

                $target = $stageDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeName);
                if (str_ends_with($archiveName, '/')) {
                    $this->ensureDirectory($target);
                    continue;
                }

                $this->ensureDirectory(dirname($target));
                $source = $zip->getStream((string)$stat['name']);
                if (!is_resource($source)) {
                    throw new RuntimeException("Could not read archive entry: {$archiveName}");
                }

                $destination = fopen($target, 'xb');
                if (!is_resource($destination)) {
                    fclose($source);
                    throw new RuntimeException("Could not create staged entry: {$relativeName}");
                }

                try {
                    $totalWritten += $this->copyBounded($inspector, $source, $destination, $relativeName, $totalWritten);
                } finally {
                    fclose($source);
                    fclose($destination);
                }
            }
        } catch (Throwable $error) {
            $this->removeDirectory($stageDirectory);
            throw $error;
        } finally {
            $zip->close();
        }

        return [
            'directory' => $stageDirectory,
            'manifest_path' => $inspection['manifest_path'],
            'files' => $inspection['files'],
            'uncompressed_bytes' => $inspection['uncompressed_bytes'],
        ];
    }

    /**
     * v0.8-dev security audit: PackageArchiveInspector's size checks read
     * declared ZIP entry metadata (stat()['size']) - attacker-controlled,
     * before any bytes are actually inflated. A plain
     * stream_copy_to_stream($source,$destination) call has no length cap,
     * so a crafted entry whose header declares a small uncompressed size
     * but whose real deflate stream produces far more data would sail past
     * the inspector's gate and then have all of it written to disk during
     * staging - a decompression-bomb bypass of the declared-size check.
     * This copies in bounded chunks and enforces both the per-file and
     * cumulative-across-the-whole-archive limits against bytes genuinely
     * written, aborting (and letting the caller's catch block clean up the
     * whole staging directory) the moment either is exceeded, regardless of
     * what the ZIP header claimed.
     * @return int bytes written for this one entry
     */
    private function copyBounded(PackageArchiveInspector $inspector, $source, $destination, string $relativeName, int $totalWrittenSoFar): int
    {
        $maxSingleFile = $inspector->maxSingleFileBytes();
        $maxTotal = $inspector->maxUncompressedBytes();
        $chunkSize = 65536;
        $writtenForThisFile = 0;

        while (!feof($source)) {
            $chunk = fread($source, $chunkSize);
            if ($chunk === false) {
                throw new RuntimeException("Could not read archive entry: {$relativeName}");
            }
            if ($chunk === '') {
                continue;
            }
            $writtenForThisFile += strlen($chunk);
            if ($writtenForThisFile > $maxSingleFile) {
                throw new RuntimeException("Archive entry exceeded its declared size while extracting: {$relativeName}");
            }
            if ($totalWrittenSoFar + $writtenForThisFile > $maxTotal) {
                throw new RuntimeException('Archive exceeded the uncompressed size limit while extracting.');
            }
            if (fwrite($destination, $chunk) === false) {
                throw new RuntimeException("Could not extract archive entry: {$relativeName}");
            }
        }

        return $writtenForThisFile;
    }

    public function discard(string $stageDirectory, string $stagingRoot): void
    {
        $root = realpath($stagingRoot);
        $stage = realpath($stageDirectory);
        if ($root === false || $stage === false || !str_starts_with($stage.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Refusing to discard a directory outside the staging root.');
        }
        $this->removeDirectory($stage);
    }

    public function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create directory: {$directory}");
        }
    }

    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
