<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/CategoryRepository.php';

use ErasedCommerce\Domain\CategoryRepository;

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
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE commerce_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        icon TEXT NOT NULL DEFAULT \'\',
        slug TEXT NOT NULL UNIQUE,
        description TEXT NULL,
        parent_id INTEGER NULL,
        image_media_id INTEGER NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE commerce_products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT NOT NULL DEFAULT \'draft\',
        category_id INTEGER NULL
    )');

    $categories = new CategoryRepository($pdo);

    // ---- CRUD + uniqueSlug ----
    $homeId = $categories->create(['name' => 'Home', 'slug' => 'home']);
    $check($homeId > 0, 'create() returns a new category id');
    $found = $categories->find($homeId);
    $check($found !== null && $found['name'] === 'Home', 'find() returns the created category');
    $check($categories->findBySlug('home')['id'] === $found['id'], 'findBySlug() finds the same row');
    $categories->update($homeId, ['name' => 'Home & Garden', 'slug' => 'home']);
    $check($categories->find($homeId)['name'] === 'Home & Garden', 'update() overwrites the name');
    $check($categories->uniqueSlug('home') === 'home-2', 'uniqueSlug() appends a suffix on collision');
    $check($categories->uniqueSlug('home', $homeId) === 'home', 'uniqueSlug() ignores the record\'s own id when re-saving unchanged');

    // ---- hierarchy: children(), tree() depth, descendantIds() ----
    $kitchenId = $categories->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'parent_id' => $homeId]);
    $lightingId = $categories->create(['name' => 'Lighting', 'slug' => 'lighting', 'parent_id' => $homeId]);
    $booksId = $categories->create(['name' => 'Books', 'slug' => 'books']);

    $children = $categories->children($homeId);
    $childNames = array_column($children, 'name');
    $check(count($children) === 2 && in_array('Kitchen', $childNames, true) && in_array('Lighting', $childNames, true), 'children() lists direct children only');
    $check($categories->children($booksId) === [], 'a category with no children returns an empty list');

    $tree = $categories->tree();
    $byName = [];
    foreach ($tree as $row) {
        $byName[$row['name']] = $row;
    }
    $check(count($tree) === 4, 'tree() returns every category');
    $check((int)$byName['Home & Garden']['depth'] === 0 && (int)$byName['Books']['depth'] === 0, 'tree() marks top-level categories at depth 0');
    $check((int)$byName['Kitchen']['depth'] === 1 && (int)$byName['Lighting']['depth'] === 1, 'tree() marks children at depth 1');

    $descendants = $categories->descendantIds($homeId);
    sort($descendants);
    $expected = [$kitchenId, $lightingId];
    sort($expected);
    $check($descendants === $expected, 'descendantIds() lists direct children (no grandchildren exist here)');
    $check($categories->descendantIds($kitchenId) === [], 'a leaf category has no descendants');

    // ---- productCounts(): direct + rolled up from descendants ----
    $pdo->exec("INSERT INTO commerce_products (status, category_id) VALUES ('published', {$kitchenId})");
    $pdo->exec("INSERT INTO commerce_products (status, category_id) VALUES ('published', {$kitchenId})");
    $pdo->exec("INSERT INTO commerce_products (status, category_id) VALUES ('published', {$lightingId})");
    $pdo->exec("INSERT INTO commerce_products (status, category_id) VALUES ('draft', {$lightingId})"); // draft: must not count
    $pdo->exec("INSERT INTO commerce_products (status, category_id) VALUES ('published', {$homeId})"); // filed directly on the parent

    $counts = $categories->productCounts();
    $check(($counts[$kitchenId] ?? 0) === 2, 'productCounts() counts published products filed directly on a leaf category');
    $check(($counts[$lightingId] ?? 0) === 1, 'productCounts() excludes draft products');
    $check(($counts[$homeId] ?? 0) === 4, 'productCounts() on a parent rolls up its own direct products plus every descendant\'s');
    $check(($counts[$booksId] ?? 0) === 0, 'a category with no products counts as zero, not missing/null');

    // ---- delete() unlinks products rather than requiring cascade handling here (the real FK's ON DELETE SET NULL does the unlinking in MySQL; this repo-level test only confirms the row itself is gone) ----
    $categories->delete($booksId);
    $check($categories->find($booksId) === null, 'delete() removes the category');
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    $fail++;
}

if ($fail > 0) {
    fwrite(STDERR, "\n{$fail} check(s) failed.\n");
    exit(1);
}

echo "\nCommerce CategoryRepository test passed.\n";
fwrite(STDOUT, "Validated CRUD, slug uniqueness, two-level hierarchy (children/tree depth/descendantIds), and productCounts() rolling up descendant totals onto a parent category.\n");
