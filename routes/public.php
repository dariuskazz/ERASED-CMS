<?php
declare(strict_types=1);

function erased_public_category_links(): string
{
    $rows = db()->query(
        "SELECT category, COUNT(*) total
         FROM content
         WHERE status='published'
           AND category IS NOT NULL
           AND category<>''
         GROUP BY category
         ORDER BY total DESC, category
         LIMIT 50"
    )->fetchAll();

    $limit = max(1, min(50, (int)setting('categories_widget_limit', '12')));
    $rows = array_slice($rows, 0, $limit);
    $links = '';

    foreach ($rows as $row) {
        $links .= '<a href="/posts?category='.rawurlencode((string)$row['category']).'">'
            .'<span>'.e($row['category']).'</span>'
            .'<small>'.(int)$row['total'].'</small>'
            .'</a>';
    }

    return $links !== ''
        ? $links
        : '<span class="homepage-empty">No categories yet.</span>';
}

function erased_public_tag_links(): string
{
    $rows = db()->query(
        "SELECT tags
         FROM content
         WHERE status='published'
           AND tags IS NOT NULL
           AND tags<>''
         ORDER BY updated_at DESC
         LIMIT 50"
    )->fetchAll();

    $counts = [];
    foreach ($rows as $row) {
        $tags = array_filter(array_map('trim', explode(',', (string)$row['tags'])));
        foreach ($tags as $tag) {
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }
    }

    arsort($counts);
    $limit = max(1, min(50, (int)setting('tags_widget_limit', '12')));
    $links = '';

    foreach (array_slice($counts, 0, $limit, true) as $tag => $count) {
        $links .= '<a href="/posts?tag='.rawurlencode((string)$tag).'">'.e($tag).'</a>';
    }

    return $links !== ''
        ? $links
        : '<span class="homepage-empty">No tags yet.</span>';
}

function erased_public_latest_comments(): string
{
    $comments = '';

    try {
        $rows = db()->query(
            "SELECT comments.author_name, comments.body, content.title, content.slug
             FROM comments
             JOIN content ON content.id=comments.content_id
             WHERE comments.status='approved'
             ORDER BY comments.created_at DESC
             LIMIT 5"
        )->fetchAll();

        foreach ($rows as $comment) {
            $body = trim(strip_tags((string)$comment['body']));
            if (mb_strlen($body) > 70) {
                $body = mb_substr($body, 0, 67).'…';
            }

            $comments .= '<div class="homepage-comment"><div>'
                .'<strong>'.e($comment['author_name'] ?: 'Reader').'</strong> on '
                .'<a href="/'.rawurlencode((string)$comment['slug']).'#comments">'.e($comment['title']).'</a>'
                .'<br><small>'.e($body).'</small>'
                .'</div></div>';
        }
    } catch (Throwable $e) {
        // Comments are optional on the public layout. Keep rendering if unavailable.
    }

    return $comments !== ''
        ? $comments
        : '<p class="homepage-empty">No comments yet.</p>';
}

function erased_public_addon_area(): string
{
    try {
        $count = (int)db()->query(
            "SELECT COUNT(*) FROM packages WHERE package_type='addon' AND enabled=1"
        )->fetchColumn();

        if ($count > 0) {
            return '<section class="card homepage-addon-slot">'
                .'<h2 class="homepage-section-title">Add-on area</h2>'
                .'</section>';
        }
    } catch (Throwable $e) {
        // Add-ons are optional. Keep rendering if their table is unavailable.
    }

    return '';
}

function erased_public_sidebar_blocks(?array $featured = null): array
{
    $blocks = [];

    if ($featured !== null) {
        $blocks['featured'] = '<div class="homepage-featured-card">'.public_article($featured).'</div>';
    } else {
        $blocks['featured'] = '<div class="card homepage-empty">'.e(tr('no_content')).'</div>';
    }

    $blocks['latest_posts'] = erased_latest_posts_widget(6, null);
    $blocks['categories'] = '<section class="card">'
        .'<h2 class="homepage-section-title">'.e(setting('categories_widget_title', 'Categories')).'</h2>'
        .'<div class="homepage-categories">'.erased_public_category_links().'</div>'
        .'</section>';
    $blocks['popular_tags'] = '<section class="card">'
        .'<h2 class="homepage-section-title">'.e(setting('tags_widget_title', 'Popular tags')).'</h2>'
        .'<div class="homepage-tags">'.erased_public_tag_links().'</div>'
        .'</section>';
    $blocks['addons'] = erased_public_addon_area();
    $blocks['latest_comments'] = '<section class="card">'
        .'<h2 class="homepage-section-title">Latest comments</h2>'
        .'<div class="homepage-comments">'.erased_public_latest_comments().'</div>'
        .'</section>';

    return $blocks;
}

function erased_public_region_layout(
    array $config,
    array $regions,
    array $blocks,
    bool $skipFeaturedInCenter = false
): string {
    foreach ($config['order'] as $block) {
        if (!in_array($block, $config['enabled'], true) || !array_key_exists($block, $blocks)) {
            continue;
        }

        if ($blocks[$block] === '') {
            continue;
        }

        $region = $config['regions'][$block]
            ?? (in_array($block, ['categories', 'popular_tags'], true)
                ? 'left'
                : (in_array($block, ['addons', 'latest_comments'], true) ? 'right' : 'center'));

        if (!in_array($region, $config['active_regions'], true)) {
            $region = 'center';
        }

        if ($skipFeaturedInCenter && $region === 'center' && in_array($block, ['featured', 'latest_posts'], true)) {
            continue;
        }

        $regions[$region] .= $blocks[$block];
    }

    $visibleRegions = [];
    $regionMarkup = '';

    foreach ($config['active_regions'] as $region) {
        $html = trim($regions[$region] ?? '');
        if ($html === '' && !($config['show_empty'] ?? false)) {
            continue;
        }

        if ($html === '') {
            $html = '<section class="card"><p class="homepage-empty">Empty region</p></section>';
        }

        $tag = $region === 'center' ? 'div' : 'aside';
        $role = $region === 'center' ? ' role="main"' : '';
        $regionMarkup .= '<'.$tag.' class="homepage-region homepage-'.e($region).'"'.$role.'>'
            .$html
            .'</'.$tag.'>';
        $visibleRegions[] = $region;
    }

    $classCount = max(1, count($visibleRegions));
    $regionClasses = '';
    foreach ($visibleRegions as $region) {
        $regionClasses .= ' homepage-has-'.preg_replace('/[^a-z0-9_-]/', '', (string)$region);
    }

    $widthStyle = homepage_studio_width_style($config, $visibleRegions);

    return homepage_studio_public_css()
        .'<div class="homepage-shell">'
        .'<section class="homepage-layout-three homepage-columns-'.$classCount.$regionClasses
        .' website-style-'.e((string)($config['style'] ?? 'standard')).'"'
        .' style="'.e($widthStyle).'">'
        .$regionMarkup
        .'</section></div>';
}

function erased_public_posts_page(): void
{
    $limit = max(1, min(100, (int)setting('posts_per_page', '10')));
    $query = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $tag = trim((string)($_GET['tag'] ?? ''));

    $where = [
        "type='post'",
        "status='published'",
        '(publish_at IS NULL OR publish_at<=NOW())',
    ];
    $params = [];

    if ($query !== '') {
        $where[] = '(title LIKE ? OR excerpt LIKE ? OR body LIKE ? OR category LIKE ? OR tags LIKE ?)';
        $like = '%'.$query.'%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if ($category !== '') {
        $where[] = 'category=?';
        $params[] = $category;
    }

    if ($tag !== '') {
        $where[] = "FIND_IN_SET(?,REPLACE(tags,', ',','))>0";
        $params[] = $tag;
    }

    $sql = 'SELECT * FROM content WHERE '.implode(' AND ', $where).' ORDER BY updated_at DESC LIMIT ?';
    $statement = db()->prepare($sql);
    $position = 1;

    foreach ($params as $value) {
        $statement->bindValue($position++, $value, PDO::PARAM_STR);
    }
    $statement->bindValue($position, $limit, PDO::PARAM_INT);
    $statement->execute();

    $list = '';
    foreach ($statement as $item) {
        $list .= public_article($item);
    }

    $center = $list ?: '<div class="card">No matching published posts found.</div>';
    $config = homepage_studio_config();
    $blocks = erased_public_sidebar_blocks();
    unset($blocks['featured']);

    $layout = erased_public_region_layout(
        $config,
        ['left' => '', 'center' => $center, 'right' => ''],
        $blocks
    );

    layout(tr('posts'), $layout);
    exit;
}

function erased_public_homepage(): void
{
    $homepage = homepage_studio_render_public();
    layout($homepage['title'], $homepage['html']);
    exit;
}

function erased_public_content_page(string $slug): bool
{
    if ($slug === '') {
        return false;
    }

    $statement = db()->prepare(
        "SELECT * FROM content
         WHERE slug=?
           AND status='published'
           AND (publish_at IS NULL OR publish_at<=NOW())
         LIMIT 1"
    );
    $statement->execute([$slug]);
    $content = $statement->fetch();

    if (!$content) {
        return false;
    }

    $items = db()->query(
        "SELECT * FROM content
         WHERE type='post'
           AND status='published'
           AND (publish_at IS NULL OR publish_at<=NOW())
         ORDER BY CASE WHEN homepage_order=0 THEN 1 ELSE 0 END,
                  homepage_order ASC,
                  updated_at DESC
         LIMIT 20"
    )->fetchAll();

    $featured = $items[0] ?? null;
    $config = homepage_studio_config();
    $blocks = erased_public_sidebar_blocks($featured);
    $center = v100_page_template($content, public_article($content, true));

    $layout = erased_public_region_layout(
        $config,
        ['left' => '', 'center' => $center, 'right' => ''],
        $blocks,
        true
    );

    layout($content['title'], $layout);
    exit;
}

function erased_public_not_found(): void
{
    $art = '';
    $mediaId = (int)setting('error_404_media_id', '0');

    if ($mediaId > 0) {
        $media = media_by_id($mediaId);
        if ($media && str_starts_with((string)$media['mime_type'], 'image/')) {
            $art = '<img class="not-found-art" src="'.media_url($media).'" alt="404">';
        }
    }

    http_response_code(404);
    layout(
        tr('not_found'),
        '<div class="card">'.$art
        .'<h1>404</h1>'
        .'<p>'.e(tr('page_not_found')).'</p>'
        .'<p><a class="btn secondary" href="/">Return to homepage</a></p>'
        .'</div>'
    );
}

function erased_dispatch_public_route(string $path): void
{
    if ($path === '/posts') {
        erased_public_posts_page();
        return;
    }

    if ($path === '/') {
        erased_public_homepage();
        return;
    }

    if (erased_public_content_page(trim($path, '/'))) {
        return;
    }

    erased_public_not_found();
}
