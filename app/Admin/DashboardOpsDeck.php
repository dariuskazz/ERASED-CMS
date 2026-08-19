<?php
declare(strict_types=1);

/**
 * OPS DECK — mission-control dashboard render.
 *
 * Dark instrument-panel treatment: telemetry strip up top, gauge-bar stat
 * tiles, monospace readouts throughout, amber reserved for "live/active"
 * signal and held back from noise. Renders inside the shared .sheet-inner
 * wrapper — no <html>/<body>/.frame/.rail/.head here, that's the shell's job.
 */
function erased_dashboard_render_ops_deck(array $view): string
{
    $user       = $view['user'] ?? [];
    $name       = trim((string)($user['display_name'] ?? '')) ?: trim((string)($user['username'] ?? '')) ?: 'there';
    $daypart    = $view['daypart'] ?? 'day';

    $resume     = $view['resume_item'] ?? null;
    $alsoInProg = $view['also_in_progress'] ?? [];
    $recent     = $view['recent_content'] ?? [];
    $attention  = $view['needs_attention'] ?? [];

    $totalPosts = (int)($view['total_posts'] ?? 0);
    $totalPages = (int)($view['total_pages'] ?? 0);
    $totalMedia = (int)($view['total_media'] ?? 0);
    $pendingComments = (int)($view['pending_comments'] ?? 0);

    $stats      = $view['stats'] ?? [];
    $https      = !empty($stats['https']);
    $visitors   = (int)($stats['todayVisitors'] ?? 0);
    $views      = (int)($stats['todayViews'] ?? 0);
    $score      = (int)($stats['score'] ?? 0);
    $proc       = $stats['proc'] ?? [];
    $procAvail  = !empty($proc['available']);
    $cores      = (int)($proc['cores'] ?? 0);
    $memUsed    = (int)($proc['memory_used'] ?? 0);
    $uptimeSec  = (int)($proc['uptime'] ?? 0);
    $load1      = (float)($proc['load1'] ?? 0);
    $load5      = (float)($proc['load5'] ?? 0);
    $load15     = (float)($proc['load15'] ?? 0);
    $dbSize     = (int)($stats['dbSize'] ?? 0);
    $totalSize  = (int)($stats['total'] ?? 0);

    $server         = $stats['server'] ?? [];
    $diskAvailable  = !empty($server['disk_available']);
    $diskTotal      = (int)($server['disk_total'] ?? 0);
    $diskFree       = (int)($server['disk_free'] ?? 0);
    $diskUsedPct    = ($diskAvailable && $diskTotal > 0) ? (int)round(($diskTotal - $diskFree) / $diskTotal * 100) : 0;
    $hostname       = (string)($server['hostname'] ?? 'unknown');
    $osName         = (string)($server['os'] ?? '');

    $cpuLoad    = max(0, min(100, (int)($view['cpu_load'] ?? 0)));
    $ramPct     = max(0, min(100, (int)($view['ram_pct'] ?? 0)));

    $hasWarn = false;
    foreach ($attention as $a) {
        if (($a['severity'] ?? '') === 'warn') { $hasWarn = true; break; }
    }

    // Proportional gauge fills for telemetry that isn't already a 0-100 pct.
    $uptimePct = $uptimeSec > 0 ? max(2, min(100, (int)round($uptimeSec / 604800 * 100))) : 0; // scaled vs 7-day window
    $cmsCapBytes = 250 * 1024 * 1024; // 250MB reference cap for the visual gauge
    $cmsPct = $totalSize > 0 ? max(2, min(100, (int)round($totalSize / $cmsCapBytes * 100))) : 0;
    $dbCapBytes = 50 * 1024 * 1024; // 50MB reference cap
    $dbPct = $dbSize > 0 ? max(2, min(100, (int)round($dbSize / $dbCapBytes * 100))) : 0;

    $eyebrowDate = strtoupper(date('d M Y'));

    $fmtWhen = static function (?string $ts): string {
        if (!$ts) return '';
        $t = strtotime($ts);
        if (!$t) return '';
        return date('M j, H:i', $t);
    };

    $statusBadge = static function (string $status): string {
        $status = strtolower($status);
        $cls = $status === 'draft' ? 'draft' : ($status === 'published' ? 'published' : 'other');
        return '<span class="badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
    };

    // ---------- Customizable widget grid: order + size are admin-editable and
    // persisted (see routes/admin.php POST /admin/dashboard/save-layout),
    // merged over these defaults so a widget id that's new (e.g. a future
    // plugin-contributed one) or missing from a stale saved config always
    // gets a sane fallback rather than silently vanishing or erroring. ----------
    $defaultOrder = ['cwl', 'quick_draft', 'needs_attention', 'recent_content'];
    $defaultSizes = [
        'cwl' => ['cols' => 1, 'height' => null],
        'quick_draft' => ['cols' => 1, 'height' => null],
        'needs_attention' => ['cols' => 1, 'height' => null],
        'recent_content' => ['cols' => 3, 'height' => null],
    ];
    $stored = json_decode((string)setting('dashboard_layout_config', '{}'), true);
    if (!is_array($stored)) $stored = [];
    $widgetOrder = is_array($stored['order'] ?? null)
        ? array_values(array_intersect(array_map('strval', $stored['order']), $defaultOrder))
        : [];
    foreach ($defaultOrder as $id) if (!in_array($id, $widgetOrder, true)) $widgetOrder[] = $id;
    $widgetSizes = $defaultSizes;
    if (is_array($stored['widgets'] ?? null)) {
        foreach ($stored['widgets'] as $wid => $cfg) {
            $wid = (string)$wid;
            if (!isset($defaultSizes[$wid]) || !is_array($cfg)) continue;
            $cols = max(1, min(3, (int)($cfg['cols'] ?? $defaultSizes[$wid]['cols'])));
            $height = isset($cfg['height']) && (int)$cfg['height'] > 0 ? max(120, min(1200, (int)$cfg['height'])) : null;
            $widgetSizes[$wid] = ['cols' => $cols, 'height' => $height];
        }
    }

    ob_start();
    ?>
<style>
  /* The gradient decoration lives on .canvas, not .dash-ops: .dash-ops sits
     inside .sheet-inner, which is centered with a max-width (so the page
     reads comfortably on an ultrawide monitor) - painting the background on
     .dash-ops itself meant it stopped at that centered column's edges,
     leaving flat, undecorated canvas margins on either side on anything
     wider than ~2200px. .canvas spans the actual full available width (and,
     via the grid row it's in, the full available height) regardless of
     .sheet-inner's own width, so anchoring the decoration there instead
     covers the whole window on any monitor while .dash-ops's real content
     stays exactly as centered as before. Scoped with :has() so every other
     admin screen sharing .canvas is unaffected. */
  .admin-area .canvas:has(.dash-ops){
    background-image:
      radial-gradient(ellipse 1200px 700px at 15% -10%, color-mix(in srgb, var(--accent) 5%, transparent), transparent 60%),
      repeating-linear-gradient(180deg, rgba(255,255,255,.012) 0px, rgba(255,255,255,.012) 1px, transparent 1px, transparent 3px);
  }
  .dash-ops{
    --dop-glow: color-mix(in srgb, var(--accent) 35%, transparent);
    font-family: var(--font-body);
    color: var(--ink);
    margin: -28px;
    padding: 26px 32px 60px;
  }
  .dash-ops *{ box-sizing:border-box; }
  .dash-ops a{ color:inherit; text-decoration:none; }
  .dash-ops button{ font-family:inherit; cursor:pointer; }
  .dash-ops .mono{ font-family:var(--font-mono); }
  .dash-ops ::selection{ background:var(--accent); color:var(--paper); }

  .dash-ops .dot{
    width:6px; height:6px; border-radius:50%; background:var(--accent); flex:none; display:inline-block;
    box-shadow:0 0 6px var(--dop-glow);
    animation:dop-pulse 2.4s ease-in-out infinite;
  }
  @keyframes dop-pulse{ 0%,100%{ opacity:1; } 50%{ opacity:.35; } }

  .dash-ops .topbar{
    display:flex; justify-content:flex-start; align-items:flex-start;
    margin-bottom:40px; gap:36px; flex-wrap:wrap;
  }
  .dash-ops .greeting-eyebrow{
    font-family:var(--font-mono); font-size:11px; letter-spacing:.16em;
    color:var(--accent); margin-bottom:8px; display:flex; align-items:center; gap:8px;
  }
  .dash-ops h1.greeting{
    font-family:var(--font-mono); font-size:26px; font-weight:600;
    margin:0 0 6px; letter-spacing:-.01em; color:var(--ink);
  }
  .dash-ops .greeting-sub{ font-size:14px; color:var(--ink-dim); }
  .dash-ops .topbar-right{ display:flex; align-items:center; gap:10px; }
  .dash-ops .hbtn{
    font-family:var(--font-mono); font-size:11px; letter-spacing:.05em; font-weight:600;
    padding:9px 14px; border:1px solid var(--line); color:var(--ink-dim);
    background:var(--sheet); white-space:nowrap;
  }
  .dash-ops .hbtn:hover{ border-color:var(--accent); color:var(--accent); }
  .dash-ops .hbtn.primary{
    background:var(--accent); color:var(--paper); border-color:var(--accent);
  }
  .dash-ops .hbtn.primary:hover{ filter:brightness(1.08); }

  .dash-ops .telemetry-wrap{
    border:1px solid var(--line); box-shadow:var(--lift); flex:1 1 780px; min-width:0;
  }
  .dash-ops .telemetry{
    display:grid; grid-template-columns:repeat(10, minmax(78px,1fr)); gap:1px;
    background:var(--line); overflow-x:auto;
  }
  .dash-ops .tcell{ background:var(--sheet); padding:9px 12px 8px; }
  .dash-ops .tcell.warn .tcell-value, .dash-ops .tcell.warn .tcell-label{ color:var(--warn); }
  .dash-ops .tcell-label{
    font-family:var(--font-mono); font-size:9px; letter-spacing:.1em;
    color:var(--ink-faint); margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .dash-ops .tcell-value{
    font-family:var(--font-mono); font-size:17px; color:var(--ink); font-weight:700;
    line-height:1.05; min-height:18px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .dash-ops .tcell-value small{ font-size:10px; color:var(--ink-faint); font-weight:400; margin-left:4px; }
  .dash-ops .tcell-sub{
    font-family:var(--font-mono); font-size:9px; color:var(--ink-dim); margin-top:4px;
    white-space:nowrap; overflow:hidden; position:relative;
  }

  .dash-ops .stats{
    display:grid; grid-template-columns:repeat(4, minmax(84px,1fr)); gap:1px;
    background:var(--line); border:1px solid var(--line); box-shadow:var(--lift);
    flex:1 1 384px; min-width:0;
  }
  .dash-ops .stat{ background:var(--sheet); padding:9px 12px 8px; position:relative; box-shadow:none; }
  /* Only rendered as <a class="stat stat-link"> when erased.analytics-pro
     is installed and enabled (see erased_dashboard_render_ops_deck) - the
     open-source stat tile stays the plain, non-interactive <div> above. */
  .dash-ops a.stat-link{ display:block; text-decoration:none; color:inherit; cursor:pointer; transition:background .12s ease; }
  .dash-ops a.stat-link:hover{ background:var(--sheet-2); }
  .dash-ops a.stat-link:hover .stat-label{ color:var(--accent); }
  .dash-ops .stat-label{
    font-family:var(--font-mono); font-size:9px; letter-spacing:.1em;
    color:var(--ink-faint); margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .dash-ops .stat-value{
    font-family:var(--font-mono); font-size:17px; font-weight:700; color:var(--ink); line-height:1.05; min-height:18px;
  }
  .dash-ops .stat-detail{
    font-family:var(--font-mono); font-size:9px; color:var(--ink-dim); margin-top:4px;
    white-space:nowrap; overflow:hidden; position:relative;
  }
  .dash-ops .tcell-sub .track-wrap, .dash-ops .stat-detail .track-wrap{ display:inline-flex; }
  .dash-ops .tcell-sub .track, .dash-ops .stat-detail .track{ flex:none; white-space:nowrap; }
  .dash-ops .tcell-sub .track + .track, .dash-ops .stat-detail .track + .track{ margin-left:28px; }
  .dash-ops .tcell-sub.is-marquee .track-wrap, .dash-ops .stat-detail.is-marquee .track-wrap{
    animation: dash-marquee var(--marquee-dur, 8s) linear infinite;
  }
  @keyframes dash-marquee{
    from { transform:translateX(0); }
    to   { transform:translateX(var(--marquee-dist, 0px)); }
  }
  .dash-ops .stat.warn .stat-value{ color:var(--warn); }
  .dash-ops .stat.warn .stat-label{ color:var(--warn); }

  /* Customizable widget grid - 3 equal columns, each widget spans 1-3 of them
     (its saved/default "cols") in saved/default order. Same grid for both the
     row-of-3 default layout and any admin-rearranged one, since cols+order
     are just data now instead of two separate hardcoded CSS grids. */
  /* align-items:stretch (grid's own default, stated explicitly) makes every
     widget in the same row match the row's tallest widget - Quick Draft's
     shorter form previously sat top-aligned at its own natural height while
     Continue Where You Left Off/Needs Attention ran taller beside it. Each
     .dash-widget becomes a column flex box so its .panel can flex:1 to fill
     that stretched height, and .panel-body/.recent-list flex+scroll inside
     it - this also gives an admin's explicit height (set via the Customize
     controls) the same fill-and-scroll behaviour uniformly, no separate
     .has-fixed-height case needed. */
  .dash-ops .dash-widgets-grid{ display:grid; grid-template-columns:repeat(3, 1fr); gap:18px; align-items:stretch; }
  .dash-ops .dash-widget{ min-width:0; display:flex; flex-direction:column; }
  .dash-ops .dash-widget > .panel{ flex:1 1 auto; display:flex; flex-direction:column; min-height:0; }
  .dash-ops .dash-widget > .panel > .panel-body,
  .dash-ops .dash-widget > .panel > .recent-list{ flex:1 1 auto; min-height:0; overflow:auto; }
  @media (max-width: 1100px){
    .dash-ops .dash-widgets-grid{ grid-template-columns:1fr; }
    .dash-ops .dash-widget{ grid-column:span 1!important; }
  }

  /* ---------- Customize mode ---------- */
  .dash-ops .dash-customize-bar{ display:flex; justify-content:flex-end; gap:10px; margin-bottom:14px; }
  .dash-ops .dash-widget-controls{
    display:none; align-items:center; gap:6px; flex-wrap:wrap;
    padding:8px 10px; margin-bottom:-1px; border:1px solid var(--accent); border-bottom:0;
    background:color-mix(in srgb, var(--accent) 8%, var(--sheet)); font-family:var(--font-mono); font-size:10.5px;
  }
  .dash-ops .dash-widgets-grid.is-editing .dash-widget-controls{ display:flex; }
  .dash-ops .dash-widgets-grid.is-editing .dash-widget > .panel{ border-color:var(--accent); }
  .dash-ops .dash-widget-controls button{
    height:22px; padding:0 8px; border:1px solid var(--line); background:var(--sheet); color:var(--ink-dim);
  }
  .dash-ops .dash-widget-controls button:hover{ border-color:var(--accent); color:var(--accent); }
  .dash-ops .dash-widget-controls button:disabled{ opacity:.35; cursor:default; }
  .dash-ops .dash-widget-controls label{ display:flex; align-items:center; gap:4px; color:var(--ink-faint); }
  .dash-ops .dash-widget-controls select,.dash-ops .dash-widget-controls input{
    height:22px; padding:0 4px; border:1px solid var(--line); background:var(--sheet); color:var(--ink); font:inherit; font-size:10.5px;
  }
  .dash-ops .dash-widget-controls input[type=number]{ width:56px; }
  .dash-ops .col{ display:flex; flex-direction:column; gap:18px; min-width:0; }

  .dash-ops .panel{ background:var(--sheet); border:1px solid var(--line); }
  .dash-ops .panel-head{
    display:flex; justify-content:space-between; align-items:center;
    padding:13px 18px; border-bottom:1px solid var(--line);
  }
  .dash-ops .panel-title{
    font-family:var(--font-mono); font-size:11.5px; letter-spacing:.14em; color:var(--ink-dim);
    display:flex; align-items:center; gap:8px;
  }
  .dash-ops .panel-tag{
    font-family:var(--font-mono); font-size:10px; color:var(--ink-faint);
    border:1px solid var(--line); padding:2px 7px; border-radius:2px;
  }
  .dash-ops .panel-tag.link:hover{ border-color:var(--accent); color:var(--accent); }
  .dash-ops .panel-body{ padding:18px; }

  /* column, not row - this card now lives in a fixed 340px-wide slot (see .row-top),
     the same width Quick Draft used to have, so it no longer has room for a
     side-by-side title/button row. */
  .dash-ops .cwl-card{
    display:flex; flex-direction:column; align-items:stretch; gap:10px;
    padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--sheet-2);
  }
  /* align-self:flex-start - .cwl-card's own align-items:stretch (needed so
     the title/excerpt/meta block above still spans the card's full width)
     otherwise stretches every direct flex child, including this button, to
     the same full width - producing the "why is the Continue button so
     long" look instead of a normal button sized to its own text. */
  .dash-ops .cwl-card .btn{ justify-content:center; align-self:flex-start; }
  .dash-ops .cwl-title{ font-size:14.5px; font-weight:600; margin:0 0 4px; color:var(--ink); }
  .dash-ops .cwl-excerpt{ font-size:12px; color:var(--ink-faint); font-style:italic; margin:0 0 6px; max-width:52ch; }
  .dash-ops .cwl-meta{ display:flex; gap:8px; align-items:center; font-family:var(--font-mono); font-size:11px; color:var(--ink-faint); }
  .dash-ops .badge{
    font-family:var(--font-mono); font-size:10px; letter-spacing:.08em; font-weight:600;
    padding:2px 7px; border-radius:2px; text-transform:uppercase;
  }
  .dash-ops .badge.draft{ color:var(--warn); background:var(--warn-wash); border:1px solid color-mix(in srgb, var(--warn) 35%, transparent); }
  .dash-ops .badge.published{ color:var(--good); background:var(--good-wash); border:1px solid rgba(92,138,118,.35); }
  .dash-ops .badge.other{ color:var(--ink-dim); background:var(--sheet-2); border:1px solid var(--line); }

  .dash-ops .btn{
    font-family:var(--font-mono); font-size:12px; letter-spacing:.04em; font-weight:600;
    background:var(--accent); color:var(--paper); border:none; padding:0 10px; height:27px;
    white-space:nowrap; flex:none; border-radius:6px; box-shadow:none;
  }
  .dash-ops .btn:hover{ filter:brightness(1.08); }

  .dash-ops .empty-state{
    padding:20px 0; text-align:center; color:var(--ink-faint); font-size:13px;
  }
  .dash-ops .empty-state a{ color:var(--accent); }
  .dash-ops .empty-state a:hover{ text-decoration:underline; }

  .dash-ops .aip-label{ font-family:var(--font-mono); font-size:10.5px; letter-spacing:.14em; color:var(--ink-faint); margin-bottom:7px; }
  .dash-ops .aip-item{
    display:flex; justify-content:space-between; align-items:center; gap:10px;
    padding:6px 0; font-size:13px; color:var(--ink-dim);
    border-top:1px solid var(--sheet-2);
  }
  .dash-ops .aip-item:first-of-type{ border-top:none; }
  .dash-ops .aip-item a{ color:var(--ink); font-weight:500; }
  .dash-ops .aip-item a:hover{ color:var(--accent); }
  .dash-ops .aip-item .mono{ font-size:11px; color:var(--ink-faint); }

  .dash-ops .recent-list{ display:flex; flex-direction:column; }
  .dash-ops .recent-row{
    display:flex; justify-content:space-between; align-items:center; gap:14px;
    padding:12px 18px; border-bottom:1px solid var(--sheet-2);
  }
  .dash-ops .recent-row:last-child{ border-bottom:none; }
  .dash-ops .recent-row:hover{ background:rgba(255,255,255,.02); }
  .dash-ops .recent-main{ min-width:0; flex:1 1 auto; }
  .dash-ops .recent-meta{ margin-top:4px; display:flex; align-items:center; }
  .dash-ops .recent-status{ flex:none; display:flex; align-items:center; white-space:nowrap; }
  .dash-ops .row-title{
    color:var(--ink); font-weight:500; font-size:13.5px;
    display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .dash-ops .row-title a{ color:inherit; }
  .dash-ops .row-title a:hover{ color:var(--accent); }
  .dash-ops .row-path{ font-family:var(--font-mono); font-size:11.5px; color:var(--ink-faint); }
  .dash-ops .type-tag{
    font-family:var(--font-mono); font-size:9.5px; letter-spacing:.08em; color:var(--ink-dim);
    border:1px solid var(--line); padding:1px 5px; margin-right:7px; border-radius:2px;
  }
  .dash-ops .stat-dot{ width:6px; height:6px; border-radius:50%; display:inline-block; margin-right:7px; }
  .dash-ops .stat-dot.draft{ background:var(--warn); box-shadow:0 0 6px var(--dop-glow); }
  .dash-ops .stat-dot.published{ background:var(--good); }
  .dash-ops .stat-dot.other{ background:var(--ink-faint); }

  .dash-ops .alert-item{ display:flex; gap:10px; padding:11px 0; border-top:1px solid var(--sheet-2); }
  .dash-ops .alert-item:first-child{ border-top:none; }
  .dash-ops a.alert-item:hover .alert-title{ color:var(--accent); }
  .dash-ops .alert-icn{ width:8px; height:8px; border-radius:50%; margin-top:5px; flex:none; }
  .dash-ops .alert-icn.ok{ background:var(--good); }
  .dash-ops .alert-icn.warn{ background:var(--warn); box-shadow:0 0 8px var(--dop-glow); }
  .dash-ops .alert-title{ font-size:13px; color:var(--ink); font-weight:500; margin-bottom:2px; }
  .dash-ops .alert-sub{ font-size:12px; color:var(--ink-faint); }
  .dash-ops .alert-sub.warn-text{ color:var(--warn); }

  .dash-ops .qd-input, .dash-ops .qd-textarea{
    width:100%; background:var(--sheet-2); border:1px solid var(--line); color:var(--ink);
    font-family:var(--font-body); font-size:13px; padding:10px 12px; margin-bottom:10px;
  }
  .dash-ops .qd-input:focus, .dash-ops .qd-textarea:focus{ outline:none; border-color:var(--accent); }
  .dash-ops .qd-textarea{ resize:vertical; min-height:78px; font-family:var(--font-body); }
  .dash-ops .qd-textarea::placeholder, .dash-ops .qd-input::placeholder{ color:var(--ink-faint); }
  .dash-ops .qd-foot{ display:flex; justify-content:flex-end; }

  @media (max-width: 1100px){
    .dash-ops .telemetry{ grid-template-columns:repeat(3,1fr); }
    .dash-ops .stats{ grid-template-columns:repeat(3,1fr); }
    .dash-ops .grid{ grid-template-columns:1fr; }
  }
  @media (max-width: 640px){
    .dash-ops{ margin:-16px; padding:18px 16px 40px; }
    .dash-ops .telemetry{ grid-template-columns:repeat(2,1fr); }
    .dash-ops .stats{ grid-template-columns:repeat(2,1fr); }
  }
</style>

<div class="dash-ops">

  <div class="topbar">
    <div>
      <div class="greeting-eyebrow"><span class="dot"></span> CONSOLE // <?= e($eyebrowDate) ?></div>
      <h1 class="greeting">Good <?= e($daypart) ?>, <?= e($name) ?></h1>
      <div class="greeting-sub">Here's what's happening across the site.</div>
    </div>

    <!-- ============ STAT TILES ============ -->
    <section class="stats">
      <div class="stat">
        <div class="stat-label">Content</div>
        <div class="stat-value"><?= (int)($totalPosts + $totalPages) ?></div>
        <div class="stat-detail"><span class="track"><?= (int)$totalPosts ?> posts · <?= (int)$totalPages ?> pages</span></div>
      </div>
      <div class="stat">
        <div class="stat-label">Media</div>
        <div class="stat-value"><?= (int)$totalMedia ?></div>
        <div class="stat-detail"><span class="track">files stored</span></div>
      </div>
      <div class="stat<?= $pendingComments > 0 ? ' warn' : '' ?>">
        <div class="stat-label">Comments</div>
        <div class="stat-value"><?= (int)$pendingComments ?></div>
        <div class="stat-detail"><span class="track"><?= $pendingComments > 0 ? 'awaiting moderation' : 'queue clear' ?></span></div>
      </div>
<?php $analyticsProActive = function_exists('erased_package_active') && erased_package_active('erased.analytics-pro'); ?>
      <?php if ($analyticsProActive): ?>
      <a class="stat stat-link" href="/admin/analytics" title="Open detailed visitor analytics">
      <?php else: ?>
      <div class="stat">
      <?php endif; ?>
        <div class="stat-label">Visitors Today</div>
        <div class="stat-value"><?= (int)$visitors ?></div>
        <div class="stat-detail"><span class="track"><?= (int)$views ?> views</span></div>
      <?= $analyticsProActive ? '</a>' : '</div>' ?>
    </section>

    <!-- ============ TELEMETRY STRIP ============ -->
    <div class="telemetry-wrap">
    <section class="telemetry">
    <div class="tcell">
      <div class="tcell-label">CPU Load</div>
      <?php if ($procAvail): ?>
        <div class="tcell-value"><?= (int)$cpuLoad ?><small>%</small></div>
        <div class="tcell-sub"><span class="track"><?= $cores > 0 ? e((string)$cores) . ' cores' : 'cores N/A' ?></span></div>
      <?php else: ?>
        <div class="tcell-value" style="font-size:14px;color:var(--ink-faint);">N/A</div>
        <div class="tcell-sub"><span class="track">unavailable</span></div>
      <?php endif; ?>
    </div>
    <div class="tcell">
      <div class="tcell-label">RAM Usage</div>
      <?php if ($procAvail): ?>
        <div class="tcell-value"><?= (int)$ramPct ?><small>%</small></div>
        <div class="tcell-sub"><span class="track"><?= e(erased_bytes($memUsed)) ?> allocated</span></div>
      <?php else: ?>
        <div class="tcell-value" style="font-size:14px;color:var(--ink-faint);">N/A</div>
        <div class="tcell-sub"><span class="track">unavailable</span></div>
      <?php endif; ?>
    </div>
    <div class="tcell">
      <div class="tcell-label">Uptime</div>
      <?php if ($procAvail && $uptimeSec > 0): ?>
        <div class="tcell-value" style="font-size:14px;"><?= e(erased_uptime($uptimeSec)) ?></div>
        <div class="tcell-sub"><span class="track">since last restart</span></div>
      <?php else: ?>
        <div class="tcell-value" style="font-size:14px;color:var(--ink-faint);">N/A</div>
        <div class="tcell-sub"><span class="track">unavailable</span></div>
      <?php endif; ?>
    </div>
    <div class="tcell">
      <div class="tcell-label">CMS Size</div>
      <div class="tcell-value" style="font-size:14px;"><?= e(erased_bytes($totalSize)) ?></div>
      <div class="tcell-sub"><span class="track">disk footprint</span></div>
    </div>
    <div class="tcell">
      <div class="tcell-label">Database</div>
      <div class="tcell-value" style="font-size:14px;"><?= e(erased_bytes($dbSize)) ?></div>
      <div class="tcell-sub"><span class="track">MySQL / MariaDB</span></div>
    </div>
    <div class="tcell">
      <div class="tcell-label">Runtime</div>
      <div class="tcell-value" style="font-size:14px;">PHP <?= e(PHP_VERSION) ?></div>
      <div class="tcell-sub"><span class="track"><?= $https ? 'HTTPS secure' : 'HTTP · not secure' ?></span></div>
    </div>
    <div class="tcell">
      <div class="tcell-label">Disk</div>
      <?php if ($diskAvailable): ?>
        <div class="tcell-value"><?= (int)$diskUsedPct ?><small>%</small></div>
        <div class="tcell-sub"><span class="track"><?= e(erased_bytes($diskFree)) ?> free / <?= e(erased_bytes($diskTotal)) ?></span></div>
      <?php else: ?>
        <div class="tcell-value" style="font-size:14px;color:var(--ink-faint);">N/A</div>
        <div class="tcell-sub"><span class="track">unavailable</span></div>
      <?php endif; ?>
    </div>
    <div class="tcell">
      <div class="tcell-label">Host / OS</div>
      <div class="tcell-value" style="font-size:14px;" title="<?= e($hostname) ?>"><?= e($hostname) ?></div>
      <div class="tcell-sub"><span class="track"><?= e($osName) ?></span></div>
    </div>
    <div class="tcell">
      <div class="tcell-label">Load Avg</div>
      <?php if ($procAvail): ?>
        <div class="tcell-value" style="font-size:14px;"><?= number_format($load1, 2) ?></div>
        <div class="tcell-sub"><span class="track">5m <?= number_format($load5, 2) ?> · 15m <?= number_format($load15, 2) ?></span></div>
      <?php else: ?>
        <div class="tcell-value" style="font-size:14px;color:var(--ink-faint);">N/A</div>
        <div class="tcell-sub"><span class="track">unavailable</span></div>
      <?php endif; ?>
    </div>
    <div class="tcell<?= ($score < 90 || $hasWarn) ? ' warn' : '' ?>">
      <div class="tcell-label">Health Score</div>
      <div class="tcell-value"><?= (int)$score ?><small>%</small></div>
      <div class="tcell-sub"><span class="track"><?= $hasWarn ? 'issue flagged below' : 'all systems nominal' ?></span></div>
    </div>
    </section>
    </div>
  </div>

  <?php
    // ---------- Widget bodies, keyed by id - order/size come from $widgetOrder/$widgetSizes above ----------
    $widgetMarkup = [];

    ob_start(); ?>
    <div class="panel-head">
      <div class="panel-title"><span class="dot"></span> CONTINUE WHERE YOU LEFT OFF</div>
      <div class="panel-tag">LOG C1</div>
    </div>
    <div class="panel-body">
      <?php if ($resume): ?>
        <?php
          $excerpt = trim((string)($resume['excerpt'] ?? ''));
          if ($excerpt === '') $excerpt = 'No excerpt yet — open the editor to keep writing.';
          $status = (string)($resume['status'] ?? '');
        ?>
        <div class="cwl-card">
          <div>
            <h3 class="cwl-title"><?= e((string)$resume['title']) ?></h3>
            <p class="cwl-excerpt"><?= e($excerpt) ?></p>
            <div class="cwl-meta">
              <?= $statusBadge($status) ?>
              <span>Updated <?= e($fmtWhen($resume['updated_at'] ?? null)) ?></span>
            </div>
          </div>
          <a class="btn" href="/admin/content/<?= (int)$resume['id'] ?>/edit">Continue</a>
        </div>
      <?php else: ?>
        <div class="empty-state">
          Nothing drafted yet. <a href="/admin/content/new">Start your first post →</a>
        </div>
      <?php endif; ?>

      <?php if (!empty($alsoInProg)): ?>
        <div class="aip-label">ALSO IN PROGRESS</div>
        <?php foreach ($alsoInProg as $item): ?>
          <div class="aip-item">
            <span><a href="/admin/content/<?= (int)$item['id'] ?>/edit"><?= e((string)$item['title']) ?></a> <span class="mono">· <?= e(ucfirst((string)($item['status'] ?? ''))) ?></span></span>
            <span class="mono"><?= e($fmtWhen($item['updated_at'] ?? null)) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php $widgetMarkup['cwl'] = ob_get_clean();

    ob_start(); ?>
    <div class="panel-head">
      <div class="panel-title"><span class="dot"></span> QUICK DRAFT</div>
      <div class="panel-tag">LOG Q1</div>
    </div>
    <div class="panel-body">
      <form method="post" action="/admin/content/new">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="type" value="post">
        <input type="hidden" name="status" value="draft">
        <input class="qd-input" type="text" name="title" placeholder="Title" required>
        <textarea class="qd-textarea" name="body" placeholder="Jot down the idea…"></textarea>
        <div class="qd-foot"><button class="btn" type="submit">Save draft</button></div>
      </form>
    </div>
    <?php $widgetMarkup['quick_draft'] = ob_get_clean();

    ob_start(); ?>
    <div class="panel-head">
      <div class="panel-title"><span class="dot" style="background:var(--warn);"></span> NEEDS ATTENTION</div>
      <div class="panel-tag">ALERT LOG</div>
    </div>
    <div class="panel-body">
      <?php if (empty($attention)): ?>
        <div class="empty-state">Nothing flagged. All systems nominal.</div>
      <?php else: ?>
        <?php foreach ($attention as $a): ?>
          <?php $sev = ($a['severity'] ?? 'ok') === 'warn' ? 'warn' : 'ok'; ?>
          <a class="alert-item" href="<?= e((string)($a['href'] ?? '#')) ?>">
            <div class="alert-icn <?= $sev ?>"></div>
            <div>
              <div class="alert-title"><?= e((string)$a['title']) ?></div>
              <div class="alert-sub<?= $sev === 'warn' ? ' warn-text' : '' ?>"><?= e((string)$a['subtitle']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php $widgetMarkup['needs_attention'] = ob_get_clean();

    ob_start(); ?>
    <div class="panel-head">
      <div class="panel-title"><span class="dot"></span> RECENT CONTENT</div>
      <a class="panel-tag link" href="/admin/content">FULL INDEX →</a>
    </div>
    <?php if (empty($recent)): ?>
      <div class="panel-body">
        <div class="empty-state">No content yet. <a href="/admin/content/new">Create your first post →</a></div>
      </div>
    <?php else: ?>
      <div class="recent-list">
        <?php foreach ($recent as $row): ?>
          <?php
            $rStatus = strtolower((string)($row['status'] ?? ''));
            $dotCls = $rStatus === 'draft' ? 'draft' : ($rStatus === 'published' ? 'published' : 'other');
            $colorVar = $rStatus === 'draft' ? 'var(--warn)' : ($rStatus === 'published' ? 'var(--good)' : 'var(--ink-faint)');
            $type = strtoupper((string)($row['type'] ?? 'post'));
          ?>
          <div class="recent-row">
            <div class="recent-main">
              <span class="row-title"><a href="/admin/content/<?= (int)$row['id'] ?>/edit"><?= e((string)$row['title']) ?></a></span>
              <div class="recent-meta"><span class="type-tag"><?= e($type) ?></span><span class="row-path">/<?= e((string)$row['slug']) ?></span></div>
            </div>
            <div class="recent-status"><span class="stat-dot <?= $dotCls ?>"></span><span class="mono" style="font-size:11.5px;color:<?= $colorVar ?>;"><?= e(strtoupper($rStatus)) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php $widgetMarkup['recent_content'] = ob_get_clean();
  ?>

  <!-- ============ MAIN GRID ============ -->
  <!-- Data-driven: order/cols/height per widget come from $widgetOrder/$widgetSizes
       (loaded above, admin-editable via the Customize toggle below and persisted
       through POST /admin/dashboard/save-layout) rather than two hardcoded rows -
       adding a future widget (core or plugin-contributed) only needs an entry in
       $widgetMarkup plus $defaultOrder/$defaultSizes, the grid and the customize
       controls both pick it up automatically. -->
  <div class="dash-customize-bar" id="dash-customize-bar">
    <button type="button" class="hbtn" data-dash-customize>Customize dashboard</button>
    <button type="button" class="hbtn" data-dash-cancel style="display:none">Cancel</button>
    <button type="button" class="hbtn primary" data-dash-save style="display:none">Save layout</button>
  </div>
  <div class="dash-widgets-grid" id="dash-widgets-grid" data-dash-grid>
    <?php foreach ($widgetOrder as $wid): if (!isset($widgetMarkup[$wid])) continue; ?>
      <?php $size = $widgetSizes[$wid]; ?>
      <div class="dash-widget" data-widget-id="<?= e($wid) ?>" style="grid-column:span <?= (int)$size['cols'] ?><?= $size['height'] ? ';height:'.(int)$size['height'].'px' : '' ?>">
        <div class="dash-widget-controls">
          <button type="button" data-dash-move="-1" title="Move earlier">←</button>
          <button type="button" data-dash-move="1" title="Move later">→</button>
          <label>Width <select data-dash-cols>
            <option value="1">1/3</option>
            <option value="2">2/3</option>
            <option value="3">Full</option>
          </select></label>
          <label>Height <input type="number" data-dash-height placeholder="auto" min="120" max="1200" step="10" value="<?= $size['height'] ? (int)$size['height'] : '' ?>"></label>
        </div>
        <div class="panel<?= $size['height'] ? ' has-fixed-height' : '' ?>"><?= $widgetMarkup[$wid] ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
  (function(){
    var grid = document.getElementById('dash-widgets-grid');
    var bar = document.getElementById('dash-customize-bar');
    if (!grid || !bar) return;
    var customizeBtn = bar.querySelector('[data-dash-customize]');
    var cancelBtn = bar.querySelector('[data-dash-cancel]');
    var saveBtn = bar.querySelector('[data-dash-save]');

    function widgets(){ return Array.prototype.slice.call(grid.querySelectorAll('.dash-widget')); }

    grid.querySelectorAll('.dash-widget').forEach(function(w){
      var cols = w.style.gridColumn.replace('span ', '').trim() || '1';
      var colsSelect = w.querySelector('[data-dash-cols]');
      if (colsSelect) colsSelect.value = cols;
    });

    customizeBtn.addEventListener('click', function(){
      grid.classList.add('is-editing');
      customizeBtn.style.display = 'none';
      cancelBtn.style.display = '';
      saveBtn.style.display = '';
    });
    cancelBtn.addEventListener('click', function(){ location.reload(); });

    grid.addEventListener('click', function(e){
      var moveBtn = e.target.closest('[data-dash-move]');
      if (!moveBtn) return;
      var widget = moveBtn.closest('.dash-widget');
      var dir = parseInt(moveBtn.getAttribute('data-dash-move'), 10);
      var sibling = dir < 0 ? widget.previousElementSibling : widget.nextElementSibling;
      if (!sibling) return;
      if (dir < 0) grid.insertBefore(widget, sibling);
      else grid.insertBefore(sibling, widget);
    });

    grid.addEventListener('change', function(e){
      var widget = e.target.closest('.dash-widget');
      if (!widget) return;
      if (e.target.matches('[data-dash-cols]')) {
        widget.style.gridColumn = 'span ' + e.target.value;
      } else if (e.target.matches('[data-dash-height]')) {
        var panel = widget.querySelector('.panel');
        var px = parseInt(e.target.value, 10);
        if (px > 0) {
          widget.style.height = px + 'px';
          panel.classList.add('has-fixed-height');
        } else {
          widget.style.height = '';
          panel.classList.remove('has-fixed-height');
        }
      }
    });

    saveBtn.addEventListener('click', function(){
      var order = [];
      var widgetsCfg = {};
      widgets().forEach(function(w){
        var id = w.dataset.widgetId;
        order.push(id);
        var colsSelect = w.querySelector('[data-dash-cols]');
        var heightInput = w.querySelector('[data-dash-height]');
        widgetsCfg[id] = {
          cols: colsSelect ? parseInt(colsSelect.value, 10) : 1,
          height: heightInput && heightInput.value ? parseInt(heightInput.value, 10) : null
        };
      });
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving…';
      fetch('/admin/dashboard/save-layout', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({csrf: <?= json_encode(csrf()) ?>, order: order, widgets: widgetsCfg})
      }).then(function(){ location.reload(); }).catch(function(){
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save layout';
        alert('Could not save the layout. Please try again.');
      });
    });
  })();
  </script>

</div>
<script>(function(){
  function syncPanelHeights(){
    var cols = document.querySelectorAll('.dash-ops .grid > .col');
    if (cols.length < 2) return;
    var leftPanels = cols[0].querySelectorAll(':scope > .panel');
    var rightPanels = cols[1].querySelectorAll(':scope > .panel');
    if (leftPanels.length < 2 || rightPanels.length < 2) return;
    var rightPanel1Body = rightPanels[0].querySelector('.panel-body');
    if (rightPanel1Body) {
      rightPanel1Body.style.minHeight = '';
      rightPanel1Body.style.minHeight = leftPanels[0].offsetHeight + 'px';
    }
    rightPanels[1].style.minHeight = '';
    rightPanels[1].style.minHeight = leftPanels[1].offsetHeight + 'px';
  }
  function syncScrollText(){
    var TRACK_GAP = 28; // must match CSS `.track + .track{ margin-left:28px }`
    var els = document.querySelectorAll('.dash-ops .tcell-sub, .dash-ops .stat-detail');
    els.forEach(function(el){
      var wrap = el.querySelector('.track-wrap');
      var track = wrap ? wrap.querySelector('.track') : el.querySelector('.track');
      if (!track) return;
      if (!wrap) {
        wrap = document.createElement('span');
        wrap.className = 'track-wrap';
        track.parentNode.insertBefore(wrap, track);
        wrap.appendChild(track);
      }
      var dup = wrap.querySelectorAll('.track')[1];
      if (dup) dup.remove();
      el.classList.remove('is-marquee');
      el.style.removeProperty('--marquee-dur');
      el.style.removeProperty('--marquee-dist');

      var overflow = track.scrollWidth - el.clientWidth;
      if (overflow > 2) {
        // Exact pixel distance, not a -50% approximation: the wrap's total
        // width is trackWidth*2 + TRACK_GAP (one gap, between the two
        // copies only), so -50% of that shifts by trackWidth + GAP/2 —
        // short of the trackWidth + GAP needed to land the second copy
        // exactly where the first one started. That GAP/2 shortfall is
        // what read as a stutter/jump once per loop.
        var clone = track.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        wrap.appendChild(clone);
        var trackWidth = track.getBoundingClientRect().width;
        var dist = trackWidth + TRACK_GAP;
        var duration = Math.max(4, dist / 40);
        el.style.setProperty('--marquee-dist', '-' + dist + 'px');
        el.style.setProperty('--marquee-dur', duration + 's');
        el.classList.add('is-marquee');
      }
    });
  }
  syncPanelHeights();
  syncScrollText();
  var scrollTextResizeTimer;
  window.addEventListener('resize', syncPanelHeights);
  window.addEventListener('resize', function(){
    clearTimeout(scrollTextResizeTimer);
    scrollTextResizeTimer = setTimeout(syncScrollText, 250);
  });
})();</script>
    <?php
    return (string)ob_get_clean();
}
