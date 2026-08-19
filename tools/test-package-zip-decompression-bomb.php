<?php
declare(strict_types=1);

// PackageArchiveInspector's size checks read *declared* ZIP entry metadata
// (stat()['size']) before any bytes are inflated - metadata a malicious ZIP
// controls. The old PackageArchiveStager::stage() then extracted with
// stream_copy_to_stream($source,$destination), which has no length cap, so
// a crafted entry whose header declares a small size but whose real deflate
// stream produces far more data would sail past the inspector's gate and
// have all of it written to disk during staging - a decompression-bomb
// bypass of the declared-size check. Found via a v0.8-dev security audit -
// see docs/STATUS.md.
//
// This test exercises the fix (PackageArchiveStager::copyBounded(), a
// private method) directly via reflection against controlled php://temp
// streams, rather than attempting to forge a ZIP with genuinely mismatched
// declared-vs-actual entry sizes - ext-zip's normal write API always
// computes correct, matching metadata when creating an archive, so
// constructing a real proof-of-concept ZIP would require raw central-
// directory byte manipulation outside what this codebase's own tooling
// does. Testing the bounded-copy logic directly against real streamed
// bytes - independent of how those bytes' declared metadata might have
// been mismatched - proves the actual property that matters: the
// extraction-time limit is enforced against genuine output, not trusted
// input metadata.

use Erased\Packages\PackageArchiveInspector;
use Erased\Packages\PackageArchiveStager;
use Erased\Packages\PackageValidator;
use Erased\Packages\PackageZipExtractor;

require dirname(__DIR__).'/app/Packages/PackageManifest.php';
require dirname(__DIR__).'/app/Packages/PackageValidator.php';
require dirname(__DIR__).'/app/Packages/PackageArchiveInspector.php';
require dirname(__DIR__).'/app/Packages/PackageZipExtractor.php';
require dirname(__DIR__).'/app/Packages/PackageArchiveStager.php';

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

/** Invokes the private copyBounded() method directly. */
$invokeCopyBounded = static function (
    PackageZipExtractor $extractor,
    PackageArchiveInspector $inspector,
    $source,
    $destination,
    string $relativeName,
    int $totalWrittenSoFar
): int {
    $method = new ReflectionMethod(PackageZipExtractor::class, 'copyBounded');
    $method->setAccessible(true);
    return $method->invoke($extractor, $inspector, $source, $destination, $relativeName, $totalWrittenSoFar);
};

try {
    // Tiny configured limits so the test runs fast and the numbers are easy
    // to reason about - the limit values themselves aren't the point, the
    // enforcement mechanism is.
    $inspector = new PackageArchiveInspector(maxFiles: 10, maxUncompressedBytes: 2000, maxSingleFileBytes: 1000);
    $stager = new PackageArchiveStager($inspector, new PackageValidator());
    $extractor = new PackageZipExtractor();

    $check(
        $inspector->maxSingleFileBytes() === 1000 && $inspector->maxUncompressedBytes() === 2000,
        'PackageArchiveInspector exposes its configured limits for the stager to reuse'
    );

    // 1. A single entry whose real stream content exceeds maxSingleFileBytes
    //    must be rejected mid-copy, even though nothing here claims a
    //    declared size at all - the bound applies to what's actually read.
    $source = fopen('php://temp', 'r+');
    fwrite($source, str_repeat('A', 1500)); // exceeds the 1000-byte per-file limit
    rewind($source);
    $destination = fopen('php://temp', 'r+');
    $rejected = false;
    try {
        $invokeCopyBounded($extractor, $inspector, $source, $destination, 'oversized-single-file.txt', 0);
    } catch (Throwable $error) {
        $rejected = str_contains($error->getMessage(), 'exceeded its declared size');
    }
    fclose($source);
    fclose($destination);
    $check($rejected, 'a single entry whose real bytes exceed the per-file limit is rejected mid-copy');

    // 2. A file within the per-file limit must still be rejected if adding
    //    it would push the running cumulative total over the archive-wide
    //    limit - this is the actual decompression-bomb protection: no
    //    single file needs to look large for the total to matter.
    $source = fopen('php://temp', 'r+');
    fwrite($source, str_repeat('B', 900)); // under the 1000-byte per-file cap
    rewind($source);
    $destination = fopen('php://temp', 'r+');
    $rejectedCumulative = false;
    try {
        // Simulate 1900 bytes already written by prior entries this staging
        // run - adding another 900 would total 2800, over the 2000 cap.
        $invokeCopyBounded($extractor, $inspector, $source, $destination, 'pushes-total-over.txt', 1900);
    } catch (Throwable $error) {
        $rejectedCumulative = str_contains($error->getMessage(), 'uncompressed size limit');
    }
    fclose($source);
    fclose($destination);
    $check($rejectedCumulative, 'an entry under the per-file limit is still rejected if it would push the cumulative total over the archive-wide limit');

    // 3. A genuinely small entry must still stage correctly - the bound
    //    must not be so aggressive it breaks ordinary, legitimate packages.
    $source = fopen('php://temp', 'r+');
    fwrite($source, 'small and legitimate');
    rewind($source);
    $destination = fopen('php://temp', 'r+');
    $written = $invokeCopyBounded($extractor, $inspector, $source, $destination, 'fine.txt', 0);
    rewind($destination);
    $writtenContent = stream_get_contents($destination);
    fclose($source);
    fclose($destination);
    $check(
        $written === 20 && $writtenContent === 'small and legitimate',
        'a legitimate small entry still copies through correctly and completely'
    );

    // 4. End-to-end sanity: PackageArchiveInspector's own declared-metadata
    //    check still fires first for an honestly-labeled oversized entry
    //    (this is the existing, pre-fix protection layer - confirm it's
    //    untouched by this change).
    if (class_exists(ZipArchive::class)) {
        $workspace = sys_get_temp_dir().'/erased-package-bomb-'.bin2hex(random_bytes(6));
        mkdir($workspace, 0750, true);
        $zipPath = $workspace.'/oversized.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('example/package.json', '{}');
        $zip->addFromString('example/big.bin', str_repeat('C', 1500)); // honestly declares 1500 bytes, over the 1000 cap
        $zip->close();

        $rejectedByInspector = false;
        try {
            $stager->stage($zipPath, $workspace.'/staging');
        } catch (Throwable $error) {
            $rejectedByInspector = str_contains($error->getMessage(), 'too large');
        }
        $check($rejectedByInspector, 'an honestly-labeled oversized entry is still caught by the pre-existing declared-size check');

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
        $remove($workspace);
    } else {
        echo "SKIP: end-to-end ZIP check (ext-zip not available)\n";
    }

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll package ZIP decompression-bomb checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
