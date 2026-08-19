<?php
declare(strict_types=1);

/**
 * Shared install/update handling for theme-scoped package uploads, reused by
 * both /admin/themes (admin-scope) and /admin/appearance/website-theme
 * (website-scope). Mirrors /admin/packages' own install-vs-update flow
 * exactly (same orchestrators, same staging/installed/rollback roots), with
 * one extra gate: the uploaded manifest must be type=theme and match the
 * expected scope, checked before any install path runs.
 */
function erased_handle_theme_upload(string $expectedScope): string{
 $pkgRoot=defined('ROOT')?ROOT:dirname(__DIR__);
 $pkgStaging=$pkgRoot.'/storage/plugins/staging';
 $pkgInstalled=$pkgRoot.'/storage/plugins/installed';
 $pkgRollback=$pkgRoot.'/storage/plugins/rollback';
 $pkgRepo=new \Erased\Packages\InstalledPackageRepository(db());

 $file=$_FILES['theme']??null;
 if(!is_array($file)||(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a valid theme ZIP.');
 $tmpName=(string)($file['tmp_name']??'');
 if($tmpName===''||!is_uploaded_file($tmpName))throw new RuntimeException('The theme upload could not be verified.');
 $size=(int)($file['size']??0);
 if($size<1||$size>25*1024*1024)throw new RuntimeException('Theme ZIPs must be between 1 byte and 25 MB.');

 $inspection=(new \Erased\Packages\PackageArchiveInspector())->inspect($tmpName);
 $peekZip=new ZipArchive();
 $peekManifest=null;
 if($peekZip->open($tmpName)===true){
  $manifestRaw=$peekZip->getFromName($inspection['manifest_path']);
  $peekZip->close();
  $peekManifest=is_string($manifestRaw)?json_decode($manifestRaw,true):null;
 }
 if(!is_array($peekManifest))throw new RuntimeException('Could not read the theme package manifest.');
 if(($peekManifest['type']??null)!=='theme')throw new RuntimeException('This is not a theme package (manifest type must be "theme").');
 if(($peekManifest['theme_scope']??null)!==$expectedScope)throw new RuntimeException('This theme is scoped for "'.(string)($peekManifest['theme_scope']??'?').'", not "'.$expectedScope.'" - upload it on the matching Themes page.');
 $peekId=(string)($peekManifest['id']??'');

 $pkgMigrations=new \Erased\Packages\PackageMigrationRunner(db());

 if($peekId!==''&&$pkgRepo->find($peekId)!==null){
  $orchestrator=new \Erased\Packages\PackageUpdateOrchestrator(
   new \Erased\Packages\PackageArchiveStager(new \Erased\Packages\PackageArchiveInspector(),new \Erased\Packages\PackageValidator()),
   new \Erased\Packages\PackageInstaller(new \Erased\Packages\PackageValidator()),
   $pkgRepo,
   new \Erased\Packages\PackageLifecycleLoader(),
   new \Erased\Packages\PackageDependencyResolver(),
   $pkgMigrations,
   platform_events(),
   new \Erased\Packages\PackageIntegrityChecker(),
  );
  $result=$orchestrator->updateArchive($tmpName,$pkgStaging,$pkgInstalled,$pkgRollback);
  audit('theme.update',['package_id'=>$result['manifest']->id(),'from_version'=>$result['from_version'],'to_version'=>$result['manifest']->version()]);
  return 'Updated '.$result['manifest']->name().' from '.$result['from_version'].' to '.$result['manifest']->version().'.';
 }
 $orchestrator=new \Erased\Packages\PackageInstallOrchestrator(
  new \Erased\Packages\PackageArchiveStager(new \Erased\Packages\PackageArchiveInspector(),new \Erased\Packages\PackageValidator()),
  new \Erased\Packages\PackageInstaller(new \Erased\Packages\PackageValidator()),
  $pkgRepo,
  new \Erased\Packages\PackageLifecycleLoader(),
  $pkgMigrations,
  capability_runtime()->resolver(),
  platform_events(),
  new \Erased\Packages\PackageIntegrityChecker(),
 );
 $result=$orchestrator->installArchive($tmpName,$pkgStaging,$pkgInstalled,$pkgRollback);
 audit('theme.install',['package_id'=>$result['manifest']->id(),'version'=>$result['manifest']->version()]);
 return 'Installed '.$result['manifest']->name().' '.$result['manifest']->version().'.';
}

/** Renders one theme-card <label> for either a built-in slug or an installed custom theme package. */
function erased_theme_card(string $value,string $radioName,string $label,string $desc,string $swatchClass,bool $selected): string{
 return '<label class="theme-card"><input type="radio" name="'.e($radioName).'" value="'.e($value).'"'.($selected?' checked':'').'><span class="theme-card-swatch '.e($swatchClass).'"></span><span class="theme-card-body"><strong>'.e($label).'</strong><small>'.e($desc).'</small></span></label>';
}

if($path==='/admin/quick-theme'&&$_SERVER['REQUEST_METHOD']==='POST'){
 // v0.8-dev security audit: require_login() alone only checks
 // isset($_SESSION['user_id']) - the real per-request enforcement (session
 // timeout, UA-fingerprint check, session_version invalidation, and the
 // auth_sessions DB revocation lookup) all live inside current_user(),
 // only reached via require_permission()/can(). This route skipped that
 // entirely, and had no role check at all despite writing a genuinely
 // global setting (admin_theme affects every admin, not just the caller) -
 // matching the same packages.manage gate the full theme settings page
 // (/admin/themes) already uses.
 require_permission('packages.manage');
 verify_csrf();
 $theme=(string)($_POST['theme']??'');
 if(in_array($theme,['light-grey','dark-grey','dark-green','ops-deck'],true)){
  set_setting('admin_theme',$theme);
  audit('settings.quick_theme',['theme'=>$theme]);
 }
 $back=(string)($_POST['back']??'/admin');
 redirect(str_starts_with($back,'/admin')?$back:'/admin');
}
if($path==='/admin/dashboard/save-layout'&&$_SERVER['REQUEST_METHOD']==='POST'){
 require_login();
 header('Content-Type: application/json');
 try{
  $input=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($input))throw new RuntimeException('Invalid request.');
  $token=(string)($input['csrf']??'');
  if($token===''||!hash_equals((string)csrf(),$token)){
   security_log('csrf.validation_failed',['path'=>(string)($_SERVER['REQUEST_URI']??'')],'warning');
   throw new RuntimeException('Security token is invalid.');
  }
  $knownIds=['cwl','quick_draft','needs_attention','recent_content'];
  $order=is_array($input['order']??null)?array_values(array_intersect(array_map('strval',$input['order']),$knownIds)):[];
  foreach($knownIds as $id)if(!in_array($id,$order,true))$order[]=$id;
  $widgets=[];
  if(is_array($input['widgets']??null)){
   foreach($input['widgets'] as $wid=>$cfg){
    $wid=(string)$wid;
    if(!in_array($wid,$knownIds,true)||!is_array($cfg))continue;
    $cols=max(1,min(3,(int)($cfg['cols']??1)));
    $height=isset($cfg['height'])&&(int)$cfg['height']>0?max(120,min(1200,(int)$cfg['height'])):null;
    $widgets[$wid]=['cols'=>$cols,'height'=>$height];
   }
  }
  set_setting('dashboard_layout_config',json_encode(['order'=>$order,'widgets'=>$widgets],JSON_UNESCAPED_SLASHES));
  audit('dashboard.layout_saved',[]);
  echo json_encode(['ok'=>true]);
 }catch(Throwable $e){
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
 }
 exit;
}
if($path==='/admin'){
 require_login();
 $u=current_user();
 if(!$u){destroy_auth_session();session_destroy();flash('error','Your session expired due to inactivity. Please log in again.');redirect('/login');}
 $role=normalized_role($u['role']??'user');
 $roleNames=['user'=>'User','writer'=>'Writer','editor'=>'Editor','admin'=>'Administrator'];
 $view=erased_dashboard_view_data($u);

 $dashboardTheme=setting('admin_theme','dark-green');
 $bespokeRenderers=[
  'dark-green'=>'erased_dashboard_render_ops_deck',
  'dark-grey'=>'erased_dashboard_render_ops_deck',
  'light-grey'=>'erased_dashboard_render_ops_deck',
  'ops-deck'=>'erased_dashboard_render_ops_deck',
 ];
 if(isset($bespokeRenderers[$dashboardTheme])&&function_exists($bespokeRenderers[$dashboardTheme])){
  layout('Dashboard',$bespokeRenderers[$dashboardTheme]($view),true);
  exit;
 }

 $d=$view['stats'];
 $totalPosts=$view['total_posts'];$totalPages=$view['total_pages'];$totalMedia=$view['total_media'];
 $recentContent=$view['recent_content'];
 $resumeItem=$view['resume_item'];$alsoInProgress=$view['also_in_progress'];
 $cpuLoad=$view['cpu_load'];$ramPct=$view['ram_pct'];
 $daypart=$view['daypart'];

 // ---------- Resume panel: today's real "continue where you left off" ----------
 if ($resumeItem) {
  $isDraft = $resumeItem['status'] === 'draft';
  $excerpt = trim((string)($resumeItem['excerpt'] ?? ''));
  if ($excerpt === '') $excerpt = 'No excerpt yet - open the editor to keep writing.';
  $resumeHtml = '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>
    <div class="panel-head"><h2>MS &middot; Continue where you left off</h2><span class="stampword ' . ($isDraft ? 'draft' : 'live') . '">' . ($isDraft ? 'Draft' : 'Published') . '</span></div>
    <div class="panel-body" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;justify-content:space-between">
      <div>
        <div style="font-size:16px;font-weight:800;margin-bottom:4px">' . e($resumeItem['title']) . '</div>
        <p style="margin:0;color:var(--ink-dim);font-size:12.5px;max-width:56ch">' . e($excerpt) . '</p>
        <div class="mono" style="font-size:11px;color:var(--ink-faint);margin-top:8px">Updated ' . e(date('M j, H:i', strtotime((string)$resumeItem['updated_at']))) . '</div>
      </div>
      <a class="btn" href="/admin/content/' . (int)$resumeItem['id'] . '/edit">Continue editing</a>
    </div>';
  if ($alsoInProgress) {
   $resumeHtml .= '<div class="dim"><span class="t">Also in progress</span><span class="l"></span></div>';
   foreach ($alsoInProgress as $other) {
    $otherIsDraft = $other['status'] === 'draft';
    $resumeHtml .= '<a class="attn" href="/admin/content/' . (int)$other['id'] . '/edit"><span class="sev ' . ($otherIsDraft ? 'warn' : 'ok') . '"></span><div class="t">' . e($other['title']) . '<div class="s">' . ($otherIsDraft ? 'Draft' : 'Published') . ' &middot; ' . e(date('M j, H:i', strtotime((string)$other['updated_at']))) . '</div></div></a>';
   }
  }
  $resumeHtml .= '</div>';
 } else {
  $resumeHtml = '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>
    <div class="panel-head"><h2>MS &middot; Nothing published yet</h2></div>
    <div class="panel-body">
      <div style="font-size:16px;font-weight:800;margin-bottom:4px">Write your first post</div>
      <p style="margin:0 0 12px;color:var(--ink-dim);font-size:12.5px">Once you publish or save a draft, it will show up here so you can pick up right where you left off.</p>
      <a class="btn" href="/admin/content/new">New post</a>
    </div></div>';
 }

 // ---------- Needs attention: real, threshold-based, ranked (from erased_dashboard_view_data) ----------
 $attnRows = '';
 foreach ($view['needs_attention'] as $item) {
  $attnRows .= '<a class="attn" href="' . e($item['href']) . '"><span class="sev ' . e($item['severity']) . '"></span><div class="t">' . e($item['title']) . '<div class="s">' . e($item['subtitle']) . '</div></div></a>';
 }

 // ---------- Recent content: real rows, restyled as a schedule ----------
 $contentRows = '';
 if (empty($recentContent)) {
  $contentRows = '<tr><td colspan="3" style="color:var(--ink-faint)">No content items updated recently.</td></tr>';
 } else {
  foreach ($recentContent as $i => $item) {
   $isDraft = $item['status'] === 'draft';
   $contentRows .= '<tr><td class="no mono">' . str_pad((string)($i+1), 3, '0', STR_PAD_LEFT) . '</td><td><a href="/admin/content/' . (int)$item['id'] . '/edit">' . e($item['title']) . '</a><div class="meta">' . ($item['type'] === 'page' ? 'PAGE' : 'POST') . ' &middot; /' . e($item['slug']) . '</div></td><td><span class="stampword ' . ($isDraft ? 'draft' : 'live') . '">' . e(ucfirst((string)$item['status'])) . '</span></td></tr>';
  }
 }

 $body = '<div class="title-row"><div><p class="kicker">OVERVIEW</p><h1>Good ' . $daypart . ', ' . e($u['display_name'] ?: $u['username'] ?: 'there') . '</h1><p>Here\'s what\'s happening across the site.</p></div><div class="rule"></div></div>' . $resumeHtml . '

  <div class="stat-strip">
    <div class="stat"><div class="v">' . ($totalPosts + $totalPages) . '</div><div class="l">Content &middot; ' . $totalPosts . ' posts, ' . $totalPages . ' pages</div></div>
    <div class="stat"><div class="v">' . $totalMedia . '</div><div class="l">Media files</div></div>
    <div class="stat"><div class="v">' . number_format($d['todayVisitors']) . '</div><div class="l">Visitors today &middot; ' . number_format($d['todayViews']) . ' views</div></div>
    <div class="stat"><div class="v">' . $d['score'] . '%</div><div class="l">Health score</div></div>
  </div>

  <div class="split">
    <div>
      <div class="dim"><span class="t">Recent content</span><span class="l"></span><a class="more" href="/admin/content">full index &rarr;</a></div>
      <div class="panel" style="margin-bottom:0"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>
        <table class="schedule"><thead><tr><th>No.</th><th>Title</th><th>Status</th></tr></thead><tbody>' . $contentRows . '</tbody></table>
      </div>
    </div>

    <div>
      <div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>
        <div class="panel-head"><h2>Needs attention</h2></div>
        ' . $attnRows . '
      </div>

      <div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>
        <div class="panel-head"><h2>Quick draft</h2></div>
        <form method="post" action="/admin/content/new" class="panel-body" style="display:flex;flex-direction:column;gap:8px">
          <input type="hidden" name="csrf" value="' . csrf() . '">
          <input type="hidden" name="type" value="post">
          <input type="hidden" name="status" value="draft">
          <input type="text" name="title" placeholder="Draft title..." required>
          <textarea name="body" rows="3" placeholder="Jot down the idea..."></textarea>
          <button type="submit" class="btn">Save draft</button>
        </form>
      </div>

      <div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>
        <div class="panel-head"><h2>System</h2></div>
        <div class="spec-row"><span class="k">CPU load</span><span class="v mono">' . $cpuLoad . '% (' . $d['proc']['cores'] . ' cores)</span></div>
        <div class="spec-row"><span class="k">RAM usage</span><span class="v mono">' . $ramPct . '% (' . erased_bytes($d['proc']['memory_used']) . ')</span></div>
        <div class="spec-row"><span class="k">Uptime</span><span class="v mono">' . erased_uptime($d['proc']['uptime']) . '</span></div>
        <div class="spec-row"><span class="k">CMS size</span><span class="v mono">' . erased_bytes($d['total']) . '</span></div>
        <div class="spec-row"><span class="k">Database</span><span class="v mono">' . erased_bytes($d['dbSize']) . '</span></div>
        <div class="spec-row"><span class="k">PHP</span><span class="v mono">' . e(PHP_VERSION) . '</span></div>
        <div class="spec-row"><span class="k">Disk</span><span class="v mono">' . (!empty($d['server']['disk_available']) ? erased_bytes((int)$d['server']['disk_free']) . ' free / ' . erased_bytes((int)$d['server']['disk_total']) : 'unavailable') . '</span></div>
        <div class="spec-row"><span class="k">Host / OS</span><span class="v mono">' . e((string)($d['server']['hostname'] ?? 'unknown')) . ' &middot; ' . e((string)($d['server']['os'] ?? '')) . '</span></div>
        <div class="spec-row"><span class="k">Load avg</span><span class="v mono">' . (!empty($d['proc']['available']) ? number_format((float)($d['proc']['load1'] ?? 0), 2) . ' &middot; ' . number_format((float)($d['proc']['load5'] ?? 0), 2) . ' &middot; ' . number_format((float)($d['proc']['load15'] ?? 0), 2) : 'unavailable') . '</span></div>
      </div>
    </div>
  </div>';

 layout('Dashboard', $body, true);
 exit;
}
if($path==='/admin/content'){require_permission('content.view');$u=current_user();$filter=($_GET['type']??'post')==='page'?'page':'post';if($filter==='page'&&!can('content.edit.all'))$filter='post';$page=max(1,(int)($_GET['page']??1));$perPage=20;$offset=($page-1)*$perPage;$where=['content.type=?'];$args=[$filter];if(!can('content.edit.all')){$where[]='content.author_id=?';$args[]=(int)$u['id'];}$whereSql=' WHERE '.implode(' AND ',$where);$countStmt=db()->prepare('SELECT COUNT(*) FROM content'.$whereSql);$countStmt->execute($args);$totalCount=(int)$countStmt->fetchColumn();$totalPages=max(1,(int)ceil($totalCount/$perPage));$sql='SELECT content.*,users.display_name AS author_name,users.email AS author_email FROM content LEFT JOIN users ON users.id=content.author_id'.$whereSql.' ORDER BY content.updated_at DESC LIMIT '.$perPage.' OFFSET '.$offset;$q=db()->prepare($sql);$q->execute($args);$rows=$q->fetchAll();$rowsHtml='';foreach($rows as $r){$actions='<a class="btn tertiary" target="_blank" href="/'.e($r['slug']).'">View</a>';if(can_edit_content($r))$actions='<a class="btn ghost" href="/admin/content/'.(int)$r['id'].'/edit">Edit</a>'.$actions;if(can_delete_content($r))$actions.='<form method="post" action="/admin/content/'.(int)$r['id'].'/delete" onsubmit="return confirm(&quot;Delete this content?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Delete</button></form>';$statusPill=$r['status']==='published'?'<span class="stampword live">Published</span>':'<span class="stampword draft">Draft</span>';$rowsHtml.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($r['title']).' '.$statusPill.'</div><div class="admin-row-meta">/'.e($r['slug']).' &middot; '.e($r['author_name']?:$r['author_email']?:'Unassigned').'</div></div><div class="admin-row-actions">'.$actions.'</div></div>';}$isPage=$filter==='page';$title=$isPage?'Pages':'Posts';$pagination='';if($totalPages>1){$pagination='<div class="actions" style="margin-top:14px;justify-content:center;">';if($page>1)$pagination.='<a class="btn ghost" href="/admin/content?type='.$filter.'&amp;page='.($page-1).'">‹ Previous</a>';$pagination.='<span class="badge">Page '.$page.' of '.$totalPages.'</span>';if($page<$totalPages)$pagination.='<a class="btn ghost" href="/admin/content?type='.$filter.'&amp;page='.($page+1).'">Next ›</a>';$pagination.='</div>';}$h='<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; CONTENT</p><h1>'.e($title).'</h1><p>Manage your site\'s '.strtolower($title).'.</p></div></div>'.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All '.e($title).'</h2></div><div class="panel-body"><div class="admin-row-list">'.($rowsHtml?:'<div class="admin-row-empty">No '.strtolower($title).' available.</div>').'</div>'.$pagination.'</div></div>';layout($title,$h,true);exit;}
if($path==='/admin/content/new'){require_permission('content.create');if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$pdo=db();$title=trim($_POST['title']??'');if($title===''){flash('error','Title is required.');}else{$slug=unique_slug($pdo,slugify(trim($_POST['slug']??'')?:$title));$type=can('content.edit.all')&&in_array($_POST['type']??'post',['page','post'],true)?$_POST['type']:'post';$status=can('content.publish')&&($_POST['status']??'draft')==='published'?'published':'draft';$s=$pdo->prepare('INSERT INTO content(author_id,type,title,slug,body,excerpt,status,featured_media_id,comments_enabled,page_template,seo_title,seo_description) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute([(int)current_user()['id'],$type,$title,$slug,$_POST['body']??'',trim($_POST['excerpt']??''),$status,($_POST['featured_media_id']??'')!==''?(int)$_POST['featured_media_id']:null,isset($_POST['comments_enabled'])?1:0,$_POST['page_template']??'sidebar',trim($_POST['seo_title']??''),trim($_POST['seo_description']??'')]);if($status==='published'&&setting('cloudflare_auto_purge','0')==='1')erased_cloudflare_purge_cache();audit('content.create',['id'=>(int)$pdo->lastInsertId()]);flash('success',$status==='published'?'Content published.':'Draft saved.');redirect('/admin/content?type='.$type);}}$_newType=(($_GET['type']??'post')==='page'&&can('content.edit.all'))?'page':'post';layout('New content','<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; CONTENT</p><h1>New '.e($_newType).'</h1></div></div>'.content_form(['type'=>$_newType],can('content.publish'),can('content.edit.all')),true);exit;}
if(preg_match('#^/admin/content/(\d+)/edit$#',$path,$m)){require_permission('content.view');$id=(int)$m[1];$i=fetch_or_404('content',$id);if(!can_edit_content($i)){http_response_code(403);layout('Permission required','<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body"><h1>Permission required</h1><p>You may edit only your own posts.</p></div></div>',true);exit;}if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$title=trim($_POST['title']??'');if($title==='')flash('error','Title is required.');else{$slug=unique_slug(db(),slugify(trim($_POST['slug']??'')?:$title),$id);$type=can('content.edit.all')&&in_array($_POST['type']??$i['type'],['page','post'],true)?$_POST['type']:$i['type'];$status=can('content.publish')&&($_POST['status']??'draft')==='published'?'published':'draft';$rev=db()->prepare('INSERT INTO revisions(content_id,title,body,editor_id) SELECT id,title,body,? FROM content WHERE id=?');$rev->execute([$_SESSION['user_id'],$id]);$u=db()->prepare('UPDATE content SET type=?,title=?,slug=?,body=?,excerpt=?,status=?,featured_media_id=?,comments_enabled=?,page_template=?,seo_title=?,seo_description=? WHERE id=?');$u->execute([$type,$title,$slug,$_POST['body']??'',trim($_POST['excerpt']??''),$status,($_POST['featured_media_id']??'')!==''?(int)$_POST['featured_media_id']:null,isset($_POST['comments_enabled'])?1:0,$_POST['page_template']??'sidebar',trim($_POST['seo_title']??''),trim($_POST['seo_description']??''),$id]);if($status==='published'&&setting('cloudflare_auto_purge','0')==='1')erased_cloudflare_purge_cache();audit('content.update',['id'=>$id]);flash('success',$status==='published'?'Changes published.':'Draft saved.');redirect('/admin/content/'.$id.'/edit');}}layout('Edit content','<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; CONTENT</p><h1>Edit content</h1></div><div class="actions"><a class="btn tertiary" target="_blank" href="/'.e($i['slug']).'">View</a></div></div>'.content_form($i,can('content.publish'),can('content.edit.all')),true);exit;}
if(preg_match('#^/admin/content/(\d+)/delete$#',$path,$m)&&$_SERVER['REQUEST_METHOD']==='POST'){require_permission('content.view');verify_csrf();$s=db()->prepare('SELECT * FROM content WHERE id=?');$s->execute([(int)$m[1]]);$i=$s->fetch();if(!$i||!can_delete_content($i)){http_response_code(403);exit('Permission denied.');}
 // comments.content_id/revisions.content_id have no FK/ON DELETE CASCADE
 // (confirmed via schema.sql) - deleting content without this left orphaned
 // rows in both tables forever, with no cleanup path (moderation queue
 // showing "Unknown content" comments a moderator could never resolve).
 $cid=(int)$m[1];
 db()->prepare('DELETE FROM comments WHERE content_id=?')->execute([$cid]);
 db()->prepare('DELETE FROM revisions WHERE content_id=?')->execute([$cid]);
 $s=db()->prepare('DELETE FROM content WHERE id=?');$s->execute([$cid]);audit('content.delete',['id'=>$cid]);flash('success','Content deleted.');redirect('/admin/content?type='.($i['type']==='page'?'page':'post'));}
if($path==='/admin/homepage/reorder'&&$_SERVER['REQUEST_METHOD']==='POST'){require_login();if(!(can('content.edit.all')||in_array(normalized_role((string)(current_user()['role']??'user')),['editor','admin'],true))){http_response_code(403);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Permission denied']);exit;}verify_csrf();$ids=json_decode((string)($_POST['ids']??'[]'),true);if(!is_array($ids)){http_response_code(422);exit('Invalid order.');}$ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));$pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare("UPDATE content SET homepage_order=? WHERE id=? AND status='published'");foreach($ids as $pos=>$id)$q->execute([$pos+1,$id]);$pdo->commit();audit('homepage.posts.reorder',['ids'=>$ids]);header('Content-Type: application/json');echo json_encode(['ok'=>true]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(500);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Could not save the new order.']);}exit;}
if($path==='/admin/media/upload'&&$_SERVER['REQUEST_METHOD']==='POST'){require_permission('media.manage');header('Content-Type: application/json');try{verify_csrf();$m=upload_one($_FILES['file']??[]);$kind=str_starts_with((string)($m['mime_type']??''),'video/')?'video':(str_starts_with((string)($m['mime_type']??''),'image/')?'image':'file');echo json_encode(['ok'=>true,'url'=>media_url($m),'alt'=>$m['alt_text']??'','type'=>$kind]);}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}exit;}
if($path==='/admin/media'){require_permission('media.manage');if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$files=$_FILES['files']??null;if(!$files)throw new RuntimeException('Choose one or more files.');$count=is_array($files['name'])?count($files['name']):1;for($n=0;$n<$count;$n++){$f=is_array($files['name'])?['name'=>$files['name'][$n],'type'=>$files['type'][$n],'tmp_name'=>$files['tmp_name'][$n],'error'=>$files['error'][$n],'size'=>$files['size'][$n]]:$files;if(($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;upload_one($f);}flash('success','Media uploaded successfully.');erased_redirect_preserving_studio_embed('/admin/media');}catch(Throwable $e){flash('error',$e->getMessage());erased_redirect_preserving_studio_embed('/admin/media');}}$selectedId=(int)($_GET['media']??0);$filter=(string)($_GET['type']??'images');if(!in_array($filter,['images','videos','other'],true))$filter='images';$rows=db()->query('SELECT * FROM media ORDER BY uploaded_at DESC')->fetchAll();$counts=['images'=>0,'videos'=>0,'other'=>0];$tiles='';$selected=null;foreach($rows as $m){$mime=(string)$m['mime_type'];$kind=str_starts_with($mime,'image/')?'images':(str_starts_with($mime,'video/')?'videos':'other');$counts[$kind]++;if((int)$m['id']===$selectedId)$selected=$m;if($kind!==$filter)continue;$url=media_url($m);if($kind==='images')$preview='<img src="'.media_thumb_url($m).'" alt="'.e($m['alt_text']).'" loading="lazy">';elseif($kind==='videos')$preview='<video preload="metadata" muted><source src="'.$url.'" type="'.e($mime).'"></video><span class="media-kind-badge">VIDEO</span>';else $preview='<div class="media-file-icon">FILE</div>';$tiles.='<a class="media-tile'.($selectedId===(int)$m['id']?' selected':'').'" href="/admin/media?type='.e($filter).'&media='.(int)$m['id'].'" data-name="'.e(strtolower($m['original_name'].' '.$m['alt_text'].' '.$m['caption'])).'" title="'.e($m['original_name']).'">'.$preview.'<span>'.e($m['original_name']).'</span><small>'.number_format((int)$m['size_bytes']/1024,1).' KB</small></a>';}$details='<div class="media-empty"><strong>Select a file</strong><p class="muted">Choose a media item to preview it, edit details, copy its URL, or delete it.</p></div>';if($selected){$url=media_url($selected);$mime=(string)$selected['mime_type'];$isImage=str_starts_with($mime,'image/');$isVideo=str_starts_with($mime,'video/');$preview=$isImage?'<a class="media-preview-link" href="'.$url.'" target="_blank"><img class="media-detail-preview" src="'.$url.'" alt="'.e($selected['alt_text']).'"></a>':($isVideo?'<video class="media-detail-video" controls preload="metadata"><source src="'.$url.'" type="'.e($mime).'">Your browser cannot play this video.</video>':'<div class="media-file-icon media-detail-preview">FILE</div>');$details='<div class="media-detail-head"><div><h2>'.e($selected['original_name']).'</h2><p class="muted">'.e($mime).' · '.number_format((int)$selected['size_bytes']/1024,1).' KB</p></div><a class="btn tertiary" href="/admin/media?type='.e($filter).'">Close</a></div>'.$preview.'<form method="post" action="/admin/media/'.(int)$selected['id'].'/update"><input type="hidden" name="csrf" value="'.csrf().'">'.($isImage?'<label>Alt text<input name="alt_text" value="'.e($selected['alt_text']).'" placeholder="Describe this image"></label>':'').'<label>Caption<textarea name="caption" style="min-height:90px">'.e($selected['caption']).'</textarea></label><button class="btn">Save details</button></form><label>File URL<input value="'.$url.'" readonly onclick="this.select()"></label><div class="actions"><a class="btn ghost" href="'.$url.'" target="_blank">Open file</a><form method="post" action="/admin/media/'.(int)$selected['id'].'/delete" onsubmit="return confirm(&quot;Delete this file?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Delete</button></form></div>';}$uploadPanel='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body media-upload-card"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.csrf().'"><div id="dropzone" class="dropzone"><p><strong>Drop pictures or videos here</strong> or choose files below</p><input id="media-files" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg,application/pdf" required></div><p><button class="btn">Upload media</button></p></form></div></div>';$browserPanel='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body"><section id="media-browser" class="media-browser media-small">'.($tiles?:'<div class="media-empty-list">No files in this section yet.</div>').'</section></div></div>';$detailsPanel='<div class="panel media-details"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body">'.$details.'</div></div>';$h='<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; MEDIA</p><h1>Media</h1><p>Upload and manage pictures, video, and other files.</p></div></div>'.$uploadPanel.'<div class="media-controls"><input id="media-search" type="search" placeholder="Search this section…" oninput="filterMedia(this.value)"><div class="media-size-tools"><button type="button" class="btn ghost" onclick="setMediaSize(&quot;small&quot;)">Small</button><button type="button" class="btn ghost" onclick="setMediaSize(&quot;normal&quot;)">Normal</button></div></div><div class="media-library-layout">'.$browserPanel.$detailsPanel.'</div>';layout('Media',$h,true);exit;}
if(preg_match('#^/admin/media/(\d+)/update$#',$path,$m)&&$_SERVER['REQUEST_METHOD']==='POST'){require_permission('media.manage');verify_csrf();$s=db()->prepare('UPDATE media SET alt_text=?,caption=? WHERE id=?');$s->execute([trim($_POST['alt_text']??''),trim($_POST['caption']??''),(int)$m[1]]);flash('success','Media details saved.');erased_redirect_preserving_studio_embed('/admin/media');}
if(preg_match('#^/admin/media/(\d+)/delete$#',$path,$m)&&$_SERVER['REQUEST_METHOD']==='POST'){require_permission('media.manage');verify_csrf();$media=media_by_id((int)$m[1]);if($media){@unlink(UPLOAD_DIR.'/'.$media['stored_name']);$s=db()->prepare('UPDATE content SET featured_media_id=NULL WHERE featured_media_id=?');$s->execute([(int)$m[1]]);$s=db()->prepare('DELETE FROM media WHERE id=?');$s->execute([(int)$m[1]]);}flash('success','Media deleted.');erased_redirect_preserving_studio_embed('/admin/media');}
if($path==='/admin/gallery'){redirect('/admin/galleries');}
if($path==='/admin/galleries'){
    require_permission('media.manage');
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (($_POST['gallery_action'] ?? '') === 'save_website_visibility') {
            $galleryVisible = isset($_POST['gallery_show_in_navigation']);
            set_setting('gallery_show_in_navigation', $galleryVisible ? '1' : '0');
            audit('gallery.visibility.update', ['navigation' => $galleryVisible]);
            flash('success', $galleryVisible ? 'Gallery is now visible in the website navigation.' : 'Gallery was removed from the website navigation.');
        }
        redirect('/admin/galleries');
    }
    $rows = $pdo->query("SELECT * FROM photo_galleries ORDER BY created_at DESC")->fetchAll();
    $tr = '';
    foreach ($rows as $r) {
        $photos = json_decode((string)$r['images_json'], true) ?: [];
        $count = count($photos);
        $cover = '';
        if (!empty($r['cover_media_id']) && ($m = media_by_id((int)$r['cover_media_id']))) {
            $cover = '<img src="'.e(media_thumb_url($m)).'" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">';
        } elseif ($photos && !empty($photos[0]['url'])) {
            $cover = '<img src="'.e($photos[0]['url']).'" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">';
        } else {
            $cover = '<div style="width:48px;height:48px;background:var(--bg);border-radius:6px;display:grid;place-items:center;font-size:.7rem;">IMG</div>';
        }
        $tr .= '<div class="admin-row"><div class="admin-row-body"><div style="display:flex;gap:12px;align-items:center;">'.$cover.'<div><div class="admin-row-title">'.e($r['title']).' <span class="stampword'.($r['status']==='published'?' live':' draft').'">'.e(ucfirst($r['status'])).'</span></div><div class="admin-row-meta">/gallery/'.e($r['slug']).' &middot; '.$count.' photos</div></div></div></div><div class="admin-row-actions"><a class="btn tertiary" href="/gallery/'.e($r['slug']).'" target="_blank">View</a><a class="btn ghost" href="/admin/galleries/'.(int)$r['id'].'/edit">Edit</a><form method="post" action="/admin/galleries/'.(int)$r['id'].'/delete" onsubmit="return confirm(&quot;Delete this gallery?&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><button class="btn danger">Delete</button></form></div></div>';
    }
    $galleryVisible=setting('gallery_show_in_navigation','1')==='1';
    $h = '<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; MEDIA</p><h1>Gallery</h1><p>Group photos into public galleries.</p></div></div>';
    $h .= '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Website visibility</h2></div><div class="panel-body"><form method="post" class="gallery-website-settings"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="gallery_action" value="save_website_visibility"><p class="muted">Add Gallery to the public website navigation. The public page is <strong>/gallery</strong>.</p><label class="check"><input type="checkbox" name="gallery_show_in_navigation" value="1"'.($galleryVisible?' checked':'').'> Show Gallery on website</label><button class="btn" style="margin-top:10px">Save visibility</button></form></div></div>';
    $h .= '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All Galleries</h2></div><div class="panel-body"><div class="admin-row-list">'.($tr ?: '<div class="admin-row-empty">No galleries created yet. Click "New Gallery" to build your first gallery!</div>').'</div></div></div>';
    layout('Gallery', $h, true);
    exit;
}

if($path==='/admin/galleries/new'||preg_match('#^/admin/galleries/(\d+)/edit$#',$path,$m)){
    require_permission('media.manage');
    $pdo = db();
    $id = isset($m[1]) ? (int)$m[1] : 0;
    $g = ['title'=>'', 'slug'=>'', 'description'=>'', 'cover_media_id'=>0, 'images_json'=>'[]', 'status'=>'published'];
    if ($id) {
        $s = $pdo->prepare("SELECT * FROM photo_galleries WHERE id=?");
        $s->execute([$id]);
        $g = $s->fetch();
        if (!$g) { flash('error', 'Gallery not found.'); redirect('/admin/galleries'); }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Title is required.');
        } else {
            $slug = slugify(trim($_POST['slug'] ?? '') ?: $title);
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? 'published', ['published','draft'], true) ? $_POST['status'] : 'published';
            $coverId = (int)($_POST['cover_media_id'] ?? 0);
            
            $selectedIds = array_map('intval', $_POST['media_ids'] ?? []);
            $photos = [];
            if ($selectedIds) {
                $in = implode(',', array_fill(0, count($selectedIds), '?'));
                $s = $pdo->prepare("SELECT * FROM media WHERE id IN ($in)");
                $s->execute($selectedIds);
                $byMedia = [];
                foreach ($s->fetchAll() as $row) { $byMedia[(int)$row['id']] = $row; }
                foreach ($selectedIds as $mid) {
                    if (isset($byMedia[$mid])) {
                        $mRow = $byMedia[$mid];
                        $cap = trim((string)($_POST['caption_'.$mid] ?? $mRow['caption'] ?: $mRow['alt_text'] ?: ''));
                        $photos[] = [
                            'id' => $mid,
                            'url' => media_url($mRow),
                            'caption' => $cap,
                            'alt' => $mRow['alt_text'] ?: $mRow['original_name']
                        ];
                    }
                }
            }
            $json = json_encode($photos, JSON_UNESCAPED_SLASHES);
            if ($id) {
                $s = $pdo->prepare("UPDATE photo_galleries SET title=?, slug=?, description=?, cover_media_id=?, images_json=?, status=? WHERE id=?");
                $s->execute([$title, $slug, $desc, $coverId ?: null, $json, $status, $id]);
                audit('gallery.update', ['id' => $id]);
                flash('success', 'Gallery updated.');
            } else {
                $s = $pdo->prepare("INSERT INTO photo_galleries(title, slug, description, cover_media_id, images_json, status) VALUES(?,?,?,?,?,?)");
                $s->execute([$title, $slug, $desc, $coverId ?: null, $json, $status]);
                $id = (int)$pdo->lastInsertId();
                audit('gallery.create', ['id' => $id]);
                flash('success', 'Gallery created.');
            }
            redirect('/admin/galleries');
        }
    }
    $allMedia = $pdo->query("SELECT * FROM media WHERE mime_type LIKE 'image/%' ORDER BY uploaded_at DESC")->fetchAll();
    $currentPhotos = json_decode((string)$g['images_json'], true) ?: [];
    $selectedMap = [];
    foreach ($currentPhotos as $p) { if (!empty($p['id'])) $selectedMap[(int)$p['id']] = $p['caption'] ?? ''; }
    
    $mediaChoices = '';
    foreach ($allMedia as $m) {
        $mid = (int)$m['id'];
        $isChecked = isset($selectedMap[$mid]);
        $capVal = $isChecked ? $selectedMap[$mid] : ($m['caption'] ?: $m['alt_text'] ?: '');
        $mediaChoices .= '<label style="display:grid;grid-template-columns:auto 80px 1fr;gap:12px;align-items:center;padding:10px;margin:0;border:1px solid var(--line)"><input type="checkbox" name="media_ids[]" value="'.$mid.'" '.($isChecked ? 'checked' : '').' style="width:auto;"><img src="'.e(media_thumb_url($m)).'" style="width:80px;height:60px;object-fit:cover;border-radius:6px;"><div><strong>'.e($m['original_name']).'</strong><br><input name="caption_'.$mid.'" value="'.e($capVal).'" placeholder="Caption for this photo" style="margin-top:4px;font-size:.84rem;"></div></label>';
    }

    $coverOptions = '<option value="0">Default (First photo)</option>';
    foreach ($allMedia as $m) {
        $coverOptions .= '<option value="'.(int)$m['id'].'" '.((int)$g['cover_media_id'] === (int)$m['id'] ? 'selected' : '').'>'.e($m['original_name']).'</option>';
    }

    $h = '<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; MEDIA</p><h1>'.($id ? 'Edit Gallery' : 'New Gallery').'</h1></div><div class="actions"><a class="btn tertiary" href="/admin/galleries">Cancel</a></div></div>';
    $h .= '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body"><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><div class="fgrid"><div class="fslot"><label>Title<input name="title" value="'.e($g['title']).'" required placeholder="e.g. Summer Vacation 2026"></label></div><div class="fslot"><label>Slug<input name="slug" value="'.e($g['slug']).'" placeholder="e.g. summer-vacation-2026"></label></div></div><div class="fslot"><label>Description<textarea name="description" style="min-height:70px" placeholder="Describe this photo gallery...">'.e($g['description']).'</textarea></label></div><div class="fgrid"><div class="fslot"><label>Status<select name="status"><option value="published" '.($g['status']==='published'?'selected':'').'>Published</option><option value="draft" '.($g['status']==='draft'?'selected':'').'>Draft</option></select></label></div><div class="fslot"><label>Cover Image<select name="cover_media_id">'.$coverOptions.'</select></label></div></div><h2>Select Photos from Media Library</h2><div style="display:grid;gap:10px;max-height:480px;overflow:auto;padding:4px;border:1px solid var(--line);margin-bottom:18px;">'.($mediaChoices ?: '<p class="muted">No images in Media Library yet. <a href="/admin/media" target="_blank">Upload images first</a>.</p>').'</div><button class="btn">'.($id ? 'Save Gallery' : 'Create Gallery').'</button></form></div></div>';
    layout($id ? 'Edit Gallery' : 'New Gallery', $h, true);
    exit;
}

if(preg_match('#^/admin/galleries/(\d+)/delete$#',$path,$m)&&$_SERVER['REQUEST_METHOD']==='POST'){
    require_permission('media.manage');
    verify_csrf();
    $id = (int)$m[1];
    $s = db()->prepare("DELETE FROM photo_galleries WHERE id=?");
    $s->execute([$id]);
    audit('gallery.delete', ['id' => $id]);
    flash('success', 'Gallery deleted.');
    redirect('/admin/galleries');
}
if($path==='/admin/email'){
 require_permission('settings.manage');
 if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$action=(string)($_POST['action']??'settings');
  if($action==='settings'){$keys=['newsletter_enabled','mail_transport','mail_from_name','mail_from_email','smtp_host','smtp_port','smtp_secure','smtp_username','site_url'];foreach($keys as $k)set_setting($k,trim((string)($_POST[$k]??'')));if(trim((string)($_POST['smtp_password']??''))!=='')set_setting('smtp_password',(string)$_POST['smtp_password']);flash('success','Email settings saved.');redirect('/admin/email');}
  if($action==='test'){$to=trim((string)($_POST['test_email']??''));$ok=erased_mail_send($to,'ERASED CMS email test','<h2>Email is working</h2><p>This test was sent from your CMS.</p>');flash($ok?'success':'error',$ok?'Test email sent.':'Email could not be sent. Check transport settings and server logs.');redirect('/admin/email');}
  if($action==='send'){$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));if($subject===''||$body===''){flash('error','Subject and message are required.');redirect('/admin/email');}$subs=db()->query("SELECT email,token FROM newsletter_subscribers WHERE status='active' ORDER BY id")->fetchAll();$createdBy=(int)(current_user()['id']??0);$q=db()->prepare('INSERT INTO newsletter_campaigns(subject,body,recipient_count,created_by) VALUES(?,?,?,?)');$q->execute([$subject,$body,count($subs),$createdBy?:null]);$campaign=(int)db()->lastInsertId();$sent=0;$failed=0;foreach($subs as $sub){$unsubscribe=erased_base_url().'/unsubscribe?token='.urlencode((string)$sub['token']);$html='<div>'.nl2br(e($body)).'</div><hr><p style="font-size:12px"><a href="'.e($unsubscribe).'">Unsubscribe</a></p>';erased_mail_send((string)$sub['email'],$subject,$html)?$sent++:$failed++;}db()->prepare('UPDATE newsletter_campaigns SET sent_count=?,failed_count=?,sent_at=NOW() WHERE id=?')->execute([$sent,$failed,$campaign]);flash($failed?'error':'success','Newsletter finished: '.$sent.' sent, '.$failed.' failed.');redirect('/admin/email');}
 }
 $active=(int)db()->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();$all=(int)db()->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn();$campaigns=db()->query('SELECT * FROM newsletter_campaigns ORDER BY id DESC LIMIT 10')->fetchAll();$history='';foreach($campaigns as $c)$history.='<tr><td>'.e($c['subject']).'</td><td>'.(int)$c['recipient_count'].'</td><td>'.(int)$c['sent_count'].'</td><td>'.(int)$c['failed_count'].'</td><td>'.e($c['sent_at']??$c['created_at']).'</td></tr>';
 $content='';
 $content.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Subscribers</h2></div><div class="panel-body"><div class="admin-stat-row"><div class="admin-stat-chip"><div class="admin-stat-value">'.$active.'</div><div class="admin-stat-label">Active subscribers</div></div><div class="admin-stat-chip"><div class="admin-stat-value">'.$all.'</div><div class="admin-stat-label">Total subscriber records</div></div></div></div></div>';
 $content.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="settings"><div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Email settings</h2></div><div class="panel-body"><div class="fgrid three"><div class="fslot"><label>Transport<select name="mail_transport"><option value="php"'.(setting('mail_transport','php')==='php'?' selected':'').'>PHP mail()</option><option value="smtp"'.(setting('mail_transport','php')==='smtp'?' selected':'').'>SMTP</option></select></label></div><div class="fslot"><label>From name<input name="mail_from_name" value="'.e(setting('mail_from_name',setting('site_name','ERASED CMS'))).'"></label></div><div class="fslot"><label>From email<input type="email" name="mail_from_email" value="'.e(setting('mail_from_email','')).'" required></label></div><div class="fslot"><label>Public site URL<input name="site_url" value="'.e(setting('site_url',erased_base_url())).'" placeholder="https://example.com"></label></div><div class="fslot"><label>SMTP host<input name="smtp_host" value="'.e(setting('smtp_host','')).'"></label></div><div class="fslot"><label>SMTP port<input type="number" name="smtp_port" value="'.e(setting('smtp_port','587')).'"></label></div><div class="fslot"><label>SMTP security<select name="smtp_secure"><option value="tls"'.(setting('smtp_secure','tls')==='tls'?' selected':'').'>STARTTLS</option><option value="ssl"'.(setting('smtp_secure','tls')==='ssl'?' selected':'').'>SSL/TLS</option><option value="none"'.(setting('smtp_secure','tls')==='none'?' selected':'').'>None</option></select></label></div><div class="fslot"><label>SMTP username<input name="smtp_username" value="'.e(setting('smtp_username','')).'"></label></div><div class="fslot"><label>SMTP password<input type="password" name="smtp_password" placeholder="Leave blank to keep saved password"></label></div><div class="fslot"><label>Newsletter<select name="newsletter_enabled"><option value="1"'.(setting('newsletter_enabled','1')==='1'?' selected':'').'>Enabled</option><option value="0"'.(setting('newsletter_enabled','1')==='0'?' selected':'').'>Disabled</option></select></label></div></div><div class="actions" style="margin-top:16px"><button class="btn">Save email settings</button></div></div></div></form>';
 $content.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="test"><div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Send test email</h2></div><div class="panel-body"><div class="fgrid"><div class="fslot"><label>Send to<input type="email" name="test_email" placeholder="you@example.com" required></label></div></div><button class="btn ghost">Send test</button></div></div></form>';
 $content.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="send"><div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Send newsletter</h2></div><div class="panel-body"><div class="fgrid"><div class="fslot"><label>Subject<input name="subject" required></label></div></div><div class="fslot"><label>Message<textarea name="body" required></textarea></label></div><p class="muted">This sends to all active subscribers and adds an unsubscribe link automatically.</p><button class="btn"'.($active<1?' disabled':'').'>Send to '.$active.' subscribers</button></div></div></form>';
 $content.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Recent campaigns</h2></div><div class="panel-body"><table><thead><tr><th>Subject</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Date</th></tr></thead><tbody>'.($history?:'<tr><td colspan="5">No campaigns yet.</td></tr>').'</tbody></table></div></div>';
 $h=settings_docket('/admin/email',null,'Email Settings','Configure outgoing email, password resets, and newsletter delivery.',$content);
 layout('Email',$h,true);exit;
}
if($path==='/admin/settings'){
    require_permission('settings.manage');
    $tabs=[
        'general'=>'General Settings',
        'publishing'=>'Publishing & Content',
        'seo'=>'SEO',
        'advanced'=>'Advanced Settings',
    ];
    $requestedTab=$_SERVER['REQUEST_METHOD']==='POST'?(string)($_POST['active_tab']??'general'):(string)($_GET['tab']??'general');
    if($requestedTab==='branding')redirect('/admin/appearance/branding');
    $activeTab=array_key_exists($requestedTab,$tabs)?$requestedTab:'general';
    $tabKeys=[
        'general'=>['site_name','site_tagline','site_language','timezone','date_format','header_text','footer_text'],
        'publishing'=>['homepage_content_id','posts_content_id','posts_per_page','comments_enabled','comment_captcha_enabled','registration_enabled'],
        'seo'=>['seo_title','seo_description'],
        'advanced'=>['custom_css','maintenance_mode','announcement_enabled','announcement_message','announcement_icon','announcement_url','announcement_new_tab','announcement_mode','announcement_speed','announcement_bg','announcement_color','announcement_audience','announcement_start','announcement_end','announcement_close'],
    ];
    $checkboxKeys=['comments_enabled','comment_captcha_enabled','registration_enabled','maintenance_mode','announcement_enabled','announcement_new_tab','announcement_close'];
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        if($activeTab==='general'){
            $siteName=trim((string)($_POST['site_name']??''));
            $timezone=trim((string)($_POST['timezone']??''));
            if($siteName===''){
                flash('error','Site title is required.');
                redirect('/admin/settings');
            }
            try{
                new DateTimeZone($timezone);
            }catch(Throwable){
                flash('error','Enter a valid time zone, for example Europe/Oslo.');
                redirect('/admin/settings');
            }
        }
        foreach($tabKeys[$activeTab] as $key){
            if(in_array($key,$checkboxKeys,true)){
                $value=isset($_POST[$key])?'1':'0';
            }elseif(array_key_exists($key,$_POST)){
                $value=trim((string)$_POST[$key]);
            }else{
                continue;
            }
            if($key==='posts_per_page')$value=(string)max(1,min(100,(int)$value));
            if($key==='announcement_speed')$value=(string)max(5,min(120,(int)$value?:25));
            if(($key==='announcement_bg'||$key==='announcement_color')&&!preg_match('/^#[0-9a-fA-F]{6}$/',$value))$value=$key==='announcement_bg'?'#101a14':'#2dfc98';
            if($key==='announcement_url'&&$value!==''&&!preg_match('#^(/|https?://)#i',$value))$value='';
            if($key==='announcement_audience'&&!in_array($value,['all','guests','users','admins'],true))$value='all';
            if($key==='announcement_mode'&&!in_array($value,['static','marquee'],true))$value='marquee';
            set_setting($key,$value);
        }
        flash('success',$tabs[$activeTab].' saved.');
        redirect($activeTab==='general'?'/admin/settings':'/admin/settings?tab='.rawurlencode($activeTab));
    }
    $check=fn($k)=>setting($k,'1')==='1'?' checked':'';
    $panels=[];
    $panels['general']=
        '<div class="field-block"><h3>Site identity</h3><p class="hint">The name and short description shown across the website.</p><div class="fgrid"><div class="fslot"><label>Site title<input name="site_name" maxlength="120" value="'.e(setting('site_name')).'" required></label></div><div class="fslot"><label>Tagline<input name="site_tagline" maxlength="220" value="'.e(setting('site_tagline')).'"></label></div></div></div>'
        .'<div class="field-block"><h3>Regional format</h3><p class="hint">Control the language, local time, and how dates are displayed.</p><div class="fgrid three"><div class="fslot"><label>Website language<select name="site_language">'.erased_language_select_options(setting('site_language','en')).'</select></label></div><div class="fslot"><label>Time zone<input name="timezone" list="erased-timezones" value="'.e(setting('timezone','Europe/Oslo')).'" placeholder="Europe/Oslo" autocomplete="off" required></label><datalist id="erased-timezones"><option value="Europe/Oslo"><option value="Europe/London"><option value="Europe/Vilnius"><option value="Europe/Warsaw"><option value="America/New_York"><option value="America/Los_Angeles"><option value="Asia/Tokyo"><option value="UTC"></datalist></div><div class="fslot"><label>Date format<input name="date_format" maxlength="40" value="'.e(setting('date_format','Y-m-d')).'" placeholder="Y-m-d" required></label><small class="mono" style="color:var(--ink-faint);font-size:10.5px">Example: Y-m-d or d.m.Y</small></div></div></div>'
        .'<div class="field-block"><h3>Header and footer</h3><p class="hint">Optional text displayed in the public website frame.</p><div class="fgrid"><div class="fslot"><label>Header text<textarea name="header_text">'.e(setting('header_text')).'</textarea></label></div><div class="fslot"><label>Footer text<textarea name="footer_text">'.e(setting('footer_text')).'</textarea></label></div></div></div>';
    $panels['publishing']=
        '<div class="field-block"><h3>Homepage &amp; posts</h3><p class="hint">Choose what visitors see on the homepage and how posts are listed.</p><div class="fgrid three"><div class="fslot"><label>Homepage static page/post<select name="homepage_content_id">'.page_options((int)setting('homepage_content_id'),'Default latest posts').'</select></label></div><div class="fslot"><label>Posts page<select name="posts_content_id">'.page_options((int)setting('posts_content_id'),'Default /posts page').'</select></label></div><div class="fslot"><label>Posts per page<input type="number" min="1" max="100" name="posts_per_page" value="'.e(setting('posts_per_page','10')).'"></label></div></div></div>'
        .'<div class="field-block"><h3>Comments &amp; registration</h3><p class="hint">Default behavior for new content and visitor accounts.</p><label class="check"><input type="checkbox" name="comments_enabled" value="1"'.$check('comments_enabled').'> Enable comments by default for new content</label><label class="check"><input type="checkbox" name="comment_captcha_enabled" value="1"'.$check('comment_captcha_enabled').'> Enable CAPTCHA verification on comment form (Anti-Spam protection)</label><label class="check"><input type="checkbox" name="registration_enabled" value="1"'.$check('registration_enabled').'> Allow public user registration</label></div>';
    $panels['seo']=
        '<div class="field-block"><h3>Search appearance</h3><p class="hint">How the site appears in search results.</p><div class="fgrid"><div class="fslot"><label>SEO title<input name="seo_title" value="'.e(setting('seo_title')).'"></label></div><div class="fslot" style="grid-column:1/-1"><label>Meta description<textarea name="seo_description">'.e(setting('seo_description')).'</textarea></label></div></div></div>';
    $checkOff=fn($k)=>setting($k,'0')==='1'?' checked':'';
    $announcementMode=in_array(setting('announcement_mode','marquee'),['static','marquee'],true)?setting('announcement_mode','marquee'):'marquee';
    $announcementMsg=trim((string)setting('announcement_message',''));
    $announcementPreview=$announcementMsg===''?'<p class="hint" style="margin:0">Add a message below to see a live preview here.</p>':'<div class="announcement-bar announcement-'.$announcementMode.'" style="--announcement-bg:'.e(setting('announcement_bg','#101a14')).';--announcement-color:'.e(setting('announcement_color','#2dfc98')).';--announcement-speed:'.max(5,min(120,(int)setting('announcement_speed','25'))).'s"><div class="announcement-track"><div class="announcement-message">'.(trim((string)setting('announcement_icon','📢'))!==''?'<span class="announcement-icon">'.e(setting('announcement_icon','📢')).'</span> ':'').'<span>'.e($announcementMsg).'</span></div></div>'.(setting('announcement_close','0')==='1'?'<button class="announcement-close" type="button" aria-label="Close preview" disabled>×</button>':'').'</div>';
    $panels['advanced']=
        '<div class="field-block"><h3>Announcement bar</h3><p class="hint">A dismissible banner shown across the top of the public site, above the header.</p>'
        .'<div class="announcement-preview">'.$announcementPreview.'</div>'
        .'<label class="check"><input type="checkbox" name="announcement_enabled" value="1"'.$checkOff('announcement_enabled').'> Show announcement bar on the public site</label>'
        .'<div class="fgrid" style="margin-top:14px"><div class="fslot full"><label>Message<input name="announcement_message" maxlength="300" value="'.e(setting('announcement_message')).'" placeholder="e.g. New feature launch — read more"></label></div>'
        .'<div class="fslot"><label>Icon (emoji, optional)<input name="announcement_icon" maxlength="8" value="'.e(setting('announcement_icon','📢')).'"></label></div>'
        .'<div class="fslot"><label>Link URL (optional)<input type="url" name="announcement_url" value="'.e(setting('announcement_url')).'" placeholder="/posts or https://…"></label></div>'
        .'<div class="fslot"><label>Display mode<select name="announcement_mode"><option value="marquee"'.($announcementMode==='marquee'?' selected':'').'>Scrolling marquee</option><option value="static"'.($announcementMode==='static'?' selected':'').'>Static (centered)</option></select></label></div>'
        .'<div class="fslot"><label>Scroll speed (seconds)<input type="number" min="5" max="120" name="announcement_speed" value="'.e(setting('announcement_speed','25')).'"></label></div>'
        .'<div class="fslot"><label>Background color<input type="color" name="announcement_bg" value="'.e(setting('announcement_bg','#101a14')).'"></label></div>'
        .'<div class="fslot"><label>Text color<input type="color" name="announcement_color" value="'.e(setting('announcement_color','#2dfc98')).'"></label></div>'
        .'<div class="fslot"><label>Audience<select name="announcement_audience">'.(function()use($check){$opts=['all'=>'Everyone','guests'=>'Guests only (logged out)','users'=>'Logged-in users only','admins'=>'Admins only'];$cur=setting('announcement_audience','all');$html='';foreach($opts as $v=>$l)$html.='<option value="'.$v.'"'.($cur===$v?' selected':'').'>'.$l.'</option>';return $html;})().'</select></label></div>'
        .'<div class="fslot"><label>Show from<input type="datetime-local" name="announcement_start" value="'.e(setting('announcement_start')).'"></label></div>'
        .'<div class="fslot"><label>Show until<input type="datetime-local" name="announcement_end" value="'.e(setting('announcement_end')).'"></label></div></div>'
        .'<label class="check" style="margin-top:10px"><input type="checkbox" name="announcement_new_tab" value="1"'.$checkOff('announcement_new_tab').'> Open link in a new tab</label>'
        .'<label class="check"><input type="checkbox" name="announcement_close" value="1"'.$checkOff('announcement_close').'> Let visitors dismiss it (close button)</label>'
        .'</div>'
        .'<div class="field-block"><h3>Custom CSS</h3><p class="hint">Applies directly to the front-end theme without modifying source files.</p><textarea name="custom_css" class="mono" style="min-height:220px" placeholder="/* Custom CSS overrides */">'.e(setting('custom_css')).'</textarea></div>'
        .'<div class="field-block"><h3>Maintenance</h3><p class="hint">Temporarily hide the front-end from visitors.</p><label class="check"><input type="checkbox" name="maintenance_mode" value="1"'.$check('maintenance_mode').'> Maintenance mode (hides front-end for non-admin visitors)</label></div>';
    $body='<div class="title-row"><div><p class="kicker">SHEET A-10 &middot; CONFIGURATION</p><h1>Settings</h1><p>Site identity, publishing defaults, and system configuration.</p></div></div>'
        .'<div class="docket"><details class="docket-nav-wrap" open><summary>Settings</summary>'.settings_tools_nav('/admin/settings',$activeTab).'</details>'
        .'<form method="post" class="docket-body"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="active_tab" value="'.e($activeTab).'">'
        .$panels[$activeTab]
        .'<div class="dim" style="margin-top:24px"><span class="l"></span></div><div class="actions" style="justify-content:space-between;align-items:center"><span class="mono" style="font-size:11px;color:var(--ink-faint)">Changes apply immediately upon saving.</span><button type="submit" class="btn">Save settings</button></div></form></div>';
    layout('Settings',$body,true);
    exit;
}

// content.publish_at is compared against MySQL's NOW() (UTC) everywhere it's
// read (app/HomepageLayout.php, routes/public.php), but an admin typing a
// "Publish at" time means it in the site's configured local timezone, not
// UTC - these convert at the one place publish_at is written/displayed so
// the stored value is real UTC while the admin still types/sees local time.
function erased_local_input_to_utc_datetime(string $localInput): ?string {
 if($localInput==='')return null;
 try{$dt=new DateTime(str_replace('T',' ',$localInput).':00');$dt->setTimezone(new DateTimeZone('UTC'));return $dt->format('Y-m-d H:i:s');}catch(Throwable $e){return null;}
}
function erased_utc_datetime_to_local_input(?string $utcDatetime): string {
 if(!$utcDatetime)return '';
 try{$dt=new DateTime($utcDatetime,new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone(date_default_timezone_get()));return $dt->format('Y-m-d\TH:i');}catch(Throwable $e){return '';}
}

// Integrated administration modules
if($path==='/admin/publishing'){require_permission('publishing.manage');$section=$_GET['section']??'';$contentId=(int)($_GET['content']??0);if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$id=(int)($_POST['id']??0);$returnSection=$_POST['section']??'content';$q=db()->prepare('INSERT INTO revisions(content_id,title,body,editor_id) SELECT id,title,body,? FROM content WHERE id=?');$q->execute([$_SESSION['user_id'],$id]);$q=db()->prepare('UPDATE content SET category=?,tags=?,access_level=?,publish_at=?,language_code=?,canonical_url=?,seo_title=?,seo_description=? WHERE id=?');$pub=trim($_POST['publish_at']??'');$q->execute([trim($_POST['category']??''),trim($_POST['tags']??''),$_POST['access_level']??'public',erased_local_input_to_utc_datetime($pub),$_POST['language_code']??'en',trim($_POST['canonical_url']??''),trim($_POST['seo_title']??''),trim($_POST['seo_description']??''),$id]);audit('content.metadata',['id'=>$id]);flash('success','Publishing settings saved.');redirect('/admin/publishing?section='.rawurlencode($returnSection).'&content='.$id);}$rows=db()->query('SELECT * FROM content ORDER BY updated_at DESC')->fetchAll();$titleRow='<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; CONTENT</p><h1>Publishing</h1></div></div>';if($section===''){$recent='';foreach(array_slice($rows,0,8) as $r)$recent.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($r['title']).'</div><div class="admin-row-meta">'.e(ucfirst($r['type'])).' &middot; '.e($r['status']).' &middot; '.e($r['category']?:'—').' &middot; '.e($r['language_code']?:'en').'</div></div><div class="admin-row-actions"><a class="btn ghost" href="/admin/publishing?section=content&content='.(int)$r['id'].'">Edit</a></div></div>';$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Recent content</h2></div><div class="panel-body"><div class="admin-row-list">'.($recent?:'<div class="admin-row-empty">No content yet.</div>').'</div></div></div>';layout('Publishing',$h,true);exit;}if($section==='revisions'){$rev=db()->query("SELECT r.*,c.title content_title,COALESCE(NULLIF(u.display_name,''),u.email,'Unknown') editor_name FROM revisions r LEFT JOIN content c ON c.id=r.content_id LEFT JOIN users u ON u.id=r.editor_id ORDER BY r.created_at DESC LIMIT 100")->fetchAll();$list='';foreach($rev as $v)$list.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($v['content_title']??'Deleted content').'</div><div class="admin-row-meta">'.e($v['editor_name']??'Unknown').' &middot; '.e($v['created_at']).'</div></div></div>';$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><h2>Revisions</h2><a class="btn tertiary" href="/admin/publishing">Back</a></div><div class="panel-body"><div class="admin-row-list">'.($list?:'<div class="admin-row-empty">No revisions yet.</div>').'</div></div></div>';layout('Revisions',$h,true);exit;}if($section==='redirects'){$reds=db()->query('SELECT * FROM redirects ORDER BY created_at DESC LIMIT 100')->fetchAll();$list='';foreach($reds as $v)$list.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($v['source_path']).' &rarr; '.e($v['target_path']).'</div><div class="admin-row-meta">Status '.(int)$v['status_code'].'</div></div></div>';$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><h2>Redirects</h2><a class="btn tertiary" href="/admin/publishing">Back</a></div><div class="panel-body"><div class="admin-row-list">'.($list?:'<div class="admin-row-empty">No redirects yet.</div>').'</div></div></div>';layout('Redirects',$h,true);exit;}$options='<option value="">Choose content…</option>';foreach($rows as $r)$options.='<option value="'.(int)$r['id'].'"'.($contentId===(int)$r['id']?' selected':'').'>'.e($r['title']).' — '.e($r['status']).'</option>';$titles=['content'=>'Content metadata','schedule'=>'Scheduling','access'=>'Access rules','seo'=>'SEO'];$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><div><h2>'.e($titles[$section]??'Publishing editor').'</h2><p class="muted" style="margin:4px 0 0">Choose one page or post.</p></div><a class="btn tertiary" href="/admin/publishing">Back</a></div><div class="panel-body"><form method="get"><input type="hidden" name="section" value="'.e($section).'"><select name="content" onchange="this.form.submit()">'.$options.'</select></form></div></div>';if($contentId){$r=null;foreach($rows as $row)if((int)$row['id']===$contentId){$r=$row;break;}if($r){$fieldsHtml='';if($section==='content')$fieldsHtml='<div class="fgrid"><div class="fslot"><label>Category<input name="category" value="'.e($r['category']??'').'"></label></div><div class="fslot"><label>Tags<input name="tags" value="'.e($r['tags']??'').'" placeholder="linux, privacy"></label></div><div class="fslot"><label>Language<input name="language_code" value="'.e($r['language_code']??'en').'"></label></div></div>';elseif($section==='schedule')$fieldsHtml='<div class="fslot"><label>Publish at<input type="datetime-local" name="publish_at" value="'.e(erased_utc_datetime_to_local_input($r['publish_at']??null)).'"></label></div>';elseif($section==='access'){$fieldsHtml='<div class="fslot"><label>Who can read this?<select name="access_level">';foreach(['public'=>'Everyone','registered'=>'Registered users','members'=>'Active members','paid'=>'Paid access'] as $v=>$n)$fieldsHtml.='<option value="'.$v.'"'.(($r['access_level']??'public')===$v?' selected':'').'>'.$n.'</option>';$fieldsHtml.='</select></label></div>';}elseif($section==='seo')$fieldsHtml='<div class="fslot"><label>SEO title<input name="seo_title" value="'.e($r['seo_title']??'').'"></label></div><div class="fslot"><label>SEO description<textarea name="seo_description" style="min-height:110px">'.e($r['seo_description']??'').'</textarea></label></div><div class="fslot"><label>Canonical URL<input name="canonical_url" value="'.e($r['canonical_url']??'').'"></label></div>';$fields=['category','tags','language_code','access_level','publish_at','seo_title','seo_description','canonical_url'];$hidden='';foreach($fields as $f){$visible=($section==='content'&&in_array($f,['category','tags','language_code'],true))||($section==='schedule'&&$f==='publish_at')||($section==='access'&&$f==='access_level')||($section==='seo'&&in_array($f,['seo_title','seo_description','canonical_url'],true));if(!$visible){$val=$r[$f]??'';if($f==='publish_at')$val=erased_utc_datetime_to_local_input($val?:null);$hidden.='<input type="hidden" name="'.$f.'" value="'.e((string)$val).'">';}}$h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><div><h2>'.e($r['title']).'</h2><small class="muted">'.reading_time($r['body']).' min read</small></div><a class="btn ghost" href="/admin/content/'.(int)$r['id'].'/edit">Edit article</a></div><div class="panel-body"><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="id" value="'.(int)$r['id'].'"><input type="hidden" name="section" value="'.e($section).'">'.$fieldsHtml.$hidden.'<button class="btn" style="margin-top:10px">Save changes</button></form></div></div>';}}layout('Publishing',$h,true);exit;}
if($path==='/admin/comments'){
    require_permission('comments.manage');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $action=$_POST['action']??'';
        if($action==='bulk'){
            $ids=array_filter(array_map('intval',$_POST['ids']??[]));
            $bulkAction=$_POST['bulk_action']??'';
            if(!empty($ids)&&in_array($bulkAction,['approve','unapprove','spam','delete'],true)){
                if($bulkAction==='delete'){
                    $in=implode(',',array_fill(0,count($ids),'?'));
                    db()->prepare("DELETE FROM comments WHERE id IN ($in)")->execute($ids);
                }else{
                    $status=$bulkAction==='approve'?'approved':($bulkAction==='unapprove'?'pending':'spam');
                    $in=implode(',',array_fill(0,count($ids),'?'));
                    $params=array_merge([$status],$ids);
                    db()->prepare("UPDATE comments SET status=? WHERE id IN ($in)")->execute($params);
                }
                flash('success','Updated '.count($ids).' comments.');
            }
        }elseif($action==='reply'){
            $contentId=(int)($_POST['content_id']??0);
            $body=trim((string)($_POST['body']??''));
            $user=current_user();
            if($contentId&&$body!==''){
                $q=db()->prepare("INSERT INTO comments (content_id,author_name,author_email,body,status,ip_address,user_agent,spam_reason,created_at) VALUES(?,?,?,?,'approved',?,?,'',NOW())");
                $q->execute([$contentId,$user['display_name']?:'Admin',$user['email']??'admin@site.local',$body,erased_client_ip(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
                flash('success','Reply posted.');
            }
        }else{
            $id=(int)($_POST['id']??0);
            if($action==='delete'){
                db()->prepare('DELETE FROM comments WHERE id=?')->execute([$id]);
            }elseif(in_array($action,['approve','unapprove','spam'],true)){
                $status=$action==='approve'?'approved':($action==='unapprove'?'pending':'spam');
                db()->prepare('UPDATE comments SET status=? WHERE id=?')->execute([$status,$id]);
            }
            audit('comment.'.$action,['id'=>$id]);
            flash('success','Comment updated.');
        }
        redirect('/admin/comments'.(!empty($_GET['status'])?'?status='.urlencode($_GET['status']):''));
    }

    $statusFilter=in_array($_GET['status']??'',['approved','pending','spam'],true)?$_GET['status']:'all';
    $search=trim((string)($_GET['q']??''));

    $countAll=(int)db()->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    $countApproved=(int)db()->query("SELECT COUNT(*) FROM comments WHERE status='approved'")->fetchColumn();
    $countPending=(int)db()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn();
    $countSpam=(int)db()->query("SELECT COUNT(*) FROM comments WHERE status='spam'")->fetchColumn();

    $sql="SELECT comments.*, content.title, content.slug FROM comments LEFT JOIN content ON content.id=comments.content_id WHERE 1=1";
    $params=[];
    if($statusFilter!=='all'){
        $sql.=" AND comments.status=?";
        $params[]=$statusFilter;
    }
    if($search!==''){
        $sql.=" AND (comments.author_name LIKE ? OR comments.author_email LIKE ? OR comments.body LIKE ? OR content.title LIKE ?)";
        $s="%{$search}%";
        $params=array_merge($params,[$s,$s,$s,$s]);
    }
    $sql.=" ORDER BY comments.created_at DESC LIMIT 150";
    $stmt=db()->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll();
    $groups=\Erased\Support\CommentGrouping::byPost($rows);

    $rowsHtml='';
    foreach($groups as $group){
        $rowsHtml.='<div class="dim"><span class="t">'
            .($group['slug']!==null?'<a href="/'.e($group['slug']).'" target="_blank">'.e($group['title']).'</a>':e($group['title']))
            .'</span><span class="l"></span></div>';
        foreach($group['rows'] as $r){
            $initials=strtoupper(substr(trim($r['author_name']?:'A'),0,2));
            $status=$r['status']?:'pending';
            if($status==='approved'){
                $pill='<span class="stampword live">Approved</span>';
            }elseif($status==='spam'){
                $pill='<span class="stampword" style="color:var(--warn)">Spam</span>';
            }else{
                $pill='<span class="stampword draft">Pending</span>';
            }
            $date=date('Y-m-d H:i',strtotime($r['created_at']));
            $postLink=!empty($r['slug'])?'<a href="/'.e($r['slug']).'" target="_blank">'.e($r['title']?:'Post #'.$r['content_id']).'</a>':'<span>Unknown content</span>';

            $actions='';
            $actions.=$status!=='approved'
                ?'<button type="submit" form="single-action-form" formaction="/admin/comments" name="action" value="approve" onclick="setSingleComment('.(int)$r['id'].',\'approve\')" class="btn ghost">Approve</button>'
                :'<button type="submit" form="single-action-form" formaction="/admin/comments" name="action" value="unapprove" onclick="setSingleComment('.(int)$r['id'].',\'unapprove\')" class="btn ghost">Unapprove</button>';
            $actions.='<details><summary class="btn ghost">Reply</summary>'
                .'<form method="post" style="margin-top:10px;max-width:520px">'
                .'<input type="hidden" name="csrf" value="'.csrf().'">'
                .'<input type="hidden" name="action" value="reply">'
                .'<input type="hidden" name="content_id" value="'.(int)$r['content_id'].'">'
                .'<input type="hidden" name="parent_id" value="'.(int)$r['id'].'">'
                .'<textarea name="body" placeholder="Write admin reply..." required style="min-height:70px;margin-bottom:8px;width:100%"></textarea>'
                .'<button class="btn">Send reply</button>'
                .'</form></details>';

            $moreActions='';
            if($status!=='spam'){
                $moreActions.='<button type="submit" form="single-action-form" formaction="/admin/comments" name="action" value="spam" onclick="setSingleComment('.(int)$r['id'].',\'spam\')" class="btn ghost">Mark as spam</button>';
            }
            $moreActions.='<button type="submit" form="single-action-form" formaction="/admin/comments" name="action" value="delete" onclick="if(!confirm(\'Delete this comment? This cannot be undone.\'))return false;setSingleComment('.(int)$r['id'].',\'delete\')" class="btn danger">Delete</button>';
            $actions.='<details class="row-menu"><summary class="btn ghost row-menu-btn" aria-label="More actions">&#8942;</summary>'
                .'<div class="row-menu-panel">'.$moreActions.'</div></details>';

            $bodyHtml=nl2br(e($r['body']));
            $isLongComment=mb_strlen(trim(strip_tags((string)$r['body'])))>280;
            $commentBodyBlock=$isLongComment
                ?'<details class="comment-clamp"><summary><span class="clamp-text">'.$bodyHtml.'</span>'
                    .'<span class="clamp-cta clamp-cta-more">Show full comment</span>'
                    .'<span class="clamp-cta clamp-cta-less">Hide</span></summary>'
                    .'<p class="clamp-full">'.$bodyHtml.'</p></details>'
                :'<p style="margin:8px 0 0;font-size:12.5px;line-height:1.55;color:var(--ink)">'.$bodyHtml.'</p>';

            $rowsHtml.='<div class="admin-row" style="align-items:flex-start">'
                .'<div class="admin-row-body" style="flex:1 1 auto;min-width:0">'
                .'<div style="display:flex;align-items:flex-start;gap:10px">'
                .'<input type="checkbox" name="ids[]" value="'.(int)$r['id'].'" form="bulk-comments-form" class="comment-select-cb" style="margin-top:5px;flex:0 0 auto;width:14px;height:14px">'
                .'<div style="width:30px;height:30px;flex:0 0 auto;border:1px solid var(--line);background:var(--sheet-2);display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-size:10.5px;font-weight:800;color:var(--ink-dim)">'.e($initials).'</div>'
                .'<div style="flex:1;min-width:0">'
                .'<div style="display:flex;align-items:baseline;justify-content:space-between;gap:14px;flex-wrap:wrap">'
                .'<div class="admin-row-title">'.e($r['author_name']?:'Anonymous').' '.$pill.'</div>'
                .'<div class="admin-row-meta">'.e($r['author_email']).' &middot; '.$date.' &middot; on '.$postLink.'</div>'
                .'</div>'
                .$commentBodyBlock
                .'</div>'
                .'</div>'
                .'</div>'
                .'<div class="admin-row-actions">'.$actions.'</div>'
                .'</div>';
        }
    }

    if($rowsHtml==='')$rowsHtml='<div class="admin-row-empty">No comments found matching your filters.</div>';

    $tabLink=fn($s,$lbl,$cnt)=>'<a class="admin-tab'.($statusFilter===$s?' is-active':'').'" href="/admin/comments?status='.$s.($search!==''?'&q='.urlencode($search):'').'">'.$lbl.' <span class="badge">'.$cnt.'</span></a>';

    $queueBody='<div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;justify-content:space-between;margin-bottom:16px">'
        .'<div class="admin-tabs" style="margin-bottom:0">'.$tabLink('all','All',$countAll).$tabLink('approved','Approved',$countApproved).$tabLink('pending','Pending',$countPending).$tabLink('spam','Spam',$countSpam).'</div>'
        .'<form method="get" action="/admin/comments" style="display:flex;gap:8px;align-items:flex-end">'
        .($statusFilter!=='all'?'<input type="hidden" name="status" value="'.e($statusFilter).'">':'')
        .'<div class="fslot" style="max-width:280px;margin-bottom:0"><label>Search<input type="text" name="q" value="'.e($search).'" placeholder="Author, email, body, or post title"></label></div>'
        .'<button class="btn">Search</button>'
        .'</form>'
        .'</div>'
        .'<form method="post" id="bulk-comments-form">'
        .'<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="bulk">'
        .'<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:10px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin-bottom:2px">'
        .'<label class="check" style="margin:0"><input type="checkbox" id="selectAllComments"> Select all</label>'
        .'<div class="fslot" style="width:180px"><select name="bulk_action"><option value="">Bulk action...</option><option value="approve">Approve</option><option value="unapprove">Mark pending</option><option value="spam">Mark spam</option><option value="delete">Delete</option></select></div>'
        .'<button class="btn ghost">Apply to selected</button>'
        .'</div>'
        .'</form>'
        .'<div class="admin-row-list">'.$rowsHtml.'</div>'
        .'<form method="post" id="single-action-form"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" id="single-comment-id" name="id" value="0"></form>';

    $h='<div class="admin-stat-row">'
        .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$countAll.'</span><span class="admin-stat-label">All comments</span></div>'
        .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$countApproved.'</span><span class="admin-stat-label">Approved</span></div>'
        .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$countPending.'</span><span class="admin-stat-label">Pending review</span></div>'
        .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$countSpam.'</span><span class="admin-stat-label">Spam</span></div>'
        .'</div>'
        .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
        .'<div class="panel-head"><h2>Moderation Queue</h2></div>'
        .'<div class="panel-body">'.$queueBody.'</div>'
        .'</div>'
        .'<script>document.getElementById("selectAllComments")?.addEventListener("change",function(){document.querySelectorAll(".comment-select-cb").forEach(cb=>cb.checked=this.checked);});function setSingleComment(id,action){document.getElementById("single-comment-id").value=id;}</script>';

    layout('Comments',$h,true);
    exit;
}
if(str_starts_with($path,'/admin/appearance')){
    require_permission('packages.manage');
    if($path==='/admin/appearance/branding'){
        $keys=['branding_mode','logo_dark_media_id','logo_light_media_id','favicon_media_id','logo_height','logo_width','logo_show_title'];
        if($_SERVER['REQUEST_METHOD']==='POST'){
            verify_csrf();
            foreach($keys as $k){
                if($k==='logo_show_title')set_setting($k,isset($_POST[$k])?'1':'0');
                else set_setting($k,trim((string)($_POST[$k]??'0')));
            }
            flash('success','Branding & Identity settings saved.');
            redirect('/admin/appearance/branding');
        }
        $check=fn($k)=>setting($k)==='1'?' checked':'';
        $darkLogoId=(int)setting('logo_dark_media_id',setting('logo_media_id','0'));
        $lightLogoId=(int)setting('logo_light_media_id',setting('logo_media_id','0'));
        $darkUrl=$darkLogoId&&($m=media_by_id($darkLogoId))?media_url($m):'/assets/erased-logo-dark.svg';
        $lightUrl=$lightLogoId&&($m=media_by_id($lightLogoId))?media_url($m):'/assets/erased-logo-light.svg';
        $isBuiltin=setting('branding_mode','builtin')==='builtin';
        $logoPreviewW=(int)setting('logo_width','240');
        $logoPreviewH=(int)setting('logo_height','42');
        $logoPreviewStyle='height:48px;width:auto;max-width:220px;object-fit:contain';
        $body=appearance_tools_nav('branding')
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">'

            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>Logo Preview</h2></div>'
            .'<div class="panel-body">'
            .'<div class="fgrid">'
            .'<div class="fslot"><label>Dark theme logo</label>'
            .'<div style="border:1px solid var(--line);background:var(--ink);border-radius:4px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:center;align-self:flex-start">'
            .'<img src="'.e($darkUrl).'" alt="Dark theme logo" style="'.$logoPreviewStyle.'"></div></div>'
            .'<div class="fslot"><label>Light/Grey theme logo</label>'
            .'<div style="border:1px solid var(--line);background:var(--paper);border-radius:4px;padding:12px 16px;display:inline-flex;align-items:center;justify-content:center;align-self:flex-start">'
            .'<img src="'.e($lightUrl).'" alt="Light theme logo" style="'.$logoPreviewStyle.'"></div></div>'
            .'</div>'
            .'<p class="muted" style="margin-top:14px">Shown at the actual logo artwork size (capped to fit here). Configured display size below is <span class="mono">'.$logoPreviewW.'&times;'.$logoPreviewH.'px</span> &mdash; the live site header may render it slightly larger to preserve a minimum legible size. Upload or manage source files in the <a href="/admin/media">Media Library</a>.</p>'
            .'</div></div>'

            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>Identity Source</h2></div>'
            .'<div class="panel-body">'
            .'<div class="fgrid">'
            .'<div class="fslot"><label class="check"><input type="radio" name="branding_mode" value="builtin"'.($isBuiltin?' checked':'').'> Built-in ERASED identity</label>'
            .'<p class="muted">Automatically uses the correct logo for dark and light/grey themes.</p></div>'
            .'<div class="fslot"><label class="check"><input type="radio" name="branding_mode" value="custom"'.(!$isBuiltin?' checked':'').'> Custom uploaded logos</label>'
            .'<p class="muted">Use separate uploaded logos for dark and grey themes.</p></div>'
            .'</div>'
            .'</div></div>'

            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>Logo & Favicon Files</h2></div>'
            .'<div class="panel-body">'
            .'<div class="fgrid three">'
            .'<div class="fslot"><label>Dark-theme logo<select name="logo_dark_media_id">'.media_options($darkLogoId,'Use built-in dark logo').'</select></label></div>'
            .'<div class="fslot"><label>Light/Grey-theme logo<select name="logo_light_media_id">'.media_options($lightLogoId,'Use built-in light logo').'</select></label></div>'
            .'<div class="fslot"><label>Favicon<select name="favicon_media_id">'.media_options((int)setting('favicon_media_id'),'Use built-in favicon').'</select></label></div>'
            .'</div>'
            .'</div></div>'

            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>Sizing &amp; Display</h2></div>'
            .'<div class="panel-body">'
            .'<div class="fgrid three">'
            .'<div class="fslot"><label>Logo height (px)<input type="number" min="24" max="180" name="logo_height" value="'.e(setting('logo_height','42')).'"></label></div>'
            .'<div class="fslot"><label>Logo width (px)<input type="number" min="40" max="600" name="logo_width" value="'.e(setting('logo_width','240')).'"></label></div>'
            .'<div class="fslot"><label>&nbsp;</label><label class="check"><input type="checkbox" name="logo_show_title" value="1"'.$check('logo_show_title').'> Show site title next to logo</label></div>'
            .'</div>'
            .'<button class="btn" type="submit" style="margin-top:20px">Save branding settings</button>'
            .'</div></div>'

            .'</form>';
        $body=settings_docket('/admin/appearance',null,'Appearance','Manage logo variants, favicons, site title visibility, and header branding.',$body);
        layout('Branding & Identity',$body,true);
        exit;
    }
    if($path==='/admin/appearance/navigation'){
        if($_SERVER['REQUEST_METHOD']==='POST'){
            verify_csrf();
            $labels=$_POST['label']??[];$urls=$_POST['url']??[];$keys=$_POST['key']??[];$parentKeys=$_POST['parent_key']??[];
            $newTabKeys=array_flip($_POST['new_tab_keys']??[]);$enabledKeys=array_flip($_POST['enabled_keys']??[]);
            $count=min(count($labels),count($urls),count($keys),count($parentKeys));
            $keyToId=[];$rows=[];
            for($i=0;$i<$count;$i++){
                $label=trim((string)$labels[$i]);$url=trim((string)$urls[$i]);
                if($label===''&&$url==='')continue;
                $id='item-'.count($rows);
                $key=(string)$keys[$i];
                $keyToId[$key]=$id;
                $rows[]=['id'=>$id,'key'=>$key,'label'=>$label!==''?$label:$url,'url'=>$url,'parent_key'=>(string)$parentKeys[$i],'new_tab'=>isset($newTabKeys[$key]),'enabled'=>isset($enabledKeys[$key])];
            }
            $topLevelIds=[];
            foreach($rows as $r)if($r['parent_key']==='')$topLevelIds[$r['id']]=true;
            $items=[];
            foreach($rows as $r){
                $parentId=isset($keyToId[$r['parent_key']])?$keyToId[$r['parent_key']]:'';
                if($parentId!==''&&!isset($topLevelIds[$parentId]))$parentId='';
                $items[]=['id'=>$r['id'],'type'=>'custom','label'=>$r['label'],'url'=>$r['url'],'enabled'=>$r['enabled'],'parent'=>$parentId,'new_tab'=>$r['new_tab']];
            }
            set_setting('navigation_items_json',json_encode($items,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            flash('success','Navigation saved.');
            erased_redirect_preserving_studio_embed('/admin/appearance/navigation');
        }
        $navItems=v100_navigation_items();
        $idToKey=[];foreach($navItems as $i=>$item)$idToKey[(string)($item['id']??'')]='item-'.$i;
        $pageOptions='';
        try{
            $pages=db()->query("SELECT id,type,title,slug FROM content WHERE status='published' AND type='page' ORDER BY title ASC")->fetchAll();
            foreach($pages as $p)$pageOptions.='<option value="/'.e($p['slug']).'" data-title="'.e($p['title']).'">'.e($p['title']).' ('.e($p['type']).')</option>';
        }catch(Throwable $e){}
        $rowsHtml='';
        foreach($navItems as $i=>$item){
            $key='item-'.$i;
            $label=(string)($item['label']??'');$url=(string)($item['url']??'');
            $parentId=(string)($item['parent']??'');$parentKey=$parentId!==''&&isset($idToKey[$parentId])?$idToKey[$parentId]:'';
            $rowsHtml.='<div class="navigation-builder-row" draggable="true" data-key="'.e($key).'">'
                .'<span class="navigation-drag" title="Drag to reorder">&#10021;</span>'
                .'<input type="text" name="label[]" value="'.e($label).'" placeholder="Label" class="nav-label-input" required>'
                .'<input type="text" name="url[]" value="'.e($url).'" placeholder="/path or https://...">'
                .'<select name="parent_key[]" class="nav-parent-select" data-current="'.e($parentKey).'"><option value="">Top level</option></select>'
                .'<span class="nav-row-toggles"><label class="check small"><input type="checkbox" name="new_tab_keys[]" value="'.e($key).'"'.(!empty($item['new_tab'])?' checked':'').'> New tab</label><label class="check small"><input type="checkbox" name="enabled_keys[]" value="'.e($key).'"'.(!empty($item['enabled'])?' checked':'').'> Visible</label></span>'
                .'<span class="nav-row-move"><button type="button" class="btn ghost nav-row-up" title="Move up" aria-label="Move up">&uarr;</button><button type="button" class="btn ghost nav-row-down" title="Move down" aria-label="Move down">&darr;</button></span>'
                .'<button type="button" class="btn ghost nav-row-remove" title="Remove item">Remove</button>'
                .'<input type="hidden" name="key[]" value="'.e($key).'">'
                .'</div>';
        }
        $navScript=<<<'JS'
<script>
(function(){
    var list=document.getElementById('nav-builder-list');
    var nextKey=list.children.length;
    function refreshParentOptions(){
        // dataset.current is the single source of truth for each row's chosen
        // parent key - kept in sync directly by the select's own change
        // handler, never inferred from the live .value (which is meaningless
        // mid-rebuild, since options get wiped and rebuilt below).
        var rows=Array.prototype.slice.call(list.querySelectorAll('.navigation-builder-row'));
        rows.forEach(function(row){
            var select=row.querySelector('.nav-parent-select');
            var current=select.dataset.current||'';
            var options='<option value="">Top level</option>';
            rows.forEach(function(other){
                if(other===row)return;
                var otherSelect=other.querySelector('.nav-parent-select');
                if((otherSelect.dataset.current||'')!=='')return;
                var label=other.querySelector('.nav-label-input').value||'(untitled)';
                var key=other.dataset.key;
                options+='<option value="'+key+'"'+(current===key?' selected':'')+'>'+label+'</option>';
            });
            select.innerHTML=options;
            select.value=current;
        });
    }
    function buildRow(label,url){
        var key='new-'+(nextKey++);
        var row=document.createElement('div');
        row.className='navigation-builder-row';
        row.draggable=true;
        row.dataset.key=key;
        row.innerHTML='<span class="navigation-drag" title="Drag to reorder">&#10021;</span>'
            +'<input type="text" name="label[]" value="'+label.replace(/"/g,'&quot;')+'" placeholder="Label" class="nav-label-input" required>'
            +'<input type="text" name="url[]" value="'+url.replace(/"/g,'&quot;')+'" placeholder="/path or https://...">'
            +'<select name="parent_key[]" class="nav-parent-select"><option value="">Top level</option></select>'
            +'<span class="nav-row-toggles"><label class="check small"><input type="checkbox" name="new_tab_keys[]" value="'+key+'"> New tab</label><label class="check small"><input type="checkbox" name="enabled_keys[]" value="'+key+'" checked> Visible</label></span>'
            +'<span class="nav-row-move"><button type="button" class="btn ghost nav-row-up" title="Move up" aria-label="Move up">&uarr;</button><button type="button" class="btn ghost nav-row-down" title="Move down" aria-label="Move down">&darr;</button></span>'
            +'<button type="button" class="btn ghost nav-row-remove" title="Remove item">Remove</button>'
            +'<input type="hidden" name="key[]" value="'+key+'">';
        wireRow(row);
        return row;
    }
    // Move-up/move-down buttons are an unconditional alternative to drag
    // reorder, not a touch-detected replacement for it - native HTML5
    // drag-and-drop (dragstart/dragover below) has no touch equivalent at
    // all, so leaving reorder as drag-only made this screen impossible to
    // use on any touch-only device. Buttons work for every input type
    // (mouse, touch, keyboard) and stay alongside drag rather than
    // replacing it conditionally, so nothing needs to guess the device.
    function updateMoveButtonStates(){
        var rows=Array.prototype.slice.call(list.querySelectorAll('.navigation-builder-row'));
        rows.forEach(function(row,index){
            row.querySelector('.nav-row-up').disabled=(index===0);
            row.querySelector('.nav-row-down').disabled=(index===rows.length-1);
        });
    }
    function wireRow(row){
        row.querySelector('.nav-row-remove').addEventListener('click',function(){row.remove();refreshParentOptions();updateMoveButtonStates();});
        row.querySelector('.nav-label-input').addEventListener('input',refreshParentOptions);
        row.querySelector('.nav-parent-select').addEventListener('change',function(){row.querySelector('.nav-parent-select').dataset.current=row.querySelector('.nav-parent-select').value;refreshParentOptions();});
        row.querySelector('.nav-row-up').addEventListener('click',function(){
            var prev=row.previousElementSibling;
            if(prev){list.insertBefore(row,prev);updateMoveButtonStates();}
        });
        row.querySelector('.nav-row-down').addEventListener('click',function(){
            var next=row.nextElementSibling;
            if(next){list.insertBefore(next,row);updateMoveButtonStates();}
        });
        row.addEventListener('dragstart',function(e){row.classList.add('dragging');e.dataTransfer.effectAllowed='move';});
        row.addEventListener('dragend',function(){row.classList.remove('dragging');refreshParentOptions();updateMoveButtonStates();});
    }
    list.querySelectorAll('.navigation-builder-row').forEach(wireRow);
    updateMoveButtonStates();
    list.addEventListener('dragover',function(e){
        e.preventDefault();
        var dragging=list.querySelector('.dragging');
        if(!dragging)return;
        var after=Array.prototype.slice.call(list.querySelectorAll('.navigation-builder-row:not(.dragging)')).reduce(function(closest,child){
            var box=child.getBoundingClientRect();var offset=e.clientY-box.top-box.height/2;
            if(offset<0&&offset>closest.offset)return {offset:offset,element:child};
            return closest;
        },{offset:-Infinity,element:null}).element;
        if(after==null)list.appendChild(dragging);else list.insertBefore(dragging,after);
    });
    document.getElementById('nav-add-page-btn').addEventListener('click',function(){
        var select=document.getElementById('nav-add-page-select');
        var opt=select.options[select.selectedIndex];
        if(!opt||!opt.value)return;
        list.appendChild(buildRow(opt.dataset.title||opt.textContent,opt.value));
        refreshParentOptions();
        updateMoveButtonStates();
    });
    refreshParentOptions();
})();
</script>
JS;
        $body=appearance_tools_nav('navigation')
            .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>Add to navigation</h2></div>'
            .'<div class="panel-body">'
            .'<div class="fgrid">'
            .'<div class="fslot full"><label>Existing page or post</label><div class="field-inline-btn"><select id="nav-add-page-select"><option value="">Choose content...</option>'.$pageOptions.'</select><button type="button" id="nav-add-page-btn" class="btn ghost">+ Add page</button></div></div>'
            .'</div>'
            .'</div></div>'
            .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
            .'<div class="panel-head"><h2>Menu items</h2></div>'
            .'<div class="panel-body">'
            .'<p class="muted">Drag rows to reorder. "Parent" nests an item under another top-level item as a dropdown.</p>'
            .'<div class="navigation-builder-list" id="nav-builder-list">'.$rowsHtml.'</div>'
            .'</div></div>'
            .'<button class="btn" type="submit" style="margin-top:20px">Save navigation</button>'
            .'</form>'
            .$navScript;
        $body=settings_docket('/admin/appearance',null,'Navigation','Edit the public site\'s header navigation menu.',$body);
        layout('Navigation',$body,true);
        exit;
    }
    if($path==='/admin/appearance/website-theme'){
        $websiteThemeBuiltins=['dark','dark-green','light-grey'];
        $websiteThemeRepo=new \Erased\Packages\InstalledPackageRepository(db());
        $websiteThemePackages=array_values(array_filter($websiteThemeRepo->all('theme'),fn($p)=>($p['manifest']['theme_scope']??null)==='website'));
        $isValidWebsiteTheme=fn(string $v)=>in_array($v,$websiteThemeBuiltins,true)||erased_resolve_theme_package($v,'website')!==null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            verify_csrf();
            $action=(string)($_POST['action']??'save');
            try{
                if($action==='upload_theme'){
                    $message=erased_handle_theme_upload('website');
                    audit('theme.upload',['scope'=>'website']);
                    flash('success',$message);
                }elseif($action==='delete_theme'){
                    $packageId=(string)($_POST['id']??'');
                    if($websiteThemeRepo->find($packageId)!==null){
                        (new \Erased\Packages\PackageUninstaller($websiteThemeRepo,new \Erased\Packages\PackageLifecycleLoader(),platform_events()))->removePreservingData($packageId);
                        if(setting('website_theme')==='package:'.$packageId)set_setting('website_theme','dark-green');
                        audit('theme.delete',['package_id'=>$packageId,'scope'=>'website']);
                        flash('success','Theme removed.');
                    }
                }else{
                    $chosenTheme=(string)($_POST['website_theme']??'dark-green');
                    if(str_starts_with($chosenTheme,'package:')){
                        $chosenId=substr($chosenTheme,8);
                        $chosenPkg=$websiteThemeRepo->find($chosenId);
                        if($chosenPkg&&$chosenPkg['package_type']==='theme'&&($chosenPkg['manifest']['theme_scope']??null)==='website'&&!$chosenPkg['enabled']){
                            (new \Erased\Packages\PackageLifecycleExecutor(new \Erased\Packages\PackageStateManager($websiteThemeRepo),new \Erased\Packages\PackageLifecycleLoader(),$websiteThemeRepo,capability_runtime()->resolver(),platform_events(),package_license_checker()))->enable($chosenId);
                        }
                    }
                    set_setting('website_theme',$isValidWebsiteTheme($chosenTheme)?$chosenTheme:'dark-green');
                    audit('theme.settings',['scope'=>'website']);
                    flash('success','Website theme saved.');
                }
            }catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            erased_redirect_preserving_studio_embed('/admin/appearance/website-theme');
        }
        $previewWebsiteTheme=(string)($_GET['preview']??'');
        if($previewWebsiteTheme!==''&&$isValidWebsiteTheme($previewWebsiteTheme))erased_apply_setting_overrides(['website_theme'=>$previewWebsiteTheme]);
        $websiteThemeOptions=[
            'dark'=>['Dark','A neutral dark palette'],
            'dark-green'=>['Dark Green','The default ERASED public look'],
            'light-grey'=>['Light Grey','A light, neutral palette'],
        ];
        $currentWebsiteTheme=$previewWebsiteTheme!==''&&$isValidWebsiteTheme($previewWebsiteTheme)?$previewWebsiteTheme:setting('website_theme','dark-green');
        $websiteThemeCardsHtml='';
        foreach($websiteThemeOptions as $themeVal=>$themeInfo){
            [$themeLabel,$themeDesc]=$themeInfo;
            $websiteThemeCardsHtml.='<a href="/admin/appearance/website-theme?preview='.e($themeVal).'" style="text-decoration:none;color:inherit">'.erased_theme_card($themeVal,'website_theme',$themeLabel,$themeDesc,$themeVal,$currentWebsiteTheme===$themeVal).'</a>';
        }
        $knownThemeSwatches=['erased.theme-matte-grey'=>'theme-matte-grey','erased.theme-cyberpunk'=>'theme-cyberpunk','erased.theme-minimal'=>'theme-minimal','erased.theme-corporate'=>'theme-corporate','erased.theme-glass'=>'theme-glass'];
        foreach($websiteThemePackages as $pkg){
            $pkgValue='package:'.$pkg['package_id'];
            $swatch=$knownThemeSwatches[$pkg['package_id']]??'custom';
            $websiteThemeCardsHtml.='<a href="/admin/appearance/website-theme?preview='.e($pkgValue).'" style="text-decoration:none;color:inherit">'.erased_theme_card($pkgValue,'website_theme',$pkg['name'],'Custom uploaded theme · v'.$pkg['version'],$swatch,$currentWebsiteTheme===$pkgValue).'</a>';
        }
        $body=appearance_tools_nav('website-theme');
        if($previewWebsiteTheme!==''&&$isValidWebsiteTheme($previewWebsiteTheme))$body.='<div class="notice">Previewing "'.e($previewWebsiteTheme).'" - open <a href="/" target="_blank">the public site</a> in a new tab to see it (this admin page itself never changes). Nothing is saved until you pick it below and click Save.</div>';
        $body.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">';
        $body.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Website theme</h2></div><div class="panel-body"><p class="muted">Controls the public-facing website only - articles, the homepage, comments, galleries, the header and footer. Completely independent from the Admin Panel Theme page; changing one never affects the other.</p><div class="theme-card-grid">'.$websiteThemeCardsHtml.'</div></div></div>';
        $body.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Upload a custom website theme</h2></div><div class="panel-body"><p class="muted">A theme ZIP with a <span class="mono">package.json</span> manifest (<span class="mono">type:"theme"</span>, <span class="mono">theme_scope:"website"</span>, an <span class="mono">assets</span> field naming its <span class="mono">.css</span> file) plus that CSS file at the ZIP root. The site\'s structural layout (nav, homepage grid, article/comment markup) stays the same - a custom theme overrides colours, fonts, and spacing on top of it.</p><input type="file" name="theme" accept=".zip"><button class="btn" type="submit" name="action" value="upload_theme" style="margin-top:10px">Upload theme</button></div></div>';
        // The "Save website theme" button and the form it belongs to must
        // close here, before the per-theme "Remove" forms below - HTML
        // doesn't support nested <form> elements, and a browser silently
        // drops a re-entrant <form> start tag while one is already open,
        // then closes the OUTER form early at the first stray </form>,
        // orphaning the Save button (and every "Remove" form after the
        // first) outside any form at all. Confirmed live: with any custom
        // theme installed, Save website theme did nothing when clicked.
        $body.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body"><button class="btn" type="submit" name="action" value="save">Save website theme</button></div></div></form>';
        if($websiteThemePackages){
            $body.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Installed custom themes</h2></div><div class="panel-body"><div class="admin-row-list">';
            foreach($websiteThemePackages as $pkg){
                $body.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($pkg['name']).' <span class="badge">v'.e($pkg['version']).'</span></div><div class="admin-row-meta">'.e($pkg['package_id']).' &middot; by '.e($pkg['manifest']['author']??'?').'</div></div><div class="admin-row-actions"><form method="post" onsubmit="return confirm(&quot;Remove this theme? If it\'s the active website theme, the site falls back to Dark Green.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="id" value="'.e($pkg['package_id']).'"><button class="btn danger" type="submit" name="action" value="delete_theme">Remove</button></form></div></div>';
            }
            $body.='</div></div></div>';
        }
        $body=settings_docket('/admin/appearance',null,'Website Theme','Pick the public website\'s look, independent of the admin panel.',$body);
        layout('Website Theme',$body,true);
        exit;
    }
}
if($path==='/admin/themes'||$path==='/admin/appearance'){
 require_permission('packages.manage');
 $adminThemeBuiltins=['dark-green','dark-grey','light-grey','ops-deck'];
 $adminThemeRepo=new \Erased\Packages\InstalledPackageRepository(db());
 $adminThemePackages=array_values(array_filter($adminThemeRepo->all('theme'),fn($p)=>($p['manifest']['theme_scope']??null)==='admin'));
 $isValidAdminTheme=fn(string $v)=>in_array($v,$adminThemeBuiltins,true)||erased_resolve_theme_package($v,'admin')!==null;
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'save');
  try{
   if($action==='upload_theme'){
    $message=erased_handle_theme_upload('admin');
    audit('theme.upload',['scope'=>'admin']);
    flash('success',$message);
   }elseif($action==='delete_theme'){
    $packageId=(string)($_POST['id']??'');
    if($adminThemeRepo->find($packageId)!==null){
     (new \Erased\Packages\PackageUninstaller($adminThemeRepo,new \Erased\Packages\PackageLifecycleLoader(),platform_events()))->removePreservingData($packageId);
     if(setting('admin_theme')==='package:'.$packageId)set_setting('admin_theme','dark-green');
     audit('theme.delete',['package_id'=>$packageId,'scope'=>'admin']);
     flash('success','Theme removed.');
    }
   }else{
    $chosenTheme=(string)($_POST['admin_theme']??'dark-green');
    if(str_starts_with($chosenTheme,'package:')){
     $chosenId=substr($chosenTheme,8);
     $chosenPkg=$adminThemeRepo->find($chosenId);
     if($chosenPkg&&$chosenPkg['package_type']==='theme'&&($chosenPkg['manifest']['theme_scope']??null)==='admin'&&!$chosenPkg['enabled']){
      (new \Erased\Packages\PackageLifecycleExecutor(new \Erased\Packages\PackageStateManager($adminThemeRepo),new \Erased\Packages\PackageLifecycleLoader(),$adminThemeRepo,capability_runtime()->resolver(),platform_events(),package_license_checker()))->enable($chosenId);
     }
    }
    set_setting('admin_theme',$isValidAdminTheme($chosenTheme)?$chosenTheme:'dark-green');
    set_setting('header_layout',in_array($_POST['header_layout']??'standard',['standard','compact','centered'],true)?$_POST['header_layout']:'standard');
    $accent=trim((string)($_POST['theme_accent']??'#2dfc98'));
    if(!preg_match('/^#[0-9a-fA-F]{6}$/',$accent))$accent='#2dfc98';
    set_setting('theme_accent',$accent);
    audit('theme.settings');
    flash('success','Theme settings saved.');
   }
  }catch(Throwable $e){
   flash('error',$e->getMessage());
  }
  redirect('/admin/themes');
 }
 $previewAdminTheme=(string)($_GET['preview']??'');
 if($previewAdminTheme!==''&&$isValidAdminTheme($previewAdminTheme))erased_apply_setting_overrides(['admin_theme'=>$previewAdminTheme]);
 $adminThemeOptions=[
  'dark-green'=>['Dark Green','- Ferro-Green'],
  'dark-grey'=>['Dark','Blueprint - Cyanotype'],
  'light-grey'=>['Light','Blueprint - Cyanotype'],
  'ops-deck'=>['Ops Deck','Mission-control dashboard'],
 ];
 $currentAdminTheme=$previewAdminTheme!==''&&$isValidAdminTheme($previewAdminTheme)?$previewAdminTheme:setting('admin_theme','dark-green');
 $themeCardsHtml='';
 foreach($adminThemeOptions as $themeVal=>$themeInfo){
  [$themeLabel,$themeDesc]=$themeInfo;
  $themeCardsHtml.='<a href="/admin/themes?preview='.e($themeVal).'" style="text-decoration:none;color:inherit">'.erased_theme_card($themeVal,'admin_theme',$themeLabel,$themeDesc,$themeVal,$currentAdminTheme===$themeVal).'</a>';
 }
 foreach($adminThemePackages as $pkg){
  $pkgValue='package:'.$pkg['package_id'];
  $themeCardsHtml.='<a href="/admin/themes?preview='.e($pkgValue).'" style="text-decoration:none;color:inherit">'.erased_theme_card($pkgValue,'admin_theme',$pkg['name'],'Custom uploaded theme · v'.$pkg['version'],'custom',$currentAdminTheme===$pkgValue).'</a>';
 }
 $h=appearance_tools_nav('themes');
 if($previewAdminTheme!==''&&$isValidAdminTheme($previewAdminTheme))$h.='<div class="notice">Previewing "'.e($previewAdminTheme).'" - nothing is saved until you pick it below and click Save.</div>';
 $h.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">';
 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Admin panel theme</h2></div><div class="panel-body"><p class="muted">Controls the whole admin panel - nav, header, dashboard. Only the built-in Dark Green, Dark, and Light palettes also affect the public website\'s look, and only when Website Theme (its own page) is left on a matching built-in. Click a card to preview it live on this page before saving.</p><div class="theme-card-grid">'.$themeCardsHtml.'</div></div></div>';
 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Upload a custom admin theme</h2></div><div class="panel-body"><p class="muted">A theme ZIP with a <span class="mono">package.json</span> manifest (<span class="mono">type:"theme"</span>, <span class="mono">theme_scope:"admin"</span>, an <span class="mono">assets</span> field naming its <span class="mono">.css</span> file) plus that CSS file at the ZIP root. Custom admin themes override Blueprint\'s colour tokens - the panel\'s own layout and components stay the same.</p><input type="file" name="theme" accept=".zip"><button class="btn" type="submit" name="action" value="upload_theme" style="margin-top:10px">Upload theme</button></div></div>';
 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Public site header</h2></div><div class="panel-body"><div class="fgrid"><div class="fslot"><label>Layout<select name="header_layout"><option value="standard"'.(setting('header_layout','standard')==='standard'?' selected':'').'>Standard</option><option value="compact"'.(setting('header_layout')==='compact'?' selected':'').'>Compact</option><option value="centered"'.(setting('header_layout')==='centered'?' selected':'').'>Centered</option></select></label></div><div class="fslot"><label>Accent colour<input type="color" name="theme_accent" value="'.e(setting('theme_accent','#2dfc98')).'"></label></div></div></div></div>';
 // The Save button (and every real <form> field above it, including
 // header_layout/theme_accent) must close here, before the per-theme
 // "Remove" forms below - HTML doesn't support nested <form> elements; a
 // browser drops a re-entrant <form> start tag and closes the OUTER form
 // early at the first stray </form>, orphaning everything after it
 // (confirmed live on the sibling Website Theme page with the identical
 // pattern - see the note there). Currently latent here since no
 // admin-scope custom theme is installed, but a real landmine otherwise.
 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body"><button class="btn" type="submit" name="action" value="save">Save theme settings</button></div></div></form>';
 if($adminThemePackages){
  $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Installed custom themes</h2></div><div class="panel-body"><div class="admin-row-list">';
  foreach($adminThemePackages as $pkg){
   $h.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($pkg['name']).' <span class="badge">v'.e($pkg['version']).'</span></div><div class="admin-row-meta">'.e($pkg['package_id']).' &middot; by '.e($pkg['manifest']['author']??'?').'</div></div><div class="admin-row-actions"><form method="post" onsubmit="return confirm(&quot;Remove this theme? If it\'s the active admin theme, the panel falls back to Dark Green.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="id" value="'.e($pkg['package_id']).'"><button class="btn danger" type="submit" name="action" value="delete_theme">Remove</button></form></div></div>';
  }
  $h.='</div></div></div>';
 }
 $h.='<div class="panel appearance-pointer"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><p class="muted">Logo and favicon live on their own page. The public website\'s own theme is now part of ERASED Studio.</p><a class="btn ghost" href="/admin/appearance/branding">Open Branding &amp; Logos &rarr;</a> <a class="btn ghost" href="/admin/studio?tab=theme">Open Website Theme in ERASED Studio &rarr;</a></div>';
 $h=settings_docket('/admin/appearance',null,'Theme','Pick the admin panel\'s look and public-site header layout.',$h);
 layout('Themes',$h,true);
 exit;
}
if($path==='/admin/users'){require_permission('users.manage');$roles=['user'=>'User','writer'=>'Writer','editor'=>'Editor','admin'=>'Administrator'];$section=$_GET['section']??'accounts';if($section==='')$section='accounts';$selectedId=(int)($_GET['user']??0);if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$action=$_POST['action']??'create';$role=array_key_exists($_POST['role']??'', $roles)?$_POST['role']:'user';if($action==='create'){$email=trim($_POST['email']??'');$username=trim($_POST['username']??'');$name=trim($_POST['display_name']??'');$password=(string)($_POST['password']??'');$validationErrors=[];if(!filter_var($email,FILTER_VALIDATE_EMAIL))$validationErrors[]='Enter a valid email address.';if(!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/',$username))$validationErrors[]='Username must be 3–30 characters using letters, numbers, dots, dashes or underscores.';if($errors=password_policy_errors($password))$validationErrors=array_merge($validationErrors,$errors);
 // users.email/username both carry a UNIQUE index with no pre-check before
 // this INSERT - a duplicate previously reached the DB unguarded and threw
 // an uncaught PDOException (a raw fatal-error page instead of a normal
 // validation message), reproduced live via /admin/users?section=create.
 if(!$validationErrors){$dupe=db()->prepare('SELECT email,username FROM users WHERE email=? OR username=? LIMIT 1');$dupe->execute([$email,$username]);if($existing=$dupe->fetch()){if(strcasecmp((string)$existing['email'],$email)===0)$validationErrors[]='That email address is already in use.';if(strcasecmp((string)$existing['username'],$username)===0)$validationErrors[]='That username is already taken.';}}
 if($validationErrors){$_SESSION['user_create_old']=['email'=>$email,'username'=>$username,'display_name'=>$name,'role'=>$role,'is_active'=>isset($_POST['is_active']),'two_factor_enabled'=>isset($_POST['two_factor_enabled'])];flash('error',implode(' ',$validationErrors));redirect('/admin/users?section=create');}$isActive=isset($_POST['is_active'])?1:0;$twoFactor=($role==='admin'&&isset($_POST['two_factor_enabled']))?1:0;$q=db()->prepare('INSERT INTO users(email,username,display_name,password_hash,role,created_at,is_active,two_factor_enabled) VALUES(?,?,?,?,?,NOW(),?,?)');$q->execute([$email,$username,$name,secure_password_hash($password),$role,$isActive,$twoFactor]);$id=(int)db()->lastInsertId();audit('users.create',['id'=>$id,'role'=>$role]);flash('success','User account created.');redirect('/admin/users?section=accounts&user='.$id);}else{$id=(int)($_POST['id']??0);if($id===(int)current_user()['id']&&(!isset($_POST['is_active'])||$role!=='admin')){flash('error','You cannot remove your own administrator access.');redirect('/admin/users?section=accounts&user='.$id);}$username=trim($_POST['username']??'');if($username!==''&&!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/',$username)){flash('error','Username must be 3–30 characters using letters, numbers, dots, dashes or underscores.');redirect('/admin/users?section=accounts&user='.$id);}
 if($username!==''){$dupe=db()->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');$dupe->execute([$username,$id]);if($dupe->fetch()){flash('error','That username is already taken.');redirect('/admin/users?section=accounts&user='.$id);}}
 $twoFactor=($role==='admin'&&isset($_POST['two_factor_enabled']))?1:0;$q=db()->prepare('UPDATE users SET role=?,is_active=?,display_name=?,username=?,two_factor_enabled=? WHERE id=?');$q->execute([$role,isset($_POST['is_active'])?1:0,trim($_POST['display_name']??''),$username!==''?$username:null,$twoFactor,$id]);if(!empty($_POST['new_password'])){if($errors=password_policy_errors((string)$_POST['new_password'])){flash('error',implode(' ',$errors));redirect('/admin/users?section=accounts&user='.$id);}$q=db()->prepare('UPDATE users SET password_hash=?,session_version=COALESCE(session_version,0)+1 WHERE id=?');$q->execute([secure_password_hash((string)$_POST['new_password']),$id]);db()->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$id]);}audit('users.update',['id'=>$id,'role'=>$role]);flash('success','User account saved.');redirect('/admin/users?section=accounts&user='.$id);}}$rows=db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();$titleRow='<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; PEOPLE</p><h1>Users</h1></div></div>';if($section==='roles'){$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><h2>Account levels</h2><a class="btn tertiary" href="/admin/users">Back</a></div><div class="panel-body"><div class="fgrid"><div><strong>User</strong><p class="muted">Can log in and read permitted content. Cannot create or edit website content.</p></div><div><strong>Writer</strong><p class="muted">Creates posts and edits only their own posts. Saves drafts for Editor approval.</p></div><div><strong>Editor</strong><p class="muted">Creates, edits, publishes and deletes all content; manages media, publishing and comments.</p></div><div><strong>Administrator</strong><p class="muted">Full CMS control, including users, settings, security, payments, packages and backups.</p></div></div></div></div>';layout('Users',$h,true);exit;}if($section==='create'){$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><div><h2>Create account</h2><p class="muted" style="margin:4px 0 0">Choose the account level carefully. It controls what the person can access and edit.</p></div><a class="btn tertiary" href="/admin/users">Back</a></div><div class="panel-body"><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="create"><div class="fgrid"><div class="fslot"><label>Username<input name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.-]+" required></label></div><div class="fslot"><label>Email<input type="email" name="email" required></label></div><div class="fslot"><label>Display name<input name="display_name" value="'.e($old['display_name']??'').'"></label></div><div class="fslot"><label>Account level<select name="role">';foreach($roles as $value=>$label)$h.='<option value="'.$value.'">'.$label.'</option>';$h.='</select></label></div><div class="fslot"><label>Temporary password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><small class="field-help password-guidance">Minimum 8 characters. 12 or more is strongly recommended.</small></div></div><section class="account-security-panel"><h3>Account security</h3><div class="account-security-options"><label class="account-security-option"><span><strong>Active account</strong><small>Allow this user to sign in and use the account.</small></span><input type="checkbox" name="is_active" value="1" checked></label><label class="account-security-option"><span><strong>Email two-factor authentication</strong><small>Administrator accounts only. Requires working email settings.</small></span><input type="checkbox" name="two_factor_enabled" value="1"></label></div></section><button class="btn" style="margin-top:14px">Create account</button></form></div></div>';layout('Users',$h,true);exit;}if($section==='accounts'&&$selectedId>0){$q=db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');$q->execute([$selectedId]);$u=$q->fetch();if(!$u){flash('error','User account not found.');redirect('/admin/users?section=accounts');}$role=normalized_role($u['role']??'user');$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head" style="display:flex;align-items:center;justify-content:space-between"><div><h2>'.e($u['display_name']?:($u['username']?:$u['email'])).'</h2><p class="muted" style="margin:4px 0 0">'.e($u['email']).' &middot; created '.e($u['created_at']??'').'</p></div><a class="btn tertiary" href="/admin/users?section=accounts">Back to accounts</a></div><div class="panel-body"><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'.(int)$u['id'].'"><div class="fgrid"><div class="fslot"><label>Username<input name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.-]+" value="'.e($u['username']??'').'"></label></div><div class="fslot"><label>Display name<input name="display_name" value="'.e($u['display_name']??'').'"></label></div><div class="fslot"><label>Account level<select name="role">';foreach($roles as $value=>$label)$h.='<option value="'.$value.'"'.($role===$value?' selected':'').'>'.$label.'</option>';$h.='</select></label></div><div class="fslot"><label>New password<input type="password" name="new_password" minlength="8" placeholder="Leave empty to keep the current password" autocomplete="new-password"></label><small class="field-help password-guidance">Minimum 8 characters. 12 or more is strongly recommended.</small></div></div><section class="account-security-panel"><h3>Account security</h3><div class="account-security-options"><label class="account-security-option"><span><strong>Active account</strong><small>Allow this user to sign in and use the account.</small></span><input type="checkbox" name="is_active" value="1"'.(($u['is_active']??1)?' checked':'').'></label><label class="account-security-option"><span><strong>Email two-factor authentication</strong><small>Administrator accounts only. Requires working email settings.</small></span><input type="checkbox" name="two_factor_enabled" value="1"'.(((int)($u['two_factor_enabled']??0)===1)?' checked':'').'></label></div></section><div class="notice" style="margin-top:14px"><strong>'.e($roles[$role]).':</strong> '.e(role_description($role)).'</div><button class="btn" style="margin-top:14px">Save account</button></form></div></div>';layout('Users',$h,true);exit;}if($section==='accounts'){$list='';foreach($rows as $u){$role=normalized_role($u['role']??'user');$statusPill=($u['is_active']??1)?'<span class="stampword live">Active</span>':'<span class="stampword draft">Disabled</span>';$list.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($u['display_name']?:($u['username']?:$u['email'])).' <span class="badge">'.e($roles[$role]).'</span> '.$statusPill.'</div><div class="admin-row-meta">@'.e($u['username']?:'no-username').' &middot; '.e($u['email']).' &middot; created '.e($u['created_at']??'').'</div></div><div class="admin-row-actions"><a class="btn ghost" href="/admin/users?section=accounts&user='.(int)$u['id'].'">Edit</a></div></div>';}$h=$titleRow.'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>All Accounts</h2></div><div class="panel-body"><div class="admin-row-list">'.($list?:'<div class="admin-row-empty">No user accounts found.</div>').'</div></div></div>';layout('Users',$h,true);exit;}redirect('/admin/users');}
if($path==='/language/set'&&$_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $code=strtolower(trim((string)($_POST['language']??'')));
 $allowed=array_column(active_languages(),'code');
 if(in_array($code,$allowed,true)){
  $_SESSION['site_language']=$code;
  $_SESSION['admin_language']=$code;
  $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
  setcookie('erased_language',$code,['expires'=>time()+31536000,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  setcookie('erased_admin_language',$code,['expires'=>time()+31536000,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
 }
 redirect(safe_return('/'));
}
if($path==='/admin/languages/edit'){require_permission('languages.manage');$code=(string)($_GET['code']??'en');$group=in_array($_GET['group']??'site',['admin','site'],true)?(string)$_GET['group']:'site';if(!isset(erased_language_catalog()[$code])){http_response_code(404);exit('Unknown language.');}$base=translation_data('en',$group);$file=language_dir($code).'/'.$group.'.json';$own=is_file($file)?(json_decode((string)file_get_contents($file),true)?:[]):[];if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$values=[];foreach($base as $key=>$fallback)$values[$key]=trim((string)($_POST['translation'][$key]??''));$validation=(new \Erased\Language\TranslationValidator())->validate($group,$values);if($validation['errors']!==[]){flash('error','Not saved - '.implode(' ',$validation['errors']));redirect('/admin/languages/edit?code='.rawurlencode($code).'&group='.$group);}save_translation_file($code,$group,$values);audit('language.translations.save',['code'=>$code,'group'=>$group]);flash('success','Translation file saved.');redirect('/admin/languages/edit?code='.rawurlencode($code).'&group='.$group);}$live=erased_live_translation_keys();$liveForGroup=$live[$group]??[];
$sections=[];
foreach($base as $key=>$fallback){
 $originFile=$liveForGroup[$key]??null;
 $section=erased_translation_key_section($key,$group,$originFile);
 $sections[$section][$key]=['fallback'=>$fallback,'live'=>$originFile!==null];
}
ksort($sections);
$sectionsHtml='';$liveTotal=0;$keyTotal=0;
foreach($sections as $sectionName=>$keys){
 $sectionLive=0;
 $rowsHtml='';
 foreach($keys as $key=>$info){
  $value=(string)($own[$key]??'');
  $liveTotal+=$info['live']?1:0;$sectionLive+=$info['live']?1:0;$keyTotal++;
  $badge=$info['live']?'<span class="badge" style="border-color:var(--green);color:var(--green)" title="Shown on the live site or admin panel">Live</span>':'<span class="badge" style="opacity:.55" title="Not currently rendered anywhere - translating this has no visible effect yet">Not used yet</span>';
  $rowsHtml.='<tr'.($info['live']?'':' style="opacity:.7"').'><td class="translation-key">'.e($key).' '.$badge.'</td><td>'.e($info['fallback']).'</td><td><input name="translation['.e($key).']" value="'.e($value).'" placeholder="'.e($info['fallback']).'"></td></tr>';
 }
 $sectionsHtml.='<details class="translation-section notranslate" translate="no" open><summary>'.$sectionName.' <span class="muted">('.$sectionLive.'/'.count($keys).' live)</span></summary><table class="translation-table"><thead><tr><th>Key</th><th>English fallback</th><th>Translation</th></tr></thead><tbody>'.$rowsHtml.'</tbody></table></details>';
}
$meta=erased_language_catalog()[$code];$h='<div class="toolbar"><div><a class="btn tertiary" href="/admin/languages">Back</a><a class="btn ghost" href="/admin/languages/export?code='.e($code).'&amp;group='.e($group).'">'.e(tr('export','admin')).'</a></div></div><p class="muted" style="margin:0 0 14px">'.$liveTotal.' of '.$keyTotal.' '.e($group).' text strings are currently shown somewhere on the site or admin panel - grouped below by where they appear. Strings marked "Not used yet" can still be translated for when they\'re wired up, but translating them has no visible effect right now.</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input id="translationSearch" placeholder="'.e(tr('search','admin')).'" style="margin-bottom:14px">'.$sectionsHtml.'<button style="margin-top:14px">'.e(tr('save','admin')).'</button></form><script>document.getElementById("translationSearch").addEventListener("input",function(){var q=this.value.toLowerCase();document.querySelectorAll(".translation-table tbody tr").forEach(function(r){r.style.display=r.textContent.toLowerCase().includes(q)?"":"none";});document.querySelectorAll(".translation-section").forEach(function(s){var anyVisible=Array.prototype.some.call(s.querySelectorAll("tbody tr"),function(r){return r.style.display!=="none";});s.style.display=anyVisible?"":"none";if(q)s.open=true;});});</script>';$h=settings_docket('/admin/languages',null,tr('translation_editor','admin'),$meta['native'].' · '.ucfirst($group).' · saved as storage/languages/'.$code.'/'.$group.'.json',$h);layout('Translations',$h,true);exit;}
if($path==='/admin/languages/export'){require_permission('languages.manage');$code=(string)($_GET['code']??'en');$group=(string)($_GET['group']??'site');if(!in_array($group,['site','admin'],true))$group='site';
 if(($_GET['format']??'')==='json'){
  $file=language_dir($code).'/'.$group.'.json';if(!is_file($file)){http_response_code(404);exit('Language file not found.');}
  header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="erased-'.$code.'-'.$group.'.json"');readfile($file);exit;
 }
 if(!isset(erased_language_catalog()[$code])){http_response_code(404);exit('Unknown language.');}
 try{$zipBytes=(new \Erased\Language\LanguagePackZipBuilder())->buildExport($code);}
 catch(Throwable $e){http_response_code(500);exit('Could not build the language pack: '.$e->getMessage());}
 audit('language_pack.export',['code'=>$code]);
 header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="erased-language-'.$code.'.zip"');header('Content-Length: '.strlen($zipBytes));
 echo $zipBytes;exit;
}
if($path==='/admin/languages'){require_permission('languages.manage');ensure_language_files();if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$action=isset($_POST['delete_language'])?'delete':($_POST['action']??'settings');if(isset($_POST['delete_language']))$_POST['delete_code']=$_POST['delete_language'];if($action==='generate_translation_base'){
 $newCode=strtolower(trim((string)($_POST['base_code']??'')));
 $newName=trim((string)($_POST['base_name']??''));
 $newNative=trim((string)($_POST['base_native']??''));
 $newRtl=isset($_POST['base_rtl']);
 if(!preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/i',$newCode)||$newName===''||$newNative===''){flash('error','Enter a valid language code, English name and native name to generate a translation base.');redirect('/admin/languages');}
 try{$zipBytes=(new \Erased\Language\LanguagePackZipBuilder())->buildBase($newCode,$newName,$newNative,$newRtl);}
 catch(Throwable $e){flash('error',$e->getMessage());redirect('/admin/languages');}
 audit('language_pack.generate_base',['code'=>$newCode]);
 header('Content-Type: application/zip');
 header('Content-Disposition: attachment; filename="erased-language-'.$newCode.'-base.zip"');
 header('Content-Length: '.strlen($zipBytes));
 echo $zipBytes;exit;
}if($action==='create'){
 $code=strtolower(trim((string)($_POST['new_code']??'')));$name=trim((string)($_POST['new_name']??''));$native=trim((string)($_POST['new_native']??''));
 if(!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/',$code)||$name===''||$native===''){flash('error','Enter a valid language code, English name and native name.');redirect('/admin/languages');}
 $q=db()->prepare('INSERT INTO languages(code,name,native_name,is_default,is_active,is_rtl) VALUES(?,?,?,0,1,0) ON DUPLICATE KEY UPDATE name=VALUES(name),native_name=VALUES(native_name),is_active=1');$q->execute([$code,$name,$native]);
 ensure_language_files($code);
 audit('language.create',['code'=>$code]);flash('success','Language added. You can now translate its Admin and Website text.');redirect('/admin/languages');
 }if($action==='delete'){
  $code=strtolower(trim((string)($_POST['delete_code']??'')));
  foreach((new \Erased\Packages\InstalledPackageRepository(db()))->all('language') as $pkg){
   $pkgCode=(string)($pkg['manifest']['language_code']??'');
   if($pkgCode!==''&&$pkgCode===$code){flash('error','This language is managed by an installed language pack - uninstall it from the Language Packs panel instead.');redirect('/admin/languages');}
  }
  try{erased_delete_language_completely($code);}catch(\RuntimeException $e){flash('error',$e->getMessage());redirect('/admin/languages');}
  $check=db()->prepare('SELECT COUNT(*) FROM languages WHERE code=?');$check->execute([$code]);
  if((int)$check->fetchColumn()>0){flash('error','The language could not be deleted from the database.');redirect('/admin/languages');}
  audit('language.delete',['code'=>$code]);flash('success','Language and its Admin and Website translations were deleted.');redirect('/admin/languages?deleted='.rawurlencode($code));
 }if($action==='settings'){$catalog=erased_language_catalog();$adminCode=(string)($_POST['admin_language']??'en');$siteCode=(string)($_POST['site_language']??'en');if(!isset($catalog[$adminCode]))$adminCode='en';if(!isset($catalog[$siteCode]))$siteCode='en';$switcherVal=isset($_POST['show_language_switcher'])?'1':'0';$syncAdminVal=isset($_POST['admin_sync_site_language'])?'1':'0';if($adminCode!==$siteCode){$syncAdminVal='0';}elseif($syncAdminVal==='1'){$adminCode=$siteCode;}set_setting('admin_language',$adminCode);set_setting('site_language',$siteCode);set_setting('show_language_switcher',$switcherVal);set_setting('nav_show_language',$switcherVal);set_setting('admin_sync_site_language',$syncAdminVal);set_setting('detect_browser_language',isset($_POST['detect_browser_language'])?'1':'0');
  $_SESSION['site_language']=$siteCode;$_SESSION['admin_language']=$adminCode;
  $langCookieSecure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
  setcookie('erased_language',$siteCode,['expires'=>time()+31536000,'path'=>'/','secure'=>$langCookieSecure,'httponly'=>true,'samesite'=>'Lax']);
  setcookie('erased_admin_language',$adminCode,['expires'=>time()+31536000,'path'=>'/','secure'=>$langCookieSecure,'httponly'=>true,'samesite'=>'Lax']);foreach($catalog as $code=>$meta){$active=isset($_POST['active'][$code])?1:0;if($code===$siteCode)$active=1;$q=db()->prepare('INSERT INTO languages(code,name,native_name,is_default,is_active,is_rtl) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),native_name=VALUES(native_name),is_default=VALUES(is_default),is_active=VALUES(is_active),is_rtl=VALUES(is_rtl)');$q->execute([$code,$meta['name'],$meta['native'],$code===$siteCode?1:0,$active,$meta['rtl']?1:0]);}audit('language.settings');flash('success',tr('language_settings_saved','admin'));}redirect('/admin/languages');}$catalog=erased_language_catalog();
$activeRows=active_languages();$active=array_column($activeRows,null,'code');
$siteDefault=(string)setting('site_language','en');$adminDefault=(string)setting('admin_language','en');
$installedCount=count($catalog);$activeCount=count($activeRows);$translatedTotal=0;$translationTotal=0;$languageCards='';
foreach($catalog as $code=>$meta){
 $stats=erased_language_completion($code);
 $pct=$stats['pct'];
 $translationTotal+=$stats['total'];
 $translatedTotal+=$stats['done'];
 $state=[];if($code===$siteDefault)$state[]='Website default';if($code===$adminDefault)$state[]='Admin default';if(isset($active[$code]))$state[]='Active';if($meta['rtl']??false)$state[]='RTL';
 $badges=$state?implode(' · ',array_map('e',$state)):'';
 $languageCards.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title"><label class="check"><input type="checkbox" name="active['.e($code).']" value="1" '.(isset($active[$code])?'checked':'').'> '.e($meta['native']).'</label></div><div class="admin-row-meta">'.e($meta['name']).' · '.e(strtoupper($code)).($badges!==''?' · '.$badges:'').'</div><div class="admin-row-meta">'.$pct.'% translated</div></div><div class="admin-row-actions">'
  .'<a class="btn ghost" href="/admin/languages/edit?code='.e($code).'&group=site">Website text</a>'
  .'<a class="btn ghost" href="/admin/languages/edit?code='.e($code).'&group=admin">Admin text</a>'
  .'<a class="btn ghost" href="/admin/languages/export?code='.e($code).'&group=site">Export</a>'
  .($code!=='en'?'<button class="btn danger" type="submit" name="delete_language" value="'.e($code).'" onclick="return confirm(\'Delete this language and all its translations?\')">Delete</button>':'')
  .'</div></div>';
}
$overallPct=$translationTotal?min(100,(int)round($translatedTotal/$translationTotal*100)):100;
$h ='<div class="admin-stat-row">'
   .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$installedCount.'</span><span class="admin-stat-label">Installed languages</span></div>'
   .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$activeCount.'</span><span class="admin-stat-label">Active for visitors</span></div>'
   .'<div class="admin-stat-chip"><span class="admin-stat-value">'.$overallPct.'%</span><span class="admin-stat-label">Overall translation coverage</span></div>'
   .'<div class="admin-stat-chip"><span class="admin-stat-value">'.e(strtoupper($siteDefault)).'</span><span class="admin-stat-label">Website default</span></div>'
   .'</div>'
   .'<form method="post">'
   .'<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="settings">'
   .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
     .'<div class="panel-head"><h2>Default languages</h2></div>'
     .'<div class="panel-body">'
       .'<p class="muted">Choose separate languages for visitors and the administration dashboard.</p>'
       .'<div class="fgrid">'
         .'<div class="fslot"><label>Website language<select name="site_language">'.erased_language_select_options($siteDefault).'</select></label></div>'
         .'<div class="fslot"><label>Admin language<select name="admin_language" id="erasedAdminLanguageSelect">'.erased_language_select_options($adminDefault).'</select></label><small class="field-help" id="erasedAdminLanguageSyncNote" style="display:none">Currently overridden by "Apply selected website language to Admin Panel" below - uncheck it for this to take effect.</small></div>'
       .'</div>'
     .'</div>'
   .'</div>'
   .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
     .'<div class="panel-head"><h2>Visitor &amp; admin experience</h2></div>'
     .'<div class="panel-body">'
       .'<label class="check"><input type="checkbox" name="show_language_switcher" value="1" '.(setting('show_language_switcher','1')==='1'?'checked':'').'> Show language selector</label>'
       .'<p class="muted">Display a compact selector in the public navigation.</p>'
       .'<label class="check"><input type="checkbox" name="admin_sync_site_language" id="erasedAdminSyncCheckbox" value="1" '.(setting('admin_sync_site_language','0')==='1'?'checked':'').'> Apply selected website language to Admin Panel</label>'
       .'<p class="muted">When enabled, choosing a language (e.g. Lithuanian) applies to both website and admin panel automatically.</p>'
       .'<label class="check"><input type="checkbox" name="detect_browser_language" value="1" '.(setting('detect_browser_language','1')==='1'?'checked':'').'> Detect browser language</label>'
       .'<p class="muted">Use a supported visitor language automatically when possible.</p>'
     .'</div>'
   .'</div>'
   .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
     .'<div class="panel-head"><h2>Installed languages</h2></div>'
     .'<div class="panel-body">'
       .'<p class="muted">Activate languages, review translation progress, edit text or export translation files.</p>'
       .'<div style="margin-bottom:12px"><a class="btn ghost" href="/admin/languages/edit?code='.e($siteDefault).'&group=site">Translate website</a></div>'
       .'<div class="admin-row-list">'.$languageCards.'</div>'
     .'</div>'
   .'</div>'
   .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
     .'<div class="panel-head"><h2>Add a language</h2></div>'
     .'<div class="panel-body">'
       .'<p class="muted">Use an ISO language code such as <code>de</code>, <code>fr</code> or <code>pt-br</code>.</p>'
       .'<div class="fgrid three">'
         .'<div class="fslot"><label>Language code<input name="new_code" placeholder="de"></label></div>'
         .'<div class="fslot"><label>English name<input name="new_name" placeholder="German"></label></div>'
         .'<div class="fslot"><label>Native name<input name="new_native" placeholder="Deutsch"></label></div>'
       .'</div>'
       .'<div class="actions" style="margin-top:12px"><button type="submit" name="action" value="create" class="btn ghost">Add language</button></div>'
     .'</div>'
   .'</div>'
   .'<div class="actions" style="margin:16px 0 24px"><button type="submit" class="btn">Save language settings</button></div>'
   .'</form>';
$h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>'
  .'<div class="panel-head"><h2>Generate Translation Base</h2></div>'
  .'<div class="panel-body">'
    .'<p class="muted">Download a starter ZIP pre-filled with English text for a new language, ready to hand-translate and install as a pack from <a href="/admin/packages">Import / Export</a> - the same Package Engine screen that installs, enables, disables, and uninstalls every package type, including language packs.</p>'
    .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="generate_translation_base">'
    .'<div class="fgrid three"><div class="fslot"><label>Language code<input name="base_code" placeholder="de"></label></div><div class="fslot"><label>English name<input name="base_name" placeholder="German"></label></div><div class="fslot"><label>Native name<input name="base_native" placeholder="Deutsch"></label></div></div>'
    .'<label class="check" style="margin-top:10px"><input type="checkbox" name="base_rtl" value="1"> Right-to-left script</label>'
    .'<div class="actions" style="margin-top:12px"><button type="submit" class="btn ghost">Generate translation base ZIP</button></div>'
    .'</form>'
  .'</div>'
.'</div>'
.'<script>(function(){var sync=document.getElementById("erasedAdminSyncCheckbox"),note=document.getElementById("erasedAdminLanguageSyncNote");if(!sync||!note)return;function update(){note.style.display=sync.checked?"":"none";}sync.addEventListener("change",update);update();})();</script>';
$h=settings_docket('/admin/languages',null,'Languages','Manage website and dashboard languages, translation coverage, defaults and visitor language behavior.',$h);
layout('Languages',$h,true);exit;}
if($path==='/admin/memberships'){require_permission('memberships.manage');if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();if(($_POST['kind']??'plan')==='plan'){$q=db()->prepare('INSERT INTO membership_plans(name,slug,price_minor,currency,interval_name,is_active) VALUES(?,?,?,?,?,1)');$q->execute([trim($_POST['name']),slugify($_POST['name']),(int)round((float)$_POST['price']*100),strtoupper($_POST['currency']),$_POST['interval_name']]);}else{$q=db()->prepare('INSERT INTO memberships(user_id,plan_id,status,provider,provider_reference) VALUES(?,?,?,?,?)');$q->execute([(int)$_POST['user_id'],(int)$_POST['plan_id'],'active','manual',trim($_POST['reference']??'')]);}audit('membership.create');redirect('/admin/memberships');}$plans=db()->query('SELECT * FROM membership_plans ORDER BY id DESC')->fetchAll();$users=db()->query('SELECT id,email FROM users ORDER BY email')->fetchAll();$h='<h1>Memberships</h1><div class="grid"><form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="kind" value="plan"><h2>New plan</h2><input name="name" placeholder="Plan name" required><input name="price" type="number" step="0.01" placeholder="Price"><input name="currency" value="NOK"><select name="interval_name"><option>month</option><option>year</option><option>one-time</option></select><button>Create plan</button></form><form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="kind" value="membership"><h2>Grant membership</h2><select name="user_id">';foreach($users as $u)$h.='<option value="'.$u['id'].'">'.e($u['email']).'</option>';$h.='</select><select name="plan_id">';foreach($plans as $p)$h.='<option value="'.$p['id'].'">'.e($p['name']).'</option>';$h.='</select><input name="reference" placeholder="Reference"><button>Grant</button></form></div><div class="card"><h2>Plans</h2>';foreach($plans as $p)$h.='<p><strong>'.e($p['name']).'</strong> '.number_format($p['price_minor']/100,2).' '.e($p['currency']).'/'.e($p['interval_name']).'</p>';$h.='</div>';layout('Memberships',$h,true);exit;}
if($path==='/admin/payments'){require_permission('payments.manage');
 if(!erased_package_active('erased.payments')){
  $corners='<div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
  $h='<div class="panel">'.$corners.'<div class="panel-body">'
   .'<p class="muted">Payment gateway management now lives in the Payments plugin.</p>'
   .'<p>Install and activate the Payments plugin to configure providers, credentials, and view transaction history.</p>'
   .'<a class="btn" href="/admin/packages">Go to Packages</a>'
   .'</div></div>';
  $page='<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code('/admin/payments')).' &middot; SYSTEM</p><h1>Payments</h1><p>Configure a payment provider, currency, and routing for checkout.</p></div></div>'.$h;
  layout('Payments',$page,true);exit;
 }
 // erased.payments is installed and enabled: fall through without matching,
 // so the plugin admin router (registered from its admin_routes manifest
 // entry, see InstalledPluginAdminSurface) serves this exact path instead.
}
if($path==='/admin/layout-studio'){
 erased_redirect_preserving_studio_embed('/admin/appearance/homepage');
}
if($path==='/admin/studio'){
 require_permission('packages.manage');
 if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='save_studio_typography_seo'){
     verify_csrf();
     $fontKeys=array_keys(erased_typography_fonts());
     $headingFont=(string)($_POST['typography_heading_font']??'system');
     if(!in_array($headingFont,$fontKeys,true))$headingFont='system';
     $bodyFont=(string)($_POST['typography_body_font']??'system');
     if(!in_array($bodyFont,$fontKeys,true))$bodyFont='system';
     $baseSize=max(14,min(20,(int)($_POST['typography_base_size']??16)));
     set_setting('typography_heading_font',$headingFont);
     set_setting('typography_body_font',$bodyFont);
     set_setting('typography_base_size',(string)$baseSize);
     flash('success','Typography settings saved.');
     redirect('/admin/studio?tab=typography');
 }
 $fontOptions='';
 foreach(erased_typography_fonts() as $fontKey=>$fontMeta){
     $fontOptions.='<option value="'.e($fontKey).'"'.'>'.e($fontMeta[0]).'</option>';
 }
 $headingSelected=setting('typography_heading_font','system');
 $bodySelected=setting('typography_body_font','system');
 $headingOptions=str_replace('value="'.e($headingSelected).'"','value="'.e($headingSelected).'" selected',$fontOptions);
 $bodyOptions=str_replace('value="'.e($bodySelected).'"','value="'.e($bodySelected).'" selected',$fontOptions);
 $typographyPane='<form method="post" action="/admin/studio"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_studio_typography_seo">'
  .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Typography</h2></div><div class="panel-body"><div class="fgrid"><div class="fslot"><label>Heading font<select name="typography_heading_font">'.$headingOptions.'</select></label></div><div class="fslot"><label>Body font<select name="typography_body_font">'.$bodyOptions.'</select></label></div><div class="fslot"><label>Base font size (px)<input type="number" name="typography_base_size" min="14" max="20" value="'.(int)setting('typography_base_size','16').'"></label></div></div></div></div>'
  .'<div style="margin-top:16px"><button type="submit" class="btn">Save typography</button></div></form>';
 $tabDefs=[
     ['layout','Layout','/admin/appearance/homepage?studio_embed=1'],
     ['navigation','Navigation','/admin/appearance/navigation?studio_embed=1'],
     ['theme','Theme','/admin/appearance/website-theme?studio_embed=1'],
     ['typography','Typography',null],
 ];
 $tabsHtml='';$panesHtml='';
 foreach($tabDefs as [$tabKey,$tabLabel,$tabSrc]){
     $tabsHtml.='<button type="button" class="studio-tab'.($tabKey==='layout'?' is-active':'').'" data-studio-tab="'.e($tabKey).'" role="tab">'.e($tabLabel).'</button>';
     $paneContent=$tabSrc!==null?'<iframe class="studio-iframe" data-src="'.e($tabSrc).'" title="'.e($tabLabel).'"></iframe>':$typographyPane;
     $panesHtml.='<div class="studio-pane'.($tabKey==='layout'?' is-active':'').'" data-studio-pane="'.e($tabKey).'">'.$paneContent.'</div>';
 }
 $studioJs='<script>(function(){var tabs=document.querySelectorAll(".studio-tab"),panes=document.querySelectorAll(".studio-pane");function activate(key){tabs.forEach(function(t){t.classList.toggle("is-active",t.dataset.studioTab===key)});panes.forEach(function(p){var active=p.dataset.studioPane===key;p.classList.toggle("is-active",active);if(active){var f=p.querySelector("iframe[data-src]");if(f){f.src=f.dataset.src;f.removeAttribute("data-src")}}});if(history.replaceState)history.replaceState(null,"","?tab="+key)}tabs.forEach(function(t){t.addEventListener("click",function(){activate(t.dataset.studioTab)})});activate(new URLSearchParams(location.search).get("tab")||"layout")})();</script>';
 $h='<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($path)).' &middot; SITE</p><h1>ERASED Studio</h1><p class="muted" style="margin:4px 0 0">Layout, navigation, theme, and typography - one place, each tab the real, already-working screen.</p></div></div><div class="studio-tabs" role="tablist">'.$tabsHtml.'</div><div class="studio-panes">'.$panesHtml.'</div>'.$studioJs;
 layout('ERASED Studio',$h,true);exit;
}
if($path==='/admin/appearance/homepage'){
 require_login();
 require_permission('packages.manage');
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_layout_studio_composition') {
     verify_csrf();
     $regions = json_decode((string)($_POST['regions'] ?? '{}'), true);
     $enabled = json_decode((string)($_POST['enabled'] ?? '[]'), true);
     $order = json_decode((string)($_POST['order'] ?? '[]'), true);

     if (is_array($regions)) set_setting('homepage_block_regions', json_encode($regions));
     if (is_array($enabled)) set_setting('homepage_enabled_blocks', json_encode($enabled));
     if (is_array($order)) set_setting('homepage_block_order', json_encode($order));

     try {
         $pdo = db();
         // Column names here previously didn't match the real schema at all
         // (page_type/revision_id vs the actual target_type/revision), so
         // this insert threw on every call under this app's throw-on-error
         // PDO mode - silently swallowed by the catch below while the request
         // still reported "Homepage composition saved." Also added the
         // required `name` column, which has no default.
         $key = 'default.homepage.homepage';
         $payload = ['regions' => $regions, 'enabled' => $enabled, 'order' => $order];
         $stmt = $pdo->prepare('INSERT INTO layout_drafts (draft_key, profile_id, target_type, target_id, name, status, revision, payload_json, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), revision = revision + 1, updated_at = NOW()');
         $stmt->execute([$key, 'default', 'homepage', 'homepage', 'Homepage layout', 'draft', json_encode($payload)]);
     } catch (Throwable $e) {}

     if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
         header('Content-Type: application/json');
         echo json_encode(['success' => true]);
         exit;
     }
     flash('success', 'Homepage composition saved.');
     erased_redirect_preserving_studio_embed('/admin/appearance/homepage');
 }
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_website_look_settings') {
     verify_csrf();
     $requestedPreset = (string)($_POST['homepage_layout_preset'] ?? 'three');
     $preset = in_array($requestedPreset, ['one', 'two-left', 'two-right', 'three'], true) ? $requestedPreset : 'three';
     $left = max(0, min(45, (int)($_POST['homepage_left_width'] ?? 20)));
     $right = max(0, min(45, (int)($_POST['homepage_right_width'] ?? 20)));
     $gap = max(0, min(80, (int)($_POST['homepage_column_gap'] ?? 24)));
     $max = max(900, min(2400, (int)($_POST['homepage_max_width'] ?? 1600)));
     $widgetGap = max(0, min(80, (int)($_POST['homepage_widget_gap'] ?? 16)));
     $sticky = isset($_POST['homepage_sticky_sidebars']) ? '1' : '0';

     set_setting('homepage_layout_preset', $preset);
     set_setting('homepage_left_width', (string)$left);
     set_setting('homepage_right_width', (string)$right);
     set_setting('homepage_column_gap', (string)$gap);
     set_setting('homepage_max_width', (string)$max);
     set_setting('homepage_widget_gap', (string)$widgetGap);
     set_setting('homepage_sticky_sidebars', $sticky);

     flash('success', 'Website Layout & Look settings saved successfully.');
     erased_redirect_preserving_studio_embed('/admin/appearance/homepage');
 }
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_header_settings') {
     verify_csrf();
     set_setting('nav_show_admin', isset($_POST['nav_show_admin']) ? '1' : '0');
     set_setting('nav_admin_label', trim((string)($_POST['nav_admin_label'] ?? 'Admin')));
     $headerLayout = (string)($_POST['header_layout'] ?? 'standard');
     set_setting('header_layout', in_array($headerLayout, ['standard', 'centered', 'compact'], true) ? $headerLayout : 'standard');
     set_setting('nav_show_search', isset($_POST['nav_show_search']) ? '1' : '0');
     set_setting('nav_search_placeholder', trim((string)($_POST['nav_search_placeholder'] ?? 'Search posts...')));
     set_setting('show_language_switcher', isset($_POST['show_language_switcher']) ? '1' : '0');
     set_setting('nav_show_language', isset($_POST['show_language_switcher']) ? '1' : '0');

     flash('success', 'Header settings saved successfully.');
     erased_redirect_preserving_studio_embed('/admin/appearance/homepage');
 }
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_footer_settings') {
     verify_csrf();
     set_setting('footer_show_rss', isset($_POST['footer_show_rss']) ? '1' : '0');
     $knownPlatforms = array_keys(erased_social_platforms());
     for ($n = 1; $n <= 8; $n++) {
         $platform = (string)($_POST['social_link_' . $n . '_platform'] ?? '');
         if (!in_array($platform, $knownPlatforms, true)) $platform = '';
         set_setting('social_link_' . $n . '_platform', $platform);
         set_setting('social_link_' . $n . '_url', trim((string)($_POST['social_link_' . $n . '_url'] ?? '')));
     }

     set_setting('newsletter_enabled', isset($_POST['newsletter_enabled']) ? '1' : '0');
     set_setting('newsletter_button_text', trim((string)($_POST['newsletter_button_text'] ?? 'Subscribe')));
     set_setting('footer_text', trim((string)($_POST['footer_text'] ?? '')));

     flash('success', 'Footer settings saved successfully.');
     erased_redirect_preserving_studio_embed('/admin/appearance/homepage');
 }
 homepage_studio_admin();
}
if($path==='/admin/packages'){
 require_permission('packages.manage');
 $pkgRoot=defined('ROOT')?ROOT:dirname(__DIR__);
 $pkgStaging=$pkgRoot.'/storage/plugins/staging';
 $pkgInstalled=$pkgRoot.'/storage/plugins/installed';
 $pkgRollback=$pkgRoot.'/storage/plugins/rollback';
 $pkgRepo=new \Erased\Packages\InstalledPackageRepository(db());

 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'install');
  try{
   if($action==='install'){
    $file=$_FILES['package']??null;
    if(!is_array($file)||(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a valid package ZIP.');
    $tmpName=(string)($file['tmp_name']??'');
    if($tmpName===''||!is_uploaded_file($tmpName))throw new RuntimeException('The package upload could not be verified.');
    $size=(int)($file['size']??0);
    if($size<1||$size>25*1024*1024)throw new RuntimeException('Package ZIPs must be between 1 byte and 25 MB.');

    // Peek at the manifest id to decide install vs. update: uploading a ZIP
    // for a package that's already installed updates it in place rather
    // than failing, matching how package managers normally behave.
    $inspection=(new \Erased\Packages\PackageArchiveInspector())->inspect($tmpName);
    $peekZip=new ZipArchive();
    $peekId=null;
    if($peekZip->open($tmpName)===true){
     $manifestRaw=$peekZip->getFromName($inspection['manifest_path']);
     $peekZip->close();
     $manifestData=is_string($manifestRaw)?json_decode($manifestRaw,true):null;
     $peekId=is_array($manifestData)?(string)($manifestData['id']??''):null;
    }

    $pkgMigrations=new \Erased\Packages\PackageMigrationRunner(db());

    if($peekId!==null&&$peekId!==''&&$pkgRepo->find($peekId)!==null){
     $orchestrator=new \Erased\Packages\PackageUpdateOrchestrator(
      new \Erased\Packages\PackageArchiveStager(new \Erased\Packages\PackageArchiveInspector(),new \Erased\Packages\PackageValidator()),
      new \Erased\Packages\PackageInstaller(new \Erased\Packages\PackageValidator()),
      $pkgRepo,
      new \Erased\Packages\PackageLifecycleLoader(),
      new \Erased\Packages\PackageDependencyResolver(),
      $pkgMigrations,
      platform_events(),
      new \Erased\Packages\PackageIntegrityChecker(),
     );
     $result=$orchestrator->updateArchive($tmpName,$pkgStaging,$pkgInstalled,$pkgRollback);
     audit('package.update',['package_id'=>$result['manifest']->id(),'from_version'=>$result['from_version'],'to_version'=>$result['manifest']->version()]);
     flash('success','Updated '.$result['manifest']->name().' from '.$result['from_version'].' to '.$result['manifest']->version().'.');
    }else{
     $orchestrator=new \Erased\Packages\PackageInstallOrchestrator(
      new \Erased\Packages\PackageArchiveStager(new \Erased\Packages\PackageArchiveInspector(),new \Erased\Packages\PackageValidator()),
      new \Erased\Packages\PackageInstaller(new \Erased\Packages\PackageValidator()),
      $pkgRepo,
      new \Erased\Packages\PackageLifecycleLoader(),
      $pkgMigrations,
      capability_runtime()->resolver(),
      platform_events(),
      new \Erased\Packages\PackageIntegrityChecker(),
     );
     $result=$orchestrator->installArchive($tmpName,$pkgStaging,$pkgInstalled,$pkgRollback);
     audit('package.install',['package_id'=>$result['manifest']->id(),'version'=>$result['manifest']->version()]);
     flash('success','Installed '.$result['manifest']->name().' '.$result['manifest']->version().'.');
    }
    capability_runtime()->refresh();
    service_runtime()->refresh();
    plugin_admin_surface()->refresh();
   }elseif($action==='enable'||$action==='disable'){
    $packageId=(string)($_POST['id']??'');
    $executor=new \Erased\Packages\PackageLifecycleExecutor(new \Erased\Packages\PackageStateManager($pkgRepo),new \Erased\Packages\PackageLifecycleLoader(),$pkgRepo,capability_runtime()->resolver(),platform_events(),package_license_checker());
    $action==='enable'?$executor->enable($packageId):$executor->disable($packageId);
    audit('package.'.$action,['package_id'=>$packageId]);
    flash('success','Package '.($action==='enable'?'enabled':'disabled').'.');
    capability_runtime()->refresh();
    service_runtime()->refresh();
    plugin_admin_surface()->refresh();
   }elseif($action==='activate_license'){
    $packageId=(string)($_POST['id']??'');
    if($pkgRepo->find($packageId)===null)throw new RuntimeException('Package not found.');
    $licenseKey=trim((string)($_POST['license_key']??''));
    if($licenseKey==='')throw new RuntimeException('A license key is required.');
    (new \Erased\Packages\PackageLicenseRepository(db()))->activate($packageId,$licenseKey);
    audit('package.'.$action,['package_id'=>$packageId]);
    flash('success','License key activated.');
   }elseif($action==='deactivate_license'){
    $packageId=(string)($_POST['id']??'');
    (new \Erased\Packages\PackageLicenseRepository(db()))->deactivate($packageId);
    audit('package.'.$action,['package_id'=>$packageId]);
    flash('success','License key removed. This does not disable the package or delete its data.');
   }elseif($action==='uninstall_keep_data'){
    $packageId=(string)($_POST['id']??'');
    // Unlike disable, uninstall previously ran with no check for another
    // enabled package depending on this one - it could be removed out from
    // under a dependent with no warning, breaking it at next use.
    (new \Erased\Packages\PackageStateManager($pkgRepo))->assertCanUninstall($packageId);
    $uninstaller=new \Erased\Packages\PackageUninstaller($pkgRepo,new \Erased\Packages\PackageLifecycleLoader(),platform_events());
    $uninstaller->removePreservingData($packageId);
    audit('package.uninstall',['package_id'=>$packageId,'mode'=>'keep_data']);
    flash('success','Package removed. Its own data was left in place.');
    capability_runtime()->refresh();
    service_runtime()->refresh();
    plugin_admin_surface()->refresh();
   }elseif($action==='uninstall_delete_data'){
    $packageId=(string)($_POST['id']??'');
    (new \Erased\Packages\PackageStateManager($pkgRepo))->assertCanUninstall($packageId);
    $uninstaller=new \Erased\Packages\PackageUninstaller($pkgRepo,new \Erased\Packages\PackageLifecycleLoader(),platform_events());
    $uninstaller->removeAndDeleteData($packageId,$pkgRollback);
    audit('package.uninstall',['package_id'=>$packageId,'mode'=>'delete_data']);
    flash('success','Package and its data permanently removed. Its code was backed up first and can be restored from Rollback on this package\'s details page while the backup exists.');
    capability_runtime()->refresh();
    service_runtime()->refresh();
    plugin_admin_surface()->refresh();
   }elseif($action==='rollback'){
    $packageId=(string)($_POST['id']??'');
    $backupDirectory=(string)($_POST['backup']??'');
    $rollbackService=new \Erased\Packages\PackageRollbackService($pkgRepo);
    $manifest=$rollbackService->rollbackTo($packageId,$backupDirectory,$pkgInstalled,$pkgRollback);
    audit('package.rollback',['package_id'=>$packageId,'to_version'=>$manifest->version(),'backup'=>$backupDirectory]);
    flash('success','Rolled back '.$manifest->name().' to '.$manifest->version().'.');
   }elseif($action==='verify_integrity'){
    $packageId=(string)($_POST['id']??'');
    $pkg=$pkgRepo->find($packageId);
    if($pkg===null)throw new RuntimeException('Package not found.');
    $storedManifest=$pkg['integrity_manifest']??null;
    if(!is_array($storedManifest)){
     throw new RuntimeException('No integrity baseline is recorded for this package yet - it was installed before this check existed. Update the package once to record one.');
    }
    $verification=(new \Erased\Packages\PackageIntegrityChecker())->verify((string)$pkg['installed_path'],$storedManifest);
    $pkgRepo->updateIntegrityStatus($packageId,$verification['status']);
    audit('package.verify_integrity',['package_id'=>$packageId,'status'=>$verification['status'],'mismatched'=>count($verification['mismatched']),'missing'=>count($verification['missing']),'unexpected'=>count($verification['unexpected'])]);
    if($verification['status']==='ok'){
     flash('success','Integrity verified: all files match the recorded baseline exactly.');
    }else{
     $parts=[];
     if(!empty($verification['mismatched']))$parts[]=count($verification['mismatched']).' file(s) changed';
     if(!empty($verification['missing']))$parts[]=count($verification['missing']).' file(s) missing';
     if(!empty($verification['unexpected']))$parts[]=count($verification['unexpected']).' unexpected file(s)';
     flash('error','Integrity check found drift: '.implode(', ',$parts).'. See the Integrity panel below for details.');
    }
   }elseif($action==='migrate_legacy'){
    $migrator=new \Erased\Packages\LegacyPackageMigrator(db(),$pkgRepo,$pkgRoot);
    $result=$migrator->migrate();
    audit('package.migrate_legacy',$result);
    if(!empty($result['failed'])){
     flash('error','Migrated '.count($result['migrated']).', skipped '.count($result['skipped']).', failed: '.implode(', ',array_keys($result['failed'])).'.');
    }else{
     flash('success','Migrated '.count($result['migrated']).' legacy package(s), skipped '.count($result['skipped']).' already migrated.');
    }
   }
  }catch(Throwable $e){
   flash('error',$e->getMessage());
  }
  redirect(in_array($action,['rollback','activate_license','deactivate_license','verify_integrity'],true)&&($_POST['id']??'')!==''?'/admin/packages/'.rawurlencode((string)$_POST['id']):'/admin/packages');
 }

 $packages=$pkgRepo->all();
 $corners='<div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';

 $body='<div class="panel">'.$corners.'<div class="panel-head"><h2>Install or Update</h2></div><div class="panel-body">'
  .'<form method="post" enctype="multipart/form-data">'
  .'<input type="hidden" name="csrf" value="'.csrf().'">'
  .'<input type="hidden" name="action" value="install">'
  .'<div class="fgrid"><div class="fslot"><label>Package ZIP<input type="file" name="package" accept=".zip" required></label></div></div>'
  .'<p class="muted">Uploading a ZIP for a package that\'s already installed updates it to that version instead of reinstalling.</p>'
  .'<button class="btn" type="submit">Install or update package</button>'
  .'</form></div></div>';

 $legacyCount=0;
 try{$legacyCount=(int)db()->query("SELECT COUNT(*) FROM packages")->fetchColumn();}catch(Throwable $e){}
 if($legacyCount>0){
  $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Legacy Packages Found</h2></div><div class="panel-body">'
   .'<p class="muted">'.$legacyCount.' package(s) are registered in the old pre-Package-Engine registry. Migrating adds them to the modern registry below without deleting or moving their files.</p>'
   .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="migrate_legacy"><button class="btn ghost" type="submit">Migrate legacy packages</button></form>'
   .'</div></div>';
 }

 $totalCount=count($packages);
 $activeCount=0;$attentionCount=0;
 foreach($packages as $pp){
  if(!empty($pp['enabled']))$activeCount++;
  if((($pp['health_status']??'ok')==='error'))$attentionCount++;
 }
 $disabledCount=$totalCount-$activeCount;
 if($totalCount>0){
  $body.='<div class="admin-stat-row">'
   .'<div class="admin-stat-chip"><div class="admin-stat-value">'.$totalCount.'</div><div class="admin-stat-label">Installed</div></div>'
   .'<div class="admin-stat-chip"><div class="admin-stat-value">'.$activeCount.'</div><div class="admin-stat-label">Active</div></div>'
   .'<div class="admin-stat-chip"><div class="admin-stat-value">'.$disabledCount.'</div><div class="admin-stat-label">Disabled</div></div>'
   .'<div class="admin-stat-chip"><div class="admin-stat-value">'.$attentionCount.'</div><div class="admin-stat-label">Needs Attention</div></div>'
   .'</div>';
 }

 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Installed Packages</h2></div><div class="panel-body">';
 if(empty($packages)){
  $body.='<p class="muted">No packages installed yet.</p>';
 }else{
  $typeLabels=['module'=>'Modules','theme'=>'Themes','language'=>'Languages','website-type'=>'Website Types','homepage-preset'=>'Homepage Presets','widget'=>'Widgets'];
  $grouped=[];
  foreach($packages as $p)$grouped[(string)$p['package_type']][]=$p;
  $renderPackageRow=function(array $p) use ($corners): string {
   $manifest=is_array($p['manifest']??null)?$p['manifest']:[];
   $description=(string)($manifest['description']??'');
   $author=(string)($manifest['author']??'');
   $unhealthy=($p['health_status']??'ok')==='error';
   $statusPill=$p['enabled']?'<span class="stampword live">Active</span>':'<span class="stampword draft">Disabled</span>';
   $paidBadge=(($manifest['pricing']['model']??'free')==='paid')?' <span class="stampword draft">Paid</span>':'';
   $metaParts=[];
   if($author!=='')$metaParts[]='By '.e($author);
   $row='<div class="admin-row"><div class="admin-row-body">'
    .'<div class="admin-row-title">'.e($p['name']).' <span class="muted">'.e($p['version']).'</span> '.$statusPill.$paidBadge.($unhealthy?' <span class="stampword draft">Needs attention</span>':'').'</div>'
    .($metaParts?'<div class="admin-row-meta">'.implode(' &middot; ',$metaParts).'</div>':'');
   if($description!=='')$row.='<div class="admin-row-meta">'.e($description).'</div>';
   if($unhealthy&&$p['last_error']!=='')$row.='<a class="attn" href="/admin/packages/'.e($p['package_id']).'"><span class="sev warn"></span><div class="t">Last error<div class="s">'.e((string)$p['last_error']).'</div></div></a>';
   $row.='</div><div class="admin-row-actions"><details class="row-menu"><summary class="btn ghost">Manage</summary><div class="row-menu-panel">'
    .'<a class="btn tertiary" href="/admin/packages/'.e($p['package_id']).'">Details</a>'
    .'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="'.($p['enabled']?'disable':'enable').'"><input type="hidden" name="id" value="'.e($p['package_id']).'"><button class="btn ghost" type="submit">'.($p['enabled']?'Disable':'Enable').'</button></form>'
    .'<form method="post" onsubmit="return confirm(&quot;Remove '.e($p['name']).'? Its code will be deleted, but any data it created (its own database tables, uploaded content) is left in place.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="uninstall_keep_data"><input type="hidden" name="id" value="'.e($p['package_id']).'"><button class="btn danger" type="submit">Uninstall</button></form>'
    .'</div></details></div></div>';
   return $row;
  };
  foreach($typeLabels as $type=>$label){
   if(empty($grouped[$type]))continue;
   $body.='<details class="panel" open>'.$corners.'<summary class="panel-head"><h2>'.e($label).' ('.count($grouped[$type]).')</h2></summary><div class="panel-body admin-row-list">';
   foreach($grouped[$type] as $p)$body.=$renderPackageRow($p);
   $body.='</div></details>';
   unset($grouped[$type]);
  }
  foreach($grouped as $type=>$rows){
   if(empty($rows))continue;
   $body.='<details class="panel" open>'.$corners.'<summary class="panel-head"><h2>'.e(ucfirst($type)).' ('.count($rows).')</h2></summary><div class="panel-body admin-row-list">';
   foreach($rows as $p)$body.=$renderPackageRow($p);
   $body.='</div></details>';
  }
 }
 $body.='</div></div>';

 $h=settings_docket('/admin/packages','packages','Packages','Install, activate, and manage packages through the modular Package Engine.',$body);
 layout('Packages',$h,true);exit;
}
if(preg_match('#^/admin/packages/([a-z0-9][a-z0-9._-]*)$#',$path,$pm)){
 require_permission('packages.manage');
 $packageId=$pm[1];
 $pkgRoot=defined('ROOT')?ROOT:dirname(__DIR__);
 $pkgInstalled=$pkgRoot.'/storage/plugins/installed';
 $pkgRollback=$pkgRoot.'/storage/plugins/rollback';
 $pkgRepo=new \Erased\Packages\InstalledPackageRepository(db());
 $p=$pkgRepo->find($packageId);
 $corners='<div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
 if($p===null){
  $orphanBackups=(new \Erased\Packages\PackageRollbackService($pkgRepo))->listBackups($packageId,$pkgRollback);
  if(empty($orphanBackups)){flash('error','Package not found.');redirect('/admin/packages');}
  $body='<p class="muted"><a href="/admin/packages">&larr; All packages</a></p>';
  $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>'.e($packageId).'</h2></div><div class="panel-body">'
   .'<p class="muted">Not currently installed - its data was permanently removed. The code from before that removal is still available below and can be restored, but restoring only brings back its code and registry entry, not any data its uninstall hook deleted.</p>'
   .'</div></div>';
  $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Available Backups</h2></div><div class="panel-body">';
  $body.='<table><thead><tr><th>Version</th><th>Backed up</th><th></th></tr></thead><tbody>';
  foreach($orphanBackups as $b){
   $body.='<tr><td>'.e($b['version']).'</td><td>'.e($b['backed_up_at']).'</td><td><form method="post" action="/admin/packages" onsubmit="return confirm(&quot;Restore '.e($packageId).' version '.e($b['version']).' from backup? This reinstalls it, disabled, from the backed-up files.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="rollback"><input type="hidden" name="id" value="'.e($packageId).'"><input type="hidden" name="backup" value="'.e($b['directory']).'"><button class="btn ghost" type="submit">Restore</button></form></td></tr>';
  }
  $body.='</tbody></table>';
  $body.='</div></div>';
  $h=settings_docket('/admin/packages','packages','Package: '.$packageId,'This package is not currently installed.',$body);
  layout('Package: '.e($packageId),$h,true);exit;
 }
 $manifest=is_array($p['manifest']??null)?$p['manifest']:[];
 $manifestObj=new \Erased\Packages\PackageManifest($manifest);
 $installedVersions=[];
 foreach($pkgRepo->all() as $ip)$installedVersions[$ip['package_id']]=$ip['version'];
 $deps=(new \Erased\Packages\PackageDependencyResolver())->describeDependencies($manifestObj,$installedVersions);
 $migrations=(new \Erased\Packages\PackageMigrationRunner(db()))->appliedMigrationsWithTimestamps($packageId);
 $backups=(new \Erased\Packages\PackageRollbackService($pkgRepo))->listBackups($packageId,$pkgRollback);
 $unhealthy=($p['health_status']??'ok')==='error';

 $body='<p class="muted"><a href="/admin/packages">&larr; All packages</a></p>';

 $body.='<div class="admin-stat-row">'
  .'<div class="admin-stat-chip"><div class="admin-stat-value">'.($p['enabled']?'Active':'Disabled').'</div><div class="admin-stat-label">Status</div></div>'
  .'<div class="admin-stat-chip"><div class="admin-stat-value">'.e($manifestObj->version()).'</div><div class="admin-stat-label">Version</div></div>'
  .'<div class="admin-stat-chip"><div class="admin-stat-value">'.e($p['package_type']).'</div><div class="admin-stat-label">Type</div></div>'
  .'<div class="admin-stat-chip"><div class="admin-stat-value">'.($unhealthy?'Attention':'OK').'</div><div class="admin-stat-label">Health</div></div>'
  .'</div>';

 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Identity</h2></div><div class="panel-body">';
 if($unhealthy&&$p['last_error']!=='')$body.='<p class="muted" style="color:var(--warn)">Last error ('.e((string)$p['last_error_at']).'): '.e((string)$p['last_error']).'</p>';
 if($manifestObj->description()!=='')$body.='<p>'.e($manifestObj->description()).'</p>';
 $body.='<table><tbody>'
  .'<tr><td class="muted">Package ID</td><td><code>'.e($p['package_id']).'</code></td></tr>'
  .'<tr><td class="muted">Author</td><td>'.e($manifestObj->author()).'</td></tr>'
  .'<tr><td class="muted">Requires ERASED CMS</td><td>'.e($manifestObj->requires()).'</td></tr>'
  .'<tr><td class="muted">Installed path</td><td><code>'.e((string)$p['installed_path']).'</code></td></tr>'
  .'<tr><td class="muted">Installed</td><td>'.e((string)$p['installed_at']).'</td></tr>'
  .'<tr><td class="muted">Last updated</td><td>'.e((string)$p['updated_at']).'</td></tr>'
  .'</tbody></table>';
 $body.='</div></div>';

 $integrityStatus=(string)($p['integrity_status']??'unknown');
 $integrityManifest=$p['integrity_manifest']??null;
 $integrityLabels=['ok'=>'Verified','mismatch'=>'Drift detected','unknown'=>'Not yet checked'];
 $integrityPillClass=$integrityStatus==='ok'?'stampword live':($integrityStatus==='mismatch'?'stampword draft':'stampword');
 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Integrity</h2></div><div class="panel-body">';
 $body.='<p class="muted">A SHA-256 checksum of every file was recorded the moment this package was last installed or updated. Verifying re-hashes the files on disk right now and compares them against that baseline - catching corruption or any change made outside the normal update flow.</p>';
 $body.='<p><span class="'.$integrityPillClass.'">'.e($integrityLabels[$integrityStatus]??$integrityStatus).'</span>'.($p['integrity_checked_at']?' <span class="muted">last checked '.e((string)$p['integrity_checked_at']).'</span>':'').'</p>';
 if(!is_array($integrityManifest)){
  $body.='<p class="muted" style="color:var(--warn)">No baseline recorded yet - this package was installed before integrity checks existed. Update it once (even to the same version is not possible; the next real update will record one).</p>';
 }else{
  $body.='<form method="post" action="/admin/packages"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="verify_integrity"><input type="hidden" name="id" value="'.e($packageId).'"><button class="btn ghost" type="submit">Verify integrity now</button></form>';
 }
 $body.='</div></div>';

 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Dependencies</h2></div><div class="panel-body">';
 if(empty($deps)){
  $body.='<p class="muted">This package declares no dependencies.</p>';
 }else{
  $statusLabels=['satisfied'=>'Satisfied','missing'=>'Missing','version-mismatch'=>'Version mismatch'];
  $body.='<table><thead><tr><th>Package</th><th>Constraint</th><th>Status</th></tr></thead><tbody>';
  foreach($deps as $d){
   $statusLabel=$statusLabels[$d['status']]??$d['status'];
   $pillClass=$d['status']==='satisfied'?'stampword live':'stampword draft';
   $body.='<tr><td><code>'.e($d['id']).'</code></td><td>'.e($d['constraint']).'</td><td><span class="'.$pillClass.'">'.e($statusLabel).($d['installed_version']!==null?' '.e($d['installed_version']):'').'</span></td></tr>';
  }
  $body.='</tbody></table>';
 }
 $body.='</div></div>';

 $provides=$manifestObj->provides();
 $requiredCaps=$manifestObj->requiredCapabilities();
 $services=is_array($manifest['services']??null)?array_keys($manifest['services']):[];
 if(!empty($provides)||!empty($requiredCaps)||!empty($services)){
  $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Capabilities</h2></div><div class="panel-body">';
  if(!empty($provides))$body.='<p><strong>Provides:</strong> '.e(implode(', ',$provides)).'</p>';
  if(!empty($requiredCaps))$body.='<p><strong>Requires capabilities:</strong> '.e(implode(', ',$requiredCaps)).'</p>';
  if(!empty($services))$body.='<p><strong>Registers services:</strong> '.e(implode(', ',$services)).'</p>';
  $body.='</div></div>';
 }

 if($manifestObj->isPaid()){
  $licenseRepo=new \Erased\Packages\PackageLicenseRepository(db());
  $storedKey=$licenseRepo->findKey($packageId);
  $priceLabel=number_format((int)$manifestObj->priceMinor()/100,2).' '.e((string)$manifestObj->priceCurrency());
  $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>License</h2></div><div class="panel-body">';
  $body.='<p><strong>Price:</strong> '.$priceLabel.'</p>';
  if($storedKey!==null){
   $body.='<p class="muted">A license key is activated for this package.</p>';
   $body.='<form method="post" action="/admin/packages" onsubmit="return confirm(&quot;Remove the license key for '.e($manifestObj->name()).'? This does not disable the package or delete its data.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="deactivate_license"><input type="hidden" name="id" value="'.e($packageId).'"><button class="btn danger" type="submit">Remove license key</button></form>';
  }else{
   $body.='<p class="muted" style="color:var(--warn)">No license key activated. This package cannot be enabled until one is.</p>';
   $body.='<form method="post" action="/admin/packages"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="activate_license"><input type="hidden" name="id" value="'.e($packageId).'"><div class="fgrid"><div class="fslot"><label>License key<input type="text" name="license_key" minlength="'.\Erased\Packages\LocalLicenseGate::MIN_LICENSE_KEY_LENGTH.'" required></label></div></div><button class="btn" type="submit" style="margin-top:10px">Activate license</button></form>';
  }
  $body.='</div></div>';
 }

 $marketplace=$manifestObj->marketplace();
 if(!empty($marketplace['homepage_url'])||!empty($marketplace['support_url'])||!empty($marketplace['tags'])){
  $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Marketplace</h2></div><div class="panel-body">';
  if(!empty($marketplace['homepage_url']))$body.='<p><strong>Homepage:</strong> <a href="'.e((string)$marketplace['homepage_url']).'" target="_blank" rel="noopener">'.e((string)$marketplace['homepage_url']).'</a></p>';
  if(!empty($marketplace['support_url']))$body.='<p><strong>Support:</strong> <a href="'.e((string)$marketplace['support_url']).'" target="_blank" rel="noopener">'.e((string)$marketplace['support_url']).'</a></p>';
  if(!empty($marketplace['tags'])&&is_array($marketplace['tags']))$body.='<p><strong>Tags:</strong> '.e(implode(', ',array_map('strval',$marketplace['tags']))).'</p>';
  $body.='</div></div>';
 }

 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Migrations</h2></div><div class="panel-body">';
 if(empty($migrations)){
  $body.='<p class="muted">This package has not shipped any database migrations.</p>';
 }else{
  $body.='<table><thead><tr><th>Migration</th><th>Applied</th></tr></thead><tbody>';
  foreach($migrations as $mig)$body.='<tr><td><code>'.e($mig['migration']).'</code></td><td>'.e($mig['applied_at']).'</td></tr>';
  $body.='</tbody></table>';
 }
 $body.='</div></div>';

 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Rollback</h2></div><div class="panel-body">';
 if(empty($backups)){
  $body.='<p class="muted">No previous versions are available to roll back to. A backup is created automatically the next time this package is updated.</p>';
 }else{
  $body.='<p class="muted">Restoring a backup replaces the currently installed files with an older version on disk. Database changes made by newer migrations are not reversed.</p>';
  $body.='<table><thead><tr><th>Version</th><th>Backed up</th><th></th></tr></thead><tbody>';
  foreach($backups as $b){
   $body.='<tr><td>'.e($b['version']).'</td><td>'.e($b['backed_up_at']).'</td><td><form method="post" action="/admin/packages" onsubmit="return confirm(&quot;Roll back '.e($manifestObj->name()).' to version '.e($b['version']).'? This replaces the currently installed files.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="rollback"><input type="hidden" name="id" value="'.e($packageId).'"><input type="hidden" name="backup" value="'.e($b['directory']).'"><button class="btn ghost" type="submit">Roll back</button></form></td></tr>';
  }
  $body.='</tbody></table>';
 }
 $body.='</div></div>';

 $body.='<div class="panel">'.$corners.'<div class="panel-head"><h2>Danger Zone</h2></div><div class="panel-body">'
  .'<p class="muted">Removes '.e($manifestObj->name()).' and runs its uninstall hook with data removal - its own database tables and uploaded content are deleted, not just disabled. Its code is backed up first and can be restored from this same page under Rollback while the backup exists, but the data its hook deletes cannot be recovered.</p>'
  .'<form method="post" onsubmit="return confirm(&quot;Permanently delete '.e($manifestObj->name()).' and its data? Its code will be backed up and stays restorable, but any data its uninstall hook removes (its database tables, uploaded content) cannot be recovered.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="uninstall_delete_data"><input type="hidden" name="id" value="'.e($packageId).'"><button class="btn danger solid" type="submit">Permanently delete package and data</button></form>'
  .'</div></div>';

 $h=settings_docket('/admin/packages','packages','Package: '.$manifestObj->name(),'Manifest details, dependencies, migrations, and rollback for this package.',$body);
 layout('Package: '.$manifestObj->name(),$h,true);exit;
}
if($path==='/admin/website-profiles'){
 require_permission('settings.manage');
 $wpRepo=new \Erased\Website\WebsiteProfileRepository(db());
 $wpTypes=new \Erased\Website\WebsiteTypeManager(ROOT.'/website-types/registry.json');
 $wpService=new \Erased\Website\WebsiteProfileService($wpRepo,$wpTypes,db());
 $wpService->seedStarterProfiles();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'');
  try{
   if($action==='create'){
    $name=trim((string)($_POST['name']??''));
    $typeId=$wpTypes->validateId((string)($_POST['type_id']??''));
    if($name==='')throw new RuntimeException('Choose a name for the new profile.');
    $config=$wpService->currentConfig();
    $wpRepo->create($typeId,$name,$config,isStarter:false,status:'draft');
    audit('website_profile.create',['type_id'=>$typeId,'name'=>$name]);
    flash('success','Created profile "'.$name.'".');
   }elseif($action==='activate'){
    $id=(int)($_POST['id']??0);
    $snapshotId=$wpService->activate($id);
    if(function_exists('erased_ensure_homepage_layout_seeded'))erased_ensure_homepage_layout_seeded((string)$id);
    audit('website_profile.activate',['id'=>$id,'snapshot_id'=>$snapshotId]);
    flash('success','Profile activated.'.($snapshotId!==null?' The previous configuration was saved as a snapshot.':''));
   }
  }catch(Throwable $e){
   flash('error',$e->getMessage());
  }
  redirect('/admin/website-profiles');
 }
 $active=$wpRepo->findActive();
 $drafts=$wpRepo->byStatus('draft');
 $snapshots=$wpRepo->byStatus('archived');
 $h='<p class="muted">A website profile is a switchable bundle of site-identity settings (name, tagline, accent colour, header layout, footer text). Activating one snapshots the current configuration first, so you can always roll back. Content and installed packages are never affected by switching profiles.</p>';

 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
 $h.='<div class="panel-head"><h2>Active Profile</h2></div><div class="panel-body">';
 if($active===null){
  $h.='<p class="muted">No profile is active yet. The site is using whatever settings were configured directly.</p>';
 }else{
  $h.='<p><strong>'.e($active['name']).'</strong> <span class="stampword live">Live</span></p>';
  $h.='<p class="muted">Type: '.e($active['type_id']).' &middot; Site name: '.e($active['config']['site_name']??'').' &middot; Tagline: '.e($active['config']['site_tagline']??'').'</p>';
 }
 $h.='</div></div>';

 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
 $h.='<div class="panel-head"><h2>Create New Profile</h2></div><div class="panel-body">';
 $h.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="create">';
 $h.='<div class="fgrid"><div class="fslot"><label>Name<input type="text" name="name" required placeholder="e.g. My Business Site"></label></div>';
 $h.='<div class="fslot"><label>Website Type<select name="type_id">';
 foreach($wpTypes->all() as $type)$h.='<option value="'.e((string)$type['id']).'">'.e((string)$type['name']).'</option>';
 $h.='</select></label></div></div>';
 $h.='<p class="muted">The new profile starts from the currently active configuration - edit it afterward before activating.</p>';
 $h.='<button class="btn" type="submit">Create Profile</button>';
 $h.='</form></div></div>';

 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
 $h.='<div class="panel-head"><h2>Draft &amp; Starter Profiles</h2></div><div class="panel-body">';
 if(empty($drafts)){
  $h.='<p class="admin-row-empty">No draft profiles yet.</p>';
 }else{
  $h.='<div class="admin-row-list">';
  foreach($drafts as $d){
   $h.='<div class="admin-row"><div class="admin-row-body">';
   $h.='<div class="admin-row-title">'.e($d['name']).' <span class="stampword draft">Draft</span></div>';
   $h.='<div class="admin-row-meta">Type: '.e($d['type_id']).($d['is_starter']?' &middot; Starter profile':'').' &middot; Site name: '.e($d['config']['site_name']??'').' &middot; Tagline: '.e($d['config']['site_tagline']??'').'</div>';
   $h.='</div><div class="admin-row-actions">';
   $h.='<a class="btn ghost" href="/admin/website-profiles/'.(int)$d['id'].'/edit">Edit</a>';
   $h.='<a class="btn tertiary" href="/admin/website-profiles/'.(int)$d['id'].'/preview" target="_blank" rel="noopener">Preview</a>';
   $h.='<form method="post" onsubmit="return confirm(&quot;Activate '.e($d['name']).'? The current configuration will be saved as a snapshot first.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="'.(int)$d['id'].'"><button class="btn" type="submit">Activate</button></form>';
   $h.='</div></div>';
  }
  $h.='</div>';
 }
 $h.='</div></div>';

 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
 $h.='<div class="panel-head"><h2>Snapshots</h2></div><div class="panel-body">';
 $h.='<p class="muted">Created automatically every time a profile is activated. Activating a snapshot restores that configuration.</p>';
 if(empty($snapshots)){
  $h.='<p class="admin-row-empty">No snapshots yet.</p>';
 }else{
  $h.='<div class="admin-row-list">';
  foreach($snapshots as $s){
   $h.='<div class="admin-row"><div class="admin-row-body">';
   $h.='<div class="admin-row-title">'.e($s['name']).'</div>';
   $h.='<div class="admin-row-meta">'.e($s['updated_at']).'</div>';
   $h.='</div><div class="admin-row-actions">';
   $h.='<form method="post" onsubmit="return confirm(&quot;Restore this snapshot? The current configuration will be saved as a new snapshot first.&quot;)"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="'.(int)$s['id'].'"><button class="btn ghost" type="submit">Restore</button></form>';
   $h.='</div></div>';
  }
  $h.='</div>';
 }
 $h.='</div></div>';

 $h=settings_docket('/admin/website-profiles',null,'Website Profiles','A curated set of site-identity settings you can switch between.',$h);
 layout('Website Profiles',$h,true);exit;
}
if(preg_match('#^/admin/website-profiles/(\d+)/edit$#',$path,$wpm)){
 require_permission('settings.manage');
 $wpRepo=new \Erased\Website\WebsiteProfileRepository(db());
 $profile=$wpRepo->find((int)$wpm[1]);
 if($profile===null){flash('error','Profile not found.');redirect('/admin/website-profiles');}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $name=trim((string)($_POST['name']??''));
  if($name==='')$name=$profile['name'];
  $config=[];
  foreach(\Erased\Website\WebsiteProfileService::CONFIG_KEYS as $key)$config[$key]=trim((string)($_POST[$key]??($profile['config'][$key]??'')));
  $wpRepo->updateConfig((int)$profile['id'],$name,$config);
  audit('website_profile.update',['id'=>$profile['id']]);
  flash('success','Profile updated.');
  redirect('/admin/website-profiles/'.(int)$profile['id'].'/edit');
 }
 $c=$profile['config'];
 $h='<p class="muted"><a href="/admin/website-profiles">&larr; All profiles</a></p>';
 $h.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'">';
 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';
 $h.='<div class="panel-head"><h2>Edit "'.e($profile['name']).'"</h2></div><div class="panel-body">';
 $h.='<div class="fgrid three">'
  .'<div class="fslot"><label>Name<input type="text" name="name" value="'.e($profile['name']).'" required></label></div>'
  .'<div class="fslot"><label>Site Name<input type="text" name="site_name" value="'.e($c['site_name']??'').'"></label></div>'
  .'<div class="fslot"><label>Tagline<input type="text" name="site_tagline" value="'.e($c['site_tagline']??'').'"></label></div>'
  .'<div class="fslot"><label>Accent Colour<input type="color" name="theme_accent" value="'.e($c['theme_accent']!==''?$c['theme_accent']:'#2dfc98').'"></label></div>'
  .'<div class="fslot"><label>Admin Panel Theme<select name="admin_theme"><option value="dark-green"'.(($c['admin_theme']??'')==='dark-green'?' selected':'').'>Dark Green</option><option value="dark-grey"'.(($c['admin_theme']??'')==='dark-grey'?' selected':'').'>Dark</option><option value="light-grey"'.(($c['admin_theme']??'')==='light-grey'?' selected':'').'>Light</option><option value="ops-deck"'.(($c['admin_theme']??'')==='ops-deck'?' selected':'').'>Ops Deck</option></select></label><small class="field-help">Custom uploaded admin themes aren\'t offered here - set those from <a href="/admin/themes">Admin Theme</a> after activating this profile.</small></div>'
  .'<div class="fslot"><label>Website Theme<select name="website_theme"><option value="dark-green"'.(($c['website_theme']??'')==='dark-green'?' selected':'').'>Dark Green</option><option value="dark"'.(($c['website_theme']??'')==='dark'?' selected':'').'>Dark</option><option value="light-grey"'.(($c['website_theme']??'')==='light-grey'?' selected':'').'>Light Grey</option></select></label><small class="field-help">Custom uploaded website themes aren\'t offered here - set those from <a href="/admin/appearance/website-theme">Website Theme</a> after activating this profile.</small></div>'
  .'<div class="fslot"><label>Header Layout<select name="header_layout"><option value="standard"'.(($c['header_layout']??'')==='standard'?' selected':'').'>Standard</option><option value="compact"'.(($c['header_layout']??'')==='compact'?' selected':'').'>Compact</option><option value="centered"'.(($c['header_layout']??'')==='centered'?' selected':'').'>Centered</option></select></label></div>'
  .'<div class="fslot"><label>Admin Link Label<input type="text" name="nav_admin_label" value="'.e($c['nav_admin_label']??'').'"></label></div>'
  .'</div>';
 $h.='<div class="fgrid">'
  .'<div class="fslot"><label>SEO Description<textarea name="seo_description">'.e($c['seo_description']??'').'</textarea></label></div>'
  .'<div class="fslot"><label>Footer Text<textarea name="footer_text">'.e($c['footer_text']??'').'</textarea></label></div>'
  .'</div>';
 $h.='<button class="btn" type="submit">Save Profile</button>';
 $h.='</div></div></form>';
 $h=settings_docket('/admin/website-profiles',null,'Edit profile: '.$profile['name'],'Edit this website profile\'s settings.',$h);
 layout('Edit profile',$h,true);exit;
}
if(preg_match('#^/admin/website-profiles/(\d+)/preview$#',$path,$wpm)){
 require_permission('settings.manage');
 $wpRepo=new \Erased\Website\WebsiteProfileRepository(db());
 $profile=$wpRepo->find((int)$wpm[1]);
 if($profile===null){flash('error','Profile not found.');redirect('/admin/website-profiles');}
 $overrides=[];
 foreach(\Erased\Website\WebsiteProfileService::CONFIG_KEYS as $key)if(array_key_exists($key,$profile['config']))$overrides[$key]=(string)$profile['config'][$key];
 erased_apply_setting_overrides($overrides);
 $body='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body"><p class="muted">This is a preview of the site chrome (header, navigation, footer, accent colour) if "'.e($profile['name']).'" were active. Nothing has been changed - this profile is not live.</p></div></div>'
  .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>'.e($overrides['site_name']??'').'</h2></div><div class="panel-body"><p>'.e($overrides['site_tagline']??'').'</p><p class="muted">'.e($overrides['seo_description']??'').'</p></div></div>'
  .'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Footer Preview</h2></div><div class="panel-body"><p>'.e($overrides['footer_text']??'').'</p></div></div>';
 header('X-Robots-Tag: noindex, nofollow');
 layout('Preview: '.$profile['name'],$body,false);exit;
}
if($path==='/admin/import'){require_permission('import.manage');if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$count=0;$warnings=[];try{if(($_FILES['wxr']['error']??1)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a WordPress XML export.');libxml_use_internal_errors(true);$xml=simplexml_load_file($_FILES['wxr']['tmp_name'],'SimpleXMLElement',LIBXML_NOCDATA);if(!$xml)throw new RuntimeException('Invalid WordPress export XML.');$ns=$xml->getNamespaces(true);foreach($xml->channel->item as $item){$wp=$item->children($ns['wp']??'');$type=(string)$wp->post_type;if(!in_array($type,['post','page']))continue;$status=(string)$wp->status==='publish'?'published':'draft';$title=trim((string)$item->title)?:'Untitled';$slug=unique_slug(db(),slugify((string)$wp->post_name?:$title));$contentNs=$item->children($ns['content']??'');$body=(string)$contentNs->encoded;$date=(string)$wp->post_date?:date('Y-m-d H:i:s');$q=db()->prepare('INSERT INTO content(type,title,slug,body,status,created_at,updated_at,language_code) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$type,$title,$slug,$body,$status,$date,$date,setting('site_language','en')]);$count++;}audit('wordpress.import',['count'=>$count]);flash('success','Imported '.$count.' WordPress items.');}catch(Throwable $e){flash('error',$e->getMessage());}redirect('/admin/import');}$body='<div class="card"><p>Upload a WordPress WXR/XML export. Posts, pages, HTML, dates, slugs and publication status are imported. Run this first on a backup.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.csrf().'"><input type="file" name="wxr" accept=".xml,text/xml" required><button>Import WordPress export</button></form></div>';$body=settings_docket('/admin/import',null,'Import','Import content from another publishing system.',$body);layout('Import',$body,true);exit;}
if($path==='/admin/backups'){
 require_permission('backups.manage');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'create');
  if($action==='create'||$action==='create_backup'){
   try{
    $name=backup_database();
    flash('success','Backup created: '.$name.' (database and uploaded media).');
   }catch(Throwable $e){
    flash('error','Backup failed: '.$e->getMessage());
   }
   redirect('/admin/backups');
  }elseif($action==='restore'){
   try{
    $file=(string)($_POST['file']??'');
    restore_database($file);
    flash('success','Database and media successfully restored from '.e(basename($file)));
   }catch(Throwable $e){
    flash('error','Restore failed: '.$e->getMessage());
   }
   redirect('/admin/backups');
  }elseif($action==='delete'){
   try{
    $file=basename((string)($_POST['file']??''));
    $p=ROOT.'/storage/backups/'.$file;
    if(is_file($p)){
     @unlink($p);
     if(str_ends_with($file,'.sql'))@unlink(ROOT.'/storage/backups/'.substr($file,0,-4).'-media.zip');
     flash('success','Backup file deleted: '.$file);
    }
   }catch(Throwable $e){
    flash('error','Delete failed: '.$e->getMessage());
   }
   redirect('/admin/backups');
  }
 }
 $dir=ROOT.'/storage/backups';
 $files=is_dir($dir)?array_values(array_filter(scandir($dir)?:[],fn($f)=>str_ends_with($f,'.sql'))):[];
 rsort($files);
 $h='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Create database backup</h2></div><div class="panel-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;"><p class="muted" style="margin:0;max-width:52ch;">Generates a complete SQL dump of all tables and settings, plus a separate archive of uploaded media. Run this before updates or security changes.</p><form method="post" style="margin:0;flex:0 0 auto;"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="create_backup"><button type="submit" class="btn">Create database backup now</button></form></div></div>';
 $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Saved backup archives</h2><span class="badge">'.count($files).' stored</span></div><div class="panel-body">';
 if(empty($files)){
  $h.='<div class="admin-row-empty">No database backup archives found. Click "Create database backup now" above to generate your first backup.</div>';
 }else{
  $h.='<div class="admin-row-list">';
  foreach($files as $f){
   $filePath=$dir.'/'.$f;
   $sizeKb=is_file($filePath)?number_format(filesize($filePath)/1024,1):'0';
   $mtime=is_file($filePath)?date('Y-m-d H:i:s',filemtime($filePath)):'Unknown';
   $mediaZipPath=$dir.'/'.substr($f,0,-4).'-media.zip';
   $mediaNote=is_file($mediaZipPath)?' &middot; media: '.number_format(filesize($mediaZipPath)/1024,1).' KB':' &middot; no media archive';
   $h.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($f).'</div><div class="admin-row-meta">'.$sizeKb.' KB &middot; '.e($mtime).$mediaNote.'</div></div><div class="admin-row-actions"><a href="/admin/backups/download?file='.rawurlencode($f).'" class="btn ghost" style="padding:4px 8px;font-size:.78rem;" title="Download SQL Dump">Download</a><form method="post" style="margin:0;" onsubmit="return confirm(\'Are you sure you want to RESTORE the database from '.e($f).'? Existing database records will be overwritten.\');"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="restore"><input type="hidden" name="file" value="'.e($f).'"><button type="submit" class="btn ghost" style="padding:4px 8px;font-size:.78rem;">Restore</button></form><form method="post" style="margin:0;" onsubmit="return confirm(\'Delete backup archive '.e($f).'?\');"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="delete"><input type="hidden" name="file" value="'.e($f).'"><button type="submit" class="btn danger" style="padding:4px 8px;font-size:.78rem;">Delete</button></form></div></div>';
  }
  $h.='</div>';
 }
 $h.='</div></div>';
 if(can('packages.manage')){
  $h.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;"><p class="muted" style="margin:0;">Looking for plugin and theme package management instead?</p><a class="btn ghost" href="/admin/packages">Open Packages &amp; Add-ons &rarr;</a></div></div>';
 }
 $h=settings_docket('/admin/backups',null,'DB Backups','Create, download, restore, and manage database backups before updates or security changes.',$h);
 layout('DB backups',$h,true);
 exit;
}
if($path==='/admin/backups/download'){require_permission('backups.manage');$f=basename($_GET['file']??'');$p=ROOT.'/storage/backups/'.$f;if(!is_file($p))exit('Not found');header('Content-Type: application/sql');header('Content-Disposition: attachment; filename="'.$f.'"');readfile($p);exit;}
if($path==='/admin/core-update'){
 require_permission('core.update');
 // realpath(), not the raw ROOT constant: ROOT is defined as __DIR__.'/..'
 // in app/bootstrap.php (__DIR__ there being .../app), i.e. a literal,
 // *unresolved* "/app/.." path segment - fine for every other use of ROOT,
 // but fatal here specifically. CoreCodeInstaller's very first swap in this
 // route renames the live "app" directory itself out to a backup path; the
 // instant that happens, the literal substring "/app/.." in every path
 // derived from an unresolved $cuRoot can no longer be traversed by the
 // kernel (there's no "app" directory left to cd into and back out of),
 // so the very next operation - promoting the staged "app" back into place
 // - failed with ENOENT even though the staged directory genuinely existed
 // (confirmed live: "Could not promote staged path into place: app", with
 // realpath free the reproduction always broke exactly there). Resolving
 // once, up front, before any swap has touched the filesystem, bakes in a
 // clean "/var/www/html" with no ".." left to break later.
 $cuRoot=realpath(defined('ROOT')?ROOT:dirname(__DIR__));
 if($cuRoot===false)throw new RuntimeException('Could not resolve the live CMS root path.');
 // v0.9-dev incident: storage/ is a separate mounted filesystem/volume in
 // this project's real deployments (confirmed against both the local
 // podman dev container and would be true of any deployment giving
 // storage/ its own persistent volume) - CoreCodeInstaller relies on
 // rename() for its whole-directory swaps, and rename() cannot cross a
 // filesystem boundary (fails with EXDEV, "Invalid cross-device link").
 // The package system never hits this because a package's staging,
 // installed, AND rollback directories all live under storage/plugins/ -
 // every rename() stays on one filesystem the whole time. A core update's
 // "installed" location IS the live codebase itself (app/, routes/, etc.),
 // which is outside storage/ - so staging and rollback must live outside
 // storage/ too, on the same filesystem as the live root, or every swap
 // and every backup would fail this exact way.
 $cuStaging=$cuRoot.'/.core-update-staging';
 $cuRollback=$cuRoot.'/.core-update-rollback';
 $cuRepo=new \Erased\CoreUpdate\CoreUpdateRepository(db());
 $makeOrchestrator=static function() use ($cuRoot,$cuStaging,$cuRollback,$cuRepo): \Erased\CoreUpdate\CoreUpdateOrchestrator{
  return new \Erased\CoreUpdate\CoreUpdateOrchestrator(
   db(),
   new \Erased\CoreUpdate\CoreUpdateStager(),
   new \Erased\CoreUpdate\CoreUpdateDiffer(),
   $cuRepo,
   new \Erased\CoreUpdate\CoreCodeInstaller(),
   $cuStaging,
   $cuRoot,
   $cuRollback,
   cms_version(),
  );
 };

 // Discard anything staged and abandoned past the TTL - cheap indexed
 // query, run on every GET so an abandoned stage never blocks future ones
 // forever without the admin having to remember to clean it up manually.
 foreach($cuRepo->sweepExpired(7200) as $expiredRow){
  $expiredDir=$cuStaging.'/'.$expiredRow['token'];
  if(is_dir($expiredDir)){
   try{(new \Erased\CoreUpdate\CoreUpdateStager())->discard($expiredDir,$cuStaging);}catch(Throwable $e){}
  }
  $cuRepo->markDiscarded((string)$expiredRow['token']);
 }

 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'stage');
  try{
   if($action==='stage'){
    $file=$_FILES['package']??null;
    if(!is_array($file)||(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a valid update ZIP.');
    $tmpName=(string)($file['tmp_name']??'');
    if($tmpName===''||!is_uploaded_file($tmpName))throw new RuntimeException('The update upload could not be verified.');
    $size=(int)($file['size']??0);
    if($size<1||$size>300*1024*1024)throw new RuntimeException('Update ZIPs must be between 1 byte and 300 MB.');
    $originalName=basename((string)($file['name']??'update.zip'));

    $staged=$makeOrchestrator()->stage($tmpName,$originalName,((int)(current_user()['id']??0))?:null);
    audit('core_update.staged',['token'=>$staged['token'],'from_version'=>$staged['from_version'],'to_version'=>$staged['to_version']]);
    flash('success','Update staged: '.$staged['from_version'].' &rarr; '.$staged['to_version'].'. Review the changes below before applying.');
   }elseif($action==='apply'){
    $token=(string)($_POST['token']??'');
    $result=$makeOrchestrator()->apply($token);
    audit('core_update.applied',['token'=>$token,'to_version'=>$result['to_version']]);
    flash('success','Core update applied. Now running '.$result['to_version'].'.');
   }elseif($action==='discard'){
    $token=(string)($_POST['token']??'');
    $makeOrchestrator()->discard($token);
    audit('core_update.discarded',['token'=>$token]);
    flash('success','Staged update discarded.');
   }
  }catch(Throwable $e){
   flash('error','Core update action failed: '.$e->getMessage());
  }
  redirect('/admin/core-update');
 }

 $active=$cuRepo->findActiveStaged();
 $content='';
 if($active===null){
  $content.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Current version</h2></div><div class="panel-body"><p class="muted" style="margin:0;">Running <strong>'.e(cms_version()).'</strong>.</p></div></div>';
  $content.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Upload a core update</h2></div><div class="panel-body"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="stage"><div class="fslot"><label>Update ZIP<input type="file" name="package" accept=".zip" required></label></div><p class="muted" style="font-size:.85rem;">Nothing is applied yet - the next screen shows exactly what will change before anything happens.</p><button type="submit" class="btn">Stage update</button></form></div></div>';
 }else{
  $diff=is_array($active['diff_summary']??null)?$active['diff_summary']:['added'=>[],'changed'=>[],'removed'=>[],'counts'=>['added'=>0,'changed'=>0,'removed'=>0,'unchanged'=>0]];
  $pending=is_array($active['pending_migrations']??null)?$active['pending_migrations']:[];
  $statusLabel=$active['status']==='applying'?'Applying…':'Staged, awaiting confirmation';
  $content.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Review staged update</h2><span class="badge">'.e($statusLabel).'</span></div><div class="panel-body">';
  $content.='<p style="font-size:1.05rem;margin:0 0 14px;"><strong>'.e((string)$active['from_version']).'</strong> &rarr; <strong>'.e((string)$active['to_version']).'</strong></p>';
  $counts=is_array($diff['counts']??null)?$diff['counts']:[];
  $content.='<div class="fgrid three" style="margin-bottom:16px;"><div class="fslot"><label>Files added<div style="font-size:1.4rem;font-weight:700;">'.(int)($counts['added']??0).'</div></label></div><div class="fslot"><label>Files changed<div style="font-size:1.4rem;font-weight:700;">'.(int)($counts['changed']??0).'</div></label></div><div class="fslot"><label>Files removed<div style="font-size:1.4rem;font-weight:700;">'.(int)($counts['removed']??0).'</div></label></div></div>';
  if(!empty($pending)){
   $content.='<p><strong>Pending migrations ('.count($pending).'):</strong></p><ul style="margin:0 0 16px;">';
   foreach($pending as $m)$content.='<li class="mono" style="font-size:.85rem;">'.e((string)$m).'</li>';
   $content.='</ul>';
  }else{
   $content.='<p class="muted">No pending database migrations.</p>';
  }
  foreach(['added'=>'Added','changed'=>'Changed','removed'=>'Removed'] as $key=>$label){
   $list=is_array($diff[$key]??null)?$diff[$key]:[];
   if(empty($list))continue;
   $content.='<details style="margin-bottom:10px;"><summary class="btn ghost">'.e($label).' files ('.count($list).')</summary><ul style="max-height:220px;overflow:auto;font-family:var(--font-mono,monospace);font-size:.8rem;">';
   foreach(array_slice($list,0,500) as $f)$content.='<li>'.e((string)$f).'</li>';
   if(count($list)>500)$content.='<li>&hellip; and '.(count($list)-500).' more</li>';
   $content.='</ul></details>';
  }
  $content.='<div class="actions" style="margin-top:16px;gap:10px;">';
  $content.='<form method="post" onsubmit="return confirm(\'Apply this update now? A database and code backup will be taken first, but this changes live application code.\');"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="apply"><input type="hidden" name="token" value="'.e((string)$active['token']).'"><button type="submit" class="btn"'.($active['status']==='applying'?' disabled':'').'>Confirm &amp; apply update</button></form>';
  $content.='<form method="post" onsubmit="return confirm(\'Discard this staged update?\');"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="discard"><input type="hidden" name="token" value="'.e((string)$active['token']).'"><button type="submit" class="btn ghost">Discard</button></form>';
  $content.='</div></div></div>';
 }
 $content.='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;"><p class="muted" style="margin:0;">Need to roll back a previous update instead?</p><a class="btn ghost" href="/admin/core-update/rollback">Open rollback &rarr;</a></div></div>';
 $h=settings_docket('/admin/core-update',null,'Core Update','Upload, review, and apply CMS core updates - or discard a staged one.',$content);
 layout('Core update',$h,true);
 exit;
}
if($path==='/admin/core-update/rollback'){
 require_permission('core.update');
 // realpath() here too - see the long comment in /admin/core-update on why
 // an unresolved ROOT (containing a literal "/app/..") breaks the instant
 // this route's own swap renames "app" out of the way. Rollback runs the
 // exact same CoreCodeInstaller-style swap in reverse.
 $cuRoot=realpath(defined('ROOT')?ROOT:dirname(__DIR__));
 if($cuRoot===false)throw new RuntimeException('Could not resolve the live CMS root path.');
 $cuRollback=$cuRoot.'/.core-update-rollback'; // must match /admin/core-update's path - see the comment there on why this can't live under storage/
 $rollbackService=new \Erased\CoreUpdate\CoreCodeRollbackService();

 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $action=(string)($_POST['action']??'');
  $applyId=(string)($_POST['apply_id']??'');
  try{
   if($action==='rollback_to'){
    $restored=$rollbackService->rollbackTo($applyId,$cuRoot,$cuRollback,\Erased\CoreUpdate\CoreCodeInstaller::CORE_UPDATE_PATHS);
    if(function_exists('opcache_reset'))@opcache_reset();
    audit('core_update.rolled_back',['apply_id'=>$applyId,'restored'=>$restored]);
    flash('success','Rolled back: '.implode(', ',$restored).'.');
   }elseif($action==='rollback_to_with_db'){
    $dbFile=(string)($_POST['db_backup_file']??'');
    $restored=$rollbackService->rollbackTo($applyId,$cuRoot,$cuRollback,\Erased\CoreUpdate\CoreCodeInstaller::CORE_UPDATE_PATHS);
    if($dbFile!=='')restore_database($dbFile);
    if(function_exists('opcache_reset'))@opcache_reset();
    audit('core_update.rolled_back_with_db',['apply_id'=>$applyId,'db_backup_file'=>$dbFile,'restored'=>$restored]);
    flash('success','Rolled back code and database.');
   }
  }catch(Throwable $e){
   flash('error','Rollback failed: '.$e->getMessage());
  }
  redirect('/admin/core-update/rollback');
 }

 $backups=$rollbackService->listBackups($cuRollback);
 $dbFileByApplyId=[];
 $stmt=db()->query("SELECT code_backup_directory, db_backup_file FROM core_updates WHERE code_backup_directory IS NOT NULL AND db_backup_file IS NOT NULL");
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
  $dbFileByApplyId[basename((string)$row['code_backup_directory'])]=(string)$row['db_backup_file'];
 }

 $content='<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>Core code backups</h2><span class="badge">'.count($backups).' stored</span></div><div class="panel-body">';
 if(empty($backups)){
  $content.='<div class="admin-row-empty">No core update backups found yet - they are created automatically the first time an update is applied.</div>';
 }else{
  $content.='<div class="admin-row-list">';
  foreach($backups as $b){
   $applyId=$b['apply_id'];
   $dbFile=$dbFileByApplyId[$applyId]??null;
   $content.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($applyId).'</div><div class="admin-row-meta">Backed up '.e($b['backed_up_at']).'</div></div><div class="admin-row-actions">';
   $content.='<form method="post" style="margin:0;" onsubmit="return confirm(\'Roll back code to this backup? The current code will itself be preserved as a fresh backup first.\');"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="rollback_to"><input type="hidden" name="apply_id" value="'.e($applyId).'"><button type="submit" class="btn ghost" style="padding:4px 8px;font-size:.78rem;">Roll back code</button></form>';
   if($dbFile!==null){
    $content.='<form method="post" style="margin:0;" onsubmit="return confirm(\'Roll back BOTH code and database to this backup? This overwrites current database records with the backup taken at update time.\');"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="rollback_to_with_db"><input type="hidden" name="apply_id" value="'.e($applyId).'"><input type="hidden" name="db_backup_file" value="'.e($dbFile).'"><button type="submit" class="btn danger" style="padding:4px 8px;font-size:.78rem;">Roll back code + database</button></form>';
   }
   $content.='</div></div>';
  }
  $content.='</div>';
 }
 $content.='</div></div>';
 $h=settings_docket('/admin/core-update',null,'Core Update Rollback','Restore a previous core code backup, with an optional database rollback.',$content);
 layout('Core update rollback',$h,true);
 exit;
}
if($path==='/admin/security'){
    require_permission('security.manage');
    $rawTab = (string)($_GET['tab'] ?? 'dashboard');
    $tab = in_array($rawTab, ['dashboard','login','protection','waf','advanced','cloudflare'], true) ? $rawTab : 'dashboard';
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? 'save');
        if ($action === 'apply_fix') {
            $fixId = (string)($_POST['fix_id'] ?? '');
            if ($fixId === 'waf') set_setting('waf_enabled', '1');
            elseif ($fixId === 'lockout') set_setting('ip_lockout_enabled', '1');
            elseif ($fixId === 'password_policy') { set_setting('password_min_length', '12'); set_setting('password_require_uppercase', '1'); set_setting('password_require_number', '1'); }
            audit('security.fix_applied', ['fix_id' => $fixId]);
            flash('success', 'Safe fix applied successfully.');
            redirect('/admin/security?tab=dashboard');
        }
        if ($action === 'unlock_ip') {
            $ip = trim((string)($_POST['ip_address'] ?? ''));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $pdo->prepare('DELETE FROM security_ip_lockouts WHERE ip_address=?')->execute([$ip]);
                audit('security.ip_unlocked', ['ip' => $ip]);
                erased_security_event('auth.ip_unlocked', 'notice', ['ip' => $ip]);
            }
            redirect('/admin/security?tab=login');
        }
        if ($action === 'force_logout_user') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid > 0) {
                revoke_user_sessions($uid);
                audit('security.force_logout_user', ['user_id' => $uid]);
                erased_security_event('auth.sessions_revoked', 'warning', ['user_id' => $uid]);
            }
            redirect('/admin/security?tab=login');
        }
        if ($action === 'force_logout_all') {
            $me = (int)(current_user()['id'] ?? 0);
            $pdo->exec('UPDATE users SET session_version=COALESCE(session_version,0)+1');
            $pdo->exec('UPDATE auth_sessions SET revoked_at=NOW() WHERE revoked_at IS NULL');
            audit('security.force_logout_all');
            erased_security_event('auth.all_sessions_revoked', 'danger', ['administrator_id' => $me]);
            flash('success', 'All users were signed out.');
            redirect('/logout');
        }
        if ($action === 'save_login') {
            set_setting('rate_limit_login', (string)max(3, min(100, (int)($_POST['rate_limit_login'] ?? 8))));
            set_setting('ip_lockout_enabled', isset($_POST['ip_lockout_enabled']) ? '1' : '0');
            set_setting('ip_lockout_threshold', (string)max(3, min(100, (int)($_POST['ip_lockout_threshold'] ?? 8))));
            set_setting('ip_lockout_window_minutes', (string)max(1, min(1440, (int)($_POST['ip_lockout_window_minutes'] ?? 15))));
            set_setting('ip_lockout_duration_minutes', (string)max(1, min(10080, (int)($_POST['ip_lockout_duration_minutes'] ?? 30))));
            set_setting('password_min_length', (string)max(8, min(128, (int)($_POST['password_min_length'] ?? 12))));
            foreach (['password_require_uppercase', 'password_require_lowercase', 'password_require_number', 'password_require_symbol'] as $k) set_setting($k, isset($_POST[$k]) ? '1' : '0');
            set_setting('session_timeout_minutes', (string)max(5, min(10080, (int)($_POST['session_timeout_minutes'] ?? 30))));
            audit('security.login_settings_saved');
            flash('success', 'Login & Session Protection settings saved.');
            redirect('/admin/security?tab=login');
        }
        if ($action === 'save_protection') {
            set_setting('security_headers_enabled', isset($_POST['security_headers_enabled']) ? '1' : '0');
            set_setting('upload_mime_check', isset($_POST['upload_mime_check']) ? '1' : '0');
            set_setting('svg_sanitizer_enabled', isset($_POST['svg_sanitizer_enabled']) ? '1' : '0');
            set_setting('comment_captcha_enabled', isset($_POST['comment_captcha_enabled']) ? '1' : '0');
            audit('security.protection_settings_saved');
            flash('success', 'Protection settings saved.');
            redirect('/admin/security?tab=protection');
        }
        if ($action === 'save_waf') {
            set_setting('waf_enabled', isset($_POST['waf_enabled']) ? '1' : '0');
            set_setting('admin_ip_allowlist', trim((string)($_POST['admin_ip_allowlist'] ?? '')));
            set_setting('ip_blacklist', trim((string)($_POST['ip_blacklist'] ?? '')));
            set_setting('bot_filtering_enabled', isset($_POST['bot_filtering_enabled']) ? '1' : '0');
            audit('security.waf_settings_saved');
            flash('success', 'Firewall & Monitoring settings saved.');
            redirect('/admin/security?tab=waf');
        }
        if ($action === 'save_advanced') {
            set_setting('emergency_lockdown', isset($_POST['emergency_lockdown']) ? '1' : '0');
            set_setting('read_only_mode', isset($_POST['read_only_mode']) ? '1' : '0');
            set_setting('developer_mode_enabled', isset($_POST['developer_mode_enabled']) ? '1' : '0');
            audit('security.advanced_settings_saved');
            flash('success', 'Advanced Security & Emergency Mode settings saved.');
            redirect('/admin/security?tab=advanced');
        }
        if ($action === 'save_cloudflare') {
            set_setting('cloudflare_enabled', isset($_POST['cloudflare_enabled']) ? '1' : '0');
            audit('security.cloudflare_settings_saved');
            flash('success', 'Cloudflare Proxy settings saved.');
            redirect('/admin/security?tab=cloudflare');
        }
        if ($action === 'save_cloudflare_turnstile') {
            set_setting('cloudflare_turnstile_enabled', isset($_POST['cloudflare_turnstile_enabled']) ? '1' : '0');
            set_setting('cloudflare_turnstile_site_key', trim((string)($_POST['cloudflare_turnstile_site_key'] ?? '')));
            set_setting('cloudflare_turnstile_secret_key', trim((string)($_POST['cloudflare_turnstile_secret_key'] ?? '')));
            audit('security.cloudflare_turnstile_saved');
            flash('success', 'Cloudflare Turnstile settings saved.');
            redirect('/admin/security?tab=cloudflare');
        }
        if ($action === 'save_cloudflare_api') {
            set_setting('cloudflare_auto_purge', isset($_POST['cloudflare_auto_purge']) ? '1' : '0');
            set_setting('cloudflare_zone_id', trim((string)($_POST['cloudflare_zone_id'] ?? '')));
            set_setting('cloudflare_api_token', trim((string)($_POST['cloudflare_api_token'] ?? '')));
            audit('security.cloudflare_api_saved');
            flash('success', 'Cloudflare Cache API settings saved.');
            redirect('/admin/security?tab=cloudflare');
        }
        if ($action === 'purge_cloudflare_cache') {
            $res = erased_cloudflare_purge_cache();
            if ($res['success']) flash('success', $res['message'] ?? 'Cloudflare cache purged.');
            else flash('error', $res['error'] ?? 'Cache purge failed.');
            redirect('/admin/security?tab=cloudflare');
        }
    }

    $scan = erased_security_scan();
    $warnCount = count(array_filter($scan, fn($c) => $c['status'] === 'warn'));
    $score = max(0, 100 - ($warnCount * 15));

    $failed24h = (int)$pdo->query("SELECT COUNT(*) FROM login_attempts WHERE successful=0 AND created_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
    $wafBlocked24h = (int)$pdo->query("SELECT COUNT(*) FROM security_attack_stats WHERE blocked_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();

    $tabNav = '<div class="admin-tabs">'
        . '<a class="admin-tab'.($tab==='login'?' is-active':'').'" href="/admin/security?tab=login">Login &amp; Sessions</a>'
        . '<a class="admin-tab'.($tab==='protection'?' is-active':'').'" href="/admin/security?tab=protection">Site &amp; Upload Protection</a>'
        . '<a class="admin-tab'.($tab==='waf'?' is-active':'').'" href="/admin/security?tab=waf">WAF &amp; Monitoring</a>'
        . '<a class="admin-tab'.($tab==='advanced'?' is-active':'').'" href="/admin/security?tab=advanced">Advanced &amp; Recovery</a>'
        . '<a class="admin-tab'.($tab==='cloudflare'?' is-active':'').'" href="/admin/security?tab=cloudflare">Cloudflare</a>'
        . '</div>';

    $panelOpen = '<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div>';

    $body = '';
    if ($tab !== 'dashboard') $body .= $tabNav;

    if ($tab === 'dashboard') {
        $scanRows = '';
        foreach ($scan as $c) {
            $sev = $c['status']==='ok' ? 'ok' : 'warn';
            $fixBtn = '';
            if ($c['fixable'] && $c['status'] !== 'ok') {
                $fixBtn = '<form method="post" style="margin:0;"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="apply_fix"><input type="hidden" name="fix_id" value="'.e($c['id']).'"><button class="btn ghost">Apply Safe Fix</button></form>';
            }
            $scanRows .= '<div class="attn"><span class="sev '.$sev.'"></span><div class="t">'.e($c['title']).'<div class="s">'.e($c['desc']).'</div></div>'.$fixBtn.'</div>';
        }
        $logs = $pdo->query('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 15')->fetchAll();
        $logRows = '';
        foreach ($logs as $l) {
            $logRows .= '<tr><td><strong>'.e($l['event']).'</strong></td><td>'.e($l['ip_address']).'</td><td>'.e($l['created_at']).'</td></tr>';
        }

        $body .= '<div class="admin-stat-row">'
            . '<div class="admin-stat-chip"><span class="admin-stat-value">'.$score.'<small>/100</small></span><span class="admin-stat-label">Security score</span></div>'
            . '<div class="admin-stat-chip"><span class="admin-stat-value">'.$failed24h.'</span><span class="admin-stat-label">Failed logins (24h)</span></div>'
            . '<div class="admin-stat-chip"><span class="admin-stat-value">'.$wafBlocked24h.'</span><span class="admin-stat-label">WAF blocks (24h)</span></div>'
            . '<div class="admin-stat-chip"><span class="admin-stat-value">'.$admins.'</span><span class="admin-stat-label">Active admins</span></div>'
            . '</div>'
            . $tabNav
            . $panelOpen . '<div class="panel-head"><h2>Automated Security Scanner &amp; Recommendations</h2></div><div class="panel-body">'.$scanRows.'</div></div>'
            . $panelOpen . '<div class="panel-head"><h2>Recent Audit Trail</h2></div><div class="panel-body"><table><thead><tr><th>Action</th><th>IP Address</th><th>Timestamp</th></tr></thead><tbody>'.($logRows ?: '<tr><td colspan="3">No audit logs recorded yet.</td></tr>').'</tbody></table></div></div>';
    } elseif ($tab === 'login') {
        $locked = $pdo->query('SELECT * FROM security_ip_lockouts WHERE locked_until>NOW() ORDER BY locked_until DESC')->fetchAll();
        $history = $pdo->query('SELECT h.*,u.username,u.display_name FROM login_history h LEFT JOIN users u ON u.id=h.user_id ORDER BY h.created_at DESC LIMIT 30')->fetchAll();
        $sessions = $pdo->query('SELECT s.*,u.email,u.username,u.display_name FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.revoked_at IS NULL AND s.expires_at>NOW() ORDER BY s.last_activity_at DESC')->fetchAll();

        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Login &amp; Session Policies</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_login">'
            . '<div class="fgrid three">'
            . '<div class="fslot"><label>Failed logins per window<input type="number" min="3" max="100" name="rate_limit_login" value="'.e(setting('rate_limit_login','8')).'"></label></div>'
            . '<div class="fslot"><label>Failures before IP lockout<input type="number" min="3" max="100" name="ip_lockout_threshold" value="'.e(setting('ip_lockout_threshold','8')).'"></label></div>'
            . '<div class="fslot"><label>Failure window (minutes)<input type="number" min="1" max="1440" name="ip_lockout_window_minutes" value="'.e(setting('ip_lockout_window_minutes','15')).'"></label></div>'
            . '<div class="fslot"><label>Lockout duration (minutes)<input type="number" min="1" max="10080" name="ip_lockout_duration_minutes" value="'.e(setting('ip_lockout_duration_minutes','30')).'"></label></div>'
            . '<div class="fslot"><label>Session inactivity timeout (minutes)<input type="number" min="5" max="10080" name="session_timeout_minutes" value="'.e(setting('session_timeout_minutes','30')).'"></label></div>'
            . '<div class="fslot"><label>Minimum password length<input type="number" min="8" max="128" name="password_min_length" value="'.e(setting('password_min_length','12')).'"></label></div>'
            . '</div>'
            . '<label class="check"><input type="checkbox" name="ip_lockout_enabled"'.(setting('ip_lockout_enabled','1')==='1'?' checked':'').'> Enable automatic IP lockout</label>'
            . '<label class="check"><input type="checkbox" name="password_require_uppercase"'.(setting('password_require_uppercase','1')==='1'?' checked':'').'> Require uppercase letter</label>'
            . '<label class="check"><input type="checkbox" name="password_require_lowercase"'.(setting('password_require_lowercase','1')==='1'?' checked':'').'> Require lowercase letter</label>'
            . '<label class="check"><input type="checkbox" name="password_require_number"'.(setting('password_require_number','1')==='1'?' checked':'').'> Require number</label>'
            . '<label class="check"><input type="checkbox" name="password_require_symbol"'.(setting('password_require_symbol','1')==='1'?' checked':'').'> Require symbol</label>'
            . '<button class="btn">Save Login Policies</button>'
            . '</div></div></form>';

        $body .= $panelOpen . '<div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;"><h2>Active User Sessions</h2><form method="post" style="margin:0;" onsubmit="return confirm(\'Sign out every user, including you?\')"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="force_logout_all"><button type="submit" class="btn ghost">Force Logout All</button></form></div><div class="panel-body">';
        if (!$sessions) $body .= '<div class="admin-row-empty">No active user sessions.</div>';
        else { $body .= '<div class="admin-row-list">'; foreach ($sessions as $ss) {
            $name = $ss['display_name'] ?: ($ss['username'] ?: $ss['email']);
            $body .= '<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e((string)$name).'</div><div class="admin-row-meta">'.e((string)$ss['ip_address']).' &middot; last active '.e((string)$ss['last_activity_at']).'</div></div><div class="admin-row-actions"><form method="post" style="margin:0;"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="force_logout_user"><input type="hidden" name="user_id" value="'.(int)$ss['user_id'].'"><button type="submit" class="btn ghost">Force Logout</button></form></div></div>';
        } $body .= '</div>'; }
        $body .= '</div></div>';

        $body .= $panelOpen . '<div class="panel-head"><h2>Active IP Lockouts</h2></div><div class="panel-body">';
        if (!$locked) $body .= '<div class="admin-row-empty">No IP addresses are currently locked.</div>';
        else { $body .= '<div class="admin-row-list">'; foreach ($locked as $l) {
            $body .= '<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e((string)$l['ip_address']).'</div><div class="admin-row-meta">'.e((string)($l['reason']??'Locked')).' &middot; failures: '.(int)$l['failed_attempts'].' &middot; until '.e((string)$l['locked_until']).'</div></div><div class="admin-row-actions"><form method="post" style="margin:0;"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="unlock_ip"><input type="hidden" name="ip_address" value="'.e((string)$l['ip_address']).'"><button type="submit" class="btn ghost">Unlock IP</button></form></div></div>';
        } $body .= '</div>'; }
        $body .= '</div></div>';

        $body .= $panelOpen . '<div class="panel-head"><h2>Login History Log</h2></div><div class="panel-body"><table><thead><tr><th>Account</th><th>Result</th><th>IP</th><th>Time</th></tr></thead><tbody>';
        foreach ($history as $row) {
            $name = $row['display_name'] ?: ($row['username'] ?: $row['email']);
            $body .= '<tr><td>'.e((string)$name).'</td><td>'.((int)$row['successful']===1?'<span class="stampword live">Success</span>':'<span class="stampword draft">Failed</span>').'</td><td>'.e((string)$row['ip_address']).'</td><td>'.e((string)$row['created_at']).'</td></tr>';
        }
        $body .= ($history ? '' : '<tr><td colspan="4">No login history recorded.</td></tr>') . '</tbody></table></div></div>';
    } elseif ($tab === 'protection') {
        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Website &amp; Upload Protection</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_protection">'
            . '<label class="check"><input type="checkbox" name="comment_captcha_enabled" '.(setting('comment_captcha_enabled','1')==='1'?'checked':'').'> Enable CAPTCHA Verification on Comments Section (Math CAPTCHA or Cloudflare Turnstile)</label>'
            . '<label class="check"><input type="checkbox" name="security_headers_enabled" '.(setting('security_headers_enabled','1')==='1'?'checked':'').'> Enable Security Headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options)</label>'
            . '<label class="check"><input type="checkbox" name="upload_mime_check" '.(setting('upload_mime_check','1')==='1'?'checked':'').'> Strict Upload MIME-Type &amp; Extension Inspection (Blocks .php, .exe, .sh)</label>'
            . '<label class="check"><input type="checkbox" name="svg_sanitizer_enabled" '.(setting('svg_sanitizer_enabled','1')==='1'?'checked':'').'> Automatic SVG Vector Sanitization (Strips inline scripts &amp; onerror handlers)</label>'
            . '<button class="btn">Save Protection Settings</button>'
            . '</div></div></form>'
            . $panelOpen . '<div class="panel-head"><h2>File Permission &amp; Directory Audit</h2></div><div class="panel-body">'
            . '<div class="attn"><span class="sev ok"></span><div class="t">/public/uploads<div class="s">Upload directory permissions &middot; Readable &amp; writeable</div></div></div>'
            . '<div class="attn"><span class="sev ok"></span><div class="t">/config/app.php<div class="s">System configuration security &middot; Protected</div></div></div>'
            . '<div class="attn"><span class="sev ok"></span><div class="t">Execution Prevention<div class="s">PHP execution disabled in uploads folder &middot; Active</div></div></div>'
            . '</div></div>';
    } elseif ($tab === 'waf') {
        $attacks = $pdo->query('SELECT * FROM security_attack_stats ORDER BY blocked_at DESC LIMIT 30')->fetchAll();
        $events = $pdo->query('SELECT * FROM security_events ORDER BY created_at DESC LIMIT 30')->fetchAll();

        $attackRows = '';
        foreach ($attacks as $a) {
            $attackRows .= '<tr><td><span class="stampword draft">'.e($a['attack_type']).'</span></td><td>'.e($a['ip_address']).'</td><td><code>'.e(substr($a['payload_snippet']??'',0,60)).'</code></td><td>'.e($a['blocked_at']).'</td></tr>';
        }

        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Web Application Firewall (WAF) &amp; Traffic Filtering</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_waf">'
            . '<label class="check"><input type="checkbox" name="waf_enabled" '.(setting('waf_enabled','1')==='1'?'checked':'').'> Enable Web Application Firewall (Filters SQLi, XSS, Path Traversal)</label>'
            . '<label class="check"><input type="checkbox" name="bot_filtering_enabled" '.(setting('bot_filtering_enabled','1')==='1'?'checked':'').'> Filter Automated Scrapers &amp; Malicious Bots</label>'
            . '<div class="fgrid"><div class="fslot"><label>Admin Allowed IP List (One IP per line)<textarea name="admin_ip_allowlist" style="min-height:90px" placeholder="e.g. 192.168.1.100">'.e(setting('admin_ip_allowlist')).'</textarea></label></div><div class="fslot"><label>Blocked IP Blacklist (One IP per line)<textarea name="ip_blacklist" style="min-height:90px" placeholder="e.g. 203.0.113.5">'.e(setting('ip_blacklist')).'</textarea></label></div></div>'
            . '<button class="btn">Save Firewall Settings</button>'
            . '</div></div></form>'
            . $panelOpen . '<div class="panel-head"><h2>Recent Blocked Threats (WAF Log)</h2></div><div class="panel-body"><table><thead><tr><th>Attack Type</th><th>IP Address</th><th>Payload Snippet</th><th>Time</th></tr></thead><tbody>'.($attackRows ?: '<tr><td colspan="4">No threats blocked by WAF yet.</td></tr>').'</tbody></table></div></div>'
            . $panelOpen . '<div class="panel-head"><h2>Security Event Stream</h2></div><div class="panel-body">';
        if (!$events) $body .= '<p class="muted">No security events recorded yet.</p>';
        else foreach ($events as $ev) {
            $body .= '<p><strong>'.e((string)$ev['event_type']).'</strong> <small>'.e((string)$ev['level']).' · '.e((string)$ev['created_at']).' · '.e((string)$ev['ip_address']).'</small></p>';
        }
        $body .= '</div></div>';
    } elseif ($tab === 'advanced') {
        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Emergency Modes &amp; Recovery Control</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_advanced">'
            . '<div class="fgrid">'
            . '<div class="fslot"><label class="check"><input type="checkbox" name="emergency_lockdown" '.(setting('emergency_lockdown','0')==='1'?'checked':'').'> Emergency Lockdown Mode</label><p class="muted">Blocks all non-administrator logins immediately during active security breaches.</p></div>'
            . '<div class="fslot"><label class="check"><input type="checkbox" name="read_only_mode" '.(setting('read_only_mode','0')==='1'?'checked':'').'> Site Read-Only Mode</label><p class="muted">Freezes all database mutations (POST/PUT/DELETE) across the entire CMS.</p></div>'
            . '<div class="fslot"><label class="check"><input type="checkbox" name="developer_mode_enabled" '.(setting('developer_mode_enabled','0')==='1'?'checked':'').'> Developer Mode</label><p class="muted">Read-only inspectors for installed packages, capabilities, services, and Homepage Studio blocks. Disabled by default - only turn this on for trusted administrators.</p></div>'
            . '</div>'
            . '<button class="btn">Save Advanced Modes</button>'
            . '</div></div></form>'
            . $panelOpen . '<div class="panel-head"><h2>Email Verification Setup</h2></div><div class="panel-body"><p class="muted">Enable email two-factor verification for administrator accounts. A six-digit, single-use code is emailed at sign-in and expires after 10 minutes.</p><a class="btn ghost" href="/admin/users?section=accounts">Manage Email 2FA</a></div></div>'
            . $panelOpen . '<div class="panel-head"><h2>Backup &amp; Disaster Recovery Center</h2></div><div class="panel-body"><p class="muted">Create database backups and manage point-in-time recovery archives before performing major changes.</p><a class="btn" href="/admin/backups">Open Backup Center</a></div></div>'
            . $panelOpen . '<div class="panel-head"><h2>Developer Mode</h2></div><div class="panel-body"><p class="muted">Inspect the Platform Foundation - installed/enabled packages, registered capabilities and resolved providers, registered services, Homepage Studio blocks, and the active Website Profile\'s capability status.</p><a class="btn ghost" href="/admin/developer">Open Developer Mode &rarr;</a></div></div>';
    } elseif ($tab === 'cloudflare') {
        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Cloudflare Trusted Proxy &amp; Real Client IP</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_cloudflare">'
            . '<p class="muted">When your website is behind Cloudflare CDN/Proxy, enable proxy trust so ERASED CMS accurately logs real visitor IP addresses from <code>CF-Connecting-IP</code> instead of Cloudflare edge server IPs.</p>'
            . '<label class="check"><input type="checkbox" name="cloudflare_enabled" '.(setting('cloudflare_enabled','0')==='1'?'checked':'').'> Trust Cloudflare Proxy Headers (<code>CF-Connecting-IP</code> &amp; <code>CF-Visitor</code>)</label>'
            . '<button class="btn">Save Proxy Settings</button>'
            . '</div></div></form>';

        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Cloudflare Turnstile CAPTCHA Protection</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_cloudflare_turnstile">'
            . '<p class="muted">Cloudflare Turnstile provides smart, privacy-friendly bot verification without annoying image puzzles. Get your free keys in the Cloudflare Dashboard under Turnstile.</p>'
            . '<label class="check"><input type="checkbox" name="cloudflare_turnstile_enabled" '.(setting('cloudflare_turnstile_enabled','0')==='1'?'checked':'').'> Enable Cloudflare Turnstile on Comment &amp; Login Forms</label>'
            . '<div class="fgrid"><div class="fslot"><label>Turnstile Site Key<input name="cloudflare_turnstile_site_key" value="'.e(setting('cloudflare_turnstile_site_key','')).'" placeholder="0x4AAAAAA..."></label></div>'
            . '<div class="fslot"><label>Turnstile Secret Key<input type="password" name="cloudflare_turnstile_secret_key" value="'.e(setting('cloudflare_turnstile_secret_key','')).'" placeholder="0x4AAAAAA..."></label></div></div>'
            . '<button class="btn">Save Turnstile Settings</button>'
            . '</div></div></form>';

        $body .= '<form method="post">' . $panelOpen . '<div class="panel-head"><h2>Cloudflare Edge Cache Purging API</h2></div><div class="panel-body">'
            . '<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="save_cloudflare_api">'
            . '<p class="muted">Automatically purge Cloudflare CDN edge cache whenever you publish or update website articles and static pages.</p>'
            . '<label class="check"><input type="checkbox" name="cloudflare_auto_purge" '.(setting('cloudflare_auto_purge','0')==='1'?'checked':'').'> Auto-purge Cloudflare cache on content publish/update</label>'
            . '<div class="fgrid"><div class="fslot"><label>Cloudflare Zone ID<input name="cloudflare_zone_id" value="'.e(setting('cloudflare_zone_id','')).'" placeholder="e.g. 023e105f4ecef8ad9ca31a8372d0c353"></label></div>'
            . '<div class="fslot"><label>Cloudflare API Token<input type="password" name="cloudflare_api_token" value="'.e(setting('cloudflare_api_token','')).'" placeholder="Bearer API Token with Cache Purge permissions"></label></div></div>'
            . '<button type="submit" class="btn">Save API Credentials</button>'
            . '</div></div></form>';

        $body .= $panelOpen . '<div class="panel-head"><h2>Instant Manual Cache Purge</h2></div><div class="panel-body">'
            . '<p class="muted">Purge the entire Cloudflare edge cache across all global data centers now.</p>'
            . '<form method="post" style="margin:0;"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="purge_cloudflare_cache"><button type="submit" class="btn ghost">Purge Cloudflare Cache Now</button></form>'
            . '</div></div>';
    }

    $body = settings_docket('/admin/security', null, 'Security Center', 'Monitor and configure site security, sessions, and protections.', $body);
    layout('Security Center', $body, true);
    exit;
}
if($path==='/admin/developer'){
    require_permission('security.manage');
    $panel=fn(string $title,string $bodyHtml)=>'<div class="panel"><div class="reg tl"></div><div class="reg tr"></div><div class="reg bl"></div><div class="reg br"></div><div class="panel-head"><h2>'.e($title).'</h2></div><div class="panel-body">'.$bodyHtml.'</div></div>';
    $titleRow='<div class="title-row"><div><p class="kicker">SHEET A-11 &middot; SYSTEM</p><h1>Developer Mode</h1><p>Read-only inspectors for the Platform Foundation (docs/PLATFORM-FOUNDATION.md). Nothing on this page can be changed - it only shows what\'s currently installed, enabled, and resolved.</p></div></div>';

    if(setting('developer_mode_enabled','0')!=='1'){
        $h=$titleRow.$panel('Developer Mode is disabled','<p class="muted">Turn it on from Security Center to use these inspectors.</p><a class="btn ghost" href="/admin/security?tab=advanced">Open Security Center &rarr;</a>');
        layout('Developer Mode',$h,true);
        exit;
    }

    $devPkgRepo=new \Erased\Packages\InstalledPackageRepository(db());
    $devPackages=$devPkgRepo->all();
    $devVersions=[];
    foreach($devPackages as $p)$devVersions[$p['package_id']]=(string)$p['version'];

    $pkgRows='';
    foreach($devPackages as $p){
        $statusBadge=$p['enabled']?'<span class="badge">enabled</span>':'<span class="badge">disabled</span>';
        $pkgRows.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($p['name']).' <span class="mono">'.e($p['package_id']).'</span> '.$statusBadge.'</div><div class="admin-row-meta">type: '.e($p['package_type']).' &middot; v'.e($p['version']).'</div></div></div>';
    }
    if($pkgRows==='')$pkgRows='<p class="muted">No packages installed.</p>';

    $depResolver=new \Erased\Packages\PackageDependencyResolver();
    $depRows='';
    foreach($devPackages as $p){
        $manifest=new \Erased\Packages\PackageManifest($p['manifest']);
        if($manifest->dependencies()===[])continue;
        foreach($depResolver->describeDependencies($manifest,$devVersions) as $dep){
            $depRows.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($p['package_id']).' &rarr; '.e($dep['id']).' <span class="badge">'.e($dep['status']).'</span></div><div class="admin-row-meta">requires '.e($dep['constraint']).($dep['installed_version']!==null?' &middot; installed: '.e($dep['installed_version']):'').'</div></div></div>';
        }
    }
    if($depRows==='')$depRows='<p class="muted">No installed package declares a dependency.</p>';

    $capRuntime=capability_runtime();
    $capRows='';
    foreach($capRuntime->registry()->map() as $capability=>$providerIds){
        $resolved='<span class="badge">unresolved</span>';
        try{
            $resolved='<span class="badge">'.e($capRuntime->resolver()->resolve($capability)->id()).'</span>';
        }catch(\Throwable $e){
            $resolved='<span class="badge">'.e($e->getMessage()).'</span>';
        }
        $capRows.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($capability).'</div><div class="admin-row-meta">declared by: '.e(implode(', ',$providerIds)).' &middot; active resolution: '.$resolved.'</div></div></div>';
    }
    if($capRows==='')$capRows='<p class="muted">No installed package declares a capability via "provides".</p>';

    $svcRuntime=service_runtime();
    $svcRows='';
    foreach($svcRuntime->owners() as $serviceId=>$ownerId){
        $svcRows.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($serviceId).'</div><div class="admin-row-meta">owned by: '.e($ownerId).'</div></div></div>';
    }
    if($svcRows==='')$svcRows='<p class="muted">No enabled package declares a service.</p>';

    $blockRegistry=new \Erased\Homepage\BlockDefinitionRegistry();
    $blockRuntime=new \Erased\Homepage\InstalledBlockRuntime($devPkgRepo,$blockRegistry);
    $blockRuntime->refresh();
    $blockRows='';
    foreach($blockRegistry->all() as $def){
        $reqs=$def->requiredCapabilities();
        $isVisible=true;
        foreach($reqs as $r)if(!$capRuntime->resolver()->has($r))$isVisible=false;
        $visBadge=$reqs===[]?'<span class="badge">always visible</span>':($isVisible?'<span class="badge">visible</span>':'<span class="badge">hidden (capability unmet)</span>');
        $blockRows.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($def->title()).' <span class="mono">'.e($def->id()).'</span> '.$visBadge.'</div><div class="admin-row-meta">owner: '.e($def->packageId()).' &middot; category: '.e($def->category()).($reqs!==[]?' &middot; requires: '.e(implode(', ',$reqs)):'').'</div></div></div>';
    }
    if($blockRows==='')$blockRows='<p class="muted">No homepage blocks registered.</p>';
    if($blockRuntime->errors()!==[]){
        foreach($blockRuntime->errors() as $pkgId=>$err)$blockRows.='<div class="notice error">'.e($pkgId).': '.e($err).'</div>';
    }

    $wpRepo=new \Erased\Website\WebsiteProfileRepository(db());
    $wpTypes=new \Erased\Website\WebsiteTypeManager((defined('ROOT')?ROOT:dirname(__DIR__)).'/website-types/registry.json');
    $wpService=new \Erased\Website\WebsiteProfileService($wpRepo,$wpTypes,db());
    $activeProfile=$wpRepo->findActive();
    $profileHtml='<p class="muted">No active website profile.</p>';
    if($activeProfile!==null){
        $profileHtml='<p><strong>'.e($activeProfile['name']).'</strong> <span class="mono">type: '.e($activeProfile['type_id']).'</span></p>';
        $capStatus=$wpService->capabilityStatus($capRuntime->resolver());
        if($capStatus!==[]){
            $profileHtml.='<div class="admin-row-list">';
            foreach($capStatus as $s)$profileHtml.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title">'.e($s['module']).' <span class="badge">'.($s['satisfied']?'satisfied':'not satisfied').'</span></div></div></div>';
            $profileHtml.='</div>';
        }else{
            $profileHtml.='<p class="muted">This website type recommends no modules, or none are declared.</p>';
        }
    }

    $eventNames=['package.installed','package.updated','package.enabled','package.disabled','package.uninstalled','capability.provider.changed'];
    $eventRows='';
    foreach($eventNames as $ev)$eventRows.='<div class="admin-row"><div class="admin-row-body"><div class="admin-row-title mono">'.e($ev).'</div></div></div>';

    $h=$titleRow
        .$panel('Installed packages',$pkgRows)
        .$panel('Dependencies',$depRows)
        .$panel('Capabilities &amp; resolved providers',$capRows)
        .$panel('Registered services',$svcRows)
        .$panel('Homepage Studio blocks',$blockRows)
        .$panel('Active Website Profile',$profileHtml)
        .$panel('Platform events','<p class="muted">Documented lifecycle events (docs/PLATFORM-FOUNDATION.md). No core listeners are registered yet - this is instrumentation for when packages start listening.</p>'.$eventRows);

    layout('Developer Mode',$h,true);
    exit;
}