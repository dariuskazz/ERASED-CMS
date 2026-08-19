<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/packages/erased.commerce/src/Domain/ProductRepository.php';

use ErasedCommerce\Domain\ProductRepository;

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
    $pdo->exec('CREATE TABLE commerce_products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT NULL,
        price_minor INTEGER NOT NULL,
        currency TEXT NOT NULL,
        sku TEXT NULL UNIQUE,
        stock_quantity INTEGER NOT NULL DEFAULT 0,
        track_inventory INTEGER NOT NULL DEFAULT 1,
        category_id INTEGER NULL,
        featured_media_id INTEGER NULL,
        status TEXT NOT NULL DEFAULT \'draft\',
        featured INTEGER NOT NULL DEFAULT 0,
        kind TEXT NOT NULL DEFAULT \'physical\',
        subscription_interval TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $repo = new ProductRepository($pdo);

    $id = $repo->create(['name' => 'Widget', 'slug' => 'widget', 'price_minor' => 1999, 'currency' => 'EUR', 'stock_quantity' => 10, 'status' => 'published']);
    $check($id > 0, 'create() returns a new product id');

    $found = $repo->find($id);
    $check($found !== null && $found['name'] === 'Widget' && (int)$found['price_minor'] === 1999, 'find() returns the created product with correct fields');

    $repo->create(['name' => 'Gadget', 'slug' => 'gadget', 'price_minor' => 2999, 'currency' => 'EUR', 'stock_quantity' => 0, 'status' => 'draft']);
    $published = $repo->published();
    $check(count($published) === 1 && $published[0]['name'] === 'Widget', 'published() only returns published products');

    $bySlug = $repo->findBySlug('widget');
    $check($bySlug !== null && (int)$bySlug['id'] === $id, 'findBySlug() finds a published product by slug');
    $check($repo->findBySlug('gadget') === null, 'findBySlug() does not return a draft product');

    $slug = $repo->uniqueSlug('widget');
    $check($slug === 'widget-2', 'uniqueSlug() appends a numeric suffix on collision');
    $check($repo->uniqueSlug('widget', $id) === 'widget', 'uniqueSlug() ignores the record\'s own id when re-saving unchanged');

    $repo->update($id, ['name' => 'Widget Pro', 'slug' => 'widget', 'price_minor' => 2499, 'currency' => 'EUR', 'stock_quantity' => 5, 'status' => 'published', 'track_inventory' => true]);
    $updated = $repo->find($id);
    $check($updated['name'] === 'Widget Pro' && (int)$updated['price_minor'] === 2499, 'update() persists changed fields');

    $check($repo->decrementStock($id, 3) === true, 'decrementStock() succeeds when stock is sufficient');
    $check((int)$repo->find($id)['stock_quantity'] === 2, 'decrementStock() reduces stock_quantity by the requested amount');
    $check($repo->decrementStock($id, 10) === false, 'decrementStock() refuses and leaves stock unchanged when insufficient');
    $check((int)$repo->find($id)['stock_quantity'] === 2, 'stock_quantity is unchanged after a refused decrement');

    // ---- sort, in-stock filtering, featured/recommended ----
    $repo->create(['name' => 'Anvil', 'slug' => 'anvil', 'price_minor' => 500, 'currency' => 'EUR', 'stock_quantity' => 0, 'track_inventory' => true, 'status' => 'published', 'featured' => true]);
    $repo->create(['name' => 'Bolt', 'slug' => 'bolt', 'price_minor' => 9999, 'currency' => 'EUR', 'stock_quantity' => 5, 'status' => 'published']);

    $byPriceAsc = array_map('intval', array_column($repo->published(['sort' => 'price_asc']), 'price_minor'));
    $sortedAsc = $byPriceAsc;
    sort($sortedAsc);
    $check($byPriceAsc === $sortedAsc, 'published(sort=price_asc) sorts ascending by price');

    $byPriceDesc = array_map('intval', array_column($repo->published(['sort' => 'price_desc']), 'price_minor'));
    $sortedDesc = $byPriceDesc;
    rsort($sortedDesc);
    $check($byPriceDesc === $sortedDesc, 'published(sort=price_desc) sorts descending by price');

    $byName = array_column($repo->published(['sort' => 'name_asc']), 'name');
    $sortedNames = $byName;
    sort($sortedNames);
    $check($byName === $sortedNames, 'published(sort=name_asc) sorts alphabetically');

    $inStockNames = array_column($repo->published(['in_stock_only' => true]), 'name');
    $check(!in_array('Anvil', $inStockNames, true), 'published(in_stock_only=true) excludes a tracked product with zero stock');
    $check(in_array('Bolt', $inStockNames, true), 'published(in_stock_only=true) keeps a tracked product that has stock');

    $featuredOnlyNames = array_column($repo->published(['featured_only' => true]), 'name');
    $check($featuredOnlyNames === ['Anvil'], 'published(featured_only=true) returns only the explicitly-featured product');

    $priceFiltered = array_column($repo->published(['min_price_minor' => 1000, 'max_price_minor' => 3000]), 'name');
    $check($priceFiltered === ['Widget Pro'], 'published() filters by min/max price_minor');

    $brandFiltered = array_column($repo->published(['brand' => 'Anv']), 'name');
    $check($brandFiltered === ['Anvil'], 'published() filters by a case-sensitive-safe substring match on name (brand filter)');

    $range = $repo->priceRange();
    $check($range['min_minor'] === 500 && $range['max_minor'] === 9999, 'priceRange() returns the true min/max across published products');

    $repo->create(['name' => 'BMW Oil Filter', 'slug' => 'bmw-oil-filter', 'price_minor' => 1500, 'currency' => 'EUR', 'stock_quantity' => 1, 'status' => 'published']);
    $brands = $repo->distinctBrandsInCategory(null);
    $check($brands === ['BMW'], 'distinctBrandsInCategory() only returns brands actually present in a product name');

    $recommended = array_column($repo->recommended(2), 'name');
    $check($recommended[0] === 'Anvil', 'recommended() puts the explicitly-featured product first');
    $check(count($recommended) === 2, 'recommended() respects the limit');

    $repo->delete($id);
    $check($repo->find($id) === null, 'delete() removes the product');
    $check(count($repo->all()) === 4, 'all() reflects the deletion (Gadget, Anvil, Bolt, BMW Oil Filter remain)');

    if ($fail === 0) {
        fwrite(STDOUT, "Commerce ProductRepository test passed.\n");
        fwrite(STDOUT, "Validated CRUD, slug uniqueness, published-only filtering, guarded stock decrement, sort variants, in-stock/featured/price/brand filtering, priceRange(), distinctBrandsInCategory(), and featured-first recommended().\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
