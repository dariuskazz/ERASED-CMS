<?php
declare(strict_types=1);

namespace ErasedCommerce\Homepage;

require_once __DIR__.'/../Domain/ProductRepository.php';

use ErasedCommerce\Domain\ProductRepository;

/**
 * The one homepage_blocks entry this package declares (app/HomepageLayout.php's
 * homepage_studio_plugin_block_resolver() calls render() on whatever service
 * the manifest points its block id at - see that function's docblock for the
 * full contract). Every failure mode on the caller's side already degrades to
 * "skip this placement," so this class only needs to worry about producing
 * good HTML when there's something real to show, and nothing otherwise.
 */
final class FeaturedProductsBlock
{
    private readonly ProductRepository $products;

    public function __construct()
    {
        $this->products = new ProductRepository(db());
    }

    /** @param array<string,mixed> $settings */
    public function render(array $settings): string
    {
        $count = (int)($settings['count'] ?? 4);
        $count = max(1, min(12, $count));
        $title = trim((string)($settings['title'] ?? '')) ?: 'Featured Products';

        $products = array_slice($this->products->published(), 0, $count);
        if ($products === []) {
            return '';
        }

        $cards = '';
        foreach ($products as $product) {
            $price = number_format((int)$product['price_minor'] / 100, 2).' '.e((string)$product['currency']);
            $cards .= '<a class="gallery-card" href="/shop/'.e((string)$product['slug']).'">'
                .$this->productImage($product)
                .'<div class="gallery-card-body"><div class="gallery-card-title">'.e((string)$product['name']).'</div><div class="gallery-card-meta">'.$price.'</div></div></a>';
        }

        return '<section class="card"><h2 class="homepage-section-title">'.e($title).'</h2>'
            .'<div class="gallery-grid">'.$cards.'</div>'
            .'<p style="margin-top:14px"><a href="/shop">Visit the shop &rarr;</a></p></section>';
    }

    /** @param array<string,mixed> $product */
    private function productImage(array $product): string
    {
        if (!empty($product['featured_media_id']) && function_exists('media_by_id') && ($media = media_by_id((int)$product['featured_media_id']))) {
            return '<img src="'.e(media_url($media)).'" alt="'.e((string)$product['name']).'" loading="lazy">';
        }
        return '<div class="media-file-icon" style="height:180px">PRODUCT</div>';
    }
}
