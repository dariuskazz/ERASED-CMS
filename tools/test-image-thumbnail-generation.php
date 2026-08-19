<?php
declare(strict_types=1);

/**
 * erased_generate_thumbnail() (app/bootstrap.php) - covers the actual image
 * work (large image is scaled down and re-encoded, small image is correctly
 * skipped so the caller falls back to the original) without needing a real
 * upload or the MySQL-only db(). Requires the GD extension; skips cleanly
 * (not a failure) when it's unavailable, matching how this function itself
 * degrades in production.
 */

require_once dirname(__DIR__).'/app/bootstrap.php';

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

if (!extension_loaded('gd')) {
    fwrite(STDOUT, "SKIP: GD extension not available in this environment - erased_generate_thumbnail() degrades to false, which is exactly what's tested elsewhere via the has_thumb=0 fallback path.\n");
    exit(0);
}

$workspace = sys_get_temp_dir().'/erased-thumbnail-test-'.bin2hex(random_bytes(6));
mkdir($workspace, 0750, true);

try {
    // --- A large image gets a real, smaller, valid JPEG thumbnail ---
    $large = imagecreatetruecolor(800, 400);
    imagefill($large, 0, 0, (int) imagecolorallocate($large, 30, 120, 200));
    $largePath = $workspace.'/large.png';
    imagepng($large, $largePath);
    imagedestroy($large);

    $thumbPath = $workspace.'/large_thumb.jpg';
    $ok = erased_generate_thumbnail($largePath, 'image/png', $thumbPath, 320);
    $check($ok === true, 'a large image reports successful thumbnail generation');
    $check(is_file($thumbPath), 'the thumbnail file actually exists on disk');

    $thumbInfo = @getimagesize($thumbPath);
    $check(is_array($thumbInfo), 'the thumbnail is a decodable image');
    if (is_array($thumbInfo)) {
        $check($thumbInfo[0] === 320 && $thumbInfo[1] === 160, 'the thumbnail is scaled to the max dimension, aspect ratio preserved (320x160 from 800x400)');
        $check($thumbInfo['mime'] === 'image/jpeg', 'the thumbnail is always re-encoded as JPEG regardless of source format');
    }
    $check(filesize($thumbPath) < filesize($largePath), 'the thumbnail is smaller in bytes than the original');

    // --- A small image (already under the max dimension) is correctly skipped, not upscaled ---
    $small = imagecreatetruecolor(100, 80);
    imagefill($small, 0, 0, (int) imagecolorallocate($small, 200, 50, 50));
    $smallPath = $workspace.'/small.png';
    imagepng($small, $smallPath);
    imagedestroy($small);

    $smallThumbPath = $workspace.'/small_thumb.jpg';
    $skipped = erased_generate_thumbnail($smallPath, 'image/png', $smallThumbPath, 320);
    $check($skipped === false, 'an image already under the max dimension is skipped (no thumbnail generated)');
    $check(!is_file($smallThumbPath), 'no thumbnail file is written for a skipped image');

    // --- An unreadable/corrupt source degrades to false, not a fatal error ---
    $corruptPath = $workspace.'/corrupt.png';
    file_put_contents($corruptPath, 'this is not a real image file');
    $corruptThumbPath = $workspace.'/corrupt_thumb.jpg';
    $corruptResult = erased_generate_thumbnail($corruptPath, 'image/png', $corruptThumbPath, 320);
    $check($corruptResult === false, 'a corrupt/undecodable source file degrades to false rather than throwing');

    // --- media_thumb_url() falls back correctly ---
    $check(media_thumb_url(['stored_name' => 'abc123.jpg', 'has_thumb' => 0]) === media_url(['stored_name' => 'abc123.jpg', 'has_thumb' => 0]), 'media_thumb_url() falls back to media_url() when has_thumb is false');
    $check(media_thumb_url(['stored_name' => 'abc123.jpg', 'has_thumb' => 1]) === '/media/abc123_thumb.jpg', 'media_thumb_url() points at the derived _thumb.jpg filename when has_thumb is true');

    if ($fail === 0) {
        fwrite(STDOUT, "Image thumbnail generation test passed.\n");
        fwrite(STDOUT, "Validated large-image scaling, small-image skip, corrupt-source resilience, and the media_thumb_url() fallback contract.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
} finally {
    foreach (glob($workspace.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($workspace);
}
