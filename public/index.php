<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/widgets/latest_posts.php';
require_once __DIR__.'/../app/HomepageLayout.php';
require_once __DIR__.'/../app/Debug.php';
define('ERASED_REQUEST_STARTED_AT', (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)));
// Rewrite-independent fallback: if this file is reached directly (no
// working .htaccess/mod_rewrite on the host) with ?route=/whatever, use
// that as the effective path instead of the literal request URI. Lets the
// entire app be driven through a single real file with no clean-URL
// dependency at all, matching how WordPress falls back to ?p=123-style
// URLs when pretty permalinks aren't available - same idea, applied here
// to every route, not just posts.
if(isset($_GET['route'])&&is_string($_GET['route'])&&$_GET['route']!==''){
 $path=$_GET['route'];
 if($path[0]!=='/')$path='/'.$path;
 $GLOBALS['erased_no_rewrite_mode']=true;
}else{
 $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
}
if($path!=='/' && str_ends_with($path,'/')) $path=rtrim($path,'/');
if(!installed()&&$path!=='/install')redirect('/install');
// Database schema evolution is handled exclusively by database/migrations.
function erased_dir_size(string $path): int{
 if(!is_dir($path))return is_file($path)?(int)@filesize($path):0;$size=0;
 try{$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS));foreach($it as $file)if($file->isFile())$size+=(int)$file->getSize();}catch(Throwable $e){}
 return $size;
}
function erased_bytes(int|float $bytes): string{
 $units=['B','KB','MB','GB','TB'];$i=0;$value=(float)max(0,$bytes);while($value>=1024&&$i<count($units)-1){$value/=1024;$i++;}return number_format($value,$i===0?0:1).' '.$units[$i];
}
function erased_https(): bool{return (!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');}
function erased_client_ip(): string{
 $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));
 if(setting('cloudflare_enabled','0')==='1'){
  $cf=trim((string)($_SERVER['HTTP_CF_CONNECTING_IP']??''));
  if($cf!==''&&filter_var($cf,FILTER_VALIDATE_IP))$ip=$cf;
 }
 return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'0.0.0.0';
}
function erased_cloudflare_turnstile_enabled(): bool{
 return setting('cloudflare_turnstile_enabled','0')==='1'&&setting('cloudflare_turnstile_site_key','')!==''&&setting('cloudflare_turnstile_secret_key','')!=='';
}
function erased_comment_captcha_enabled(): bool{
 return setting('comment_captcha_enabled','1')==='1';
}
function erased_comment_captcha_widget(): string{
 if(!erased_comment_captcha_enabled())return '';
 if(erased_cloudflare_turnstile_enabled()){
  return erased_cloudflare_turnstile_widget();
 }
 $n1=rand(2,9);$n2=rand(1,9);$ans=$n1+$n2;$t=time();
 $hash=hash_hmac('sha256',$ans.'|'.$t,erased_app_secret());
 return '<div class="comment-captcha-box" style="margin:14px 0;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:color-mix(in srgb,var(--panel) 92%,var(--bg))">'
  .'<label style="display:grid;gap:6px;font-weight:700;margin:0"><span>'.e(tr('comment_captcha_label')).' <small class="muted">('.e(tr('comment_captcha_subtitle')).')</small></span>'
  .'<span style="font-size:.95rem;font-weight:600">'.e(tr('comment_captcha_question')).' <strong>'.$n1.' + '.$n2.'</strong>?</span>'
  .'<input type="number" name="comment_captcha_answer" placeholder="'.e(tr('comment_captcha_answer_placeholder')).'" required style="width:130px;margin-top:2px" autocomplete="off">'
  .'</label>'
  .'<input type="hidden" name="comment_captcha_hash" value="'.e($hash).'">'
  .'<input type="hidden" name="comment_captcha_time" value="'.$t.'">'
  .'</div>';
}
function erased_verify_comment_captcha(): bool{
 if(!erased_comment_captcha_enabled())return true;
 if(erased_cloudflare_turnstile_enabled()){
  return erased_verify_cloudflare_turnstile();
 }
 $ans=trim((string)($_POST['comment_captcha_answer']??''));
 $hash=(string)($_POST['comment_captcha_hash']??'');
 $time=(int)($_POST['comment_captcha_time']??0);
 if($ans===''||$hash===''||$time===0)return false;
 if(time()-$time>1800)return false;
 $expected=hash_hmac('sha256',$ans.'|'.$time,erased_app_secret());
 return hash_equals($expected,$hash);
}
function erased_cloudflare_turnstile_widget(): string{
 if(!erased_cloudflare_turnstile_enabled())return '';
 $siteKey=e(setting('cloudflare_turnstile_site_key',''));
 return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><div class="cf-turnstile" data-sitekey="'.$siteKey.'" data-theme="auto" style="margin:12px 0"></div>';
}
function erased_verify_cloudflare_turnstile(?string $token=null): bool{
 if(!erased_cloudflare_turnstile_enabled())return true;
 $response=$token??($_POST['cf-turnstile-response']??'');
 if($response==='')return false;
 $secret=setting('cloudflare_turnstile_secret_key','');
 $remoteIp=erased_client_ip();
 if(!function_exists('curl_init'))return true;
 $ch=curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['secret'=>$secret,'response'=>$response,'remoteip'=>$remoteIp]),CURLOPT_TIMEOUT=>6]);
 $res=curl_exec($ch);curl_close($ch);
 if(!$res)return false;
 $data=json_decode((string)$res,true);
 return !empty($data['success']);
}
function erased_cloudflare_purge_cache(array $urls=[]): array{
 $zoneId=trim(setting('cloudflare_zone_id',''));
 $apiToken=trim(setting('cloudflare_api_token',''));
 if($zoneId===''||$apiToken==='')return ['success'=>false,'error'=>'Cloudflare Zone ID or API Token is missing.'];
 if(!function_exists('curl_init'))return ['success'=>false,'error'=>'cURL PHP extension is not available.'];
 $endpoint="https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache";
 $payload=$urls!==[]?['files'=>$urls]:['purge_everything'=>true];
 $ch=curl_init($endpoint);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiToken,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>10]);
 $result=curl_exec($ch);curl_close($ch);
 if(!$result)return ['success'=>false,'error'=>'Connection to Cloudflare API failed.'];
 $data=json_decode((string)$result,true);
 if(!empty($data['success'])){
  erased_security_event('cloudflare.cache_purged','info',['urls_count'=>count($urls),'purge_all'=>$urls===[]]);
  return ['success'=>true,'message'=>$urls==[]?'Entire Cloudflare edge cache purged.':count($urls).' URLs purged from Cloudflare cache.'];
 }
 $errors=isset($data['errors'])&&is_array($data['errors'])?array_column($data['errors'],'message'):['Cloudflare API returned failure status.'];
 return ['success'=>false,'error'=>implode(', ',$errors)];
}
function erased_security_event(string $type,string $level='info',array $details=[],?array $user=null): void{
 try{
  $q=db()->prepare('INSERT INTO security_events(level,event_type,user_id,username,ip_address,user_agent,details_json) VALUES(?,?,?,?,?,?,?)');
  $q->execute([$level,$type,$user['id']??($_SESSION['user_id']??null),$user['email']??null,erased_client_ip(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000),json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
 }catch(Throwable $e){}
}
function erased_ip_lockout_status(string $ip): ?array{
 if(setting('ip_lockout_enabled','1')!=='1')return null;
 try{
  db()->exec('DELETE FROM security_ip_lockouts WHERE locked_until IS NOT NULL AND locked_until<=NOW()');
  $q=db()->prepare('SELECT * FROM security_ip_lockouts WHERE ip_address=? AND locked_until>NOW() LIMIT 1');$q->execute([$ip]);
  $row=$q->fetch();return $row?:null;
 }catch(Throwable $e){return null;}
}
function erased_record_login_failure(string $ip,string $email): ?array{
 if(setting('ip_lockout_enabled','1')!=='1')return null;
 $threshold=max(3,min(100,(int)setting('ip_lockout_threshold','8')));
 $window=max(1,min(1440,(int)setting('ip_lockout_window_minutes','15')));
 $duration=max(1,min(10080,(int)setting('ip_lockout_duration_minutes','30')));
 try{
  $q=db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND successful=0 AND created_at>DATE_SUB(NOW(),INTERVAL '.$window.' MINUTE)');$q->execute([$ip]);$failures=(int)$q->fetchColumn();
  if($failures<$threshold)return null;
  $q=db()->prepare('INSERT INTO security_ip_lockouts(ip_address,failed_attempts,last_email,reason,locked_until) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL '.$duration.' MINUTE)) ON DUPLICATE KEY UPDATE failed_attempts=VALUES(failed_attempts),last_email=VALUES(last_email),reason=VALUES(reason),locked_until=VALUES(locked_until),updated_at=NOW()');
  $q->execute([$ip,$failures,$email,'Automatic lockout after repeated failed logins']);
  erased_security_event('auth.ip_locked','danger',['ip'=>$ip,'email'=>$email,'failed_attempts'=>$failures,'duration_minutes'=>$duration]);
  return erased_ip_lockout_status($ip);
 }catch(Throwable $e){return null;}
}
function erased_clear_login_failures(string $ip): void{
 try{db()->prepare('DELETE FROM security_ip_lockouts WHERE ip_address=?')->execute([$ip]);}catch(Throwable $e){}
}
function erased_track_visit(string $path): void{
 if($_SERVER['REQUEST_METHOD']!=='GET'||str_starts_with($path,'/admin')||in_array($path,['/login','/logout','/install'],true)||preg_match('~\.(?:css|js|png|jpe?g|gif|svg|webp|ico|woff2?|map)$~i',$path))return;
 $ua=trim((string)($_SERVER['HTTP_USER_AGENT']??''));if($ua===''||preg_match('~bot|crawler|spider|preview|monitor|uptime|curl|wget~i',$ua))return;
 $ip=trim((string)($_SERVER['HTTP_CF_CONNECTING_IP']??$_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??''));if(str_contains($ip,','))$ip=trim(explode(',',$ip)[0]);if($ip==='')return;
 $salt=setting('analytics_salt','');if($salt===''){$salt=bin2hex(random_bytes(24));try{set_setting('analytics_salt',$salt);}catch(Throwable $e){$salt='erased-cms-local-analytics';}}
 $hash=hash('sha256',$ip.'|'.$ua.'|'.date('Y-m-d').'|'.$salt);
 try{$q=db()->prepare("INSERT INTO analytics_visits(visit_day,visitor_hash,first_seen,last_seen,page_views) VALUES(CURDATE(),?,NOW(),NOW(),1) ON DUPLICATE KEY UPDATE last_seen=NOW(),page_views=page_views+1");$q->execute([$hash]);}catch(Throwable $e){}
 // Detailed per-visit tracking (raw IP, browser, OS, device) is the paid
 // erased.analytics-pro plugin's job, not core's - core only ever sees the
 // hashed, deduplicated counter above. This forwards to the plugin's own
 // service only when it's installed and enabled, and never lets a tracking
 // failure there affect a real visitor's page load.
 if(function_exists('erased_package_active')&&erased_package_active('erased.analytics-pro')){
  try{service_runtime()->container()->get('erased.analytics-pro.tracker')->track($ip,$ua,$path);}catch(Throwable $e){}
 }
}
function erased_sanitize_svg(string $svg): string {
    $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svg) ?? $svg;
    $clean = preg_replace('/on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    $clean = preg_replace('/href\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\'|javascript:[^\s>]+)/i', '', $clean) ?? $clean;
    return $clean;
}
function erased_waf_inspect(): void {
    if (!installed()) return;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $ip = erased_client_ip();
    if (setting('read_only_mode', '0') === '1' && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        if (!in_array($path, ['/login', '/logout'], true)) {
            http_response_code(403);
            exit('<html><body style="font-family:system-ui;background:#111;color:#fff;display:grid;place-items:center;height:100vh;margin:0;"><div><h1>'.e(tr('read_only_mode_title')).'</h1><p>'.e(tr('read_only_mode_message')).'</p><p><a href="/" style="color:#2dfc98">'.e(tr('return_to_website')).'</a></p></div></body></html>');
        }
    }
    if (setting('emergency_lockdown', '0') === '1' && $path === '/login' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $q = db()->prepare("SELECT role FROM users WHERE email=? LIMIT 1");
        $q->execute([$email]);
        $role = (string)$q->fetchColumn();
        if (normalized_role($role) !== 'admin') {
            http_response_code(403);
            erased_security_event('waf.lockdown_blocked', 'warning', ['ip' => $ip, 'email' => $email]);
            exit(tr('emergency_lockdown_message'));
        }
    }
    $bl = array_filter(array_map('trim', explode("\n", (string)setting('ip_blacklist', ''))));
    if ($bl && in_array($ip, $bl, true)) {
        http_response_code(403);
        erased_security_event('waf.blacklisted_ip_blocked', 'warning', ['ip' => $ip]);
        exit(tr('access_denied_policy'));
    }
    if (str_starts_with($path, '/admin')) {
        $al = array_filter(array_map('trim', explode("\n", (string)setting('admin_ip_allowlist', ''))));
        if ($al && !in_array($ip, $al, true)) {
            http_response_code(403);
            erased_security_event('waf.allowlist_blocked', 'danger', ['ip' => $ip, 'path' => $path]);
            exit(tr('admin_ip_restricted'));
        }
    }
    if (setting('waf_enabled', '1') !== '1') return;
    $inputs = [];
    foreach ($_GET as $k => $v) if (is_string($v)) $inputs['GET:'.$k] = $v;
    foreach ($_POST as $k => $v) if (is_string($v)) $inputs['POST:'.$k] = $v;
    foreach ($_COOKIE as $k => $v) if (is_string($v)) $inputs['COOKIE:'.$k] = $v;
    if (isset($_SERVER['HTTP_USER_AGENT'])) $inputs['UA'] = (string)$_SERVER['HTTP_USER_AGENT'];
    $sqli = '/(union\s+(all\s+)?select|select\s+.*\s+from\s+information_schema|exec\s*\(|concat\s*\(|benchmark\s*\(|\b(drop|alter|truncate)\s+(table|database)\b|\'\s*or\s*\'?1\'?\s*=\s*\'?1)/i';
    $xss = '/(<script\b[^>]*>|javascript\s*:|onerror\s*=|onload\s*=|document\.cookie)/i';
    $traversal = '/(\.\.\/|\.\.\\|%2e%2e%2f|%2e%2e\/)/i';
    foreach ($inputs as $key => $val) {
        $matched = null; $type = null;
        if (preg_match($sqli, $val, $m)) { $type = 'SQL Injection'; $matched = $m[0]; }
        elseif (preg_match($xss, $val, $m)) {
            if (str_starts_with($key, 'POST:body') && logged_in() && can('content.create')) continue;
            $type = 'Cross-Site Scripting (XSS)'; $matched = $m[0];
        }
        elseif (preg_match($traversal, $val, $m)) { $type = 'Path Traversal'; $matched = $m[0]; }
        if ($type && $matched) {
            try {
                $q = db()->prepare("INSERT INTO security_attack_stats(attack_type,ip_address,payload_snippet) VALUES(?,?,?)");
                $q->execute([$type, $ip, substr($key . '=' . $val, 0, 500)]);
            } catch (Throwable $e) {}
            erased_security_event('waf.threat_blocked', 'danger', ['type' => $type, 'matched' => $matched, 'input' => $key, 'ip' => $ip]);
            http_response_code(403);
            exit(tr('waf_forbidden'));
        }
    }
}
function erased_security_scan(): array {
    $checks = [];
    $isHttps = erased_https();
    $checks[] = ['id' => 'https', 'title' => 'HTTPS Encryption', 'status' => $isHttps ? 'ok' : 'warn', 'desc' => $isHttps ? 'Connection is secure and encrypted.' : 'Site is running over unencrypted HTTP. Enable HTTPS on web server.', 'fixable' => false];
    $hasAllowlist = !empty(trim((string)setting('admin_ip_allowlist', '')));
    $checks[] = ['id' => 'admin_ip', 'title' => 'Admin IP Restriction', 'status' => $hasAllowlist ? 'ok' : 'info', 'desc' => $hasAllowlist ? 'Admin access is restricted to allowed IP addresses.' : 'Admin panel is accessible from any IP address.', 'fixable' => false];
    $wafOn = setting('waf_enabled', '1') === '1';
    $checks[] = ['id' => 'waf', 'title' => 'Web Application Firewall (WAF)', 'status' => $wafOn ? 'ok' : 'warn', 'desc' => $wafOn ? 'WAF is active and filtering SQLi, XSS, and Path Traversal threats.' : 'WAF is currently disabled.', 'fixable' => true, 'fix_setting' => ['waf_enabled' => '1']];
    $lockoutOn = setting('ip_lockout_enabled', '1') === '1';
    $checks[] = ['id' => 'lockout', 'title' => 'Automatic IP Lockouts', 'status' => $lockoutOn ? 'ok' : 'warn', 'desc' => $lockoutOn ? 'Brute-force protection automatically locks malicious IPs.' : 'Automatic IP lockout is turned off.', 'fixable' => true, 'fix_setting' => ['ip_lockout_enabled' => '1']];
    $passLen = (int)setting('password_min_length', '12');
    $checks[] = ['id' => 'password_policy', 'title' => 'Password Policy Strength', 'status' => $passLen >= 12 ? 'ok' : 'warn', 'desc' => $passLen >= 12 ? 'Passwords require 12+ characters and complexity.' : 'Minimum password length is below recommended 12 characters.', 'fixable' => true, 'fix_setting' => ['password_min_length' => '12', 'password_require_uppercase' => '1', 'password_require_number' => '1']];
    $dir = ROOT . '/storage/backups';
    $hasBackups = is_dir($dir) && count(array_filter(scandir($dir) ?: [], fn($f) => str_ends_with($f, '.sql'))) > 0;
    $checks[] = ['id' => 'backups', 'title' => 'Database Backup Status', 'status' => $hasBackups ? 'ok' : 'warn', 'desc' => $hasBackups ? 'Database backups are saved in /storage/backups.' : 'No database backups found. Create a backup in Backup Center.', 'fixable' => false];
    $cfHeader = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) || !empty($_SERVER['HTTP_CF_RAY']);
    $cfTrusted = setting('cloudflare_enabled', '0') === '1';
    $checks[] = ['id' => 'cloudflare', 'title' => 'Cloudflare Edge Proxy', 'status' => ($cfHeader || $cfTrusted) ? 'ok' : 'info', 'desc' => $cfHeader ? 'Requests are actively proxied through Cloudflare edge servers.' : ($cfTrusted ? 'Cloudflare Proxy headers trusted.' : 'Cloudflare Proxy integration is ready to be enabled.'), 'fixable' => false];
    return $checks;
}
function erased_proc_stats(): array{
 $out=['available'=>false];
 if(!is_readable('/proc/loadavg')||!is_readable('/proc/meminfo'))return $out;
 $load=preg_split('/\s+/',trim((string)@file_get_contents('/proc/loadavg')));$mem=(string)@file_get_contents('/proc/meminfo');preg_match('/MemTotal:\s+(\d+)/',$mem,$mt);preg_match('/MemAvailable:\s+(\d+)/',$mem,$ma);preg_match('/SwapTotal:\s+(\d+)/',$mem,$st);preg_match('/SwapFree:\s+(\d+)/',$mem,$sf);
 $uptime=is_readable('/proc/uptime')?(float)explode(' ',trim((string)@file_get_contents('/proc/uptime')))[0]:0;$cores=function_exists('shell_exec')?(int)trim((string)@shell_exec('getconf _NPROCESSORS_ONLN 2>/dev/null')):0;if($cores<1)$cores=1;
 $total=(int)($mt[1]??0)*1024;$avail=(int)($ma[1]??0)*1024;$swapTotal=(int)($st[1]??0)*1024;$swapFree=(int)($sf[1]??0)*1024;
 return ['available'=>true,'load1'=>(float)($load[0]??0),'load5'=>(float)($load[1]??0),'load15'=>(float)($load[2]??0),'cores'=>$cores,'memory_total'=>$total,'memory_used'=>max(0,$total-$avail),'swap_total'=>$swapTotal,'swap_used'=>max(0,$swapTotal-$swapFree),'uptime'=>$uptime];
}
function erased_server_info(): array{
 $host=@gethostname();if($host===false||$host==='')$host='unknown';
 $diskTotal=@disk_total_space(ROOT);$diskFree=@disk_free_space(ROOT);$diskAvailable=$diskTotal!==false&&$diskFree!==false;
 return ['hostname'=>$host,'os'=>PHP_OS_FAMILY.' '.php_uname('r'),'software'=>(string)($_SERVER['SERVER_SOFTWARE']??PHP_SAPI),'timezone'=>date_default_timezone_get(),'disk_available'=>$diskAvailable,'disk_total'=>$diskAvailable?(int)$diskTotal:0,'disk_free'=>$diskAvailable?(int)$diskFree:0];
}
function erased_uptime(float $seconds): string{$days=(int)floor($seconds/86400);$remainingDay=fmod($seconds,86400.0);$hours=(int)floor($remainingDay/3600);$remainingHour=fmod($seconds,3600.0);$minutes=(int)floor($remainingHour/60);return $days>0?$days.'d '.$hours.'h':$hours.'h '.$minutes.'m';}
function erased_status_row(string $label,string $value,string $status='ok'): string{return '<div class="health-row"><span><i class="status-dot status-'.e($status).'"></i>'.e($label).'</span><strong>'.e($value).'</strong></div>';}
function erased_dashboard_data(): array{
 $p=db();$https=erased_https();$dbOk=true;try{$p->query('SELECT 1')->fetchColumn();}catch(Throwable $e){$dbOk=false;}
 $todayVisitors=$todayViews=$monthVisitors=$monthViews=0;try{$r=$p->query("SELECT COUNT(*) visitors,COALESCE(SUM(page_views),0) views FROM analytics_visits WHERE visit_day=CURDATE()")->fetch();$todayVisitors=(int)$r['visitors'];$todayViews=(int)$r['views'];$r=$p->query("SELECT COUNT(*) visitors,COALESCE(SUM(page_views),0) views FROM analytics_visits WHERE visit_day>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetch();$monthVisitors=(int)$r['visitors'];$monthViews=(int)$r['views'];}catch(Throwable $e){}
 $failed=0;$admins=0;$twofa=0;try{$failed=(int)$p->query("SELECT COUNT(*) FROM login_attempts WHERE successful=0 AND created_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();$admins=(int)$p->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();$twofa=(int)$p->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1 AND two_factor_enabled=1")->fetchColumn();}catch(Throwable $e){}
 $media=erased_dir_size(ROOT.'/storage/media');$backups=erased_dir_size(ROOT.'/storage/backups');$cache=erased_dir_size(ROOT.'/storage/cache');$total=erased_dir_size(ROOT);$dbSize=0;try{$q=$p->query("SELECT COALESCE(SUM(data_length+index_length),0) FROM information_schema.tables WHERE table_schema=DATABASE()");$dbSize=(int)$q->fetchColumn();}catch(Throwable $e){}
 $backupFiles=is_dir(ROOT.'/storage/backups')?glob(ROOT.'/storage/backups/*.sql')?:[]:[];$lastBackup=0;foreach($backupFiles as $f)$lastBackup=max($lastBackup,(int)@filemtime($f));
 $mail=trim(setting('smtp_host',''))!==''||setting('mail_transport','mail')==='mail';$homepage=setting('homepage_source','latest')!=='';$proc=erased_proc_stats();$server=erased_server_info();$checks=[$https,$dbOk,$mail,$homepage,$lastBackup>0,PHP_VERSION_ID>=80200,$admins>0];$score=(int)round(array_sum(array_map(fn($v)=>$v?1:0,$checks))/count($checks)*100);
 return compact('https','dbOk','todayVisitors','todayViews','monthVisitors','monthViews','failed','admins','twofa','media','backups','cache','total','dbSize','lastBackup','mail','homepage','proc','server','score');
}
// Real dashboard data, gathered once and shared by every dashboard theme
// renderer (the built-in Blueprint one and any of the admin_theme
// alternates) - so switching themes never means re-deriving the same
// queries or "needs attention" thresholds in more than one place.
function erased_dashboard_view_data(array $user): array{
 $d=erased_dashboard_data();
 $totalPosts=(int)db()->query("SELECT COUNT(*) FROM content WHERE type='post'")->fetchColumn();
 $totalPages=(int)db()->query("SELECT COUNT(*) FROM content WHERE type='page'")->fetchColumn();
 $totalMedia=(int)db()->query("SELECT COUNT(*) FROM media")->fetchColumn();
 $pendingComments=0;
 try{$pendingComments=(int)db()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn();}catch(Throwable $e){}

 $recentContent=[];
 try{$recentContent=db()->query("SELECT content.*, users.display_name, users.email FROM content LEFT JOIN users ON users.id=content.author_id ORDER BY content.updated_at DESC LIMIT 5")->fetchAll();}catch(Throwable $e){}

 $draftRows=[];
 try{$draftRows=db()->query("SELECT id,title,slug,excerpt,status,updated_at FROM content WHERE status='draft' ORDER BY updated_at DESC LIMIT 4")->fetchAll();}catch(Throwable $e){}

 if($draftRows){$resumeItem=$draftRows[0];$alsoInProgress=array_slice($draftRows,1,3);}
 elseif($recentContent){$resumeItem=$recentContent[0];$alsoInProgress=array_slice($recentContent,1,3);}
 else{$resumeItem=null;$alsoInProgress=[];}

 $cpuLoad=$d['proc']['available']?min(100,(int)round($d['proc']['load1']/max(1,$d['proc']['cores'])*100)):0;
 $ramPct=($d['proc']['available']&&$d['proc']['memory_total']>0)?(int)round($d['proc']['memory_used']/$d['proc']['memory_total']*100):0;

 // Needs-attention: same ranked, threshold-based list every renderer draws from.
 $needsAttention=[];
 if($pendingComments>0)$needsAttention[]=['severity'=>'warn','title'=>$pendingComments.' comment'.($pendingComments===1?'':'s').' pending review','subtitle'=>'Awaiting moderation','href'=>'/admin/comments?status=pending'];
 if($d['failed']>5)$needsAttention[]=['severity'=>'warn','title'=>$d['failed'].' failed logins (24h)','subtitle'=>'Worth a look in Security','href'=>'/admin/security'];
 if(!$d['lastBackup'])$needsAttention[]=['severity'=>'warn','title'=>'No backups yet','subtitle'=>'Set one up in Backups','href'=>'/admin/backups'];
 else $needsAttention[]=['severity'=>'ok','title'=>'Backups healthy','subtitle'=>'Last run '.date('M j, H:i',$d['lastBackup']),'href'=>'/admin/backups'];
 if($d['score']<80)$needsAttention[]=['severity'=>'warn','title'=>'Website health at '.$d['score'].'%','subtitle'=>'A few checks still need attention','href'=>'/admin/security'];
 else $needsAttention[]=['severity'=>'ok','title'=>'Website health at '.$d['score'].'%','subtitle'=>($d['https']?'HTTPS secured':'Running over HTTP'),'href'=>'/admin/security'];

 $hour=(int)date('G');
 $daypart=$hour<12?'morning':($hour<18?'afternoon':'evening');

 return [
  'user'=>$user,'daypart'=>$daypart,
  'resume_item'=>$resumeItem,'also_in_progress'=>$alsoInProgress,
  'total_posts'=>$totalPosts,'total_pages'=>$totalPages,'total_media'=>$totalMedia,
  'pending_comments'=>$pendingComments,'recent_content'=>$recentContent,
  'needs_attention'=>$needsAttention,
  'stats'=>$d,'cpu_load'=>$cpuLoad,'ram_pct'=>$ramPct,
 ];
}
function page_options(?int $selected=null,string $blank='— Select —'): string{$o='<option value="">'.e($blank).'</option>';foreach(db()->query("SELECT id,title FROM content WHERE type='page' AND status='published' ORDER BY title") as $p){$id=(int)$p['id'];$o.='<option value="'.$id.'"'.($selected===$id?' selected':'').'>'.e($p['title']).'</option>';}return $o;}
function media_options(?int $selected=null,string $blank='No featured media'): string{$o='<option value="">'.e($blank).'</option>';$groups=['Images'=>[], 'Videos'=>[]];foreach(db()->query("SELECT * FROM media WHERE mime_type LIKE 'image/%' OR mime_type LIKE 'video/%' ORDER BY uploaded_at DESC") as $m){$id=(int)$m['id'];$label=e($m['original_name']);$option='<option value="'.$id.'"'.($selected===$id?' selected':'').'>'.$label.'</option>';if(str_starts_with((string)$m['mime_type'],'video/'))$groups['Videos'][]=$option;else$groups['Images'][]=$option;}foreach($groups as $label=>$options){if($options)$o.='<optgroup label="'.e($label).'">'.implode('',$options).'</optgroup>';}return $o;}
function flashes_html(): string{$h='';foreach(take_flashes() as $f)$h.='<div class="notice '.e($f['type']).'">'.e($f['message']).'</div>';return $h;}

function erased_language_catalog(): array{
 // Installed languages are database-controlled. Only English is permanently built in.
 $builtIn=language_catalog();
 $english=$builtIn['en']??['name'=>'English','native'=>'English','rtl'=>false];
 $catalog=['en'=>$english];
 try{
  foreach(db()->query("SELECT code,name,native_name,is_rtl FROM languages ORDER BY name") as $row){
   $code=strtolower(trim((string)$row['code']));
   if($code===''||$code==='en')continue;
   $catalog[$code]=['name'=>(string)$row['name'],'native'=>(string)$row['native_name'],'rtl'=>(bool)$row['is_rtl']];
  }
 }catch(Throwable $e){}
 return $catalog;
}
function erased_language_select_options(string $selected='en'): string{
 $out='';
 foreach(erased_language_catalog() as $code=>$meta)$out.='<option value="'.e((string)$code).'"'.($selected===$code?' selected':'').'>'.e((string)$meta['native']).' — '.e((string)$meta['name']).'</option>';
 return $out;
}
if (!function_exists('settings_tools_nav')) {
// Blueprint docket - a settings sub-page's sub-tabs (Publishing/SEO/
// Advanced) are always visible under General settings, no toggle
// needed to reveal them - the way a drawing set's index just lists
// what's in the set.
function settings_tools_nav(string $current='/admin/settings', ?string $settingsTab=null): string{
 $html='<nav class="docket-nav" aria-label="Settings">';
 $html.='<p class="docket-label">Site</p>';
 if(can('settings.manage')){
  $onGeneral=$current==='/admin/settings';
  $html.='<details class="docket-dropdown"'.($onGeneral?' open':'').'>';
  $html.='<summary class="'.($onGeneral&&($settingsTab===null||$settingsTab==='general')?'is-current':'').'">General settings</summary>';
  $subTabs=['publishing'=>'Publishing & content','seo'=>'SEO','advanced'=>'Advanced'];
  foreach($subTabs as $tabId=>$tabLabel){
   $subActive=$onGeneral&&$settingsTab===$tabId;
   $html.='<a class="sub'.($subActive?' is-current':'').'" href="/admin/settings?tab='.rawurlencode($tabId).'"'.($subActive?' aria-current="page"':'').'>'.e($tabLabel).'</a>';
  }
  $html.='</details>';
 }
 if(can('settings.manage'))$html.='<a class="'.($current==='/admin/website-profiles'?'is-current':'').'" href="/admin/website-profiles">Website Profiles</a>';
 if(can('packages.manage'))$html.='<a class="'.($current==='/admin/appearance'?'is-current':'').'" href="/admin/appearance">Appearance</a>';
 if(can('languages.manage'))$html.='<a class="'.($current==='/admin/languages'?'is-current':'').'" href="/admin/languages">Languages</a>';
 $html.='<p class="docket-label">System</p>';
 if(can('packages.manage')||can('backups.manage')){
  $href=can('packages.manage')?'/admin/packages':'/admin/backups';
  $html.='<a class="'.($current==='/admin/packages'?'is-current':'').'" href="'.$href.'">Import / Export</a>';
 }
 if(can('security.manage'))$html.='<a class="'.($current==='/admin/security'?'is-current':'').'" href="/admin/security">Security Center</a>';
 if(can('settings.manage'))$html.='<a class="'.($current==='/admin/email'?'is-current':'').'" href="/admin/email">Email Settings</a>';
 // The whole staged-update/diff/rollback flow at /admin/core-update has
 // existed since the CoreUpdate system was built, but this nav never got
 // a link added for it - reachable only if you already knew the exact
 // URL. is-current also matches its /rollback sub-page so the link stays
 // highlighted there.
 if(can('core.update'))$html.='<a class="'.(($current==='/admin/core-update'||$current==='/admin/core-update/rollback')?'is-current':'').'" href="/admin/core-update">Core Update</a>';
 return $html.'</nav>';
}
}
// Every settings-cluster screen (Email/Appearance/Languages/Import/
// Website Profiles/Security Center) shares this one docket shell -
// nav on the left, real page content on the right - instead of each
// route hand-rolling its own wrapper.
if (!function_exists('settings_docket')) {
function settings_docket(string $current, ?string $tab, string $title, string $sub, string $content): string {
    $titleRow = '<div class="title-row"><div><p class="kicker">SHEET '.e(admin_sheet_code($current)).' &middot; SYSTEM</p><h1>'.e($title).'</h1><p>'.e($sub).'</p></div></div>';
    if (($_GET['studio_embed'] ?? '') === '1') {
        // ERASED Studio's tab iframes (?studio_embed=1) already provide
        // this screen's own navigation via Studio's tab bar - the docket's
        // Site/System sidebar would just duplicate it and let you navigate
        // away to an unrelated settings screen from inside an iframe, so
        // it's skipped here rather than shown twice. Content still renders
        // full width, just without the two-column docket shell.
        return $titleRow.$content;
    }
    return $titleRow
        .'<div class="docket"><details class="docket-nav-wrap" open><summary>'.e($title).'</summary>'.settings_tools_nav($current, $tab).'</details><div class="docket-body">'.$content.'</div></div>';
}
}

if (!function_exists('appearance_tools_nav')) {
function appearance_tools_nav(string $current='themes'): string{
 // Same reasoning as settings_docket()'s studio_embed branch - inside an
 // ERASED Studio tab iframe, this sub-nav duplicates Studio's own tab bar,
 // which already covers the same destinations. Website Theme, Homepage
 // Studio, and Navigation were removed from this list entirely (not just
 // hidden here) for the same reason - ERASED Studio's Theme/Layout/
 // Navigation tabs are now the one real way to reach them; Admin Theme and
 // Branding & Logos stay since Studio doesn't cover either.
 if(($_GET['studio_embed']??'')==='1')return '';
 $items=[
  ['themes','/admin/themes','Admin Theme'],
  ['branding','/admin/appearance/branding','Branding & Logos'],
 ];
 $h='<nav class="apptabs" aria-label="Appearance tools">';
 foreach($items as [$key,$href,$label])$h.='<a class="'.($current===$key?'active':'').'" href="'.$href.'"'.($current===$key?' aria-current="page"':'').'>'.e($label).'</a>';
 return $h.'</nav>';
}
}
function erased_base_url(): string{
 $configured=rtrim(setting('site_url',''),'/');if($configured!=='')return $configured;
 $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';return $scheme.'://'.$host;
}
function erased_mail_send(string $to,string $subject,string $html,string $text=''): bool{
 if(!filter_var($to,FILTER_VALIDATE_EMAIL))return false;
 $fromEmail=trim(setting('mail_from_email',''));if(!filter_var($fromEmail,FILTER_VALIDATE_EMAIL))return false;
 $fromName=trim(setting('mail_from_name',setting('site_name','ERASED CMS')));$transport=setting('mail_transport','php');
 $text=$text!==''?$text:trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
 if($transport==='smtp'){
  $host=trim(setting('smtp_host',''));$port=max(1,(int)setting('smtp_port','587'));$secure=setting('smtp_secure','tls');$user=setting('smtp_username','');$pass=setting('smtp_password','');
  if($host==='')return false;$remote=($secure==='ssl'?'ssl://':'').$host;
  $fp=@stream_socket_client($remote.':'.$port,$errno,$errstr,12,STREAM_CLIENT_CONNECT);if(!$fp)return false;stream_set_timeout($fp,12);
  $read=function()use($fp){$out='';while(($line=fgets($fp,515))!==false){$out.=$line;if(strlen($line)<4||$line[3]===' ')break;}return $out;};
  $cmd=function(string $line,array $ok)use($fp,$read){fwrite($fp,$line."\r\n");$r=$read();$code=(int)substr($r,0,3);return in_array($code,$ok,true);};
  $read();$domain=parse_url(erased_base_url(),PHP_URL_HOST)?:'localhost';if(!$cmd('EHLO '.$domain,[250])){fclose($fp);return false;}
  if($secure==='tls'){if(!$cmd('STARTTLS',[220])){fclose($fp);return false;}if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return false;}if(!$cmd('EHLO '.$domain,[250])){fclose($fp);return false;}}
  if($user!==''){if(!$cmd('AUTH LOGIN',[334])||!$cmd(base64_encode($user),[334])||!$cmd(base64_encode($pass),[235])){fclose($fp);return false;}}
  if(!$cmd('MAIL FROM:<'.$fromEmail.'>',[250])||!$cmd('RCPT TO:<'.$to.'>',[250,251])||!$cmd('DATA',[354])){fclose($fp);return false;}
  $boundary='=_ERASED_'.bin2hex(random_bytes(8));$headers="From: ".str_replace(["\r","\n"],'',$fromName)." <{$fromEmail}>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"";
  $message="Subject: ".str_replace(["\r","\n"],'',$subject)."\r\nTo: <{$to}>\r\n{$headers}\r\n\r\n--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$text}\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$html}\r\n--{$boundary}--\r\n.";
  fwrite($fp,$message."\r\n");$r=$read();$ok=(int)substr($r,0,3)===250;$cmd('QUIT',[221]);fclose($fp);return $ok;
 }
 $headers=['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','From: '.str_replace(["\r","\n"],'',$fromName).' <'.$fromEmail.'>'];return @mail($to,$subject,$html,implode("\r\n",$headers));
}
function erased_complete_login(array $user): never{session_regenerate_id(true);
    // v0.8-dev security audit: session_regenerate_id() rotates the session
    // ID but carries $_SESSION's contents forward, including any CSRF token
    // that existed before authentication. Without this, a token an attacker
    // could associate with a victim's pre-login session (shared/kiosk
    // machine, an attacker-controlled page loaded before the victim logs
    // in) would remain valid for state-changing requests after login.
    // csrf() lazily regenerates it on next use.
    unset($_SESSION['csrf']);
    $_SESSION['user_id']=(int)$user['id'];$_SESSION['session_version']=(int)($user['session_version']??0);unset($_SESSION['pending_2fa_user_id'],$_SESSION['pending_2fa_challenge']);register_auth_session((int)$user['id']);try{db()->prepare('INSERT INTO login_history(user_id,email,ip_address,user_agent,successful,failure_reason) VALUES(?,?,?,?,1,NULL)')->execute([(int)$user['id'],(string)$user['email'],erased_client_ip(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);}catch(Throwable $e){}audit('auth.login');redirect('/admin');}
function erased_start_two_factor(array $user): bool{$code=(string)random_int(100000,999999);$hash=hash('sha256',$code);db()->prepare('DELETE FROM login_two_factor_challenges WHERE user_id=? OR expires_at<NOW()')->execute([(int)$user['id']]);db()->prepare('INSERT INTO login_two_factor_challenges(user_id,code_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute([(int)$user['id'],$hash]);$id=(int)db()->lastInsertId();$html='<p>Your ERASED CMS login verification code is:</p><p style="font-size:28px;font-weight:700;letter-spacing:4px">'.e($code).'</p><p>This code expires in 10 minutes.</p>';if(!erased_mail_send((string)$user['email'],'Your ERASED CMS verification code',$html))return false;$_SESSION['pending_2fa_user_id']=(int)$user['id'];$_SESSION['pending_2fa_challenge']=$id;return true;}
function erased_password_help(): string{return '<small class="field-help">'.e(tr('password_help_text')).'</small>';}
function newsletter_form(): string{
 if(setting('newsletter_enabled','1')!=='1')return '';
 $buttonText=trim(setting('newsletter_button_text','Subscribe'));
 if($buttonText==='')$buttonText='Subscribe';
 if(($_GET['subscribed']??'')==='1'){
  return '<div class="newsletter-box"><span class="newsletter-confirm">Subscribed</span></div>';
 }
 $currentPath=(string)($_SERVER['REQUEST_URI']??'/');
 $returnTo=$currentPath.(str_contains($currentPath,'?')?'&':'?').'subscribed=1';
 return '<div class="newsletter-box"><button type="button" class="newsletter-toggle" aria-expanded="false" aria-controls="newsletter-panel">'.e($buttonText).'</button><form id="newsletter-panel" class="newsletter-form" method="post" action="/subscribe" hidden><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="return_to" value="'.e($returnTo).'"><label class="sr-only" for="newsletter-email">Email address</label><input id="newsletter-email" type="email" name="email" placeholder="you@example.com" required><button type="submit">'.e($buttonText).'</button></form></div>';
}

function role_description(string $role): string{
 return match(normalized_role($role)){
  'admin'=>'Full CMS control, including users, settings, security, payments, packages and backups.',
  'editor'=>'Creates, edits, publishes and deletes all content; manages media, publishing and comments.',
  'writer'=>'Creates posts and edits only their own posts. Saves drafts for Editor approval.',
  default=>'Can log in and read permitted content. Cannot create or edit website content.'
 };
}

function can_open_settings_hub(): bool{
 foreach(['settings.manage','packages.manage','import.manage','backups.manage','security.manage'] as $permission)if(can($permission))return true;
 return false;
}

require_once ROOT.'/app/Admin/Components.php';
require_once ROOT.'/app/Admin/DashboardOpsDeck.php';
require_once ROOT.'/app/Admin/PluginAdminNav.php';

function homepage_layout_config(): array{
 $default=['featured','latest_posts','categories','popular_tags','latest_comments','addons'];
 $raw=json_decode(setting('homepage_layout_json',''),true);
 if(!is_array($raw))$raw=[];
 $order=array_values(array_filter($raw['order']??$default,fn($v)=>in_array($v,$default,true)));
 foreach($default as $block)if(!in_array($block,$order,true))$order[]=$block;
 $enabled=array_values(array_filter($raw['enabled']??$default,fn($v)=>in_array($v,$default,true)));if(in_array('categories',$enabled,true)&&!array_key_exists('popular_tags',$raw['regions']??[])&&!in_array('popular_tags',$enabled,true))$enabled[]='popular_tags';if(in_array('addons',$enabled,true)){foreach(['latest_comments'] as $legacyBlock)if(!array_key_exists($legacyBlock,$raw['regions']??[])&&!in_array($legacyBlock,$enabled,true))$enabled[]=$legacyBlock;}
 $regions=is_array($raw['regions']??null)?$raw['regions']:[];
 foreach($regions as $block=>$region)if(!in_array($region,['left','center','right'],true))$regions[$block]=$region==='sidebar'?'right':'center';
 $widths=is_array($raw['widths']??null)?$raw['widths']:[];$left=max(10,min(40,(int)($widths['left']??20)));$right=max(10,min(40,(int)($widths['right']??20)));$center=100-$left-$right;if($center<40){$left=20;$right=20;$center=60;}
 return ['order'=>$order,'enabled'=>$enabled,'regions'=>$regions,'widths'=>['left'=>$left,'center'=>$center,'right'=>$right]];
}
function homepage_builder_preview(): string{
 $cfg=homepage_studio_apply(homepage_layout_config());$labels=['featured'=>'Featured post','latest_posts'=>'Latest posts','categories'=>'Categories','popular_tags'=>'Popular tags','latest_comments'=>'Latest comments','addons'=>'Add-on area'];$h='';
 foreach($cfg['order'] as $block){if(!in_array($block,$cfg['enabled'],true))continue;$region=$cfg['regions'][$block]??(in_array($block,['categories','popular_tags'],true)?'left':(in_array($block,['addons','latest_comments'],true)?'right':'center'));$h.='<div class="home-block region-'.e($region).'" data-block="'.e($block).'"><span class="drag-handle">⋮⋮</span><strong>'.e($labels[$block]).'</strong><small>'.e(ucfirst($region)).'</small></div>';}
 return $h;
}

function homepage_featured_settings(): array{
 $method=setting('featured_selection_method','manual');if(!in_array($method,['manual','newest','category'],true))$method='manual';
 $count=max(1,min(12,(int)setting('featured_posts_count','3')));
 $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',setting('featured_post_ids',''))),fn($v)=>$v>0)));
 return ['enabled'=>setting('featured_posts_enabled','1')==='1','method'=>$method,'count'=>$count,'ids'=>$ids,'category'=>trim(setting('featured_posts_category','')),'exclude_latest'=>setting('featured_exclude_from_latest','1')==='1'];
}
function homepage_featured_posts(): array{
 $cfg=homepage_featured_settings();if(!$cfg['enabled'])return [];$where="type='post' AND status='published' AND (publish_at IS NULL OR publish_at<=NOW())";$args=[];
 if($cfg['method']==='manual'){
  if(!$cfg['ids'])return [];$marks=implode(',',array_fill(0,count($cfg['ids']),'?'));$sql="SELECT * FROM content WHERE $where AND id IN ($marks)";$q=db()->prepare($sql);$q->execute($cfg['ids']);$rows=$q->fetchAll();$by=[];foreach($rows as $r)$by[(int)$r['id']]=$r;$ordered=[];foreach($cfg['ids'] as $id)if(isset($by[$id]))$ordered[]=$by[$id];return array_slice($ordered,0,$cfg['count']);
 }
 if($cfg['method']==='category'&&$cfg['category']!==''){$where.=' AND category=?';$args[]=$cfg['category'];}
 $q=db()->prepare("SELECT * FROM content WHERE $where ORDER BY COALESCE(publish_at,created_at,updated_at) DESC,updated_at DESC LIMIT ".(int)$cfg['count']);$q->execute($args);return $q->fetchAll();
}
function homepage_featured_options(): string{
 $selected=homepage_featured_settings()['ids'];$h='';$rows=db()->query("SELECT id,title,category,status FROM content WHERE type='post' ORDER BY updated_at DESC LIMIT 200")->fetchAll();$byId=[];foreach($rows as $r)$byId[(int)$r['id']]=$r;$ordered=[];foreach($selected as $id)if(isset($byId[$id])){$ordered[]=$byId[$id];unset($byId[$id]);}foreach($rows as $r)if(isset($byId[(int)$r['id']])){$ordered[]=$r;unset($byId[(int)$r['id']]);}foreach($ordered as $r){$label=$r['title'].(!empty($r['category'])?' — '.$r['category']:'').' ['.$r['status'].']';$h.='<option value="'.(int)$r['id'].'"'.(in_array((int)$r['id'],$selected,true)?' selected':'').'>'.e($label).'</option>';}return $h;
}
function homepage_featured_html(array $posts): string{
 if(!$posts)return '';$html='<section class="homepage-featured-section" aria-label="Featured content"><div class="homepage-featured-list">';foreach($posts as $post)$html.='<div class="homepage-featured-card">'.public_article($post).'</div>';return $html.'</div></section>';
}

function social_icon_svg(string $name): string{
 $name=strtolower(trim($name));
 $icons=[
  'github'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 .8a11.2 11.2 0 0 0-3.54 21.82c.56.1.77-.24.77-.54v-2.1c-3.14.68-3.8-1.34-3.8-1.34-.51-1.3-1.25-1.65-1.25-1.65-1.02-.7.08-.68.08-.68 1.13.08 1.72 1.16 1.72 1.16 1 1.72 2.63 1.22 3.27.93.1-.73.39-1.22.71-1.5-2.5-.28-5.13-1.25-5.13-5.58 0-1.23.44-2.24 1.16-3.03-.12-.29-.5-1.44.11-2.99 0 0 .95-.3 3.08 1.16A10.7 10.7 0 0 1 12 6.28c.95 0 1.9.13 2.8.38 2.14-1.46 3.08-1.16 3.08-1.16.62 1.55.23 2.7.12 2.99.72.79 1.16 1.8 1.16 3.03 0 4.34-2.64 5.29-5.15 5.57.4.35.76 1.03.76 2.08v2.91c0 .3.2.65.78.54A11.2 11.2 0 0 0 12 .8Z"/></svg>',
  'x'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 2H22l-6.77 7.74L23.2 22h-6.24l-4.89-6.39L6.5 22H3.38l7.25-8.29L3 2h6.4l4.42 5.84L18.9 2Zm-1.1 17.84h1.73L8.46 4.05H6.61L17.8 19.84Z"/></svg>',
  'youtube'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.12C19.55 3.58 12 3.58 12 3.58s-7.55 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.12c1.85.5 9.4.5 9.4.5s7.55 0 9.4-.5a3 3 0 0 0 2.1-2.12A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.85 12 9.6 15.6Z"/></svg>',
  'facebook'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 22v-9h3l.45-3.5H13.5V7.25c0-1.01.28-1.7 1.74-1.7H17.1V2.42A25 25 0 0 0 14.39 2C11.7 2 9.85 3.64 9.85 6.66V9.5H6.8V13h3.05v9h3.65Z"/></svg>',
  'instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm11.5 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>',
  'telegram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.9 3.3-3.3 16.1c-.25 1.14-.91 1.42-1.85.88l-5.03-3.71-2.43 2.34c-.27.27-.5.5-1.01.5l.36-5.12 9.32-8.42c.41-.36-.09-.56-.63-.2L5.81 12.93.86 11.38c-1.08-.34-1.1-1.08.23-1.6L20.44 2.32c.9-.33 1.68.2 1.46.98Z"/></svg>',
  'discord'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.54 5.34A16.3 16.3 0 0 0 15.44 4l-.5 1.02a15.2 15.2 0 0 0-5.88 0L8.55 4a16.5 16.5 0 0 0-4.1 1.35C1.86 9.2 1.16 12.96 1.5 16.66a16.7 16.7 0 0 0 5.03 2.54l1.22-1.67c-.67-.25-1.31-.57-1.91-.94l.47-.36c3.69 1.7 7.69 1.7 11.33 0l.48.36c-.61.38-1.25.7-1.92.95l1.22 1.66a16.6 16.6 0 0 0 5.03-2.54c.4-4.29-.68-8.01-2.91-11.32ZM8.68 14.4c-1.1 0-2-1.02-2-2.27 0-1.26.88-2.28 2-2.28 1.12 0 2.02 1.03 2 2.28 0 1.25-.89 2.27-2 2.27Zm6.64 0c-1.1 0-2-1.02-2-2.27 0-1.26.88-2.28 2-2.28 1.12 0 2.02 1.03 2 2.28 0 1.25-.88 2.27-2 2.27Z"/></svg>',
  'mastodon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.58 7.19c0-4.46-2.93-5.76-2.93-5.76C17.17.75 14.62.47 12.03.45h-.06C9.38.47 6.83.75 5.35 1.43c0 0-2.93 1.3-2.93 5.76 0 1.02-.02 2.25.01 3.56.12 4.36.8 8.66 4.84 9.73 1.86.49 3.45.59 4.73.52 2.32-.13 3.62-.83 3.62-.83l-.08-1.68s-1.66.52-3.52.46c-1.84-.06-3.79-.2-4.09-2.48a4.7 4.7 0 0 1-.04-.64s1.81.44 4.11.54c1.41.06 2.73-.08 4.08-.24 2.59-.31 4.85-1.9 5.13-3.35.44-2.28.4-5.57.4-5.57l-.03-.02Zm-3.45 5.75h-2.15V7.67c0-1.11-.47-1.68-1.42-1.68-1.05 0-1.58.68-1.58 2.02v2.89h-2.13V8.01c0-1.34-.53-2.02-1.58-2.02-.95 0-1.42.57-1.42 1.68v5.27H5.7V7.51c0-1.11.28-1.99.85-2.65.59-.66 1.36-1 2.32-1 1.11 0 1.95.43 2.5 1.28L12 6.2l.63-1.06c.55-.85 1.39-1.28 2.5-1.28.96 0 1.73.34 2.32 1 .57.66.85 1.54.85 2.65l-.17 5.43Z"/></svg>',
  'bluesky'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 10.8C10.95 8.75 8.1 4.93 5.45 3 2.9 1.14 1.93 1.46 1.3 1.75.57 2.08.45 3.2.45 3.86c0 .66.36 5.4.6 6.19.8 2.65 3.66 3.54 6.28 3.25-4.57.68-8.63 2.36-3.3 8.29 5.86 6.07 8.03-1.3 8.67-3.22.64 1.92 2.3 9.16 8.57 3.22 4.72-4.74 1.3-7.61-3.79-8.29 2.62.29 5.48-.6 6.28-3.25.24-.79.6-5.53.6-6.19 0-.66-.12-1.78-.85-2.11-.63-.29-1.6-.61-4.15 1.25C15.9 4.93 13.05 8.75 12 10.8Z"/></svg>',
  'linkedin'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.98 3.5a2.49 2.49 0 1 1 0 4.98 2.49 2.49 0 0 1 0-4.98ZM2.8 9.8h4.35V22H2.8V9.8Zm6.78 0h4.17v1.67h.06c.58-1.1 2-2.26 4.12-2.26 4.4 0 5.22 2.9 5.22 6.67V22H18.8v-5.43c0-1.3-.02-2.96-1.8-2.96-1.81 0-2.09 1.41-2.09 2.87V22H9.58V9.8Z"/></svg>',
  'rss'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3a17 17 0 0 1 17 17h-3A14 14 0 0 0 4 6V3Zm0 6a11 11 0 0 1 11 11h-3a8 8 0 0 0-8-8V9Zm2 8a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z"/></svg>',
  'tiktok'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 2c.4 2.2 2 3.9 4.2 4.2v3.1c-1.6 0-3.1-.5-4.2-1.4v6.9a6.3 6.3 0 1 1-6.3-6.3c.3 0 .6 0 .9.05v3.2a3.1 3.1 0 1 0 2.2 3V2h3.2Z"/></svg>',
  'reddit'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12a2 2 0 0 0-3.4-1.4 9.8 9.8 0 0 0-4.9-1.5l.8-3.7 2.6.6a1.5 1.5 0 1 0 .2-1L14.4 4a.5.5 0 0 0-.6.4l-1 4.7a9.8 9.8 0 0 0-5 1.5A2 2 0 1 0 5.4 14a3.6 3.6 0 0 0 0 .8c0 3 3.9 5.4 8.6 5.4s8.6-2.4 8.6-5.4a3.6 3.6 0 0 0 0-.8A2 2 0 0 0 22 12ZM8.5 14a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm7.7 3.4a5.6 5.6 0 0 1-4.2 1.4 5.6 5.6 0 0 1-4.2-1.4.5.5 0 0 1 .7-.7 4.6 4.6 0 0 0 3.5 1.1 4.6 4.6 0 0 0 3.5-1.1.5.5 0 0 1 .7.7Zm-.2-1.9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/></svg>',
  'pinterest'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.6 19.3c0-.8 0-1.7.2-2.5l1.4-6S9.6 12 9.6 10.7c0-1.9 1.1-3.4 2.5-3.4 1.2 0 1.8.9 1.8 2 0 1.2-.8 3-1.2 4.6-.3 1.4.7 2.5 2 2.5 2.5 0 4.2-3.2 4.2-7 0-2.9-2-5-5.5-5-4 0-6.5 3-6.5 6.3 0 1.1.4 1.9 1 2.5.1.1.1.2.1.4l-.4 1.5c0 .2-.2.3-.4.2-1.4-.6-2-2.1-2-3.9 0-2.9 2.5-6.4 7.4-6.4 4 0 6.6 2.9 6.6 6 0 4.1-2.2 7.1-5.5 7.1-1.1 0-2.1-.6-2.5-1.3l-.7 2.7a10 10 0 0 1-2.2 3.9A10 10 0 1 0 12 2Z"/></svg>',
  'twitch'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.3 2 2.5 6.5v13h4.6V22l3.6-2.5h3l6-6V2H4.3Zm1.5 2h11.6v9.3l-3.6 3.6h-3.5l-3 2.1v-2.1H5.8V4Zm5.7 8.4h2V6.7h-2v5.7Zm5 0h2V6.7h-2v5.7Z"/></svg>',
  'whatsapp'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5l.4-.5c.1-.1.1-.2.2-.4.1-.1 0-.3 0-.4-.1-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2s.8 2.3.9 2.5c.1.2 1.6 2.5 4 3.5.6.2 1 .4 1.3.5.6.2 1.1.1 1.5.1.5-.1 1.4-.6 1.6-1.1.2-.5.2-.9.1-1Z"/></svg>',
  'link'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.59 13.41a2 2 0 0 0 2.82 0l4-4a2 2 0 1 0-2.82-2.82l-1.17 1.17-1.42-1.42 1.17-1.17a4 4 0 0 1 5.66 5.66l-4 4a4 4 0 0 1-5.66 0l-.58-.58 1.42-1.42.58.58Zm2.82-2.82a2 2 0 0 0-2.82 0l-4 4a2 2 0 1 0 2.82 2.82l1.17-1.17L12 17.66l-1.17 1.17a4 4 0 0 1-5.66-5.66l4-4a4 4 0 0 1 5.66 0l.58.58-1.42 1.42-.58-.58Z"/></svg>'
 ];
 return $icons[$name]??$icons['link'];
}
/** @return array<string,string> platform key => display label, in display order */
function erased_social_platforms(): array{
 return [
  'github'=>'GitHub','x'=>'X (Twitter)','youtube'=>'YouTube','facebook'=>'Facebook',
  'instagram'=>'Instagram','linkedin'=>'LinkedIn','tiktok'=>'TikTok','reddit'=>'Reddit',
  'pinterest'=>'Pinterest','twitch'=>'Twitch','discord'=>'Discord','telegram'=>'Telegram',
  'mastodon'=>'Mastodon','bluesky'=>'Bluesky','whatsapp'=>'WhatsApp',
 ];
}
/** @return array<string,array{0:string,1:string}> font key => [display label, CSS font stack] */
function erased_typography_fonts(): array{
 return [
  'system'=>['System UI (default)',"system-ui,-apple-system,'Segoe UI',Roboto,sans-serif"],
  'serif'=>['Serif',"Georgia,'Times New Roman',serif"],
  'humanist'=>['Humanist sans',"'Helvetica Neue',Arial,sans-serif"],
  'rounded'=>['Rounded sans',"'Trebuchet MS',Verdana,sans-serif"],
  'mono'=>['Monospace',"'JetBrains Mono','Courier New',monospace"],
 ];
}
function erased_typography_font_stack(string $key): string{
 $fonts=erased_typography_fonts();
 return $fonts[$key][1]??$fonts['system'][1];
}
function homepage_follow_links(): string{
 $links='';
 $platforms=erased_social_platforms();
 for($n=1;$n<=8;$n++){
  $platform=trim(setting('social_link_'.$n.'_platform',''));
  $url=trim(setting('social_link_'.$n.'_url',''));
  if($platform===''||$url===''||!isset($platforms[$platform]))continue;
  $label=$platforms[$platform];
  $links.='<a class="social-icon" href="'.e($url).'" target="_blank" rel="noopener" aria-label="'.e($label).'" title="'.e($label).'">'.social_icon_svg($platform).'<span class="sr-only">'.e($label).'</span></a>';
 }
 return $links;
}
function announcement_bar_html(): string{
 if(setting('announcement_enabled','0')!=='1')return '';
 $audience=setting('announcement_audience','all');$user=current_user();$role=(string)($user['role']??'guest');
 if($audience==='guests'&&logged_in())return '';if($audience==='users'&&!logged_in())return '';if($audience==='admins'&&$role!=='admin')return '';
 $now=time();$start=trim(setting('announcement_start',''));$end=trim(setting('announcement_end',''));
 if($start!==''&&($t=strtotime($start))!==false&&$now<$t)return '';if($end!==''&&($t=strtotime($end))!==false&&$now>$t)return '';
 $message=trim(setting('announcement_message',''));if($message==='')return '';
 $mode=in_array(setting('announcement_mode','marquee'),['static','marquee'],true)?setting('announcement_mode','marquee'):'marquee';
 $bg=setting('announcement_bg','#101a14');$color=setting('announcement_color','#2dfc98');$speed=max(5,min(120,(int)setting('announcement_speed','25')));
 $icon=trim(setting('announcement_icon','📢'));$url=trim(setting('announcement_url',''));
 $content=($icon!==''?'<span class="announcement-icon">'.e($icon).'</span> ':'').'<span>'.e($message).'</span>';
 if($url!=='')$content='<a href="'.e($url).'"'.(setting('announcement_new_tab','0')==='1'?' target="_blank" rel="noopener"':'').'>'.$content.'</a>';
 $close=setting('announcement_close','0')==='1'?'<button class="announcement-close" type="button" aria-label="Close announcement" onclick="this.parentElement.remove()">×</button>':'';
 return '<div class="announcement-bar announcement-'.$mode.'" style="--announcement-bg:'.e($bg).';--announcement-color:'.e($color).';--announcement-speed:'.$speed.'s"><div class="announcement-track"><div class="announcement-message">'.$content.'</div></div>'.$close.'</div>';
}
function language_switcher(): string{
 if(!installed())return '';
 $enabled=setting('show_language_switcher','1')==='1'||setting('nav_show_language','1')==='1';
 if(!$enabled)return '';
 $current=current_language(false);
 $labels=['en'=>'🇬🇧','en-us'=>'🇺🇸','en-gb'=>'🇬🇧','no'=>'🇳🇴','nb'=>'🇳🇴','nn'=>'🇳🇴','lt'=>'🇱🇹','lv'=>'🇱🇻','et'=>'🇪🇪','pl'=>'🇵🇱','ru'=>'🇷🇺','uk'=>'🇺🇦','ua'=>'🇺🇦','de'=>'🇩🇪','fr'=>'🇫🇷','es'=>'🇪🇸','it'=>'🇮🇹','pt'=>'🇵🇹','pt-br'=>'🇧🇷','nl'=>'🇳🇱','sv'=>'🇸🇪','da'=>'🇩🇰','fi'=>'🇫🇮','is'=>'🇮🇸','cs'=>'🇨🇿','sk'=>'🇸🇰','hu'=>'🇭🇺','ro'=>'🇷🇴','bg'=>'🇧🇬','el'=>'🇬🇷','tr'=>'🇹🇷','ar'=>'🇸🇦','he'=>'🇮🇱','zh'=>'🇨🇳','zh-tw'=>'🇹🇼','ja'=>'🇯🇵','ko'=>'🇰🇷','hi'=>'🇮🇳','id'=>'🇮🇩','vi'=>'🇻🇳','th'=>'🇹🇭','sr'=>'🇷🇸','hr'=>'🇭🇷','sl'=>'🇸🇮','ga'=>'🇮🇪'];
 $active=active_languages();
 if(count($active)<1)return '';
 $options='';
 foreach($active as $lang){
  $code=(string)$lang['code'];
  $label=$labels[$code]??strtoupper(substr($code,0,2));
  $options.='<option value="'.e($code).'" '.($code===$current?'selected':'').'>'.e($label).'</option>';
 }
 $uri=$_SERVER['REQUEST_URI']??'/';
 return '<form class="language-switcher" method="post" action="/language/set"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="return_to" value="'.e($uri).'"><label><span class="sr-only">'.e(tr('language')).'</span><select aria-label="'.e(tr('language')).'" name="language" onchange="this.form.submit()">'.$options.'</select></label></form>';
}
function ui_translation_script(bool $admin): string {
 $group=$admin?'admin':'site';
 $code=current_language($admin);
 if($code==='en')return '';
 $selected=translation_data($code,$group);
 $english=translation_data('en',$group);
 $map=[];
 foreach($english as $key=>$source){
  $target=(string)($selected[$key]??'');
  if($target!==''&&$target!==$source){
   $map[(string)$source]=$target;
   $map[strtolower((string)$source)]=$target;
   $map[ucfirst((string)$source)]=$target;
   $map[strtoupper((string)$source)]=$target;
  }
 }
 if(!$map)return '';
 return '<script>(function(){const M='.json_encode($map,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';const exact=s=>{if(!s)return null;const t=s.trim();return M[s]||M[t]||M[t.toLowerCase()]||null;};const skip=el=>el&&el.closest("[translate=\'no\'],.notranslate");function walk(root){if(!root)return;const w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);let n;while(n=w.nextNode()){if(!n.parentElement||["SCRIPT","STYLE","TEXTAREA","CODE","PRE"].includes(n.parentElement.tagName)||skip(n.parentElement))continue;const raw=n.nodeValue,trim=raw.trim(),v=exact(trim);if(v)n.nodeValue=raw.replace(trim,v);}root.querySelectorAll("input[placeholder],textarea[placeholder],button[title],a[title],[aria-label]").forEach(el=>{if(skip(el))return;["placeholder","title","aria-label"].forEach(a=>{const x=el.getAttribute(a);if(x){const v=exact(x);if(v)el.setAttribute(a,v);}});});root.querySelectorAll("option").forEach(el=>{if(skip(el))return;const x=el.textContent.trim(),v=exact(x);if(v)el.textContent=v;});}function run(){walk(document.body);}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}setTimeout(run,250);})();</script>';
}
function v100_navigation_items(): array{
 $raw=json_decode(setting('navigation_items_json',''),true);
 $items=is_array($raw)&&$raw?$raw:[
  ['id'=>'home','type'=>'system','label'=>setting('nav_home_label','Home'),'url'=>'/','enabled'=>setting('nav_show_home','1')==='1','parent'=>''],
  ['id'=>'posts','type'=>'system','label'=>setting('nav_posts_label','Posts'),'url'=>'/posts','enabled'=>setting('nav_show_posts','1')==='1','parent'=>'']
 ];
 $showGallery=setting('gallery_show_in_navigation','1')==='1';
 $normalized=[];$galleryFound=false;
 foreach($items as $item){
  if(!is_array($item))continue;
  $isGallery=(string)($item['id']??'')==='gallery'||rtrim((string)($item['url']??''),'/')==='/gallery';
  if($isGallery){
   if($galleryFound)continue;
   $galleryFound=true;
   $item['id']='gallery';
   $item['type']='system';
   $item['label']='Gallery';
   $item['url']='/gallery';
   $item['enabled']=$showGallery;
  }
  $normalized[]=$item;
 }
 if(!$galleryFound&&$showGallery){
  $gallery=['id'=>'gallery','type'=>'system','label'=>'Gallery','url'=>'/gallery','enabled'=>true,'parent'=>''];
  $insertAt=0;
  foreach($normalized as $index=>$item){
   if((string)($item['id']??'')==='home'||(string)($item['url']??'')==='/')$insertAt=$index+1;
  }
  array_splice($normalized,$insertAt,0,[$gallery]);
 }
 return array_values($normalized);
}
function v100_public_navigation(): string{
 $items=v100_navigation_items();$byParent=[];
 foreach($items as $item){if(empty($item['enabled']))continue;$parent=(string)($item['parent']??'');$byParent[$parent][]=$item;}
 $render=function(array $item) use (&$render,&$byParent): string{
  $id=(string)($item['id']??'');$url=(string)($item['url']??'#');$label=(string)($item['label']??'Link');$target=!empty($item['new_tab'])?' target="_blank" rel="noopener"':'';
  $children=$byParent[$id]??[];$h='<a href="'.e($url).'"'.$target.'>'.e($label).'</a>';
  if($children){$sub='';foreach($children as $child)$sub.=$render($child);$h='<span class="nav-dropdown"><span class="nav-dropdown-label">'.e($label).' ▾</span><span class="nav-dropdown-menu">'.$sub.'</span></span>';}
  return $h;
 };
 $h='';foreach($byParent['']??[] as $item)$h.=$render($item);return $h;
}
function v100_page_template(array $page,string $article): string{
 $template=(string)($page['page_template']??'sidebar');
 if($template==='full')return '<div class="page-template-full">'.$article.'</div>';
 if($template==='landing')return '<div class="page-template-landing">'.$article.'</div>';
 return $article;
}
/**
 * Single ordered source of truth for every core admin rail entry, keyed by
 * a stable internal key (not by code) - both the sidebar renderer
 * (admin_core_nav_button()) and admin_sheet_code()'s breadcrumb/kicker
 * lookups read from this same list, so a sheet code is always computed from
 * what's actually visible right now (current user's permissions, current
 * installed-package state) instead of a hand-typed literal duplicated
 * across files that had to be kept in sync by hand every time nav changed -
 * exactly what made Payments/ERASED Studio/Settings need manual
 * renumbering more than once as they were added and reordered.
 *
 * 'match' is a list of path prefixes checked longest-first (in
 * admin_sheet_code()), so a more specific route (ERASED Studio's own
 * /admin/appearance/homepage, the page its "Layout" tab embeds) wins over a
 * broader one that would otherwise also match (Settings' own
 * /admin/appearance catch-all).
 *
 * 'dashboard' carries 'no_code' => true - it lives in the rail-mark brand
 * header (logo + "Dashboard" link) instead of a numbered rail-item, so it
 * neither consumes a number nor shows one; admin_core_nav_codes() skips it
 * entirely and admin_sheet_code() returns '' for it rather than a stale/
 * colliding code.
 *
 * @return array<string,array{group:?string,href:string,match:list<string>,exact?:bool,label:string,visible:bool,no_code?:bool}>
 */
function admin_core_nav_entries(): array {
    return [
        'dashboard' => ['group' => null, 'href' => '/admin', 'match' => ['/admin'], 'exact' => true, 'label' => tr('dashboard', 'admin'), 'visible' => true, 'no_code' => true],
        'posts' => ['group' => 'Content', 'href' => '/admin/content?type=post', 'match' => ['/admin/content'], 'label' => 'Posts', 'visible' => can('content.view')],
        'pages' => ['group' => 'Content', 'href' => '/admin/content?type=page', 'match' => ['/admin/content'], 'label' => 'Pages', 'visible' => can('content.edit.all')],
        'publishing' => ['group' => 'Content', 'href' => '/admin/publishing', 'match' => ['/admin/publishing'], 'label' => tr('publishing', 'admin'), 'visible' => can('publishing.manage')],
        'media' => ['group' => 'Content', 'href' => '/admin/media', 'match' => ['/admin/media'], 'label' => tr('media', 'admin'), 'visible' => can('media.manage')],
        'galleries' => ['group' => 'Content', 'href' => '/admin/galleries', 'match' => ['/admin/galleries'], 'label' => 'Gallery', 'visible' => can('media.manage')],
        'comments' => ['group' => 'Content', 'href' => '/admin/comments', 'match' => ['/admin/comments'], 'label' => tr('comments', 'admin'), 'visible' => can('comments.manage')],
        'users' => ['group' => 'Site', 'href' => '/admin/users', 'match' => ['/admin/users', '/admin/memberships'], 'label' => tr('users', 'admin'), 'visible' => can('users.manage') || can('memberships.manage')],
        'payments' => ['group' => 'Site', 'href' => '/admin/payments', 'match' => ['/admin/payments'], 'label' => tr('payments', 'admin'), 'visible' => can('payments.manage') && !erased_package_active('erased.payments')],
        'erased_studio' => ['group' => 'Site', 'href' => '/admin/studio', 'match' => ['/admin/studio', '/admin/appearance/homepage', '/admin/layout-studio'], 'label' => 'ERASED Studio', 'visible' => can('packages.manage')],
        'settings' => ['group' => 'System', 'href' => '/admin/settings', 'match' => ['/admin/settings', '/admin/appearance', '/admin/themes', '/admin/languages', '/admin/packages', '/admin/security', '/admin/email', '/admin/backups'], 'label' => tr('settings', 'admin'), 'visible' => can_open_settings_hub()],
    ];
}

/** Positionally assigns "A-xx" codes to every visible core entry, keyed by entry key - memoized per request since permission/install state can't change mid-request. */
function admin_core_nav_codes(): array {
    static $codes = null;
    if ($codes === null) {
        $codes = [];
        $n = 0;
        foreach (admin_core_nav_entries() as $key => $entry) {
            if (!$entry['visible'] || !empty($entry['no_code'])) continue;
            $n++;
            $codes[$key] = 'A-' . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
        }
    }
    return $codes;
}

function admin_sheet_code(string $requestPath): string {
    $bestKey = null; $bestLen = -1; $bestNoCode = false;
    foreach (admin_core_nav_entries() as $key => $entry) {
        if (!$entry['visible']) continue;
        foreach ($entry['match'] as $prefix) {
            $isMatch = !empty($entry['exact']) ? $requestPath === $prefix : str_starts_with($requestPath, $prefix);
            if ($isMatch && strlen($prefix) > $bestLen) { $bestKey = $key; $bestLen = strlen($prefix); $bestNoCode = !empty($entry['no_code']); }
        }
    }
    if ($bestNoCode) return '';
    $codes = admin_core_nav_codes();
    return $bestKey !== null ? ($codes[$bestKey] ?? 'A-01') : 'A-01';
}

/** Renders one core rail button by its registry key, or '' if not visible - $active overrides the entry's own prefix-match logic for the one case (Posts vs Pages sharing /admin/content) that needs extra context beyond the path alone. */
function admin_core_nav_button(string $key, string $requestPath, ?bool $active = null): string {
    $entry = admin_core_nav_entries()[$key] ?? null;
    if ($entry === null || !$entry['visible']) return '';
    $code = admin_core_nav_codes()[$key] ?? '';
    if ($active === null) {
        $active = !empty($entry['exact'])
            ? in_array($requestPath, $entry['match'], true)
            : array_reduce($entry['match'], static fn(bool $carry, string $prefix) => $carry || str_starts_with($requestPath, $prefix), false);
    }
    return '<button type="button" class="rail-item ' . ($active ? 'is-active' : '') . '" onclick="location.href=\'' . e_js_str($entry['href']) . '\'"><span class="rail-item-code">' . e($code) . '</span>' . e($entry['label']) . '</button>';
}
// Plain sheet reference - a stamped code plus the page's location in the
// nav, nothing more. No dropdown, no popup menu: it answers "where am I",
// it isn't a second navigation surface.
function admin_breadcrumb_html(string $requestPath, string $title): string {
    $section = null; $sectionUrl = null;
    if (str_starts_with($requestPath, '/admin/settings') || str_starts_with($requestPath, '/admin/appearance') || str_starts_with($requestPath, '/admin/themes') || str_starts_with($requestPath, '/admin/languages') || str_starts_with($requestPath, '/admin/packages') || str_starts_with($requestPath, '/admin/security') || str_starts_with($requestPath, '/admin/email') || str_starts_with($requestPath, '/admin/backups')) {
        $section = 'Settings'; $sectionUrl = '/admin/settings';
    } elseif (str_starts_with($requestPath, '/admin/content')) {
        $section = 'Content'; $sectionUrl = '/admin/content';
    } elseif (str_starts_with($requestPath, '/admin/media') || str_starts_with($requestPath, '/admin/galleries')) {
        $section = 'Media'; $sectionUrl = '/admin/media';
    }
    $crumbs = [['label' => 'Dashboard', 'url' => '/admin']];
    if ($section !== null && $section !== $title) {
        $crumbs[] = ['label' => $section, 'url' => $sectionUrl];
    }
    if ($requestPath !== '/admin') {
        $crumbs[] = ['label' => $title, 'url' => null];
    }
    $last = count($crumbs) - 1;
    $sheetCode = admin_sheet_code($requestPath);
    $html = '<nav class="stamp" aria-label="Location">' . ($sheetCode !== '' ? '<span class="sheet-no mono">' . e($sheetCode) . '</span>' : '');
    foreach ($crumbs as $i => $c) {
        if ($i > 0) {
            $html .= '<span class="sep">/</span>';
        }
        if ($i === $last) {
            $html .= '<b aria-current="page">' . e($c['label']) . '</b>';
        } else {
            $html .= '<a href="' . e((string)$c['url']) . '">' . e($c['label']) . '</a>';
        }
    }
    $html .= '</nav>';
    return $html;
}

function admin_header_actions_html(string $requestPath, string $contentType = ''): string {
    $html = '';

    if (str_starts_with($requestPath, '/admin/publishing')) {
        $section = (string)($_GET['section'] ?? 'content');
        $html .= '<a href="/admin/publishing?section=content" class="header-action-btn' . ($section === 'content' ? ' is-active' : '') . '">Content Metadata</a>';
        $html .= '<a href="/admin/publishing?section=schedule" class="header-action-btn' . ($section === 'schedule' ? ' is-active' : '') . '">Scheduling</a>';
        $html .= '<a href="/admin/publishing?section=access" class="header-action-btn' . ($section === 'access' ? ' is-active' : '') . '">Access Rules</a>';
        $html .= '<a href="/admin/publishing?section=seo" class="header-action-btn' . ($section === 'seo' ? ' is-active' : '') . '">SEO</a>';
        $html .= '<a href="/admin/publishing?section=revisions" class="header-action-btn' . ($section === 'revisions' ? ' is-active' : '') . '">Revisions</a>';
        $html .= '<a href="/admin/publishing?section=redirects" class="header-action-btn' . ($section === 'redirects' ? ' is-active' : '') . '">Redirects</a>';
    } elseif (str_starts_with($requestPath, '/admin/media')) {
        $type = (string)($_GET['type'] ?? 'all');
        $counts = ['images' => 0, 'videos' => 0, 'other' => 0];
        try {
            $mRows = db()->query("SELECT mime_type FROM media")->fetchAll();
            foreach ($mRows as $m) {
                $mime = (string)($m['mime_type'] ?? '');
                if (str_starts_with($mime, 'image/')) $counts['images']++;
                elseif (str_starts_with($mime, 'video/')) $counts['videos']++;
                else $counts['other']++;
            }
        } catch (Throwable) {}
        $allCount = $counts['images'] + $counts['videos'] + $counts['other'];
        $html .= '<a href="/admin/media" class="header-action-btn' . ($type === 'all' ? ' is-active' : '') . '">All Media (' . $allCount . ')</a>';
        $html .= '<a href="/admin/media?type=images" class="header-action-btn' . ($type === 'images' ? ' is-active' : '') . '">▧ Pictures (' . $counts['images'] . ')</a>';
        $html .= '<a href="/admin/media?type=videos" class="header-action-btn' . ($type === 'videos' ? ' is-active' : '') . '">▶ Videos (' . $counts['videos'] . ')</a>';
        $html .= '<a href="/admin/media?type=other" class="header-action-btn' . ($type === 'other' ? ' is-active' : '') . '">••• Other Files (' . $counts['other'] . ')</a>';
    } elseif (str_starts_with($requestPath, '/admin/galleries')) {
        $html .= '<a href="/gallery" target="_blank" class="header-action-btn">🌐 View Gallery</a>';
        $html .= '<a href="/admin/galleries/new" class="header-action-btn btn-primary">＋ New Gallery</a>';
    } elseif (str_starts_with($requestPath, '/admin/comments')) {
        // No topbar filter buttons here - the Moderation Queue panel's own
        // All/Approved/Pending/Spam tabs already cover this, with live counts.
        // Having both was the same duplicate-navigation mistake the
        // settings/appearance pages already got fixed for below.
    } elseif (str_starts_with($requestPath, '/admin/users')) {
        $section = (string)($_GET['section'] ?? 'accounts');
        $html .= '<a href="/admin/users" class="header-action-btn' . ($section === 'accounts' || $section === 'list' || $section === '' ? ' is-active' : '') . '">User Accounts</a>';
        $html .= '<a href="/admin/users?section=create" class="header-action-btn btn-primary' . ($section === 'create' ? ' is-active' : '') . '">＋ Create Account</a>';
        $html .= '<a href="/admin/users?section=roles" class="header-action-btn' . ($section === 'roles' ? ' is-active' : '') . '">Account Levels</a>';
    } elseif (str_starts_with($requestPath, '/admin/content/new')) {
        $html .= '<a href="/admin/content/new?type=post" class="header-action-btn btn-primary' . ($contentType !== 'page' ? ' is-active' : '') . '">📝 New Post</a>';
        $html .= '<a href="/admin/content/new?type=page" class="header-action-btn btn-primary' . ($contentType === 'page' ? ' is-active' : '') . '">📄 New Page</a>';
    } elseif (str_starts_with($requestPath, '/admin/content')) {
        if ($contentType === 'page') {
            $html .= '<a href="/admin/content/new?type=page" class="header-action-btn btn-primary">＋ New Page</a>';
        } else {
            $html .= '<a href="/admin/content/new?type=post" class="header-action-btn btn-primary">＋ New Post</a>';
        }
    } elseif (str_starts_with($requestPath, '/admin/settings') || str_starts_with($requestPath, '/admin/appearance') || str_starts_with($requestPath, '/admin/themes') || str_starts_with($requestPath, '/admin/languages') || str_starts_with($requestPath, '/admin/packages') || str_starts_with($requestPath, '/admin/security') || str_starts_with($requestPath, '/admin/email') || str_starts_with($requestPath, '/admin/backups') || str_starts_with($requestPath, '/admin/website-profiles') || str_starts_with($requestPath, '/admin/payments') || str_starts_with($requestPath, '/admin/developer') || str_starts_with($requestPath, '/admin/studio')) {
        // No topbar action buttons here - settings_tools_nav() already renders
        // the same set of destinations inside the page body. Having both was
        // the duplicate navigation the redesign removed. /admin/studio (ERASED
        // Studio) gets the same treatment for the same reason - it's the
        // consolidated hub (Layout, Navigation, Theme, Typography & SEO,
        // Media all as tabs of one page), so the old standalone "Layout
        // Studio" nav item was removed entirely rather than just hidden here.
    } elseif ($requestPath === '/admin') {
        // No topbar action buttons here either - the Dashboard's own
        // title-row already has Upload media / New post actions.
    } elseif (function_exists('plugin_admin_surface') && plugin_admin_surface()->router()->match('GET', $requestPath) !== null) {
        // Plugin-registered admin page (e.g. erased.commerce, example-module) -
        // it renders its own in-body actions already, and the generic New
        // Post / ERASED Studio buttons don't apply to a plugin's own screens.
    } else {
        $html .= '<a href="/admin/content/new?type=post" class="header-action-btn btn-primary">＋ New Post</a>';
        $html .= '<a href="/admin/studio" class="header-action-btn">🎨 ERASED Studio</a>';
    }

    return $html;
}

function layout(string $title,string $body,bool $admin=false): void{
 if($admin&&($_GET['studio_embed']??'')==='1'){
  $adminTheme=installed()?setting('admin_theme','dark-green'):'dark-green';
  $accent=installed()?setting('theme_accent','#2dfc98'):'#2dfc98';
  $custom=installed()?setting('custom_css'):'';
  $themeSlug=in_array($adminTheme,['dark','dark-green','dark-grey','light-grey','ops-deck'],true)?$adminTheme:'dark-green';
  $adminThemePkg=erased_resolve_theme_package($adminTheme,'admin');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><link rel="stylesheet" href="/assets/admin-design-system.css?v=8.58"><style>:root{--accent:'.e($accent).';--theme-accent:'.e($accent).';--green:'.e($accent).'}body{margin:0}'.str_replace(['</style','<script','</script'],['\3C/style','\3Cscript','\3C/script'],$custom).'</style>'.($adminThemePkg?'<link rel="stylesheet" href="'.e($adminThemePkg['css_href']).'">':'').'</head><body class="admin-area" data-theme="'.e($themeSlug).'"><div style="padding:20px">'.flashes_html().$body.'</div>'
   /* ERASED Studio embeds this page chrome-free inside an iframe (see /admin/studio) -
      the media browser's filter/size-toggle JS lives in the shared admin script tail
      this branch intentionally skips, so it's duplicated here rather than pulling in
      that whole tail just for two one-line functions. */
   .'<script>function filterMedia(value){const q=value.toLowerCase().trim();document.querySelectorAll(".media-tile").forEach(tile=>{tile.style.display=!q||tile.dataset.name.includes(q)?"flex":"none";});}
function setMediaSize(size){const browser=document.getElementById("media-browser");if(!browser)return;browser.classList.toggle("media-small",size==="small");localStorage.setItem("erasedMediaSize",size);}</script></body></html>';
  return;
 }
 $isInstalled=installed();$site=$isInstalled?setting('site_name','ERASED CMS'):'ERASED CMS';$seo=$isInstalled?setting('seo_title'):'';$full=e($title).' — '.e($site);
 $brandingMode=installed()?setting('branding_mode','builtin'):'builtin';$themeForBrand=installed()?setting('admin_theme','dark-green'):'dark-green';$isLightBrand=in_array($themeForBrand,['light-grey'],true);
 $logo='';$adminLogo='';$logoUrl='';
 if($brandingMode==='custom'){$logoSetting=$isLightBrand?'logo_light_media_id':'logo_dark_media_id';$id=(int)setting($logoSetting,setting('logo_media_id','0'));if($id&&($m=media_by_id($id)))$logoUrl=media_url($m);}
 if($logoUrl==='')$logoUrl=$isLightBrand?'/assets/erased-logo-light.svg':'/assets/erased-logo-dark.svg';
 $logo='<img class="site-logo" src="'.e($logoUrl).'" alt="'.e($site).'">';$adminLogo='<img class="admin-brand-logo" src="'.e($logoUrl).'" alt="">';
 $showTitle=setting('logo_show_title','0')==='1';$brandText=$showTitle?'<span class="site-title">'.e($site).'</span>':'';
 $fav='';if(installed()&&($id=(int)setting('favicon_media_id','0'))&&($m=media_by_id($id)))$fav='<link rel="icon" href="'.media_url($m).'">';else $fav='<link rel="icon" type="image/svg+xml" href="/assets/erased-favicon.svg">';
 $requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$contentType=(string)($_GET['type']??'');
 $adminLink=static function(string $href,string $label,bool $active=false): string{return '<a href="'.e($href).'"'.($active?' class="active" aria-current="page"':'').'>'.$label.'</a>';};
 $adminLinks=$adminLink('/admin',e(tr('dashboard','admin')),$requestPath==='/admin');if(can('content.view')){$adminLinks.=$adminLink('/admin/content?type=post','Posts',str_starts_with($requestPath,'/admin/content')&&$contentType!=='page');if(can('content.edit.all'))$adminLinks.=$adminLink('/admin/content?type=page','Pages',str_starts_with($requestPath,'/admin/content')&&$contentType==='page');}if(can('publishing.manage'))$adminLinks.=$adminLink('/admin/publishing',e(tr('publishing','admin')),str_starts_with($requestPath,'/admin/publishing'));if(can('media.manage')){$adminLinks.=$adminLink('/admin/media',e(tr('media','admin')),str_starts_with($requestPath,'/admin/media')).$adminLink('/admin/galleries','Gallery',str_starts_with($requestPath,'/admin/galleries'));}if(can('comments.manage'))$adminLinks.=$adminLink('/admin/comments',e(tr('comments','admin')),str_starts_with($requestPath,'/admin/comments'));if(can('users.manage')||can('memberships.manage'))$adminLinks.=$adminLink('/admin/users',e(tr('users','admin')),str_starts_with($requestPath,'/admin/users')||str_starts_with($requestPath,'/admin/memberships'));if(can('payments.manage'))$adminLinks.=$adminLink('/admin/payments',e(tr('payments','admin')),str_starts_with($requestPath,'/admin/payments'));if(can_open_settings_hub())$adminLinks.=$adminLink('/admin/settings',e(tr('settings','admin')),str_starts_with($requestPath,'/admin/settings')||str_starts_with($requestPath,'/admin/appearance')||str_starts_with($requestPath,'/admin/themes')||str_starts_with($requestPath,'/admin/languages')||str_starts_with($requestPath,'/admin/packages')||str_starts_with($requestPath,'/admin/import')||str_starts_with($requestPath,'/admin/backups')||str_starts_with($requestPath,'/admin/security')||str_starts_with($requestPath,'/admin/email'));$adminLinks.=$adminLink('/',e(tr('view_site','admin'))).$adminLink('/logout',e(tr('logout','admin')));$publicTools='';if(!$admin&&logged_in()&&!defined('ERASED_LIVE_PREVIEW_MODE')){$tools='<a href="/admin">'.e(tr('dashboard','admin')).'</a>';if(can('content.create'))$tools.='<a href="/admin/content/new">＋ New</a>';if(can('media.manage'))$tools.='<a href="/admin/media">'.e(tr('media','admin')).'</a><a href="/admin/galleries">Gallery</a>';if(can('comments.manage'))$tools.='<a href="/admin/comments">'.e(tr('comments','admin')).'</a>';$tools.='<a href="/logout">'.e(tr('logout','admin')).'</a>';$publicTools='<div class="frontend-adminbar"><span class="frontend-adminbar-brand">ERASED</span><nav>'.$tools.'</nav></div>';}$nav=$admin?'<header><a class="brand admin-brand" href="/admin">'.$adminLogo.'<small>'.e(cms_version_label()).'</small></a><nav>'.$adminLinks.'</nav></header>':announcement_bar_html().$publicTools.'<header class="public-header header-layout-'.e(setting('header_layout','standard')).'"><a class="brand" href="/">'.$logo.$brandText.'</a><nav class="public-nav">'.v100_public_navigation().'<form class="nav-search-form" method="get" action="/posts" role="search"><label class="sr-only" for="nav-search-input">Search posts</label><input id="nav-search-input" name="q" value="'.e(trim((string)($_GET['q']??''))).'" placeholder="Search posts"></form>'.(setting('show_language_switcher',setting('nav_show_language','1'))==='1'?language_switcher():'').'</nav></header>';
 $custom=$isInstalled?setting('custom_css'):'';$lang=$isInstalled?current_language($admin):'en';$description=$isInstalled?setting('seo_description'):'';$footer=$isInstalled?setting('footer_text'):'';$adminTheme=$isInstalled?setting('admin_theme','dark-green'):'dark-green';$websiteTheme=$isInstalled?setting('website_theme','dark-green'):'dark-green';$logoHeight=max(24,min(180,(int)($isInstalled?setting('logo_height','42'):'42')));$logoWidth=max(40,min(600,(int)($isInstalled?setting('logo_width','240'):'240')));$accent=$isInstalled?setting('theme_accent','#2dfc98'):'#2dfc98';$headingFontStack=erased_typography_font_stack($isInstalled?setting('typography_heading_font','system'):'system');$bodyFontStack=erased_typography_font_stack($isInstalled?setting('typography_body_font','system'):'system');$baseFontSize=max(14,min(20,(int)($isInstalled?setting('typography_base_size','16'):'16')));$themeSlug=in_array($adminTheme,['dark','dark-green','dark-grey','light-grey','ops-deck'],true)?$adminTheme:'dark-green';$publicThemeSlug=in_array($websiteTheme,['dark','dark-green','light-grey'],true)?$websiteTheme:'dark-green';$adminClass=$admin?' admin-area':' theme-'.$publicThemeSlug.' public-area';$adminThemePkg=$admin?erased_resolve_theme_package($adminTheme,'admin'):null;$websiteThemePkg=!$admin?erased_resolve_theme_package($websiteTheme,'website'):null;
 echo '<!doctype html><html lang="'.e($lang).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$full.'</title><meta name="description" content="'.e($description).'">'.$fav.homepage_studio_public_css().(!$admin?'<link rel="stylesheet" href="/assets/public-site.css?v=17">':'').($websiteThemePkg?'<link rel="stylesheet" href="'.e($websiteThemePkg['css_href']).'">':'').'<style>
 :root{--logo-height:'.(int)$logoHeight.'px;--logo-width:'.(int)$logoWidth.'px;--accent:'.e($accent).';--theme-accent:'.e($accent).';--green:'.e($accent).';--font-heading:'.$headingFontStack.';--font-body:'.$bodyFontStack.';--font-size-base:'.(int)$baseFontSize.'px}
 '.str_replace(['</style','<script','</script'],['\\3C/style','\\3Cscript','\\3C/script'],$custom).'
</style>'.($admin?'<link rel="stylesheet" href="/assets/admin-design-system.css?v=8.58">':'').($adminThemePkg?'<link rel="stylesheet" href="'.e($adminThemePkg['css_href']).'">':'').'
</head><body class="'.trim($adminClass).'"'.($admin?' data-theme="'.e($themeSlug).'"':'').'>'.($admin ? (
  '<div class="frame" id="frame" data-admin-studio-root>
    <aside class="rail" data-system-nav aria-label="Sheet index">
      <a class="rail-mark" href="/admin"><span class="sq">E</span><span>'.e(tr('dashboard','admin')).'</span></a>
      <nav class="rail-list">
        <p class="rail-group-label">Content</p>
        '.admin_core_nav_button('posts',$requestPath,str_starts_with($requestPath,'/admin/content')&&$contentType!=='page').'
        '.admin_core_nav_button('pages',$requestPath,str_starts_with($requestPath,'/admin/content')&&$contentType==='page').'
        '.admin_core_nav_button('publishing',$requestPath).'
        '.admin_core_nav_button('media',$requestPath).admin_core_nav_button('galleries',$requestPath).'
        '.admin_core_nav_button('comments',$requestPath).'
        '.admin_plugin_menu_group_html('Content',$requestPath).'
        <p class="rail-group-label">Site</p>
        '.admin_core_nav_button('users',$requestPath).'
        '.admin_core_nav_button('payments',$requestPath).'
        '.admin_core_nav_button('erased_studio',$requestPath).'
        '.admin_plugin_menu_group_html('Site',$requestPath).'
        <p class="rail-group-label">System</p>
        '.admin_core_nav_button('settings',$requestPath).'
        '.admin_plugin_menu_group_html('System',$requestPath).'
        '.admin_plugin_extra_groups_html($requestPath).'
      </nav>
      <div class="rail-foot"><span class="dot"></span><a href="/" target="_blank">View site</a><span class="sep">·</span><a href="/logout">Logout</a></div>
    </aside>

    <header class="head">
      <button type="button" class="rail-toggle" data-system-nav-toggle title="Hide sheet index" aria-label="Hide sheet index">
        <svg class="icon" viewBox="0 0 24 24" width="14" height="14"><rect x="3" y="4" width="18" height="16" rx="1"/><line x1="9" y1="4" x2="9" y2="20"/></svg>
      </button>
      '.admin_breadcrumb_html($requestPath, $title).'
      <form class="find" method="get" action="'.(str_starts_with($requestPath, '/admin/comments') ? '/admin/comments' : '/admin/content').'">
        <span>FIND</span>
        <input type="search" id="admin-global-search-input" name="q" placeholder="'.(str_starts_with($requestPath, '/admin/comments') ? 'search comments…' : 'search content…').'" value="'.e(trim((string)($_GET['q']??''))).'">
        '.(str_starts_with($requestPath, '/admin/comments') ? '<input type="hidden" name="status" value="'.e((string)($_GET['status']??'all')).'">' : '').'
      </form>
      '.(can('comments.manage') ? (function(){
          $cnt = 0;
          try { $cnt = (int)db()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn(); } catch(Throwable $e){}
          return '<button type="button" class="head-icon-btn" title="Pending Comments" onclick="window.location.href=\'/admin/comments?status=pending\'"><svg class="icon" viewBox="0 0 24 24" width="16" height="16"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>'.($cnt > 0 ? '<span class="dot">'.$cnt.'</span>' : '').'</button>';
      })() : '').'
    </header>

    <main class="canvas"><div class="sheet-inner">
      '.(($subnavHtml=admin_header_actions_html($requestPath, $contentType))!==''?'<div class="subnav">'.$subnavHtml.'</div>':'').flashes_html().$body.'
    </div></main>
  </div>
  <script>
  (()=>{
   const root = document.querySelector("[data-admin-studio-root]");
   if(!root) return;
   const navToggleBtn = root.querySelector("[data-system-nav-toggle]");
   const savedState = localStorage.getItem("erased_system_nav_collapsed");
   // Below the 760px breakpoint the rail stops being a grid column and
   // becomes a fixed full-height overlay (see admin-design-system.css) - on
   // a first visit with no saved preference yet, defaulting to "open" (the
   // desktop behavior) means the overlay covers the entire screen with no
   // visible way to dismiss it until the user discovers the toggle button
   // underneath it. Default closed on narrow viewports instead; an explicit
   // saved preference (the user having actually used the toggle before)
   // always wins regardless of viewport width.
   // v0.9-dev: opening the drawer on a narrow viewport must NOT persist as a
   // durable cross-navigation preference the way it does on desktop -
   // .rail-item links do a real page navigation (not SPA), so saving
   // "false" here meant the very next page load found a non-null saved
   // value and defaulted the drawer open again, silently reintroducing the
   // "overlay covers everything" bug one tap later for anyone who had ever
   // opened it once on mobile. Desktop preference persistence (both
   // directions) is correct and untouched - this only carves out the
   // narrow-viewport open action as ephemeral, for this page view only.
   const prefersNarrow = window.matchMedia("(max-width:760px)").matches;
   if(savedState === "true" || (savedState === null && prefersNarrow)){ root.classList.add("rail-hidden"); }
   navToggleBtn?.addEventListener("click", event => {
    event.stopPropagation();
    const isHidden = root.classList.toggle("rail-hidden");
    if(!(prefersNarrow && !isHidden)){
     localStorage.setItem("erased_system_nav_collapsed", isHidden ? "true" : "false");
    }
   });
   if(prefersNarrow){
    root.querySelectorAll(".rail-item").forEach(item => {
     item.addEventListener("click", () => { root.classList.add("rail-hidden"); });
    });
   }
   // Settings sub-nav (.docket-nav-wrap) ships the `open` attribute in the
   // markup so it stays visible on desktop with zero JS involved. Below
   // 1040px (where .docket drops to one column) this removes `open` on
   // load instead, collapsing it to a tap-to-expand accordion - a CSS
   // override that forces a closed details element open again does not
   // reliably work in current Chromium, confirmed live: its content sits
   // behind an internal details-content wrapper whose own visibility a
   // child-level display override cannot undo.
   if(window.matchMedia("(max-width:1040px)").matches){
    root.querySelectorAll(".docket-nav-wrap[open]").forEach(d => d.removeAttribute("open"));
   }
   document.addEventListener("keydown", event => {
    if((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k"){
     event.preventDefault();
     const searchInput = document.getElementById("admin-global-search-input");
     if(searchInput){ searchInput.focus(); searchInput.select(); }
    }
   });
  })();
  </script>'
) : ($nav.'<main class="public">'.flashes_html().$body.'</main>')).(!$admin&&$isInstalled?'<footer class="footer"><div class="footer-row footer-row-newsletter">'.newsletter_form().'</div><div class="footer-row footer-row-bottom"><div class="footer-social">'.homepage_follow_links().(setting('footer_show_rss','1')==='1'?'<a class="footer-rss social-icon" href="/feed" aria-label="RSS feed" title="RSS feed">'.social_icon_svg('rss').'<span class="sr-only">RSS feed</span></a>':'').'</div><div class="footer-copy"><span>'.$footer.'</span></div></div></footer>':'').(!$admin?'<div id="erasedLightbox" class="erased-lightbox" aria-hidden="true"><div class="erased-lightbox-overlay" onclick="closeErasedLightbox()"></div><div class="erased-lightbox-content"><button class="erased-lightbox-close" type="button" onclick="closeErasedLightbox()" aria-label="Close">✕</button><button class="erased-lightbox-nav erased-lightbox-prev" type="button" onclick="navErasedLightbox(-1)" aria-label="Previous">‹</button><button class="erased-lightbox-nav erased-lightbox-next" type="button" onclick="navErasedLightbox(1)" aria-label="Next">›</button><div class="erased-lightbox-figure"><img id="erasedLightboxImg" src="" alt=""><div class="erased-lightbox-info"><div id="erasedLightboxTitle"></div><div id="erasedLightboxCount"></div><a id="erasedLightboxDL" href="" download target="_blank" class="erased-lightbox-dl">'.e(tr('download_photo')).'</div></div></div></div>':'').'<script>'.editor_js().'
document.addEventListener("click",function(e){var b=e.target.closest(".newsletter-toggle");if(!b)return;var p=document.getElementById(b.getAttribute("aria-controls"));if(!p)return;p.removeAttribute("hidden");b.remove();var i=p.querySelector("input[type=email]");if(i)i.focus();});
if(location.search.indexOf("subscribed=1")!==-1){var nb=document.querySelector(".newsletter-box");if(nb)nb.scrollIntoView({block:"center"});}

function filterMedia(value){const q=value.toLowerCase().trim();document.querySelectorAll(".media-tile").forEach(tile=>{tile.style.display=!q||tile.dataset.name.includes(q)?"flex":"none";});}
function setMediaSize(size){const browser=document.getElementById("media-browser");if(!browser)return;browser.classList.toggle("media-small",size==="small");localStorage.setItem("erasedMediaSize",size);}
let erasedLbItems=[],erasedLbIndex=0;
function openErasedLightbox(items,idx=0){erasedLbItems=items;erasedLbIndex=idx;const el=document.getElementById("erasedLightbox");if(!el)return;el.classList.add("open");el.setAttribute("aria-hidden","false");renderErasedLightbox();}
function renderErasedLightbox(){if(!erasedLbItems.length)return;const item=erasedLbItems[erasedLbIndex];const img=document.getElementById("erasedLightboxImg"),title=document.getElementById("erasedLightboxTitle"),cnt=document.getElementById("erasedLightboxCount"),dl=document.getElementById("erasedLightboxDL");if(img){img.src=item.url;img.alt=item.caption||item.title||"Photo";}if(title)title.textContent=item.caption||item.title||"";if(cnt)cnt.textContent=(erasedLbIndex+1)+" of "+erasedLbItems.length;if(dl)dl.href=item.url;}
function closeErasedLightbox(){const el=document.getElementById("erasedLightbox");if(!el)return;el.classList.remove("open");el.setAttribute("aria-hidden","true");}
function navErasedLightbox(dir){if(!erasedLbItems.length)return;erasedLbIndex=(erasedLbIndex+dir+erasedLbItems.length)%erasedLbItems.length;renderErasedLightbox();}
document.addEventListener("keydown",e=>{const el=document.getElementById("erasedLightbox");if(!el||!el.classList.contains("open"))return;if(e.key==="Escape")closeErasedLightbox();else if(e.key==="ArrowLeft")navErasedLightbox(-1);else if(e.key==="ArrowRight")navErasedLightbox(1);});
document.addEventListener("DOMContentLoaded",()=>{const saved=localStorage.getItem("erasedMediaSize")||"small";setMediaSize(saved);document.querySelectorAll(".erased-gallery img, .photo-album-item img, [data-lightbox]").forEach((img,idx,all)=>{img.style.cursor="pointer";img.addEventListener("click",e=>{if(img.closest(".photo-album-item"))return;e.preventDefault();const list=[...all].map(i=>({url:i.src,caption:i.alt||i.title||""}));openErasedLightbox(list,idx);});});});
</script>'.($admin?'<script>window.ERASED_EDITOR_ENGINE='.json_encode((string)((require __DIR__.'/../config/app.php')['editor_engine']??'legacy')).';document.body.dataset.editorEngine=window.ERASED_EDITOR_ENGINE;</script><link rel="stylesheet" href="/assets/editor/erased-cms-editor.css?v=23"><script type="module" src="/assets/editor/erased-editor.js?v=21"></script>':'').ui_translation_script($admin).($websiteThemePkg['js_href']??null?'<script src="'.e($websiteThemePkg['js_href']).'" defer></script>':'').'</body></html>';
}
function editor_js(): string{return <<<'JS'
function q(s){return document.querySelector(s)}function qa(s){return [...document.querySelectorAll(s)]}
let selectedMedia=null;
function exec(cmd,val=null){if(window.erasedEditor){window.erasedEditor.command(cmd,val);syncEditor();updateWordCount();return}document.execCommand(cmd,false,val);q('#visual-editor')?.focus();syncEditor();updateWordCount()}
function block(tag){exec('formatBlock',tag)}
function syncEditor(){const v=q('#visual-editor'),h=q('#body-editor');if(v&&h)h.value=window.erasedEditor?window.erasedEditor.getHTML():v.innerHTML}
function updateWordCount(){const v=q('#visual-editor');if(!v)return;const words=(v.innerText.trim().match(/\S+/g)||[]).length;const chars=v.innerText.length;const el=q('#editor-count');if(el)el.textContent=words+' words · '+chars+' characters'}
function cleanPastedHtml(html){const doc=new DOMParser().parseFromString(html,'text/html');doc.querySelectorAll('script,style,link,meta,iframe,object,embed,form,input,button').forEach(n=>n.remove());doc.body.querySelectorAll('*').forEach(el=>{[...el.attributes].forEach(a=>{const keep=(el.tagName==='A'&&['href','title','target','rel'].includes(a.name))||(el.tagName==='IMG'&&['src','alt','title','width','height'].includes(a.name))||(el.tagName==='FIGURE'&&a.name==='class')||(el.tagName==='TD'&&['colspan','rowspan'].includes(a.name))||(el.tagName==='TH'&&['colspan','rowspan'].includes(a.name));if(!keep)el.removeAttribute(a.name)});if(el.tagName==='A'){const href=el.getAttribute('href')||'';if(/^javascript:/i.test(href))el.removeAttribute('href');el.setAttribute('rel','noopener noreferrer')}});return doc.body.innerHTML}
function handleEditorPaste(e){if(window.erasedEditor)return;const html=e.clipboardData?.getData('text/html');const text=e.clipboardData?.getData('text/plain')||'';if(!html)return;e.preventDefault();document.execCommand('insertHTML',false,cleanPastedHtml(html)||text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'));syncEditor();updateWordCount()}
function toggleSource(){const v=q('#visual-editor'),s=q('#source-editor');if(!v||!s)return;const active=s.style.display==='block';if(active){window.erasedEditor?window.erasedEditor.setHTML(s.value):v.innerHTML=s.value;s.style.display='none';v.style.display='block'}else{s.value=window.erasedEditor?window.erasedEditor.getHTML():v.innerHTML;v.style.display='none';s.style.display='block'}syncEditor();updateWordCount()}
function togglePreview(){const wrap=q('.editor-wrap'),preview=q('#editor-preview'),btn=q('#preview-toggle');if(!wrap||!preview)return;const on=!wrap.classList.contains('previewing');wrap.classList.toggle('previewing',on);preview.classList.toggle('active',on);preview.innerHTML=window.erasedEditor?window.erasedEditor.getHTML():(q('#visual-editor')?.innerHTML||'');if(btn)btn.textContent=on?'Edit':'Preview'}
function createLink(){const u=prompt('Link URL:','https://');if(u)exec('createLink',u)}
function unlink(){exec('unlink')}
function insertHtml(html){const v=q('#visual-editor');if(!v)return;if(window.erasedEditor)window.erasedEditor.insertHTML(html);else{v.focus();document.execCommand('insertHTML',false,html)}syncEditor();updateWordCount();closeMediaPicker()}
function openMediaPicker(){q('#media-modal')?.classList.add('open')}function closeMediaPicker(){q('#media-modal')?.classList.remove('open')}
function insertPicked(url,alt,type='image'){if(window.erasedEditor&&type==='image'){window.erasedEditor.command('insertImage',{src:url,alt:alt||''});closeMediaPicker();return;}const safe=url.replace(/"/g,'&quot;');if(type==='video')insertHtml('<figure class="post-video"><video controls preload="metadata" controlsList="nodownload"><source src="'+safe+'">Your browser cannot play this video.</video></figure>');else insertHtml('<figure class="media-large media-center"><img src="'+safe+'" alt="'+(alt||'').replace(/"/g,'&quot;')+'"><figcaption contenteditable="true"></figcaption></figure><p><br></p>')}
function selectMedia(target){qa('.visual-editor .is-selected, .visual-editor img.is-selected, .visual-editor table.is-selected, .visual-editor .post-video.is-selected, .visual-editor .video-embed.is-selected').forEach(x=>x.classList.remove('is-selected'));if(!target)return;const container=target.closest('img, table, .post-video, .video-embed, figure');if(container){container.classList.add('is-selected');selectedMedia=container;if(target.tagName==='IMG'){q('#editor-media-tools')?.classList.add('active');q('#image-alt').value=target.getAttribute('alt')||''}}}
function mediaFigure(){if(!selectedMedia)return null;let f=selectedMedia.closest('figure');if(!f){f=document.createElement('figure');f.className='media-large media-center';selectedMedia.parentNode.insertBefore(f,selectedMedia);f.appendChild(selectedMedia)}return f}
function setImageSize(size){const f=mediaFigure();if(!f)return;f.classList.remove('media-small','media-medium','media-large','media-full');f.classList.add('media-'+size);syncEditor()}
function setImageAlign(a){const f=mediaFigure();if(!f)return;f.classList.remove('media-left','media-center','media-right');f.classList.add('media-'+a);syncEditor()}
function setImageAlt(){if(!selectedMedia)return;selectedMedia.setAttribute('alt',q('#image-alt').value.trim());syncEditor()}
function setImageCaption(){const f=mediaFigure();if(!f)return;let c=f.querySelector('figcaption');if(!c){c=document.createElement('figcaption');f.appendChild(c)}c.textContent=prompt('Image caption:',c.textContent||'')||'';syncEditor()}
function removeSelectedMedia(){if(!selectedMedia)return;const f=selectedMedia.closest('figure');(f||selectedMedia).remove();selectedMedia=null;q('#editor-media-tools')?.classList.remove('active');syncEditor();updateWordCount()}
function insertTable(){const rows=Math.max(1,Math.min(20,parseInt(prompt('Rows:','3')||'0',10)));const cols=Math.max(1,Math.min(10,parseInt(prompt('Columns:','3')||'0',10)));if(!rows||!cols)return;let h='<table data-align="center"><tbody>';for(let r=0;r<rows;r++){h+='<tr>';for(let c=0;c<cols;c++)h+='<td><br></td>';h+='</tr>'}insertHtml(h+'</tbody></table><p><br></p>')}
function insertRule(){insertHtml('<hr><p><br></p>')}
function embedVideo(){const u=prompt('Video URL (YouTube, Vimeo, MP4, WebM or OGG):','https://');if(!u)return;let html='';try{const url=new URL(u.trim());const host=url.hostname.toLowerCase().replace(/^www\./,'');let id='';if(host==='youtu.be')id=url.pathname.split('/').filter(Boolean)[0]||'';else if(host==='youtube.com')id=url.searchParams.get('v')||'';if(id)html='<div class="video-embed" data-align="center"><iframe src="https://www.youtube.com/embed/'+encodeURIComponent(id)+'?rel=0" title="YouTube video" loading="lazy" allowfullscreen></iframe></div>';else if(host==='vimeo.com'){id=url.pathname.split('/').filter(Boolean).pop();html='<div class="video-embed" data-align="center"><iframe src="https://player.vimeo.com/video/'+encodeURIComponent(id)+'" title="Vimeo video" loading="lazy" allowfullscreen></iframe></div>'}else if(/\.(mp4|webm|ogg)(?:$|\?)/i.test(url.href))html='<figure class="post-video" data-align="center"><video controls preload="metadata"><source src="'+url.href.replace(/"/g,'&quot;')+'"></video></figure>';else{alert('Use a supported video URL.');return}}catch(e){alert('Enter a valid URL.');return}insertHtml(html)}
function uploadFromEditor(input){if(!input.files.length)return;const fd=new FormData();fd.append('csrf',q('input[name=csrf]').value);fd.append('file',input.files[0]);fetch('/admin/media/upload',{method:'POST',body:fd}).then(r=>r.json()).then(x=>{if(!x.ok)throw new Error(x.error);insertPicked(x.url,x.alt||'',x.type||'image')}).catch(e=>alert(e.message))}
function initEditor(){const v=q('#visual-editor'),f=q('#content-form');if(!v)return;if(!window.erasedEditor){v.addEventListener('input',()=>{syncEditor();updateWordCount()});v.addEventListener('paste',handleEditorPaste);}v.addEventListener('click',e=>selectMedia(e.target.closest('img, table, .post-video, .video-embed, figure')));f?.addEventListener('submit',syncEditor);document.addEventListener('click',e=>{if(!e.target.closest('#visual-editor')&&!e.target.closest('#editor-media-tools')&&!e.target.closest('#lexical-toolbar')){qa('.visual-editor .is-selected, .visual-editor img.is-selected, .visual-editor table.is-selected, .visual-editor .post-video.is-selected, .visual-editor .video-embed.is-selected').forEach(x=>x.classList.remove('is-selected'))}});syncEditor();updateWordCount()}
function initDrop(){const d=q('#dropzone'),i=q('#media-files');if(!d||!i)return;['dragenter','dragover'].forEach(e=>d.addEventListener(e,x=>{x.preventDefault();d.classList.add('drag')}));['dragleave','drop'].forEach(e=>d.addEventListener(e,x=>{x.preventDefault();d.classList.remove('drag')}));d.addEventListener('drop',e=>{i.files=e.dataTransfer.files;d.closest('form').submit()})}
document.addEventListener('DOMContentLoaded',()=>{initEditor();initDrop()});
JS;}
function media_picker(): string{$cards='';foreach(db()->query("SELECT * FROM media WHERE mime_type LIKE 'image/%' OR mime_type LIKE 'video/%' ORDER BY uploaded_at DESC") as $m){$url=media_url($m);$isVideo=str_starts_with((string)$m['mime_type'],'video/');$preview=$isVideo?'<video preload="metadata" muted><source src="'.$url.'" type="'.e($m['mime_type']).'"></video><span class="media-kind-badge">VIDEO</span>':'<img src="'.e(media_thumb_url($m)).'" alt="'.e((string)$m['alt_text']).'" loading="lazy">';$onclick='insertPicked('.json_encode($url).','.json_encode((string)$m['alt_text']).','.json_encode($isVideo?'video':'image').')';$cards.='<button type="button" class="picker-item" onclick="'.e($onclick).'">'.$preview.'<small>'.e($m['original_name']).'</small></button>';}return '<div id="media-modal" class="modal"><div class="modal-box"><div class="toolbar"><div><h2>Choose media</h2><p class="muted">Insert an image or video into this post.</p></div><button type="button" class="btn secondary" onclick="closeMediaPicker()">Close</button></div><label>Upload new media<input type="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg" onchange="uploadFromEditor(this)"></label><div class="picker-grid">'.($cards?:'<p>No images or videos yet.</p>').'</div></div></div>';}


function content_form(array $i=[],bool $canPublish=false,bool $canManagePages=false): string{$id=(int)($i['id']??0);$body=$i['body']??'';$featured=isset($i['featured_media_id'])?(int)$i['featured_media_id']:null;return '<div class="card post-manager"><form id="content-form" method="post"><input type="hidden" name="csrf" value="'.csrf().'"><textarea id="body-editor" name="body" hidden>'.e($body).'</textarea><label>Title<input name="title" value="'.e($i['title']??'').'" required></label><label>Slug <small>Leave empty to generate automatically.</small><input name="slug" value="'.e($i['slug']??'').'"></label><div class="grid"><label>Type'.($canManagePages?'<select name="type"><option value="page"'.(($i['type']??'post')==='page'?' selected':'').'>Page</option><option value="post"'.(($i['type']??'post')==='post'?' selected':'').'>Post</option></select>':'<input type="hidden" name="type" value="post"><input value="Post" disabled>').'</label><label>Status'.($canPublish?'<select name="status"><option value="draft"'.(($i['status']??'draft')==='draft'?' selected':'').'>Draft</option><option value="published"'.(($i['status']??'')==='published'?' selected':'').'>Published</option></select>':'<input type="hidden" name="status" value="draft"><input value="Draft — editor approval required" disabled>').'</label><label>Featured media<select name="featured_media_id">'.media_options($featured).'</select></label><label><input type="checkbox" name="comments_enabled" value="1"'.(($i['comments_enabled']??1)?' checked':'').'> Allow comments on this post</label></div><label>Excerpt <small>Used on post lists and search previews.</small><textarea name="excerpt" style="min-height:90px">'.e($i['excerpt']??'').'</textarea></label><div class="grid page-options"><label>Page template<select name="page_template"><option value="sidebar"'.(($i['page_template']??'sidebar')==='sidebar'?' selected':'').'>Sidebar / site layout</option><option value="full"'.(($i['page_template']??'')==='full'?' selected':'').'>Full width</option><option value="landing"'.(($i['page_template']??'')==='landing'?' selected':'').'>Landing page</option></select></label><label>SEO title<input name="seo_title" value="'.e($i['seo_title']??'').'"></label></div><label>SEO description<textarea name="seo_description" style="min-height:80px">'.e($i['seo_description']??'').'</textarea></label><div class="toolbar"><label style="margin:0"><strong>Post content</strong></label><button id="preview-toggle" type="button" class="btn secondary" onclick="togglePreview()">Preview</button></div><div class="editor-wrap"><div id="lexical-toolbar" class="lexical-playground-toolbar" role="toolbar" aria-label="Editor toolbar">
<div class="lexical-playground-toolbar__group">
<button type="button" data-lexical-command="undo" title="Undo"><svg viewBox="0 0 24 24"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg></button>
<button type="button" data-lexical-command="redo" title="Redo"><svg viewBox="0 0 24 24"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg></button>
</div>

<span class="lexical-playground-toolbar__divider"></span>

<div class="lexical-playground-toolbar__group">
<select data-lexical-format-block aria-label="Block style">
<option value="p">Paragraph</option>
<option value="h1">Heading 1</option>
<option value="h2">Heading 2</option>
<option value="h3">Heading 3</option>
<option value="blockquote">Quote</option>
</select>
</div>

<span class="lexical-playground-toolbar__divider"></span>

<div class="lexical-playground-toolbar__group">
<button type="button" data-lexical-command="bold" title="Bold"><svg viewBox="0 0 24 24"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg></button>
<button type="button" data-lexical-command="italic" title="Italic"><svg viewBox="0 0 24 24"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg></button>
<button type="button" data-lexical-command="underline" title="Underline"><svg viewBox="0 0 24 24"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg></button>
<button type="button" data-lexical-command="strikeThrough" title="Strikethrough"><svg viewBox="0 0 24 24"><path d="M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z"/></svg></button>
<button type="button" data-lexical-command="subscript" title="Subscript"><svg viewBox="0 0 24 24"><path d="M5.82 5.5 4 7l.82 1.1L6.6 6.8v11.7H8.8V5.5H5.82zM19.5 15h-3.6v-1.1l2.1-2.2c.4-.4.7-.8.9-1.2.2-.4.3-.8.3-1.2 0-.6-.2-1.1-.6-1.4-.4-.3-1-.5-1.7-.5-.6 0-1.1.2-1.5.5-.4.4-.7.9-.8 1.5l-1.9-.3c.2-1.2.7-2.1 1.6-2.7.9-.6 2.1-.9 3.5-.9 1.4 0 2.6.4 3.4 1.1.8.7 1.2 1.7 1.2 2.8 0 .7-.2 1.4-.5 2-.4.6-.9 1.3-1.7 2.1l-1.3 1.3h4v1.8z"/></svg></button>
<button type="button" data-lexical-command="superscript" title="Superscript"><svg viewBox="0 0 24 24"><path d="M19.5 9h-3.6V7.9l2.1-2.2c.4-.4.7-.8.9-1.2.2-.4.3-.8.3-1.2 0-.6-.2-1.1-.6-1.4-.4-.3-1-.5-1.7-.5-.6 0-1.1.2-1.5.5-.4.4-.7.9-.8 1.5l-1.9-.3c.2-1.2.7-2.1 1.6-2.7C13.5.03 14.7-.27 16.1-.27c1.4 0 2.6.4 3.4 1.1.8.7 1.2 1.7 1.2 2.8 0 .7-.2 1.4-.5 2-.4.6-.9 1.3-1.7 2.1l-1.3 1.3h4V9zM5.82 10.5 4 12l.82 1.1L6.6 11.8v11.7H8.8V10.5H5.82z"/></svg></button>
<button type="button" data-lexical-command="code" title="Inline code"><svg viewBox="0 0 24 24"><path d="M9.4 16.6 4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0 4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg></button>
</div>

<span class="lexical-playground-toolbar__divider"></span>

<div class="lexical-playground-toolbar__group">
<select data-lexical-align aria-label="Text Alignment">
<option value="">Align</option>
<option value="alignLeft">Align Left</option>
<option value="alignCenter">Align Center</option>
<option value="alignRight">Align Right</option>
<option value="alignJustify">Align Justify</option>
</select>
</div>

<span class="lexical-playground-toolbar__divider"></span>

<div class="lexical-playground-toolbar__group">
<button type="button" data-lexical-command="insertUnorderedList" title="Bullet list"><svg viewBox="0 0 24 24"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg></button>
<button type="button" data-lexical-command="insertOrderedList" title="Numbered list"><svg viewBox="0 0 24 24"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg></button>
<button type="button" data-lexical-link title="Insert link"><svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg></button>
<button type="button" data-lexical-command="removeFormat" title="Clear formatting"><svg viewBox="0 0 24 24"><path d="M19.36 2.72 20.78 4.14 17.92 7l2.86 2.86-1.42 1.42L16.5 8.42l-2.86 2.86-1.42-1.42L15.08 7l-2.86-2.86 1.42-1.42 2.86 2.86 2.86-2.86zM6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12z"/></svg></button>
</div>

<span class="lexical-playground-toolbar__divider"></span>

<div class="lexical-playground-toolbar__group">
<button type="button" onclick="openMediaPicker()" title="Insert media"><svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg> Image</button>
<button type="button" onclick="embedVideo()" title="Insert video"><svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg> Video</button>
<button type="button" onclick="insertTable()" title="Insert table"><svg viewBox="0 0 24 24"><path d="M20 3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 2v3H4V5h16zm-9 5v4H4v-4h7zm2 0h7v4h-7v-4zm-2 6v3H4v-3h7zm2 0h7v3h-7v-3z"/></svg> Table</button>
<button type="button" onclick="toggleSource()" title="Edit HTML source">&lt;/&gt;</button>
</div>
</div>
<div id="visual-editor" class="visual-editor" contenteditable="true" data-placeholder="Start writing…">'.$body.'</div><textarea id="source-editor" class="source-editor"></textarea><div id="editor-preview" class="editor-preview article-body"></div><div class="editor-status"><span>Images are responsive and can be resized after selection.</span><span id="editor-count">0 words</span></div></div><div class="actions actions--sticky" style="margin-top:14px"><button>'.($id?'Save changes':'Create content').'</button> <a class="btn secondary" href="/admin/content">Cancel</a></div></form></div>'.media_picker();}
function comments_html(array $i): string{
 if(($i['type']??'post')!=='post'||!(int)($i['comments_enabled']??1))return '';
 $id=(int)$i['id'];$_SESSION['comment_form_started'][$id]=time();
 $q=db()->prepare("SELECT author_name,body,created_at FROM comments WHERE content_id=? AND status='approved' ORDER BY created_at ASC");$q->execute([$id]);$rows=$q->fetchAll();
 $items='';foreach($rows as $c){$items.='<div class="comment-item"><div class="comment-head"><strong>'.e($c['author_name']).'</strong><small>'.e(date(setting('date_format','Y-m-d'),strtotime($c['created_at']))).'</small></div><div>'.nl2br(e($c['body'])).'</div></div>';}
 $u=logged_in()?current_user():null;$name=$u?($u['display_name']?:''):'';$email=$u?($u['email']??''):'';
 return '<section id="comments" class="card comments-section"><div class="toolbar"><div><h2>'.e(tr('comments')).'</h2><p class="muted">'.($items?tr('join_discussion'):tr('first_comment')).'</p></div><span class="badge">'.count($rows).' approved</span></div><div class="comments-list">'.$items.'</div><form class="comment-form" method="post" action="/comments/submit"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="content_id" value="'.$id.'"><label class="hp-field" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label><div class="grid"><label>'.e(tr('name')).'<input name="author_name" maxlength="190" value="'.e($name).'" required></label><label>'.e(tr('email')).' <small>'.e(tr('email_not_published')).'</small><input type="email" name="author_email" maxlength="190" value="'.e($email).'" required></label></div><label>'.e(tr('comment')).'<textarea name="body" minlength="3" maxlength="3000" required></textarea></label><p class="comment-note">'.e(tr('comment_moderation_note')).'</p>'.erased_comment_captcha_widget().'<button>'.e(tr('post_comment')).'</button></form></section>';
}
if (!function_exists('erased_public_content_html')) {
function erased_public_content_html(string $html): string{
    if(trim($html)==='')return '';
    $allowedTags='<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><strike><code><pre><blockquote><ul><ol><li><a><hr><div><span><figure><figcaption><img><video><source><iframe><table><thead><tbody><tfoot><tr><th><td>';
    if(!class_exists('DOMDocument')){
        $html=preg_replace('~<(script|style|object|embed|link|meta|base|form|input|button|textarea|select|option|template|svg|math)\b[^>]*>.*?</\1>~is','',$html)??$html;
        $html=strip_tags($html,$allowedTags);
        $html=preg_replace("~\\s(?:on[a-z]+|srcdoc|contenteditable|draggable|data-block-id|data-block-type)\\s*=\\s*(?:\"[^\"]*\"|'[^']*'|[^\\s>]+)~i",'',$html)??$html;
        $html=preg_replace("~\\s(?:href|src|poster)\\s*=\\s*([\"'])\\s*(?:javascript|vbscript|data:text/html)[^\"']*\\1~i",'',$html)??$html;
        $html=str_replace([' erased-block',' is-selected',' is-dragging'],'',$html);
        return erased_parse_plugin_shortcodes(erased_parse_gallery_shortcode($html));
    }
    $doc=new DOMDocument('1.0','UTF-8');
    $previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="erased-public-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($previous);
    $xpath=new DOMXPath($doc);
    foreach(['block-controls','block-label','block-drag-handle','editor-resize-handle','slash-command-menu','block-inserter-menu','erased-image-size-badge','erased-image-node__resize-handle'] as $class){
        foreach(iterator_to_array($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," '.$class.' ")]')?:[]) as $node){$node->parentNode?->removeChild($node);}
    }
    $dangerous='self::script or self::style or self::object or self::embed or self::link or self::meta or self::base or self::form or self::input or self::button or self::textarea or self::select or self::option or self::template or self::svg or self::math';
    foreach(iterator_to_array($xpath->query('//*['.$dangerous.']')?:[]) as $node)$node->parentNode?->removeChild($node);
    $allowed=array_flip(['p','br','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s','strike','code','pre','blockquote','ul','ol','li','a','hr','div','span','figure','figcaption','img','video','source','iframe','table','thead','tbody','tfoot','tr','th','td']);
    foreach(iterator_to_array($xpath->query('//*')?:[]) as $node){
        if(!$node instanceof DOMElement||$node->getAttribute('id')==='erased-public-root')continue;
        $tag=strtolower($node->tagName);
        if(!isset($allowed[$tag])){
            $parent=$node->parentNode;
            if($parent){while($node->firstChild)$parent->insertBefore($node->firstChild,$node);$parent->removeChild($node);}
            continue;
        }
        $tagAttributes=[
            'a'=>['href','target','rel'],
            'img'=>['src','alt','width','height','loading'],
            'video'=>['src','controls','preload','poster','width','height','muted','loop','playsinline'],
            'source'=>['src','type'],
            'iframe'=>['src','title','width','height','allow','allowfullscreen','loading','referrerpolicy','sandbox'],
            'ol'=>['start','reversed','type'],
            'li'=>['value'],
            'th'=>['colspan','rowspan','scope'],
            'td'=>['colspan','rowspan'],
        ];
        $allowedAttributes=array_flip(array_merge(['class','title','lang','dir','style','data-align','aria-label'],$tagAttributes[$tag]??[]));
        foreach(iterator_to_array($node->attributes) as $attribute){
            $name=strtolower($attribute->name);
            $value=trim((string)$attribute->value);
            if(str_starts_with($name,'on')||!isset($allowedAttributes[$name])){$node->removeAttribute($attribute->name);continue;}
            if(in_array($name,['href','src','poster'],true)){
                $decoded=trim(html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8'));
                $safe=$decoded!==''&&(
                    ($decoded[0]==='/'&&!str_starts_with($decoded,'//'))||
                    $decoded[0]==='#'||
                    preg_match('~^https?://~i',$decoded)===1||
                    ($name==='href'&&preg_match('~^(?:mailto|tel):~i',$decoded)===1)||
                    ($tag==='img'&&$name==='src'&&preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,~i',$decoded)===1)
                );
                if(!$safe){$node->removeAttribute($attribute->name);continue;}
            }
            if($name==='style'){
                $safeDeclarations=[];
                foreach(explode(';',$value) as $declaration){
                    if(!str_contains($declaration,':'))continue;
                    [$property,$propertyValue]=array_map('trim',explode(':',$declaration,2));
                    $property=strtolower($property);
                    if(!in_array($property,['color','background-color','text-align','width','height','max-width','min-width','margin','margin-left','margin-right','float','display','aspect-ratio'],true))continue;
                    if(preg_match('~url\s*\(|expression\s*\(|javascript:|vbscript:|behavior\s*:|-moz-binding|@import~i',$propertyValue))continue;
                    if(!preg_match('~^[#(),.%\sa-z0-9+\-/*]+$~i',$propertyValue))continue;
                    $safeDeclarations[]=$property.': '.$propertyValue;
                }
                if($safeDeclarations)$node->setAttribute('style',implode('; ',$safeDeclarations));else$node->removeAttribute('style');
            }
            if($name==='class'){
                $classes=preg_split('/\s+/',trim($value))?:[];
                $classes=array_values(array_filter($classes,static fn($class)=>preg_match('/^[A-Za-z0-9_-]+$/',$class)&&!in_array($class,['erased-block','is-selected','is-dragging','erased-image-node--selected','erased-video-node--selected'],true)));
                if($classes)$node->setAttribute('class',implode(' ',$classes));else$node->removeAttribute('class');
            }
        }
        if($tag==='iframe'){
            $src=$node->getAttribute('src');$host=strtolower((string)parse_url($src,PHP_URL_HOST));
            if(!in_array($host,['www.youtube.com','youtube.com','www.youtube-nocookie.com','youtube-nocookie.com','player.vimeo.com'],true)){$node->parentNode?->removeChild($node);continue;}
            $node->setAttribute('sandbox','allow-scripts allow-same-origin allow-presentation');
            $node->setAttribute('referrerpolicy','strict-origin-when-cross-origin');
            $node->setAttribute('loading','lazy');
        }
        if($tag==='img'&&$node->getAttribute('loading')==='')$node->setAttribute('loading','lazy');
        if($tag==='a'&&strtolower($node->getAttribute('target'))==='_blank')$node->setAttribute('rel','noopener noreferrer');
    }
    $root=$doc->getElementById('erased-public-root');$out='';
    if($root)foreach($root->childNodes as $child)$out.=$doc->saveHTML($child);
    return erased_parse_plugin_shortcodes(erased_parse_gallery_shortcode($out));
}
}
/**
 * Plugin-declared shortcodes, run after the sanitizer above (not before) -
 * the same reason erased_parse_gallery_shortcode() already works this way:
 * a plugin's own trusted markup (a real <form>, onclick handlers) would
 * otherwise be stripped by the allowedTags/on*-attribute filtering meant
 * for user-authored WYSIWYG content. One hardcoded shortcode today
 * (contact_form) - matches the same deliberate scope-limiting precedent as
 * ERASED Commerce's single hardcoded storefront dispatch stanza, rather
 * than building a generic plugin-shortcode registry for a single caller.
 */
if (!function_exists('erased_parse_plugin_shortcodes')) {
function erased_parse_plugin_shortcodes(string $html): string {
    if(str_contains($html,'[contact_form]')&&function_exists('erased_package_active')&&erased_package_active('erased.contact-form')){
        try{
            $container=service_runtime()->container();
            if($container->has('erased.contact_form.block')){
                $html=str_replace('[contact_form]',$container->get('erased.contact_form.block')->render(),$html);
            }
        }catch(Throwable $e){}
    }
    return $html;
}
}
function erased_parse_gallery_shortcode(string $html): string {
    return preg_replace_callback('/\[gallery(?:\s+id=["\']?(\d+)["\']?)?\]/i', function($m) {
        $id = !empty($m[1]) ? (int)$m[1] : 0;
        $pdo = db();
        if ($id) {
            $s = $pdo->prepare("SELECT * FROM photo_galleries WHERE id=? AND status='published' LIMIT 1");
            $s->execute([$id]);
            $g = $s->fetch();
        } else {
            $g = $pdo->query("SELECT * FROM photo_galleries WHERE status='published' ORDER BY created_at DESC LIMIT 1")->fetch();
        }
        $photos = [];
        if ($g && !empty($g['images_json'])) {
            $photos = json_decode((string)$g['images_json'], true) ?: [];
        }
        if (!$photos) {
            $rows = $pdo->query("SELECT * FROM media WHERE mime_type LIKE 'image/%' ORDER BY uploaded_at DESC LIMIT 24")->fetchAll();
            foreach ($rows as $r) {
                $photos[] = ['url' => media_url($r), 'caption' => $r['caption'] ?: $r['alt_text'] ?: $r['original_name']];
            }
        }
        if (!$photos) return '<p class="muted">No photos in gallery yet.</p>';
        $html = '<div class="photo-album-grid">';
        $itemsJson = e(json_encode($photos, JSON_UNESCAPED_SLASHES));
        foreach ($photos as $idx => $p) {
            $url = e($p['url']);
            $cap = e($p['caption'] ?? '');
            $html .= '<div class="photo-album-item" role="button" tabindex="0" aria-label="Open photo in lightbox" onclick="openErasedLightbox('.$itemsJson.', '.$idx.')" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();openErasedLightbox('.$itemsJson.', '.$idx.')}"><img src="'.$url.'" alt="'.$cap.'" loading="lazy"><div class="photo-caption">'.$cap.'</div></div>';
        }
        $html .= '</div>';
        return $html;
    }, $html);
}
function public_article(array $i,bool $single=false): string{$f='';if(!empty($i['featured_media_id'])&&($m=media_by_id((int)$i['featured_media_id']))){$url=media_url($m);if(str_starts_with((string)$m['mime_type'],'video/'))$f='<video class="featured featured-video" controls preload="metadata" controlsList="nodownload"><source src="'.$url.'" type="'.e($m['mime_type']).'">Your browser cannot play this video.</video>';else{$dims=(!empty($m['width'])&&!empty($m['height']))?' width="'.(int)$m['width'].'" height="'.(int)$m['height'].'"':'';$f='<img class="featured" src="'.$url.'" alt="'.e($m['alt_text']).'"'.$dims.'>';}}$edit='';if(logged_in()&&!defined('ERASED_LIVE_PREVIEW_MODE')&&can_edit_content($i)){$label=tr('edit','site').' '.tr((string)$i['type'],'site');$edit='<a class="frontend-edit-action" href="/admin/content/'.(int)$i['id'].'/edit" aria-label="'.e($label).'" title="'.e($label).'">✎<span>'.e($label).'</span></a>';}$category=trim((string)($i['category']??''));$topic='';$dateSource=!empty($i['created_at'])?$i['created_at']:($i['updated_at']??'now');$minutes=reading_time((string)($i['body']??''));$readText=str_replace('{minutes}',(string)$minutes,tr($minutes===1?'minute_read':'minutes_read'));$meta='<p class="article-meta"><small>'.e(tr('created')).' '.e(date(setting('date_format','Y-m-d'),strtotime($dateSource))).' <span aria-hidden="true">·</span> '.e($readText).'</small></p>';$url='/'.rawurlencode((string)$i['slug']);$title=$single?'<h1>'.e($i['title']).'</h1>':'<h1><a class="article-title-link" href="'.$url.'">'.e($i['title']).'</a></h1>';$footer='';if(!$single){$actions='<a class="post-action" href="'.$url.'">'.e(tr(($i['type']??'post')==='page'?'open_page':'open_post')).'</a>';if(($i['type']??'')==='post'&&(int)($i['comments_enabled']??1)){$actions.='<a class="post-action" href="'.$url.'#comments">'.e(tr('comments')).'</a>';}$footer='<div class="article-card-actions">'.$actions.'</div>';}$article='<article class="card article-page"><div class="article-heading">'.$edit.$topic.$title.$meta.'</div>'.$f.'<div class="article-body">'.erased_public_content_html((string)($i['body']??'')).'</div>'.$footer.'</article>';return $article.($single?comments_html($i):''); }

// Was previously set only on the public-route branch (line ~1314, after
// routes/admin.php already ran and exited for any /admin/* request) - every
// admin-side date/time function (strtotime(), date()) ran in PHP's UTC
// ini default instead of the site's configured timezone, while MySQL's
// NOW() is also UTC. That accidental UTC-vs-UTC match on the admin side
// masked the real bug: an admin typing a "Publish at" time means it in the
// site's configured local timezone, not UTC, so the naive string stored
// still needs an explicit local-to-UTC conversion at the one place it's
// written (the /admin/publishing schedule handler) - moving this line
// earlier only fixes admin-side date *display* consistency, not that.
if(installed()){
 if($tz=setting('timezone')){try{date_default_timezone_set($tz);}catch(Throwable $e){}}
 erased_track_visit($path);erased_waf_inspect();
}
require ROOT.'/routes/gallery.php';
require __DIR__.'/../routes/install.php';
require ROOT.'/routes/newsletter.php';
require ROOT.'/routes/auth.php';
require ROOT.'/routes/debug.php';
require ROOT.'/routes/admin.php';
if(str_starts_with($path,'/admin/')){
 $pluginMatch=plugin_admin_surface()->router()->match($_SERVER['REQUEST_METHOD'],$path);
 if($pluginMatch!==null){plugin_admin_surface()->router()->call($pluginMatch);exit;}
}
if($path==='/comments/submit'&&$_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$id=(int)($_POST['content_id']??0);$q=db()->prepare("SELECT id,slug,type,status,comments_enabled FROM content WHERE id=? LIMIT 1");$q->execute([$id]);$item=$q->fetch();if(!$item||$item['type']!=='post'||$item['status']!=='published'||!(int)$item['comments_enabled']){http_response_code(404);exit(tr('comments_unavailable'));}
 $target='/'.$item['slug'].'#comments';
 if(erased_comment_captcha_enabled()&&!erased_verify_comment_captcha()){
  flash('error',tr('comment_captcha_failed'));
  redirect($target);
 }
 $name=trim((string)($_POST['author_name']??''));$email=trim((string)($_POST['author_email']??''));$body=trim((string)($_POST['body']??''));$honeypot=trim((string)($_POST['website']??''));$ip=erased_client_ip();$ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);$started=(int)($_SESSION['comment_form_started'][$id]??0);unset($_SESSION['comment_form_started'][$id]);
 $reason='';if($honeypot!=='')$reason='honeypot';elseif($started===0||time()-$started<3)$reason='submitted_too_fast';elseif(time()-$started>7200)$reason='expired_form';elseif($name===''||mb_strlen($name)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($body)<3||mb_strlen($body)>3000)$reason='invalid_input';elseif(preg_match_all('~https?://|www\.~i',$body)>2)$reason='too_many_links';elseif(preg_match('/(.)\1{12,}/u',$body))$reason='repeated_characters';
 $rate=db()->prepare("SELECT COUNT(*) FROM comments WHERE ip_address=? AND created_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)");$rate->execute([$ip]);if((int)$rate->fetchColumn()>=3)$reason='rate_limit';
 if($reason!==''){if($reason==='rate_limit'){flash('error',tr('too_many_comments'));redirect($target);}audit('comment.blocked',['content_id'=>$id,'reason'=>$reason]);flash('success',tr('comment_received'));redirect($target);}
 $dupe=db()->prepare("SELECT COUNT(*) FROM comments WHERE content_id=? AND author_email=? AND body=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 DAY)");$dupe->execute([$id,$email,$body]);if((int)$dupe->fetchColumn()>0){flash('success',tr('duplicate_comment'));redirect($target);}
 $status=logged_in()&&can('comments.manage')?'approved':'pending';$ins=db()->prepare('INSERT INTO comments(content_id,author_name,author_email,body,status,ip_address,user_agent,spam_reason,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');$ins->execute([$id,$name,$email,$body,$status,$ip,$ua,'']);audit('comment.submit',['content_id'=>$id,'status'=>$status]);flash('success',$status==='approved'?tr('comment_posted'):tr('comment_waiting'));redirect($target);
}
if($path==='/contact-form/submit'&&$_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $target=safe_return('/');
 if(!function_exists('erased_package_active')||!erased_package_active('erased.contact-form')){http_response_code(404);exit('Not found');}
 if(erased_comment_captcha_enabled()&&!erased_verify_comment_captcha()){
  flash('error',tr('comment_captcha_failed'));
  redirect($target);
 }
 $repoFile=null;
 foreach([__DIR__.'/../storage/plugins/installed/erased.contact-form/src/Domain/ContactSubmissionRepository.php',__DIR__.'/../packages/erased.contact-form/src/Domain/ContactSubmissionRepository.php'] as $candidate){if(is_file($candidate)){$repoFile=$candidate;break;}}
 if($repoFile===null){http_response_code(404);exit('Not found');}
 require_once $repoFile;

 $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));$subject=trim((string)($_POST['subject']??''));$message=trim((string)($_POST['message']??''));$honeypot=trim((string)($_POST['website']??''));$ip=erased_client_ip();
 $started=(int)($_SESSION['contact_form_started']??0);unset($_SESSION['contact_form_started']);
 $reason='';if($honeypot!=='')$reason='honeypot';elseif($started===0||time()-$started<3)$reason='submitted_too_fast';elseif(time()-$started>7200)$reason='expired_form';elseif($name===''||mb_strlen($name)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($message)<3||mb_strlen($message)>5000)$reason='invalid_input';elseif(preg_match_all('~https?://|www\.~i',$message)>2)$reason='too_many_links';

 $repo=new \ErasedContactForm\Domain\ContactSubmissionRepository(db());
 if($reason===''&&$repo->recentFromIp($ip,10)>=3)$reason='rate_limit';
 if($reason!==''){
  if($reason==='rate_limit'){flash('error','Too many messages sent recently - please wait a bit and try again.');redirect($target);}
  audit('contact_form.blocked',['reason'=>$reason]);flash('success','Thanks - your message has been sent.');redirect($target);
 }
 $repo->create(['name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message,'ip_address'=>$ip]);
 audit('contact_form.submit',['email'=>$email]);
 flash('success','Thanks - your message has been sent.');
 redirect($target);
}
if(preg_match('#^/media/video/(\d+)/([a-f0-9]{24})$#',$path,$match)){
 $media=media_by_id((int)$match[1]);
 if(!$media||!str_starts_with((string)$media['mime_type'],'video/')||!hash_equals(media_stream_token($media),(string)$match[2])){http_response_code(404);exit('Not found');}
 $file=UPLOAD_DIR.'/'.basename((string)$media['stored_name']);
 if(!is_file($file)){http_response_code(404);exit('Not found');}
 $size=filesize($file);if($size===false){http_response_code(500);exit('Could not read media');}
 $start=0;$end=$size-1;$status=200;
 header('Content-Type: '.((string)$media['mime_type']?:'application/octet-stream'));
 header('Accept-Ranges: bytes');
 header('X-Content-Type-Options: nosniff');
 header('Content-Disposition: inline');
 header('Cache-Control: private, max-age=3600');
 if(isset($_SERVER['HTTP_RANGE'])&&preg_match('/bytes=(\d*)-(\d*)/',(string)$_SERVER['HTTP_RANGE'],$range)){
  if($range[1]===''&&$range[2]!==''){$length=min((int)$range[2],$size);$start=max(0,$size-$length);}else{$start=$range[1]!==''?(int)$range[1]:0;$end=$range[2]!==''?min((int)$range[2],$size-1):$size-1;}
  if($start>$end||$start>=$size){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
  $status=206;header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
 }
 $length=$end-$start+1;http_response_code($status);header('Content-Length: '.$length);
 if($_SERVER['REQUEST_METHOD']==='HEAD')exit;
 $handle=fopen($file,'rb');if($handle===false){http_response_code(500);exit('Could not open media');}
 fseek($handle,$start);$remaining=$length;
 while($remaining>0&&!feof($handle)){$chunk=fread($handle,min(1024*1024,$remaining));if($chunk===false||$chunk==='')break;echo $chunk;$remaining-=strlen($chunk);if(connection_aborted())break;flush();}
 fclose($handle);exit;
}
if(preg_match('#^/media/([a-f0-9]{32,64}(?:_thumb)?\.(?:jpg|png|gif|webp|pdf))$#',$path,$m)){$file=UPLOAD_DIR.'/'.$m[1];if(!is_file($file)){http_response_code(404);exit('Not found');}$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file)?:'application/octet-stream';header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline');header('Cache-Control: public, max-age=31536000, immutable');readfile($file);exit;}
if(preg_match('#^/theme-asset/([a-z0-9][a-z0-9._-]*)/([a-zA-Z0-9._-]+\.(?:css|js|png|jpg|jpeg|svg|webp|woff2?|ttf))$#',$path,$m)){
 $themePkg=(new \Erased\Packages\InstalledPackageRepository(db()))->find($m[1]);
 if(!$themePkg||$themePkg['package_type']!=='theme'||!$themePkg['enabled']){http_response_code(404);exit('Not found');}
 $themeDir=realpath((string)$themePkg['installed_path']);
 $themeTarget=$themeDir!==false?realpath($themeDir.'/'.$m[2]):false;
 if($themeDir===false||$themeTarget===false||!str_starts_with($themeTarget,$themeDir.DIRECTORY_SEPARATOR)||!is_file($themeTarget)){http_response_code(404);exit('Not found');}
 $themeMime=['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','svg'=>'image/svg+xml','webp'=>'image/webp','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf'][strtolower(pathinfo($themeTarget,PATHINFO_EXTENSION))]??'application/octet-stream';
 header('Content-Type: '.$themeMime);header('Content-Length: '.filesize($themeTarget));header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline');header('Cache-Control: public, max-age=86400');
 readfile($themeTarget);exit;
}
if(preg_match('#^/package-asset/([a-z0-9][a-z0-9._-]*)/([a-zA-Z0-9._-]+\.(?:css|js|png|jpg|jpeg|svg|webp|woff2?|ttf))$#',$path,$m)){
 $assetPkg=(new \Erased\Packages\InstalledPackageRepository(db()))->find($m[1]);
 if(!$assetPkg||!$assetPkg['enabled']){http_response_code(404);exit('Not found');}
 $assetDir=realpath((string)$assetPkg['installed_path']);
 $assetTarget=$assetDir!==false?realpath($assetDir.'/'.$m[2]):false;
 if($assetDir===false||$assetTarget===false||!str_starts_with($assetTarget,$assetDir.DIRECTORY_SEPARATOR)||!is_file($assetTarget)){http_response_code(404);exit('Not found');}
 $assetMime=['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','svg'=>'image/svg+xml','webp'=>'image/webp','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf'][strtolower(pathinfo($assetTarget,PATHINFO_EXTENSION))]??'application/octet-stream';
 header('Content-Type: '.$assetMime);header('Content-Length: '.filesize($assetTarget));header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline');header('Cache-Control: public, max-age=86400');
 readfile($assetTarget);exit;
}
if(preg_match('#^/(shop|cart|checkout|commerce)(?:/.*)?$#',$path)===1){
 $commercePkg=(new \Erased\Packages\InstalledPackageRepository(db()))->find('erased.commerce');
 if($commercePkg&&$commercePkg['enabled']){
  try{
   $container=service_runtime()->container();
   if($container->has('erased.commerce.storefront')){
    $storefront=$container->get('erased.commerce.storefront');
    if(method_exists($storefront,'dispatch')&&$storefront->dispatch($path)){exit;}
   }
  }catch(Throwable $e){/* falls through to the real 404 */}
 }
}
if($path!=='/' && ($rq=db()->prepare('SELECT target_path,status_code FROM redirects WHERE source_path=? LIMIT 1'))){$rq->execute([$path]);if($red=$rq->fetch()){header('Location: '.$red['target_path'],true,(int)$red['status_code']);exit;}}
if(setting('maintenance_mode')==='1'&&!logged_in()){http_response_code(503);layout(tr('maintenance_title'),'<div class="card"><h1>'.e(tr('maintenance_title')).'</h1><p>'.e(tr('maintenance_message')).'</p></div>');exit;}
require_once ROOT.'/routes/public.php';
erased_dispatch_public_route($path);
