<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

require_once __DIR__.'/../Domain/ProductRepository.php';
require_once __DIR__.'/../Domain/ProductFileRepository.php';
require_once __DIR__.'/../Domain/ProductImageRepository.php';
require_once __DIR__.'/../Domain/CategoryRepository.php';

use ErasedCommerce\Domain\CategoryRepository;
use ErasedCommerce\Domain\ProductFileRepository;
use ErasedCommerce\Domain\ProductImageRepository;
use ErasedCommerce\Domain\ProductRepository;

final class ProductsAdminRoute
{
    private readonly ProductRepository $products;
    private readonly ProductFileRepository $files;
    private readonly ProductImageRepository $images;
    private readonly CategoryRepository $categories;

    public function __construct()
    {
        $pdo = db();
        $this->products = new ProductRepository($pdo);
        $this->files = new ProductFileRepository($pdo);
        $this->images = new ProductImageRepository($pdo);
        $this->categories = new CategoryRepository($pdo);
    }

    public function handle(string $id = '', string $secondId = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if ($id !== '' && $secondId !== '' && str_contains($path, '/files/') && str_ends_with($path, '/delete')) {
            $this->deleteFile((int)$id, (int)$secondId);
            return;
        }
        if ($id !== '' && $secondId !== '' && str_contains($path, '/images/') && str_ends_with($path, '/delete')) {
            $this->deleteImage((int)$id, (int)$secondId);
            return;
        }
        if ($id !== '' && str_ends_with($path, '/images/add')) {
            $this->addImage((int)$id);
            return;
        }
        if ($id !== '' && str_ends_with($path, '/delete')) {
            $this->delete((int)$id);
            return;
        }
        if ($id !== '') {
            $this->edit((int)$id);
            return;
        }
        if ($path === '/admin/commerce/products/new') {
            $this->create();
            return;
        }
        $this->list();
    }

    private function list(): void
    {
        $rows = $this->products->all();
        $html = '';
        foreach ($rows as $row) {
            $statusPill = $row['status'] === 'published' ? '<span class="stampword live">Published</span>' : '<span class="stampword draft">Draft</span>';
            $kindLabels = ['physical' => 'Physical', 'digital' => 'Digital', 'subscription' => 'Subscription'];
            $kindPill = '<span class="stampword">'.e($kindLabels[$row['kind'] ?? 'physical'] ?? 'Physical').'</span>';
            $featuredPill = (int)($row['featured'] ?? 0) === 1 ? ' <span class="stampword live">★ Recommended</span>' : '';
            $price = number_format((int)$row['price_minor'] / 100, 2).' '.e((string)$row['currency']);
            $stock = (int)$row['track_inventory'] === 1 ? (int)$row['stock_quantity'].' in stock' : 'Not tracked';
            $html .= '<div class="admin-row"><div class="admin-row-body">'
                .'<div class="admin-row-title">'.e((string)$row['name']).' '.$statusPill.' '.$kindPill.$featuredPill.'</div>'
                .'<div class="admin-row-meta">'.$price.' &middot; '.e($stock).($row['sku'] ? ' &middot; SKU '.e((string)$row['sku']) : '').'</div>'
                .'</div><div class="admin-row-actions">'
                .'<a class="btn ghost" href="/admin/commerce/products/'.(int)$row['id'].'/edit">Edit</a>'
                .'<form method="post" action="/admin/commerce/products/'.(int)$row['id'].'/delete" onsubmit="return confirm(&quot;Delete this product?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Delete</button></form>'
                .'</div></div>';
        }
        $body = '<div class="title-row"><div><p class="kicker">SHEET C-01 &middot; COMMERCE</p><h1>Products</h1><p>Manage the product catalog.</p></div><div class="actions"><a class="btn" href="/admin/commerce/products/new">New product</a></div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All products</h2></div><div class="panel-body"><div class="admin-row-list">'.($html ?: '<div class="admin-row-empty">No products yet.</div>').'</div></div></div>';
        layout('Products', $body, true);
    }

    private function create(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $data = $this->readFormInput();
            if ($data === null) {
                return;
            }
            $slug = $this->products->uniqueSlug(slugify($data['slug'] !== '' ? $data['slug'] : $data['name']));
            $data['slug'] = $slug;
            $id = $this->products->create($data);
            audit('commerce.product.create', ['id' => $id]);
            flash('success', 'Product created. Save again after upload to attach a digital file.');
            redirect('/admin/commerce/products/'.$id.'/edit');
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-01 &middot; COMMERCE</p><h1>New product</h1></div></div>'.$this->productForm([]);
        layout('New product', $body, true);
    }

    private function edit(int $id): void
    {
        $product = fetch_or_404('commerce_products', $id);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $data = $this->readFormInput();
            if ($data === null) {
                return;
            }
            $slug = $this->products->uniqueSlug(slugify($data['slug'] !== '' ? $data['slug'] : $data['name']), $id);
            $data['slug'] = $slug;
            $this->products->update($id, $data);
            audit('commerce.product.update', ['id' => $id]);

            if ($data['kind'] === 'digital' && (int)($_FILES['digital_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $fileId = $this->files->upload($id, $_FILES['digital_file']);
                    audit('commerce.product.file_upload', ['id' => $id, 'file_id' => $fileId]);
                    flash('success', 'Product saved and file attached.');
                } catch (\Throwable $error) {
                    flash('error', 'Product saved, but the file upload failed: '.$error->getMessage());
                }
            } else {
                flash('success', 'Product saved.');
            }
            redirect('/admin/commerce/products/'.$id.'/edit');
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-01 &middot; COMMERCE</p><h1>Edit product</h1></div></div>'.$this->productForm($product);
        layout('Edit product', $body, true);
    }

    private function delete(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/products');
        }
        verify_csrf();
        $this->products->delete($id);
        audit('commerce.product.delete', ['id' => $id]);
        flash('success', 'Product deleted.');
        redirect('/admin/commerce/products');
    }

    private function deleteFile(int $productId, int $fileId): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/products/'.$productId.'/edit');
        }
        verify_csrf();
        $file = $this->files->find($fileId);
        if ($file !== null && (int)$file['product_id'] === $productId) {
            $this->files->delete($fileId);
            audit('commerce.product.file_delete', ['id' => $productId, 'file_id' => $fileId]);
            flash('success', 'File removed.');
        }
        redirect('/admin/commerce/products/'.$productId.'/edit');
    }

    private function addImage(int $productId): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/products/'.$productId.'/edit');
        }
        verify_csrf();
        $mediaId = (int)($_POST['add_image_media_id'] ?? 0);
        if ($mediaId > 0 && function_exists('media_by_id') && media_by_id($mediaId) !== null) {
            $imageId = $this->images->attach($productId, $mediaId);
            audit('commerce.product.image_add', ['id' => $productId, 'image_id' => $imageId]);
            flash('success', 'Image added.');
        } else {
            flash('error', 'Choose an image to add.');
        }
        redirect('/admin/commerce/products/'.$productId.'/edit');
    }

    private function deleteImage(int $productId, int $imageId): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/products/'.$productId.'/edit');
        }
        verify_csrf();
        $image = $this->images->find($imageId);
        if ($image !== null && (int)$image['product_id'] === $productId) {
            $this->images->delete($imageId);
            audit('commerce.product.image_delete', ['id' => $productId, 'image_id' => $imageId]);
            flash('success', 'Image removed.');
        }
        redirect('/admin/commerce/products/'.$productId.'/edit');
    }

    /** @return array<string,mixed>|null null when validation failed (flash set, caller should return without redirecting) */
    private function readFormInput(): ?array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $priceInput = trim((string)($_POST['price'] ?? ''));
        if ($name === '' || !is_numeric($priceInput) || (float)$priceInput < 0) {
            flash('error', 'Enter a product name and a valid price.');
            return null;
        }
        return [
            'name' => $name,
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'price_minor' => (int)round((float)$priceInput * 100),
            'currency' => strtoupper(trim((string)($_POST['currency'] ?? 'EUR'))) ?: 'EUR',
            'sku' => trim((string)($_POST['sku'] ?? '')),
            'stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 0)),
            'track_inventory' => isset($_POST['track_inventory']),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'featured_media_id' => (int)($_POST['featured_media_id'] ?? 0),
            'status' => ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            'featured' => isset($_POST['featured']),
            'kind' => in_array($postedKind = (string)($_POST['kind'] ?? 'physical'), ['physical', 'digital', 'subscription'], true) ? $postedKind : 'physical',
            'subscription_interval' => in_array($postedInterval = (string)($_POST['subscription_interval'] ?? 'month'), ['month', 'year'], true) ? $postedInterval : 'month',
        ];
    }

    /** @param array<string,mixed> $product */
    private function productForm(array $product): string
    {
        $price = isset($product['price_minor']) ? number_format((int)$product['price_minor'] / 100, 2, '.', '') : '';
        $trackInventory = !isset($product['track_inventory']) || (int)$product['track_inventory'] === 1;
        $kind = (string)($product['kind'] ?? 'physical');
        $interval = (string)($product['subscription_interval'] ?? 'month');
        $productId = isset($product['id']) ? (int)$product['id'] : 0;

        // Per-item "Remove" mini-forms below cannot live inside the main
        // product-edit <form> - HTML has no nested <form> elements, and a
        // browser silently drops a re-entrant <form> start tag while one is
        // already open, then closes the OUTER form early at the inner
        // </form>, corrupting everything after it (Kind, Save button, etc).
        // So only the file-upload <input> (bundled into the main save POST)
        // stays inside the form; the attached-files list and the whole
        // gallery-images section render as siblings after </form> closes.
        $digitalUploadField = '<p class="muted" style="font-size:.8rem">Save the product first, then come back here to attach a file.</p>';
        $attachedFilesBlock = '';
        if ($productId > 0) {
            $filesHtml = '';
            foreach ($this->files->forProduct($productId) as $file) {
                $sizeKb = number_format((int)$file['size_bytes'] / 1024, 1);
                $filesHtml .= '<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e((string)$file['original_filename']).'</div><div class="admin-row-meta">'.$sizeKb.' KB &middot; '.e((string)$file['created_at']).'</div></div><div class="admin-row-actions">'
                    .'<form method="post" action="/admin/commerce/products/'.$productId.'/files/'.(int)$file['id'].'/delete" onsubmit="return confirm(&quot;Remove this file?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Remove</button></form>'
                    .'</div></div>';
            }
            $digitalUploadField = '<label style="display:block">Upload a file<input type="file" name="digital_file"></label>'
                .'<p class="muted" style="font-size:.8rem">Delivered as a link on the order confirmation page once payment is confirmed. Accepted: zip, pdf, epub, mp3, mp4, txt, docx (max 200MB).</p>';
            $attachedFilesBlock = '<div class="fslot"><label>Attached files</label>'.($filesHtml !== '' ? '<div class="admin-row-list">'.$filesHtml.'</div>' : '<p class="muted" style="font-size:.8rem">No files attached yet.</p>').'</div>';
        }

        $gallerySection = '<p class="muted" style="font-size:.8rem">Save the product first, then come back here to add gallery images.</p>';
        if ($productId > 0) {
            $imagesHtml = '';
            foreach ($this->images->forProduct($productId) as $image) {
                $thumb = function_exists('media_thumb_url') ? media_thumb_url(['stored_name' => $image['stored_name'], 'mime_type' => $image['mime_type'], 'has_thumb' => $image['has_thumb'] ?? 0]) : '';
                $imagesHtml .= '<div class="admin-row"><div class="admin-row-body"><div style="display:flex;align-items:center;gap:10px">'
                    .($thumb !== '' ? '<img src="'.e($thumb).'" alt="'.e((string)($image['alt_text'] ?? '')).'" style="width:60px;height:60px;object-fit:cover;border-radius:4px;flex:0 0 auto">' : '')
                    .'<span class="admin-row-title">'.e((string)$image['original_name']).'</span></div></div><div class="admin-row-actions">'
                    .'<form method="post" action="/admin/commerce/products/'.$productId.'/images/'.(int)$image['id'].'/delete" onsubmit="return confirm(&quot;Remove this image?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Remove</button></form>'
                    .'</div></div>';
            }
            $gallerySection = ($imagesHtml !== '' ? '<div class="admin-row-list">'.$imagesHtml.'</div>' : '<p class="muted" style="font-size:.8rem">No gallery images yet.</p>')
                .'<form method="post" action="/admin/commerce/products/'.$productId.'/images/add" style="margin-top:10px;display:flex;gap:8px;align-items:center"><input type="hidden" name="csrf" value="'.csrf().'">'
                .'<select name="add_image_media_id" style="flex:1">'.media_options(null, 'Choose an image').'</select>'
                .'<button class="btn ghost" type="submit">Add image</button></form>'
                .'<p class="muted" style="font-size:.8rem;margin-top:6px">Shown alongside the featured image on the product page.</p>';
        }

        $selectedCategoryId = (int)($product['category_id'] ?? 0);
        $categoryOptions = '<option value="0">No category</option>';
        foreach ($this->categories->tree() as $row) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$row['depth']);
            $selected = $selectedCategoryId === (int)$row['id'] ? ' selected' : '';
            $categoryOptions .= '<option value="'.(int)$row['id'].'"'.$selected.'>'.$indent.e((string)$row['name']).'</option>';
        }

        return '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body">'
            .'<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.csrf().'">'
            .'<div class="fgrid"><div class="fslot"><label>Name<input name="name" value="'.e((string)($product['name'] ?? '')).'" required></label></div>'
            .'<div class="fslot"><label>Slug<input name="slug" value="'.e((string)($product['slug'] ?? '')).'" placeholder="Leave empty to generate automatically"></label></div></div>'
            .'<div class="fslot"><label>Description<textarea name="description" style="min-height:120px">'.e((string)($product['description'] ?? '')).'</textarea></label></div>'
            .'<div class="fgrid three"><div class="fslot"><label>Price<input type="number" step="0.01" min="0" name="price" value="'.e($price).'" required></label></div>'
            .'<div class="fslot"><label>Currency<input name="currency" value="'.e((string)($product['currency'] ?? setting('payment_currency', 'EUR'))).'" maxlength="3"></label></div>'
            .'<div class="fslot"><label>SKU<input name="sku" value="'.e((string)($product['sku'] ?? '')).'"></label></div></div>'
            .'<div class="fgrid three"><div class="fslot"><label>Stock quantity<input type="number" min="0" name="stock_quantity" value="'.e((string)($product['stock_quantity'] ?? '0')).'"></label></div>'
            .'<div class="fslot"><label>&nbsp;</label><label class="check"><input type="checkbox" name="track_inventory" value="1"'.($trackInventory ? ' checked' : '').'> Track inventory</label></div>'
            .'<div class="fslot"><label>Category<select name="category_id">'.$categoryOptions.'</select></label><p class="muted" style="font-size:.8rem">Manage categories under <a href="/admin/commerce/categories">Categories</a>.</p></div></div>'
            .'<div class="fgrid three"><div class="fslot"><label>Featured image<select name="featured_media_id">'.media_options(isset($product['featured_media_id']) ? (int)$product['featured_media_id'] : null, 'No featured image').'</select></label></div>'
            .'<div class="fslot"><label>Status<select name="status"><option value="draft"'.((($product['status'] ?? 'draft') === 'draft') ? ' selected' : '').'>Draft</option><option value="published"'.((($product['status'] ?? '') === 'published') ? ' selected' : '').'>Published</option></select></label></div>'
            .'<div class="fslot"><label>&nbsp;</label><label class="check"><input type="checkbox" name="featured" value="1"'.((int)($product['featured'] ?? 0) === 1 ? ' checked' : '').'> Show in Recommended</label><p class="muted" style="font-size:.8rem;margin-top:2px">Featured products appear first in the shop\'s Recommended section.</p></div></div>'
            .'<div class="fgrid"><div class="fslot"><label>Kind<select name="kind" data-kind-select>'
            .'<option value="physical"'.($kind === 'physical' ? ' selected' : '').'>Physical</option>'
            .'<option value="digital"'.($kind === 'digital' ? ' selected' : '').'>Digital</option>'
            .'<option value="subscription"'.($kind === 'subscription' ? ' selected' : '').'>Subscription</option>'
            .'</select></label><p class="muted" style="font-size:.8rem">Physical products ship and track stock as before. Digital products deliver an attached file after payment. Subscriptions are admin-managed periods with no automated recurring billing (no payment gateway is wired up yet).</p></div></div>'
            .'<div data-kind-fields="digital" style="display:'.($kind === 'digital' ? 'block' : 'none').'"><div class="fslot"><label>Digital file</label>'.$digitalUploadField.'</div></div>'
            .'<div data-kind-fields="subscription" style="display:'.($kind === 'subscription' ? 'block' : 'none').'"><div class="fslot"><label>Billing interval<select name="subscription_interval">'
            .'<option value="month"'.($interval === 'month' ? ' selected' : '').'>Monthly</option>'
            .'<option value="year"'.($interval === 'year' ? ' selected' : '').'>Yearly</option>'
            .'</select></label><p class="muted" style="font-size:.8rem">When an order for this product is marked paid, the customer\'s subscription period is created or extended by this interval from today. Renewal is manual - there is no automated re-charge.</p></div></div>'
            .'<button class="btn" type="submit" style="margin-top:14px">Save product</button>'
            .'</form>'
            .($attachedFilesBlock !== '' ? '<div data-kind-fields="digital" style="display:'.($kind === 'digital' ? 'block' : 'none').'">'.$attachedFilesBlock.'</div>' : '')
            .'<div class="fslot"><label>Gallery images</label>'.$gallerySection.'</div>'
            .'</div>'
            .'<script>(function(){var panel=document.currentScript.previousElementSibling;var sel=panel.querySelector("[data-kind-select]");if(!sel)return;function sync(){panel.querySelectorAll("[data-kind-fields]").forEach(function(el){el.style.display=el.getAttribute("data-kind-fields")===sel.value?"block":"none";});}sel.addEventListener("change",sync);})();</script>'
            .'</div>';
    }
}
