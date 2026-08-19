<?php
declare(strict_types=1);

if($path==='/gallery'||str_starts_with($path,'/gallery/')){
    $slug = trim(substr($path, 8), '/');
    $pdo = db();
    if ($slug !== '') {
        $s = $pdo->prepare("SELECT * FROM photo_galleries WHERE slug=? AND status='published' LIMIT 1");
        $s->execute([$slug]);
        $g = $s->fetch();
        if (!$g) {
            http_response_code(404);
            layout(tr('gallery_not_found'), '<div class="card"><h1>'.e(tr('gallery_not_found')).'</h1><p><a class="btn" href="/gallery">'.e(tr('return_to_galleries')).'</a></p></div>');
            exit;
        }
        $photos = json_decode((string)$g['images_json'], true) ?: [];
        $itemsJson = e(json_encode($photos, JSON_UNESCAPED_SLASHES));
        $grid = '';
        foreach ($photos as $idx => $p) {
            $url = e($p['url']);
            $cap = e($p['caption'] ?? '');
            $grid .= '<div class="photo-album-item" onclick="openErasedLightbox('.$itemsJson.', '.$idx.')"><img src="'.$url.'" alt="'.$cap.'" loading="lazy"><div class="photo-caption">'.$cap.'</div></div>';
        }
        $h = '<div class="toolbar"><div><h1>'.e($g['title']).'</h1><p class="muted">'.e($g['description'] ?: tr('photo_collection')).' · '.count($photos).' photos</p></div><a class="btn secondary" href="/gallery">'.e(tr('all_galleries')).'</a></div>';
        $h .= '<div class="photo-album-grid">'.($grid ?: '<div class="card">'.e(tr('no_photos_in_gallery')).'</div>').'</div>';
        layout($g['title'], $h);
        exit;
    }
    $galleries = $pdo->query("SELECT * FROM photo_galleries WHERE status='published' ORDER BY created_at DESC")->fetchAll();
    if (!$galleries) {
        $images = $pdo->query("SELECT * FROM media WHERE mime_type LIKE 'image/%' ORDER BY uploaded_at DESC")->fetchAll();
        $photos = array_map(fn($m) => ['id' => $m['id'], 'url' => media_url($m), 'caption' => $m['caption'] ?: $m['alt_text'] ?: $m['original_name']], $images);
        $itemsJson = e(json_encode($photos, JSON_UNESCAPED_SLASHES));
        $grid = '';
        foreach ($photos as $idx => $p) {
            $url = e($p['url']);
            $cap = e($p['caption'] ?? '');
            $grid .= '<div class="photo-album-item" onclick="openErasedLightbox('.$itemsJson.', '.$idx.')"><img src="'.$url.'" alt="'.$cap.'" loading="lazy"><div class="photo-caption">'.$cap.'</div></div>';
        }
        $h = '<div class="toolbar"><div><h1>'.e(tr('photo_gallery_title')).'</h1><p class="muted">'.e(tr('browse_all_photos')).'</p></div>'.(logged_in() && can('media.manage') ? '<a class="btn" href="/admin/galleries">'.e(tr('manage_galleries')).'</a>' : '').'</div>';
        $h .= '<div class="photo-album-grid">'.($grid ?: '<div class="card">'.e(tr('no_photos_uploaded_yet')).' <a href="/admin/media">'.e(tr('open_media_library')).'</a></div>').'</div>';
        layout(tr('photo_gallery_title'), $h);
        exit;
    }
    $cards = '';
    foreach ($galleries as $g) {
        $photos = json_decode((string)$g['images_json'], true) ?: [];
        $coverUrl = '';
        if (!empty($g['cover_media_id']) && ($m = media_by_id((int)$g['cover_media_id']))) {
            $coverUrl = media_url($m);
        } elseif ($photos && !empty($photos[0]['url'])) {
            $coverUrl = $photos[0]['url'];
        }
        $url = '/gallery/'.rawurlencode($g['slug']);
        $cards .= '<a class="gallery-card" href="'.$url.'">'.($coverUrl ? '<img src="'.e($coverUrl).'" alt="'.e($g['title']).'" loading="lazy">' : '<div class="media-file-icon" style="height:180px">GALLERY</div>').'<div class="gallery-card-body"><div class="gallery-card-title">'.e($g['title']).'</div><div class="gallery-card-meta">'.count($photos).' photos · '.date('M j, Y', strtotime($g['created_at'])).'</div></div></a>';
    }
    $h = '<div class="toolbar"><div><h1>'.e(tr('photo_galleries_title')).'</h1><p class="muted">'.e(tr('explore_photo_albums')).'</p></div>'.(logged_in() && can('media.manage') ? '<a class="btn" href="/admin/galleries">'.e(tr('manage_galleries')).'</a>' : '').'</div>';
    $h .= '<div class="gallery-grid">'.$cards.'</div>';
    layout(tr('photo_galleries_title'), $h);
    exit;
}
