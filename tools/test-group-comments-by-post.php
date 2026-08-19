<?php
declare(strict_types=1);

/**
 * CommentGrouping::byPost() is pure array transformation with no
 * bootstrap/DB dependency, so it's required directly - no stubbing needed.
 */

require_once dirname(__DIR__).'/app/Support/CommentGrouping.php';

use Erased\Support\CommentGrouping;

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

try {
    // Rows arrive pre-sorted created_at DESC, as the real query produces.
    $rows = [
        ['id' => 5, 'content_id' => 2, 'title' => 'Post B', 'slug' => 'post-b', 'created_at' => '2026-08-12 10:00:00'],
        ['id' => 4, 'content_id' => 1, 'title' => 'Post A', 'slug' => 'post-a', 'created_at' => '2026-08-12 09:00:00'],
        ['id' => 3, 'content_id' => 0, 'title' => null, 'slug' => '', 'created_at' => '2026-08-12 08:00:00'], // orphaned: content row deleted
        ['id' => 2, 'content_id' => 1, 'title' => 'Post A', 'slug' => 'post-a', 'created_at' => '2026-08-12 07:00:00'],
        ['id' => 1, 'content_id' => 2, 'title' => 'Post B', 'slug' => 'post-b', 'created_at' => '2026-08-11 12:00:00'],
    ];

    $groups = CommentGrouping::byPost($rows);

    // ---- Row count preserved ----
    $totalRowsInGroups = array_sum(array_map(fn($g) => count($g['rows']), $groups));
    $check($totalRowsInGroups === count($rows), 'total row count is preserved across regrouping');

    // ---- 3 distinct groups: Post A, Post B, orphaned bucket ----
    $check(count($groups) === 3, 'rows are bucketed into exactly 3 groups (Post A, Post B, orphaned)');

    // ---- Groups sorted by descending latest-comment time: B (id5, 10:00) newest, then A (id4, 09:00), then orphaned (08:00) ----
    $check($groups[0]['title'] === 'Post B', 'newest-active group (Post B, latest comment 10:00) sorts first');
    $check($groups[1]['title'] === 'Post A', 'second-newest group (Post A, latest comment 09:00) sorts second');
    $check($groups[2]['title'] === 'Comments on deleted content', 'orphaned bucket (latest comment 08:00) sorts third');

    // ---- Real posts group correctly by content_id, with within-group order preserved ----
    $check(count($groups[0]['rows']) === 2 && $groups[0]['rows'][0]['id'] === 5 && $groups[0]['rows'][1]['id'] === 1, 'Post B group contains both its comments (ids 5, 1) in original order');
    $check(count($groups[1]['rows']) === 2 && $groups[1]['rows'][0]['id'] === 4 && $groups[1]['rows'][1]['id'] === 2, 'Post A group contains both its comments (ids 4, 2) in original order');
    $check($groups[0]['slug'] === 'post-b' && $groups[1]['slug'] === 'post-a', 'real post groups carry their slug for linking');

    // ---- Orphans bucket together under slug=null ----
    $check(count($groups[2]['rows']) === 1 && $groups[2]['rows'][0]['id'] === 3, 'orphaned bucket contains the row whose content was deleted');
    $check($groups[2]['slug'] === null, 'orphaned bucket has a null slug (no link to render)');

    // ---- Empty input ----
    $check(CommentGrouping::byPost([]) === [], 'empty input produces an empty group list');

    if ($fail === 0) {
        fwrite(STDOUT, "Comment grouping test passed.\n");
        fwrite(STDOUT, "Validated row-count preservation, per-post bucketing, orphan handling, and descending latest-comment sort.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
