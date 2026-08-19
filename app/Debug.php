<?php
declare(strict_types=1);

function erased_debug_page(): never
{
    require_admin();

    $started = defined('ERASED_REQUEST_STARTED_AT') ? (float) ERASED_REQUEST_STARTED_AT : (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    $memory = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    $rootSize = function_exists('erased_dir_size') ? erased_dir_size(ROOT) : 0;
    $storageSize = function_exists('erased_dir_size') ? erased_dir_size(ROOT.'/storage') : 0;
    $uploadLimit = (string) ini_get('upload_max_filesize');
    $postLimit = (string) ini_get('post_max_size');
    $timezone = date_default_timezone_get();
    $user = current_user();
    $role = normalized_role((string) ($user['role'] ?? 'user'));
    $dbStatus = 'Connected';
    $dbVersion = 'Unavailable';
    $queryCount = 'Not instrumented';

    try {
        $dbVersion = (string) db()->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable $e) {
        $dbStatus = 'Unavailable';
    }

    $rows = [
        ['CMS version', cms_full_name()],
        ['PHP', PHP_VERSION],
        ['Server API', PHP_SAPI],
        ['Database', $dbStatus],
        ['Database version', $dbVersion],
        ['Current account', (string) ($user['email'] ?? 'Unknown')],
        ['Account level', ucfirst($role)],
        ['Environment', (string) (getenv('APP_ENV') ?: 'local/default')],
        ['Timezone', $timezone],
        ['Memory now', erased_bytes($memory)],
        ['Peak memory', erased_bytes($peak)],
        ['Project size', erased_bytes($rootSize)],
        ['Storage size', erased_bytes($storageSize)],
        ['Upload limit', $uploadLimit],
        ['POST limit', $postLimit],
        ['DB query count', $queryCount],
        ['HTTPS', erased_https() ? 'Yes' : 'No'],
    ];

    $table = '';
    foreach ($rows as [$label, $value]) {
        $table .= '<tr><th>'.e($label).'</th><td>'.e((string) $value).'</td></tr>';
    }

    $html = '<style>
.debug-shell{display:grid;gap:16px}.debug-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.debug-card{min-width:0;overflow-x:auto}.debug-card h2{margin-top:0}.debug-table{width:100%;border-collapse:collapse}.debug-table th,.debug-table td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.debug-table th{width:42%;color:var(--muted);font-weight:600}.debug-value{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;word-break:break-word}.debug-toolbar{display:flex;gap:8px;flex-wrap:wrap}.debug-outline *{outline:1px solid rgba(0,255,136,.28)!important}.debug-outline main{outline:2px solid #00ff88!important}.debug-outline .card{outline:2px solid #ffb000!important}.debug-live{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.debug-live div{padding:10px;border:1px solid var(--line);border-radius:10px;background:var(--panel-2,var(--panel))}.debug-live strong{display:block;font-size:.75rem;color:var(--muted);margin-bottom:4px}.debug-live span{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.debug-note{font-size:.86rem;color:var(--muted)}
@media(max-width:1050px){.debug-grid{grid-template-columns:1fr 1fr}}@media(max-width:700px){.debug-grid{grid-template-columns:1fr}.debug-live{grid-template-columns:1fr}}
</style>';
    $html .= '<div class="debug-shell">';
    $html .= '<div class="toolbar"><div><h1>Debug</h1><p class="muted">Administrator-only runtime and layout diagnostics.</p></div><a class="btn secondary" href="/admin">Back to dashboard</a></div>';
    $html .= '<div class="debug-toolbar"><button type="button" id="debug-outline-toggle">Toggle layout outlines</button><button type="button" class="secondary" id="debug-copy">Copy report</button><a class="btn secondary" href="/admin/appearance/homepage">Open Layout Studio</a></div>';
    $html .= '<div class="debug-grid">';
    $html .= '<section class="card debug-card"><h2>Live browser</h2><div class="debug-live"><div><strong>Viewport</strong><span id="dbg-viewport">—</span></div><div><strong>Screen</strong><span id="dbg-screen">—</span></div><div><strong>Device pixel ratio</strong><span id="dbg-dpr">—</span></div><div><strong>Orientation</strong><span id="dbg-orientation">—</span></div><div><strong>Document width</strong><span id="dbg-document">—</span></div><div><strong>Admin main width</strong><span id="dbg-main">—</span></div></div></section>';
    $html .= '<section class="card debug-card"><h2>Runtime</h2><table class="debug-table">'.$table.'</table></section>';
    $html .= '<section class="card debug-card"><h2>Request</h2><div class="debug-live"><div><strong>Method</strong><span>'.e((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')).'</span></div><div><strong>Path</strong><span>'.e((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/debug', PHP_URL_PATH) ?: '/debug')).'</span></div><div><strong>Host</strong><span>'.e((string) ($_SERVER['HTTP_HOST'] ?? 'unknown')).'</span></div><div><strong>Render time</strong><span id="dbg-render">'.e(number_format((microtime(true)-$started)*1000, 2)).' ms server</span></div></div><p class="debug-note">Secrets, passwords, cookies, session IDs and database credentials are intentionally excluded.</p></section>';
    $html .= '</div></div>';
    $html .= '<script>(function(){const q=id=>document.getElementById(id);function update(){q("dbg-viewport").textContent=window.innerWidth+" × "+window.innerHeight+" px";q("dbg-screen").textContent=screen.width+" × "+screen.height+" px";q("dbg-dpr").textContent=String(window.devicePixelRatio||1);q("dbg-orientation").textContent=screen.orientation?.type||((innerWidth>innerHeight)?"landscape":"portrait");q("dbg-document").textContent=document.documentElement.scrollWidth+" px";const main=document.querySelector("main");q("dbg-main").textContent=main?Math.round(main.getBoundingClientRect().width)+" px":"not found";}update();addEventListener("resize",update,{passive:true});q("debug-outline-toggle")?.addEventListener("click",()=>document.body.classList.toggle("debug-outline"));q("debug-copy")?.addEventListener("click",async()=>{const report=["ERASED CMS debug report",...Array.from(document.querySelectorAll(".debug-table tr")).map(r=>r.innerText.replace(/\\t/g,": ")),"Viewport: "+q("dbg-viewport").textContent,"Screen: "+q("dbg-screen").textContent,"DPR: "+q("dbg-dpr").textContent,"Orientation: "+q("dbg-orientation").textContent,"Document width: "+q("dbg-document").textContent,"Admin main width: "+q("dbg-main").textContent].join("\\n");try{await navigator.clipboard.writeText(report);q("debug-copy").textContent="Copied";setTimeout(()=>q("debug-copy").textContent="Copy report",1300)}catch(e){alert(report)}});})();</script>';

    layout('Debug', $html, true);
    exit;
}
