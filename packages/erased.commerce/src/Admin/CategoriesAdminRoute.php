<?php
declare(strict_types=1);

namespace ErasedCommerce\Admin;

require_once __DIR__ . '/../Domain/CategoryRepository.php';

use ErasedCommerce\Domain\CategoryRepository;

final class CategoriesAdminRoute
{
    private readonly CategoryRepository $categories;

    public function __construct()
    {
        $this->categories = new CategoryRepository(db());
    }

    public function handle(string $id = ''): void
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        if ($id !== '' && str_ends_with($path, '/delete')) {
            $this->delete((int)$id);
            return;
        }
        if ($id !== '') {
            $this->edit((int)$id);
            return;
        }
        if ($path === '/admin/commerce/categories/new') {
            $this->create();
            return;
        }
        $this->list();
    }

    private function list(): void
    {
        $tree = $this->categories->tree();
        $counts = $this->categories->productCounts();

        $html = '';
        foreach ($tree as $row) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$row['depth']);
            $prefix = (int)$row['depth'] > 0 ? '↳ ' : '';
            $count = $counts[(int)$row['id']] ?? 0;
            $html .= '<div class="admin-row"><div class="admin-row-body">'
                . '<div class="admin-row-title">' . $indent . $prefix . e((string)$row['name']) . ' <span class="badge">' . $count . ' product' . ($count === 1 ? '' : 's') . '</span></div>'
                . '<div class="admin-row-meta">/shop/category/' . e((string)$row['slug']) . ($row['description'] !== '' ? ' &middot; ' . e((string)$row['description']) : '') . '</div>'
                . '</div><div class="admin-row-actions">'
                . '<a class="btn ghost" href="/admin/commerce/categories/' . (int)$row['id'] . '/edit">Edit</a>'
                . '<form method="post" action="/admin/commerce/categories/' . (int)$row['id'] . '/delete" onsubmit="return confirm(&quot;Delete this category? Products in it are not deleted, only unlinked.&quot;)"><input type="hidden" name="csrf" value="' . csrf() . '"><button class="btn danger">Delete</button></form>'
                . '</div></div>';
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-07 &middot; COMMERCE</p><h1>Categories</h1><p>Organize the product catalog into browsable categories, with up to one level of subcategories.</p></div><div class="actions"><a class="btn" href="/admin/commerce/categories/new">New category</a></div></div>'
            . '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All categories</h2></div><div class="panel-body"><div class="admin-row-list">' . ($html ?: '<div class="admin-row-empty">No categories yet.</div>') . '</div></div></div>';
        layout('Categories', $body, true);
    }

    private function create(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $data = $this->readFormInput(null);
            if ($data === null) {
                return;
            }
            $data['slug'] = $this->categories->uniqueSlug(slugify($data['slug'] !== '' ? $data['slug'] : $data['name']));
            $id = $this->categories->create($data);
            audit('commerce.category.create', ['id' => $id]);
            flash('success', 'Category created.');
            redirect('/admin/commerce/categories');
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-07 &middot; COMMERCE</p><h1>New category</h1></div></div>' . $this->categoryForm([]);
        layout('New category', $body, true);
    }

    private function edit(int $id): void
    {
        $category = fetch_or_404('commerce_categories', $id);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $data = $this->readFormInput($id);
            if ($data === null) {
                return;
            }
            $data['slug'] = $this->categories->uniqueSlug(slugify($data['slug'] !== '' ? $data['slug'] : $data['name']), $id);
            $this->categories->update($id, $data);
            audit('commerce.category.update', ['id' => $id]);
            flash('success', 'Category saved.');
            redirect('/admin/commerce/categories/' . $id . '/edit');
        }

        $body = '<div class="title-row"><div><p class="kicker">SHEET C-07 &middot; COMMERCE</p><h1>Edit category</h1></div></div>' . $this->categoryForm($category);
        layout('Edit category', $body, true);
    }

    private function delete(int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('/admin/commerce/categories');
        }
        verify_csrf();
        $this->categories->delete($id);
        audit('commerce.category.delete', ['id' => $id]);
        flash('success', 'Category deleted. Its products were kept, just unlinked from it.');
        redirect('/admin/commerce/categories');
    }

    /** @return array<string,mixed>|null null when validation failed (flash set, caller should return without redirecting) */
    private function readFormInput(?int $editingId): ?array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Enter a category name.');
            return null;
        }

        $parentId = (int)($_POST['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $this->categories->find($parentId);
            if ($parent === null) {
                flash('error', 'Choose a valid parent category.');
                return null;
            }
            // Two-level hierarchy only, matching the tree() renderer - a
            // category whose own parent is set can't itself become a parent.
            if ((int)($parent['parent_id'] ?? 0) > 0) {
                flash('error', 'A subcategory cannot itself have subcategories - choose a top-level category as the parent.');
                return null;
            }
            if ($editingId !== null && $parentId === $editingId) {
                flash('error', 'A category cannot be its own parent.');
                return null;
            }
        }

        return [
            'name' => $name,
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'parent_id' => $parentId,
            'image_media_id' => (int)($_POST['image_media_id'] ?? 0),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'icon' => trim((string)($_POST['icon'] ?? '')),
        ];
    }

    /** @param array<string,mixed> $category */
    private function categoryForm(array $category): string
    {
        $editingId = isset($category['id']) ? (int)$category['id'] : null;
        $parentOptions = '<option value="0">— Top level —</option>';
        foreach ($this->categories->all() as $row) {
            if ((int)($row['parent_id'] ?? 0) > 0) {
                continue; // only top-level categories can be a parent (two-level hierarchy)
            }
            if ($editingId !== null && (int)$row['id'] === $editingId) {
                continue; // can't be its own parent
            }
            $selected = (int)($category['parent_id'] ?? 0) === (int)$row['id'] ? ' selected' : '';
            $parentOptions .= '<option value="' . (int)$row['id'] . '"' . $selected . '>' . e((string)$row['name']) . '</option>';
        }

        return '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body">'
            . '<form method="post"><input type="hidden" name="csrf" value="' . csrf() . '">'
            . '<div class="fgrid three"><div class="fslot"><label>Name<input name="name" value="' . e((string)($category['name'] ?? '')) . '" required></label></div>'
            . '<div class="fslot"><label>Slug<input name="slug" value="' . e((string)($category['slug'] ?? '')) . '" placeholder="Leave empty to generate automatically"></label></div>'
            . '<div class="fslot"><label>Icon (one emoji)<input name="icon" value="' . e((string)($category['icon'] ?? '')) . '" maxlength="4" placeholder="🚗"></label><p class="muted" style="font-size:.8rem">Shown on the shop\'s category rail. Leave blank to fall back to the first letter.</p></div></div>'
            . '<div class="fslot"><label>Description<textarea name="description" style="min-height:80px">' . e((string)($category['description'] ?? '')) . '</textarea></label></div>'
            . '<div class="fgrid three"><div class="fslot"><label>Parent category<select name="parent_id">' . $parentOptions . '</select></label><p class="muted" style="font-size:.8rem">Leave as top level, or nest under one existing top-level category.</p></div>'
            . '<div class="fslot"><label>Category image<select name="image_media_id">' . media_options(($category['image_media_id'] ?? 0) ? (int)$category['image_media_id'] : null, 'No image') . '</select></label></div>'
            . '<div class="fslot"><label>Sort order<input type="number" name="sort_order" value="' . e((string)($category['sort_order'] ?? '0')) . '"></label><p class="muted" style="font-size:.8rem">Lower numbers appear first.</p></div></div>'
            . '<button class="btn" type="submit" style="margin-top:14px">Save category</button>'
            . '</form></div></div>';
    }
}
