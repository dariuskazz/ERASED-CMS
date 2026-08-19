<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap/app.php';
/*
|--------------------------------------------------------------------------
| ERASED CMS Security Headers
|--------------------------------------------------------------------------
*/

$https =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');

header('Cross-Origin-Resource-Policy: same-origin');
header('Cross-Origin-Opener-Policy: same-origin');
header('X-Permitted-Cross-Domain-Policies: none');
header('Origin-Agent-Cluster: ?1');

header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none';");

if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
 ini_set('session.use_strict_mode','1');
 ini_set('session.use_only_cookies','1');
 ini_set('session.cookie_httponly','1');
 ini_set('session.cookie_samesite','Lax');
 if(PHP_VERSION_ID<80400){
  ini_set('session.sid_length','48');
  ini_set('session.sid_bits_per_character','6');
 }
 if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ini_set('session.cookie_secure','1');
 session_start();
}
$_SERVER['ERASED_REQUEST_ID'] ??= bin2hex(random_bytes(8));
const ROOT = __DIR__ . '/..';

function cms_version(): string {
 static $version = null;
 if ($version !== null) return $version;
 $file = ROOT . '/VERSION';
 $value = is_file($file) ? trim((string) file_get_contents($file)) : '';
 return $version = ($value !== '' ? $value : '0.9-beta');
}
function cms_version_label(): string {
 $version = cms_version();
 if (preg_match('/^(\d+)\.(\d+)(?:\.\d+)?-beta(?:[.-]?\d+)?$/i', $version, $m)) return $m[1] . '.' . $m[2] . ' Beta';
 return ltrim($version, 'vV');
}
function cms_name(): string { return 'ERASED CMS'; }
function cms_full_name(): string { return cms_name() . ' ' . cms_version_label(); }

const CONFIG_FILE = ROOT . '/storage/config.php';
const UPLOAD_DIR = ROOT . '/storage/uploads';
function installed(): bool { return is_file(CONFIG_FILE); }
function config(): array { if (!installed()) return []; $cfg=require CONFIG_FILE; return is_array($cfg)?$cfg:[]; }
function db(): PDO {
 static $pdo; if($pdo instanceof PDO)return $pdo;
 $c=config()['db']??[]; foreach(['host','name','user','pass'] as $k)if(!array_key_exists($k,$c))throw new RuntimeException('Invalid database configuration.');
 $pdo=new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); run_migrations($pdo); (new \Erased\Core\MigrationRunner($pdo, ROOT.'/database/migrations'))->run(); return $pdo;
}
/**
 * Platform Foundation accessors (docs/PLATFORM-FOUNDATION.md) - lazy,
 * per-request singletons mirroring db()'s own pattern above. Rebuilt from
 * the real installed_packages table on first access each request; call
 * ->refresh() on the returned object after any install/enable/disable/
 * uninstall so the in-request view stays consistent with what just changed.
 */
function capability_runtime(): \Erased\Capabilities\InstalledCapabilityRuntime {
 static $runtime; if($runtime instanceof \Erased\Capabilities\InstalledCapabilityRuntime)return $runtime;
 $runtime=new \Erased\Capabilities\InstalledCapabilityRuntime(new \Erased\Packages\InstalledPackageRepository(db()));
 return $runtime;
}
function service_runtime(): \Erased\Container\InstalledServiceRuntime {
 static $runtime; if($runtime instanceof \Erased\Container\InstalledServiceRuntime)return $runtime;
 $runtime=new \Erased\Container\InstalledServiceRuntime(new \Erased\Packages\InstalledPackageRepository(db()),new \Erased\Container\ServiceContainer(),new \Erased\Container\PackageServiceRegistrar());
 $runtime->refresh();
 return $runtime;
}
function platform_events(): \Erased\Events\EventDispatcher {
 static $dispatcher; if($dispatcher instanceof \Erased\Events\EventDispatcher)return $dispatcher;
 $dispatcher=new \Erased\Events\EventDispatcher();
 return $dispatcher;
}
function package_license_checker(): \Erased\Packages\PackageLicenseChecker {
 static $checker; if($checker instanceof \Erased\Packages\PackageLicenseChecker)return $checker;
 $checker=new \Erased\Packages\PackageLicenseChecker(new \Erased\Packages\LocalLicenseGate(),new \Erased\Packages\PackageLicenseRepository(db()));
 return $checker;
}
/**
 * Unlike capability_runtime()/service_runtime(), this guards its own
 * refresh() in a try/catch: it is the first thing that calls
 * service_runtime() unconditionally on every /admin/* request (to check
 * each declared route's service_id), and InstalledServiceRuntime::refresh()
 * rethrows on any single enabled package's malformed services block while
 * leaving its static-cached instance behind cleared-but-populated - so an
 * unguarded call here would turn "one broken package" into "every admin
 * page fails for the rest of this request." A malformed package's admin
 * surface just fails to register (see errors()) instead.
 */
function plugin_admin_surface(): \Erased\Admin\InstalledPluginAdminSurface {
 static $runtime; if($runtime instanceof \Erased\Admin\InstalledPluginAdminSurface)return $runtime;
 $runtime=new \Erased\Admin\InstalledPluginAdminSurface(new \Erased\Packages\InstalledPackageRepository(db()));
 try{$runtime->refresh();}catch(\Throwable){}
 return $runtime;
}
/**
 * Splits a dump into individual statements without cutting through a `;\n`
 * sequence that happens to appear inside a quoted string literal (a post
 * body containing "...end of sentence;\nNext paragraph..." is entirely
 * plausible) - the previous plain explode(";\n",...) did exactly that,
 * silently truncating or corrupting the following statement. Tracks single-
 * quote and backtick context char-by-char, matching how PDO::quote() escapes
 * embedded quotes (backslash-escaped for MySQL), so a `;` only ends a
 * statement when it's genuinely outside any string/identifier.
 * @return list<string>
 */
function erased_split_sql_statements(string $sql): array {
 $statements=[];$current='';$length=strlen($sql);$inString=false;$inBacktick=false;
 for($i=0;$i<$length;$i++){
  $char=$sql[$i];$current.=$char;
  if($inString){
   if($char==='\\'&&$i+1<$length){$current.=$sql[++$i];continue;}
   if($char==="'")$inString=false;
   continue;
  }
  if($inBacktick){
   if($char==='`')$inBacktick=false;
   continue;
  }
  if($char==="'"){$inString=true;continue;}
  if($char==='`'){$inBacktick=true;continue;}
  if($char===';'&&$i+1<$length&&$sql[$i+1]==="\n"){$current.="\n";$i++;$statements[]=$current;$current='';}
 }
 if(trim($current)!=='')$statements[]=$current;
 return $statements;
}
/** Zips storage/uploads into the backups directory alongside a .sql dump - null (not an error) when there's nothing to back up yet. */
function erased_backup_uploads(string $dir, string $baseName): ?string {
 $uploadsDir=UPLOAD_DIR;
 if(!is_dir($uploadsDir))return null;
 $files=array_values(array_filter(scandir($uploadsDir)?:[],static fn($f)=>$f!=='.'&&$f!=='..'));
 if(!$files)return null;
 $zipName=$baseName.'-media.zip';
 $zipPath=$dir.'/'.$zipName;
 $zip=new ZipArchive();
 if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Could not create the media backup archive.');
 foreach($files as $file){
  $full=$uploadsDir.'/'.$file;
  if(is_file($full))$zip->addFile($full,$file);
 }
 $zip->close();
 @chmod($zipPath,0640);
 return $zipName;
}
function backup_database(): string {
 $cfg=config()['db']??[];
 foreach(['host','name','user','pass'] as $key)if(!array_key_exists($key,$cfg))throw new RuntimeException('Invalid database configuration.');
 $dir=ROOT.'/storage/backups';
 if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Could not create backup directory.');
 @chmod($dir,0750);
 $baseName='erased-cms-'.date('Y-m-d-His');
 $filename=$baseName.'.sql';
 $path=$dir.'/'.$filename;
 $pdo=db();
 $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
 $out=fopen($path,'wb');
 if($out===false)throw new RuntimeException('Could not create backup file.');
 try{
  fwrite($out,"-- ERASED CMS database backup\n-- Generated: ".date('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
  foreach($tables as $table){
   $table=(string)$table;
   if(!preg_match('/^[A-Za-z0-9_]+$/',$table))continue;
   $quoted='`'.str_replace('`','``',$table).'`';
   $create=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM);
   if(!$create||!isset($create[1]))continue;
   fwrite($out,"DROP TABLE IF EXISTS {$quoted};\n".$create[1].";\n\n");
   $rows=$pdo->query('SELECT * FROM '.$quoted);
   while($row=$rows->fetch(PDO::FETCH_ASSOC)){
    $columns=array_map(static fn($column)=>'`'.str_replace('`','``',(string)$column).'`',array_keys($row));
    $values=[];
    foreach($row as $value)$values[]=$value===null?'NULL':$pdo->quote((string)$value);
    fwrite($out,'INSERT INTO '.$quoted.' ('.implode(',',$columns).') VALUES ('.implode(',',$values).');'."\n");
   }
   fwrite($out,"\n");
  }
  fwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");
 }catch(Throwable $e){fclose($out);@unlink($path);throw $e;}
 fclose($out);
 @chmod($path,0640);
 erased_backup_uploads($dir,$baseName);
 try {
  $retention = max(1, (int)setting('backup_retention', '10'));
  $all = array_values(array_filter(scandir($dir) ?: [], fn($f) => str_ends_with($f, '.sql')));
  rsort($all);
  if (count($all) > $retention) {
   foreach (array_slice($all, $retention) as $oldFile) {
    @unlink($dir . '/' . $oldFile);
    @unlink($dir . '/' . substr($oldFile, 0, -4) . '-media.zip');
   }
  }
 } catch (Throwable $e) {}
  audit('backup.database.created',['file'=>$filename,'tables'=>count($tables)]);
  return $filename;
}
function restore_database(string $filename): void {
 $filename=basename($filename);
 if(!str_ends_with($filename,'.sql'))throw new RuntimeException('Invalid backup file extension.');
 $dir=ROOT.'/storage/backups';
 $path=$dir.'/'.$filename;
 if(!is_file($path))throw new RuntimeException('Backup file not found.');
 $sql=file_get_contents($path);
 if($sql===false||trim($sql)==='')throw new RuntimeException('Backup file is empty.');
 $pdo=db();
 $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
 $failedStatements=0;$firstError='';
 try {
  $queries=erased_split_sql_statements($sql);
  foreach($queries as $q){
   $q=trim($q);
   if($q===''||str_starts_with($q,'--'))continue;
   try{$pdo->exec($q);}catch(Throwable $e){$failedStatements++;if($firstError==='')$firstError=$e->getMessage();}
  }
 } finally {
  $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
 }
 $mediaZip=$dir.'/'.substr($filename,0,-4).'-media.zip';
 $mediaRestored=0;
 if(is_file($mediaZip)){
  $zip=new ZipArchive();
  if($zip->open($mediaZip)===true){
   if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0770,true);
   $zip->extractTo(UPLOAD_DIR);
   $mediaRestored=$zip->numFiles;
   $zip->close();
  }
 }
 audit('backup.database.restored',['file'=>$filename,'failed_statements'=>$failedStatements,'media_files_restored'=>$mediaRestored]);
 if($failedStatements>0)throw new RuntimeException("Restore finished but {$failedStatements} statement(s) failed and were skipped - the database may be incomplete. First error: {$firstError}");
}
function run_migrations(PDO $pdo): void {
 static $done=false;if($done)return;
 // Everything below this point used to run unconditionally on every single
 // request (6 DDL statements + 56 INSERT IGNORE round-trips for defaults),
 // since $done above only survives within one request, not across them.
 // This version marker turns the common case into a single cheap SELECT.
 // Bump $seedVersion whenever $defaults or the DDL below changes, so
 // existing installs pick up the change on their next request.
 $seedVersion='2026-08-08.1';
 try{
  $current=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='_schema_seed_version'")->fetchColumn();
  if($current===$seedVersion){$done=true;return;}
 }catch(Throwable $e){/* settings table doesn't exist yet - fall through and create it */}
 $pdo->exec("CREATE TABLE IF NOT EXISTS media (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL UNIQUE, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, alt_text VARCHAR(255) NOT NULL DEFAULT '', caption TEXT NULL, uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(190) PRIMARY KEY, setting_value LONGTEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("ALTER TABLE content ADD COLUMN IF NOT EXISTS featured_media_id INT UNSIGNED NULL AFTER status");
 $pdo->exec("ALTER TABLE content ADD COLUMN IF NOT EXISTS excerpt TEXT NULL AFTER body");
 $pdo->exec("ALTER TABLE media ADD COLUMN IF NOT EXISTS alt_text VARCHAR(255) NOT NULL DEFAULT '' AFTER size_bytes");
 $pdo->exec("ALTER TABLE media ADD COLUMN IF NOT EXISTS caption TEXT NULL AFTER alt_text");
 $defaults=['site_name'=>'ERASED CMS','admin_language'=>'en','show_language_switcher'=>'1','detect_browser_language'=>'1','site_tagline'=>'Site tagline','homepage_content_id'=>'','posts_content_id'=>'','posts_per_page'=>'10','site_language'=>'en','timezone'=>'Europe/Oslo','date_format'=>'Y-m-d','logo_media_id'=>'','logo_dark_media_id'=>'','logo_light_media_id'=>'','favicon_media_id'=>'','branding_mode'=>'builtin','logo_show_title'=>'0','logo_height'=>'42','logo_width'=>'240','header_text'=>'','footer_text'=>'© '.date('Y').' ERASED CMS','seo_title'=>'','seo_description'=>'','social_github'=>'','social_x'=>'','social_youtube'=>'','custom_css'=>'','maintenance_mode'=>'0','comments_enabled'=>'0','registration_enabled'=>'0','admin_ip_allowlist'=>'','rate_limit_login'=>'8','ip_lockout_enabled'=>'1','ip_lockout_threshold'=>'8','ip_lockout_window_minutes'=>'15','ip_lockout_duration_minutes'=>'30','password_min_length'=>'8','password_require_uppercase'=>'1','password_require_lowercase'=>'0','password_require_number'=>'0','password_require_symbol'=>'0','session_timeout_minutes'=>'30','payment_gateway'=>'none','payment_currency'=>'NOK','payment_statement_descriptor'=>'ERASED','payment_success_url'=>'/payment/success','payment_cancel_url'=>'/payment/cancel','payment_webhook_url'=>'/webhooks/payments','payment_stripe_enabled'=>'0','payment_paypal_enabled'=>'0','payment_vipps_enabled'=>'0','payment_klarna_enabled'=>'0','payment_bank_enabled'=>'0','payment_test_mode'=>'1','cloudflare_enabled'=>'0','backup_retention'=>'10'];
 $s=$pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)');foreach($defaults as $k=>$v)$s->execute([$k,$v]);erased_migrate_sensitive_settings($pdo);$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['_schema_seed_version',$seedVersion]);$done=true;
}
if (!function_exists('erased_sensitive_setting')) {
function erased_sensitive_setting(string $key): bool {
 return $key==='smtp_password'
  || preg_match('/(?:^|_)(?:password|secret(?:_key)?|private_key|access_token|subscription_key)$/i',$key)===1;
}
}
if (!function_exists('erased_settings_key')) {
function erased_settings_key(): string {
 static $key=null;
 if(is_string($key))return $key;
 $environment=getenv('ERASED_SETTINGS_KEY');
 if(is_string($environment)&&trim($environment)!=='')return $key=hash('sha256',$environment,true);
 $directory=ROOT.'/storage';
 $path=$directory.'/settings.key';
 if(is_file($path)){
  $decoded=base64_decode(trim((string)file_get_contents($path)),true);
  if(!is_string($decoded)||strlen($decoded)!==32)throw new RuntimeException('The sensitive-settings key is invalid.');
  return $key=$decoded;
 }
 if(!is_dir($directory)&&!mkdir($directory,0770,true)&&!is_dir($directory))throw new RuntimeException('Could not create the storage directory for the sensitive-settings key.');
 $generated=random_bytes(32);
 $temporary=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
 if(file_put_contents($temporary,base64_encode($generated)."\n",LOCK_EX)===false)throw new RuntimeException('Could not create the sensitive-settings key.');
 @chmod($temporary,0600);
 if(!@rename($temporary,$path)){
  @unlink($temporary);
  if(!is_file($path))throw new RuntimeException('Could not activate the sensitive-settings key.');
  $decoded=base64_decode(trim((string)file_get_contents($path)),true);
  if(!is_string($decoded)||strlen($decoded)!==32)throw new RuntimeException('The sensitive-settings key is invalid.');
  return $key=$decoded;
 }
 return $key=$generated;
}
}
if (!function_exists('erased_encrypt_setting')) {
function erased_encrypt_setting(string $value): string {
 if($value===''||str_starts_with($value,'enc:v1'))return $value;
 $key=erased_settings_key();
 if(function_exists('sodium_crypto_secretbox')){
  $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $cipher=sodium_crypto_secretbox($value,$nonce,$key);
  return 'enc:v1s:'.base64_encode($nonce.$cipher);
 }
 if(function_exists('openssl_encrypt')){
  $nonce=random_bytes(12);$tag='';
  $cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,'ERASED CMS settings',16);
  if(!is_string($cipher)||strlen($tag)!==16)throw new RuntimeException('Sensitive setting encryption failed.');
  return 'enc:v1o:'.base64_encode($nonce.$tag.$cipher);
 }
 throw new RuntimeException('Sensitive settings require the Sodium or OpenSSL PHP extension.');
}
}
if (!function_exists('erased_decrypt_setting')) {
function erased_decrypt_setting(string $value): string {
 if(!str_starts_with($value,'enc:v1'))return $value;
 $encoded=substr($value,8);
 $payload=base64_decode($encoded,true);
 if(!is_string($payload))throw new RuntimeException('A sensitive setting is corrupted.');
 $key=erased_settings_key();
 if(str_starts_with($value,'enc:v1s:')&&function_exists('sodium_crypto_secretbox_open')){
  $nonceLength=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
  if(strlen($payload)<$nonceLength+SODIUM_CRYPTO_SECRETBOX_MACBYTES)throw new RuntimeException('A sensitive setting is corrupted.');
  $plain=sodium_crypto_secretbox_open(substr($payload,$nonceLength),substr($payload,0,$nonceLength),$key);
  if(!is_string($plain))throw new RuntimeException('A sensitive setting could not be decrypted.');
  return $plain;
 }
 if(str_starts_with($value,'enc:v1o:')&&function_exists('openssl_decrypt')){
  if(strlen($payload)<29)throw new RuntimeException('A sensitive setting is corrupted.');
  $plain=openssl_decrypt(substr($payload,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($payload,0,12),substr($payload,12,16),'ERASED CMS settings');
  if(!is_string($plain))throw new RuntimeException('A sensitive setting could not be decrypted.');
  return $plain;
 }
 throw new RuntimeException('The PHP extension needed to decrypt a sensitive setting is unavailable.');
}
}
if (!function_exists('erased_migrate_sensitive_settings')) {
function erased_migrate_sensitive_settings(PDO $pdo): void {
 try{
  $rows=$pdo->query('SELECT setting_key,setting_value FROM settings')->fetchAll();
  $update=$pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');
  foreach($rows as $row){
   $key=(string)($row['setting_key']??'');$value=(string)($row['setting_value']??'');
   if($value!==''&&erased_sensitive_setting($key)&&!str_starts_with($value,'enc:v1'))$update->execute([erased_encrypt_setting($value),$key]);
  }
 }catch(Throwable $e){
  error_log('ERASED CMS could not migrate sensitive settings: '.$e->getMessage());
 }
}
}
/** Shared per-request cache backing setting()/set_setting() - a single request can call setting() 15-20+ times just rendering layout(). */
function &erased_setting_cache(): array { static $cache=[]; return $cache; }
function setting(string $key,string $default=''): string {
 if(isset($GLOBALS['erased_setting_overrides'][$key]))return $GLOBALS['erased_setting_overrides'][$key];
 if(!installed())return $default;
 $cache=&erased_setting_cache();
 if(array_key_exists($key,$cache))return $cache[$key]===null?$default:$cache[$key];
 $s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();
 if($v===false){$cache[$key]=null;return $default;}
 $value=(string)$v;
 $value=str_starts_with($value,'enc:v1')?erased_decrypt_setting($value):$value;
 $cache[$key]=$value;
 return $value;
}
/**
 * Non-persistent per-request override, used only to preview an inactive
 * website profile: overridden keys are read by setting() for the rest of
 * this request but nothing is written to the database, so a preview can
 * never affect the live site or any other request.
 * @param array<string,string> $overrides
 */
function erased_apply_setting_overrides(array $overrides): void { $GLOBALS['erased_setting_overrides']=$overrides; }
/**
 * Resolves an admin_theme/website_theme setting value to an installed custom
 * theme package's CSS href, or null when the value is a built-in slug (no
 * "package:" prefix) or the referenced package doesn't exist, isn't enabled,
 * isn't type=theme, or doesn't match the requested scope. Never trusts the
 * setting value directly as a URL - only ever derives a /theme-asset/ href
 * from the package's own validated manifest.
 * @return array{css_href:string,manifest:array<string,mixed>}|null
 */
function erased_resolve_theme_package(string $settingValue, string $expectedScope): ?array {
 if(!str_starts_with($settingValue,'package:'))return null;
 $id=substr($settingValue,8);
 if($id===''||!preg_match('/^[a-z0-9][a-z0-9._-]*$/',$id))return null;
 $package=(new \Erased\Packages\InstalledPackageRepository(db()))->find($id);
 if(!$package||$package['package_type']!=='theme'||!$package['enabled'])return null;
 $manifest=$package['manifest']??[];
 if(($manifest['theme_scope']??null)!==$expectedScope)return null;
 $assets=$manifest['assets']??null;
 if(!is_string($assets)||!str_ends_with(strtolower($assets),'.css'))return null;
 $result=['css_href'=>'/theme-asset/'.rawurlencode($id).'/'.$assets,'manifest'=>$manifest];
 // Optional: a theme may also declare a plain "*.js" file (e.g. for a
 // client-side light/dark switcher) - same /theme-asset/ delivery path,
 // validated the same way as assets in PackageManifest. Absent for themes
 // that don't need one; core never assumes one exists.
 $scripts=$manifest['scripts']??null;
 if(is_string($scripts)&&str_ends_with(strtolower($scripts),'.js')){
  $result['js_href']='/theme-asset/'.rawurlencode($id).'/'.$scripts;
 }
 return $result;
}
function set_setting(string $key,string $value): void {
 if($value!==''&&erased_sensitive_setting($key))$value=erased_encrypt_setting($value);
 $s=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$s->execute([$key,$value]);
 $cache=&erased_setting_cache();$cache[$key]=str_starts_with($value,'enc:v1')?erased_decrypt_setting($value):$value;
}
function media_by_id(int $id): ?array {
 if($id<1)return null;
 $q=db()->prepare('SELECT * FROM media WHERE id=? LIMIT 1');
 $q->execute([$id]);
 $row=$q->fetch();
 return is_array($row)?$row:null;
}
function fetch_or_404(string $table, int $id): array {
 if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$table))throw new InvalidArgumentException('Invalid table name.');
 $q=db()->prepare('SELECT * FROM `'.$table.'` WHERE id=? LIMIT 1');
 $q->execute([$id]);
 $row=$q->fetch();
 if(!is_array($row)){http_response_code(404);exit('Not found');}
 return $row;
}
function erased_app_secret(): string{
 $secret=setting('app_secret','');
 if($secret===''){
  $secret=bin2hex(random_bytes(32));
  set_setting('app_secret',$secret);
 }
 return $secret;
}
function media_stream_token(array $media): string {
 return substr(hash_hmac('sha256',(string)($media['id']??0),erased_app_secret()),0,24);
}
function media_url(array $media): string {
 $stored=basename((string)($media['stored_name']??''));
 if($stored==='')return '';
 if(str_starts_with((string)($media['mime_type']??''),'video/'))return '/media/video/'.(int)($media['id']??0).'/'.media_stream_token($media);
 return '/media/'.rawurlencode($stored);
}
/** Small JPEG thumbnail URL for grid/list display - falls back to the full media_url() when no thumbnail was generated (GD unavailable, decode failure, or the original was already small). */
function media_thumb_url(array $media): string {
 if(empty($media['has_thumb']))return media_url($media);
 $stored=basename((string)($media['stored_name']??''));
 if($stored==='')return media_url($media);
 return '/media/'.rawurlencode(pathinfo($stored,PATHINFO_FILENAME).'_thumb.jpg');
}
if (!function_exists('install_schema')) {
function install_schema(PDO $pdo): void {
 $file=ROOT.'/database/schema.sql';
 if(!is_file($file)||!is_readable($file))throw new RuntimeException('The installation schema is missing or unreadable.');
 $schema=(string)file_get_contents($file);
 if(trim($schema)==='')throw new RuntimeException('The installation schema is empty.');
 $statements=preg_split('/;\s*(?:\r?\n|$)/',$schema)?:[];
 try{
  foreach($statements as $statement){
   $statement=trim($statement);
   if($statement==='')continue;
   $pdo->exec($statement);
  }
 }catch(Throwable $e){
  throw new RuntimeException('Database installation failed: '.$e->getMessage(),0,$e);
 }
}
}
if (!function_exists('safe_return')) {
function safe_return(string $default='/'): string {
 $candidate=trim((string)($_POST['return_to']??$_GET['return_to']??''));
 if($candidate===''||str_contains($candidate,"\r")||str_contains($candidate,"\n"))return $default;
 $parts=parse_url($candidate);
 if($parts===false||isset($parts['scheme'])||isset($parts['host'])||isset($parts['user'])||isset($parts['pass']))return $default;
 $path=(string)($parts['path']??'');
 // Browsers normalize a leading backslash to a forward slash for special
 // schemes (WHATWG URL spec), so a Location/return_to of e.g. '/\evil.com'
 // has no scheme/host under parse_url() and doesn't start with '//', but
 // still resolves as a protocol-relative redirect off-site. Reject any
 // backslash anywhere in the path, not just a literal leading '//'.
 if($path===''||$path[0]!=='/'||str_starts_with($path,'//')||str_contains($path,'\\'))return $default;
 $query=isset($parts['query'])?'?'.$parts['query']:'';
 $fragment=isset($parts['fragment'])?'#'.$parts['fragment']:'';
 return $path.$query.$fragment;
}
}
/**
 * Best-effort small JPEG thumbnail (max 320px on the long edge) for grid/list
 * display, so the admin media library and every other thumbnail spot don't
 * re-download a multi-MB original to show a 100px tile. GD is not a declared
 * requirement, so this degrades silently (caller falls back to the original)
 * when the extension is missing, the source can't be decoded, or the image
 * is already small enough that a separate thumbnail wouldn't help.
 */
function erased_generate_thumbnail(string $sourcePath, string $mime, string $thumbPath, int $maxDim = 320): bool {
 if(!extension_loaded('gd'))return false;
 try{
  $size=@getimagesize($sourcePath);
  if(!is_array($size)||$size[0]<1||$size[1]<1)return false;
  [$width,$height]=$size;
  if(max($width,$height)<=$maxDim)return false;
  $image=match($mime){
   'image/jpeg'=>@imagecreatefromjpeg($sourcePath),
   'image/png'=>@imagecreatefrompng($sourcePath),
   'image/gif'=>@imagecreatefromgif($sourcePath),
   'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($sourcePath):false,
   default=>false,
  };
  if(!$image)return false;
  $ratio=$maxDim/max($width,$height);
  $thumbWidth=max(1,(int)round($width*$ratio));
  $thumbHeight=max(1,(int)round($height*$ratio));
  $thumb=imagecreatetruecolor($thumbWidth,$thumbHeight);
  imagefill($thumb,0,0,(int)imagecolorallocate($thumb,255,255,255));
  imagecopyresampled($thumb,$image,0,0,0,0,$thumbWidth,$thumbHeight,$width,$height);
  imagedestroy($image);
  $ok=imagejpeg($thumb,$thumbPath,75);
  imagedestroy($thumb);
  return $ok;
 }catch(Throwable $e){
  return false;
 }
}
if (!function_exists('upload_one')) {
function upload_one(array $file): array {
 $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
 if($error!==UPLOAD_ERR_OK){
  $messages=[
   UPLOAD_ERR_INI_SIZE=>'The file exceeds the server upload limit.',
   UPLOAD_ERR_FORM_SIZE=>'The file exceeds the form upload limit.',
   UPLOAD_ERR_PARTIAL=>'The file was only partially uploaded.',
   UPLOAD_ERR_NO_FILE=>'Choose a file to upload.',
  ];
  throw new RuntimeException($messages[$error]??'The upload failed.');
 }
 $temporary=(string)($file['tmp_name']??'');
 if($temporary===''||!is_uploaded_file($temporary))throw new RuntimeException('The uploaded file could not be verified.');
 $size=(int)($file['size']??0);
 if($size<1)throw new RuntimeException('The uploaded file is empty.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($temporary)?:'application/octet-stream';
 $allowed=[
  'image/jpeg'=>['jpg',20*1024*1024],
  'image/png'=>['png',20*1024*1024],
  'image/gif'=>['gif',20*1024*1024],
  'image/webp'=>['webp',20*1024*1024],
  'video/mp4'=>['mp4',500*1024*1024],
  'video/webm'=>['webm',500*1024*1024],
  'video/ogg'=>['ogv',500*1024*1024],
  'application/pdf'=>['pdf',30*1024*1024],
 ];
 if(!isset($allowed[$mime]))throw new RuntimeException('This file type is not allowed.');
 [$extension,$limit]=$allowed[$mime];
 if($size>$limit)throw new RuntimeException('The uploaded file is too large.');
 if(!is_dir(UPLOAD_DIR)&&!mkdir(UPLOAD_DIR,0770,true)&&!is_dir(UPLOAD_DIR))throw new RuntimeException('Could not create the media directory.');
 $stored=bin2hex(random_bytes(24)).'.'.$extension;
 $destination=UPLOAD_DIR.'/'.$stored;
 if(!move_uploaded_file($temporary,$destination))throw new RuntimeException('Could not store the uploaded file.');
 @chmod($destination,0660);
 $width=null;$height=null;$hasThumb=false;
 if(str_starts_with($mime,'image/')){
  $dimensions=@getimagesize($destination);
  if(is_array($dimensions)&&$dimensions[0]>0&&$dimensions[1]>0){[$width,$height]=$dimensions;}
  $thumbPath=UPLOAD_DIR.'/'.pathinfo($stored,PATHINFO_FILENAME).'_thumb.jpg';
  $hasThumb=erased_generate_thumbnail($destination,$mime,$thumbPath);
  if($hasThumb)@chmod($thumbPath,0660);
 }
 try{
  $query=db()->prepare('INSERT INTO media(original_name,stored_name,mime_type,size_bytes,width,height,has_thumb,alt_text,caption,uploaded_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
  $query->execute([basename((string)($file['name']??'upload.'.$extension)),$stored,$mime,$size,$width,$height,$hasThumb?1:0,'','']);
  $id=(int)db()->lastInsertId();
  $media=media_by_id($id);
  if(!$media)throw new RuntimeException('The uploaded media record could not be loaded.');
  audit('media.upload',['id'=>$id,'mime_type'=>$mime,'size_bytes'=>$size]);
  return $media;
 }catch(Throwable $e){
  @unlink($destination);
  throw $e;
 }
}
}
function language_catalog(): array {
 return [
  'en'=>['name'=>'English','native'=>'English','rtl'=>false],
 ];
}function erased_master_translation_defaults(string $group='site'): array {
 $group=$group==='admin'?'admin':'site';
 if($group==='site'){
  return [
   'admin'=>'Admin',
   'approved'=>'Approved',
   'categories'=>'Categories',
   'category'=>'Category',
   'close'=>'Close',
   'comment'=>'Comment',
   'comment_captcha_answer_placeholder'=>'Your answer',
   'comment_captcha_failed'=>'Security check failed. Solve the task and try again.',
   'comment_captcha_label'=>'Security check',
   'comment_captcha_question'=>'What is',
   'comment_captcha_subtitle'=>'Anti-Spam protection',
   'comment_moderation_note'=>'Comments are reviewed for spam and may be approved before publishing.',
   'comment_posted'=>'Comment posted.',
   'comment_received'=>'Thanks. Your comment was received for review.',
   'comment_waiting'=>'Comment submitted and waiting for approval.',
   'comments'=>'Comments',
   'comments_unavailable'=>'Comments unavailable.',
   'created'=>'Created',
   'dashboard'=>'Dashboard',
   'download_photo'=>'Download photo',
   'duplicate_comment'=>'That comment has already been received.',
   'edit'=>'Edit',
   'email'=>'Email',
   'email_not_published'=>'Not published.',
   'empty_region'=>'Empty region',
   'first_comment'=>'Be the first to comment.',
   'gallery'=>'Gallery',
   'home'=>'Home',
   'join_discussion'=>'Join the discussion.',
   'language'=>'Language',
   'latest_comments'=>'Latest comments',
   'latest_posts'=>'Latest posts',
   'login'=>'Sign in',
   'login_intro'=>'Sign in to manage the website.',
   'logout'=>'Log out',
   'maintenance'=>'Website temporarily unavailable for updates.',
   'maintenance_message'=>'This site is temporarily unavailable while updates are being made.',
   'media'=>'Media',
   'min_read'=>'min read',
   'minute_read'=>'{minutes} min read',
   'minutes_read'=>'{minutes} min read',
   'name'=>'Name',
   'new'=>'New',
   'next'=>'Next',
   'no_categories'=>'No categories yet.',
   'no_comments'=>'No comments yet.',
   'no_content'=>'No content published yet.',
   'no_matching_posts'=>'No matching posts found.',
   'no_posts'=>'No posts published yet.',
   'no_tags'=>'No tags yet.',
   'not_found'=>'Not found',
   'open_page'=>'Open page',
   'open_post'=>'Open post',
   'page'=>'page',
   'page_not_found'=>'Page not found.',
   'password'=>'Password',
   'pending'=>'Pending',
   'popular_tags'=>'Popular tags',
   'post'=>'post',
   'post_comment'=>'Post comment',
   'posts'=>'Posts',
   'previous'=>'Previous',
   'read_more'=>'Read more',
   'reader'=>'Reader',
   'remember_me'=>'Remember me',
   'return_to_homepage'=>'Return to homepage',
   'rss_feed'=>'RSS feed',
   'search'=>'Search',
   'search_placeholder'=>'Search posts',
   'search_posts'=>'Search posts',
   'sign_in'=>'Sign in',
   'submit_comment'=>'Submit comment',
   'subscribe'=>'Subscribe',
   'subscribe_news'=>'Subscribe to news',
   'subscribe_success'=>'Thank you for subscribing!',
   'too_many_comments'=>'Too many comments were submitted. Please try again later.',
   'view_website'=>'View website',
   'welcome_back'=>'Welcome back',
   'your_email_address'=>'Your email address',
   'access_denied_policy'=>'Access Denied by Security Policy.',
   'admin_ip_restricted'=>'Access Restricted to Allowed Administrator IP addresses.',
   'admin_login'=>'Admin Login',
   'all_galleries'=>'All galleries',
   'back_to_login'=>'Back to login',
   'browse_all_photos'=>'Browse all photos on the website',
   'cancel'=>'Cancel',
   'change_password'=>'Change password',
   'emergency_lockdown_message'=>'Emergency Lockdown Active: Non-administrator logins are disabled.',
   'enter_valid_email'=>'Enter a valid email address.',
   'explore_photo_albums'=>'Explore photo albums and collections',
   'forgot_password'=>'Forgot password',
   'forgot_password_question'=>'Forgot password?',
   'gallery_not_found'=>'Gallery not found',
   'invalid_login'=>'Invalid login.',
   'invalid_verification_code'=>'Invalid verification code.',
   'ip_locked_after_failures'=>'This IP address has been temporarily locked after repeated failed logins.',
   'ip_locked_message'=>'This IP address is temporarily locked. Try again in about {minutes} minutes.',
   'maintenance_title'=>'Maintenance',
   'manage_galleries'=>'Manage galleries',
   'newsletter'=>'Newsletter',
   'newsletter_unsubscribed'=>'You have been unsubscribed.',
   'no_photos_in_gallery'=>'No photos in this gallery yet.',
   'no_photos_uploaded_yet'=>'No photos uploaded yet.',
   'open_media_library'=>'Open Media Library',
   'password_changed_success'=>'Password changed. All previous sessions were signed out. You can log in now.',
   'password_reset_email_sent'=>'If that account exists, a password-reset email has been sent.',
   'photo_collection'=>'Photo collection',
   'photo_gallery_title'=>'Photo Gallery',
   'photo_galleries_title'=>'Photo Galleries',
   'read_only_mode_message'=>'Website changes are temporarily disabled by administrator policy.',
   'read_only_mode_title'=>'Read-Only Mode Active',
   'reset_link_invalid'=>'This reset link is invalid, expired, or already used.',
   'reset_password'=>'Reset password',
   'return_to_galleries'=>'Return to photo galleries',
   'return_to_website'=>'Return to website',
   'send_reset_link'=>'Send reset link',
   'too_many_failed_attempts'=>'Too many failed attempts. Try again later.',
   'turnstile_failed'=>'Cloudflare Turnstile security check failed. Please try again.',
   'verification_email_failed'=>'Verification email could not be sent. Check the email settings or contact the site owner.',
   'verification_expired'=>'Verification expired. Please log in again.',
   'verify'=>'Verify',
   'verify_login'=>'Verify login',
   'verify_login_intro'=>'Enter the six-digit code sent to your email address.',
   'verification_code'=>'Verification code',
   'password_help_text'=>'Minimum 8 characters. For better security, use 12 or more characters.',
   'waf_forbidden'=>'403 Forbidden - Request blocked by ERASED Web Application Firewall (WAF).',
  ];
 }

 return [
   'access_description'=>'Public, registered, members or paid',
   'access_level'=>'Access level',
   'access_rules'=>'Access rules',
   'account'=>'Account',
   'account_active'=>'Account active',
   'account_level'=>'Account level',
   'account_levels'=>'Account levels',
   'account_levels_help'=>'Review what each role is allowed to do',
   'actions'=>'Actions',
   'active'=>'Active',
   'active_administrators'=>'Active administrators',
   'active_for_visitors'=>'Active for visitors',
   'add_language'=>'Add language',
   'add_language_help'=>'Use an ISO language code such as de, fr, lt, pl or pt-br.',
   'add_ons'=>'Add-ons',
   'additional_mail_parameters'=>'Additional mail parameters',
   'admin_default'=>'Admin default',
   'admin_language'=>'Admin panel language',
   'admin_language_help'=>'Used for menus, tools, settings and system messages inside the administration panel.',
   'admin_panel'=>'Admin panel',
   'admin_text'=>'Admin text',
   'all_content'=>'All content',
   'allow_public_registration'=>'Allow public registration',
   'appearance'=>'Appearance',
   'apply_selected_site_lang_admin'=>'Apply selected website language to Admin Panel',
   'apply_to_selected'=>'Apply to selected',
   'approve'=>'Approve',
   'audit_log'=>'Audit log',
   'author'=>'Author',
   'back'=>'Back',
   'back_accounts'=>'Back to accounts',
   'backups'=>'Backups',
   'branding_and_identity'=>'Branding & Identity',
   'branding_and_logos'=>'Branding & Logos',
   'builtin_erased_identity'=>'Built-in ERASED identity',
   'bulk_action'=>'Bulk action...',
   'captcha_active'=>'CAPTCHA Active',
   'captcha_comment_protection'=>'Enable CAPTCHA verification on comment form (Anti-Spam protection)',
   'captcha_comment_security'=>'Enable CAPTCHA Verification on Comments Section (Math CAPTCHA or Cloudflare Turnstile)',
   'captcha_disabled'=>'CAPTCHA Disabled',
   'categories'=>'Categories',
   'categories_tags_language'=>'Categories, tags and language',
   'changes_published'=>'Changes published.',
   'choose_image'=>'Choose image',
   'close'=>'Close',
   'cloudflare_integration'=>'Cloudflare Turnstile Anti-Spam & Protection',
   'cms_and_website_theme'=>'CMS and website theme',
   'comment'=>'Comment',
   'comments'=>'Comments',
   'comments_heading'=>'Comments',
   'content'=>'Content',
   'content_intro'=>'Create, review and manage posts and pages.',
   'content_metadata'=>'Content metadata',
   'create_account'=>'Create account',
   'create_account_help'=>'Add a new user, writer, editor or administrator',
   'create_backup'=>'Create database backup now',
   'create_post'=>'Create post',
   'created'=>'Created',
   'custom_css'=>'Custom CSS',
   'custom_uploaded_logos'=>'Custom uploaded logos',
   'dark_theme_logo'=>'Dark theme logo',
   'dashboard'=>'Dashboard',
   'dashboard_intro'=>'Overview of your website and available tools.',
   'date_format'=>'Date format',
   'default_languages'=>'Default languages',
   'delete'=>'Delete',
   'delete_comment_confirm'=>'Delete this comment?',
   'delete_content_confirm'=>'Delete this content?',
   'delete_language_confirm'=>'Delete this language and all its translations?',
   'delivery_method'=>'Delivery method',
   'delivery_summary'=>'Delivery summary',
   'detect_browser_language'=>'Detect browser language',
   'detect_browser_language_help'=>'Uses the visitor\'s browser language before they manually choose one.',
   'disabled'=>'Disabled',
   'display_name'=>'Display name',
   'draft'=>'Draft',
   'draft_saved'=>'Draft saved.',
   'edit'=>'Edit',
   'edit_content'=>'Edit content',
   'edit_translations'=>'Edit translations',
   'email'=>'Email',
   'email_not_published'=>'Not published.',
   'email_settings'=>'Email Settings',
   'encryption'=>'Encryption',
   'english_fallback'=>'English fallback',
   'explanation'=>'Explanation',
   'export'=>'Export',
   'export_language'=>'Export language file',
   'failed_logins'=>'Failed logins (24h)',
   'footer_text'=>'Footer text',
   'from_address'=>'From address',
   'from_name'=>'From name',
   'gallery'=>'Gallery',
   'general_settings'=>'General settings',
   'header_layout'=>'Header layout',
   'header_logo'=>'Header logo',
   'homepage_layout'=>'Homepage Layout Builder',
   'homepage_studio'=>'Homepage Studio',
   'import'=>'Import / Export',
   'import_wordpress'=>'Import WordPress export',
   'installed'=>'Installed',
   'installed_languages'=>'Installed languages',
   'installed_languages_help'=>'Activate languages, review translation progress, edit text or export translation files.',
   'key'=>'Key',
   'language'=>'Language',
   'language_code'=>'Language code',
   'language_defaults'=>'Default languages',
   'language_defaults_help'=>'Choose separate interface languages for administrators and website visitors.',
   'language_settings'=>'Language settings',
   'language_settings_saved'=>'Language settings saved.',
   'languages'=>'Languages',
   'languages_intro'=>'Manage website and dashboard languages, translation coverage, defaults and visitor language behavior.',
   'leave_password'=>'Leave empty to keep the current password.',
   'level'=>'Level',
   'light_theme_logo'=>'Light/Grey theme logo',
   'logo_height'=>'Logo height',
   'logo_width'=>'Logo width',
   'logout'=>'Log out',
   'maintenance_mode'=>'Maintenance mode',
   'mark_pending'=>'Mark Pending',
   'mark_spam'=>'Mark Spam',
   'matte_charcoal_grey'=>'Matte Charcoal Grey',
   'matte_dark_green'=>'Matte Dark Green',
   'matte_light_grey'=>'Matte Light Grey',
   'media'=>'Media',
   'media_files'=>'Media files',
   'memberships'=>'Memberships',
   'memberships_and_plans'=>'Memberships & Plans',
   'missing'=>'Missing',
   'moderation_actions'=>'Moderation actions',
   'native_name'=>'Native name',
   'navigation_menu'=>'Navigation Menu',
   'new_language_added'=>'Language added. You can now translate its Admin and Website text.',
   'new_password'=>'New password',
   'new_post'=>'New post',
   'no_comments'=>'No comments yet.',
   'no_content_account'=>'No content available for this account.',
   'no_images'=>'No images yet.',
   'no_logo'=>'No logo',
   'no_users'=>'No user accounts found.',
   'overall_coverage'=>'Overall translation coverage',
   'packages'=>'Packages',
   'pages'=>'Pages',
   'password'=>'Password',
   'payments'=>'Payments',
   'payments_and_gateways'=>'Payments & Gateways',
   'permission_edit_own'=>'You may edit only your own posts.',
   'permission_required'=>'Permission required',
   'php_mail_settings'=>'PHP mail settings',
   'port'=>'Port',
   'publish_date_timing'=>'Publish date and timing',
   'published'=>'Published',
   'publishing'=>'Publishing',
   'publishing_intro'=>'Choose what you want to edit. Only one focused panel opens at a time.',
   'publishing_saved'=>'Publishing settings saved.',
   'quick_actions'=>'Quick actions',
   'recent_accounts'=>'Recent accounts',
   'redirects'=>'Redirects',
   'redirects_help'=>'View migrated and moved URLs',
   'regional_format'=>'Regional format',
   'regional_settings'=>'Regional settings',
   'reply_to_address'=>'Reply-to address',
   'return_path_address'=>'Return-path address',
   'revisions'=>'Revisions',
   'revisions_help'=>'View saved editing history',
   'save'=>'Save',
   'save_account'=>'Save account',
   'save_branding_settings'=>'Save branding settings',
   'save_changes'=>'Save changes',
   'save_email_settings'=>'Save email settings',
   'save_general_settings'=>'Save general settings',
   'save_language_settings'=>'Save language settings',
   'save_security'=>'Save security settings',
   'save_theme_settings'=>'Save theme settings',
   'scheduling'=>'Scheduling',
   'search'=>'Search translations',
   'security'=>'Security',
   'security_center'=>'Security Center',
   'security_score'=>'Security score',
   'select_all'=>'Select All',
   'sender_identity'=>'Sender identity',
   'seo'=>'SEO',
   'seo_description_short'=>'Search title, description and canonical URL',
   'settings'=>'Settings',
   'settings_saved'=>'Settings saved.',
   'show_language_switcher'=>'Show language selector',
   'show_language_switcher_help'=>'Display a compact selector in the public navigation.',
   'show_site_title_next_to_logo'=>'Show site title next to the logo',
   'signed_in_as'=>'Signed in as',
   'site_identity'=>'Site identity',
   'site_language'=>'Site language',
   'site_tagline'=>'Site tagline',
   'site_title'=>'Site title',
   'site_title_used_until_logo'=>'Site title will be used until a logo is selected.',
   'smtp_host'=>'SMTP host',
   'smtp_settings'=>'SMTP settings',
   'spam'=>'Spam',
   'status'=>'Status',
   'temporary_password'=>'Temporary password',
   'theme_manager'=>'Theme Manager',
   'theme_settings'=>'Theme settings',
   'timezone'=>'Time zone',
   'title'=>'Title',
   'title_required'=>'Title is required.',
   'tools'=>'Tools',
   'total_content'=>'Total content',
   'translated'=>'translated',
   'translation'=>'Translation',
   'translation_editor'=>'Translation editor',
   'type'=>'Type',
   'upload_another_image'=>'Upload another image',
   'upload_new_image'=>'Upload new image',
   'use_builtin_dark_logo'=>'Use built-in dark logo',
   'use_builtin_favicon'=>'Use built-in favicon',
   'use_builtin_light_logo'=>'Use built-in light logo',
   'user_accounts'=>'User accounts',
   'user_accounts_help'=>'View, edit, activate and assign account levels',
   'users'=>'Users',
   'users_intro'=>'Choose what you want to manage. Only one focused panel opens at a time.',
   'view'=>'View',
   'view_site'=>'View site',
   'visitor_and_admin_experience'=>'Visitor & Admin experience',
   'visitor_experience'=>'Visitor experience',
   'visitor_language'=>'Visitor language choice',
   'visitor_language_help'=>'Control how visitors choose and remember the website interface language.',
   'website'=>'Website',
   'website_default'=>'Website default',
   'website_language'=>'Website language',
   'website_language_help'=>'The fallback language for public pages and visitors.',
   'website_text'=>'Website text',
   'widgets_sidebars'=>'Widgets & Sidebars',
   'wordpress_migration'=>'WordPress Migration',
   'write_post'=>'Write post',
   'your_own_posts'=>'Your own posts',
   'your_permissions'=>'Your permissions',
   'your_posts'=>'Your posts',
  ];
}
function erased_master_language_defaults(string $code, string $group='site'): array {
 $code=strtolower($code);
 $group=$group==='admin'?'admin':'site';
 if($code==='lt'){
  if($group==='site'){
   return [
    'admin'=>'Administravimas',
    'approved'=>'patvirtinta',
    'categories'=>'Kategorijos',
    'category'=>'Kategorija',
    'close'=>'Uždaryti',
    'comment'=>'Komentaras',
    'comment_captcha_label'=>'Apsaugos patikra',
    'comment_captcha_subtitle'=>'Apsauga nuo šlamšto',
    'comment_captcha_question'=>'Kiek yra',
    'comment_captcha_answer_placeholder'=>'Jūsų atsakymas',
    'comment_captcha_failed'=>'Apsaugos patikra nepavyko. Išspręskite užduotį ir bandykite dar kartą.',
    'comment_moderation_note'=>'Komentarai tikrinami dėl šlamšto ir gali būti matomi tik po patvirtinimo.',
    'comment_posted'=>'Komentaras paskelbtas.',
    'comment_received'=>'Dėkojame. Jūsų komentaras gautas ir pateiktas peržiūrai.',
    'comment_waiting'=>'Komentaras pateiktas ir laukia patvirtinimo.',
    'comments'=>'Komentarai',
    'comments_unavailable'=>'Komentarai nepasiekiami.',
    'created'=>'Sukurta',
    'dashboard'=>'Skydelis',
    'download_photo'=>'Atsisiųsti nuotrauką',
    'duplicate_comment'=>'Šis komentaras jau gautas.',
    'edit'=>'Redaguoti',
    'email'=>'El. paštas',
    'email_not_published'=>'Neskelbiamas.',
    'empty_region'=>'Tuščia sritis',
    'first_comment'=>'Būkite pirmasis pakomentavęs.',
    'gallery'=>'Galerija',
    'home'=>'Pagrindinis',
    'join_discussion'=>'Prisijunkite prie diskusijos.',
    'language'=>'Kalba',
    'latest_comments'=>'Naujausi komentarai',
    'latest_posts'=>'Naujausi įrašai',
    'login'=>'Prisijungti',
    'login_intro'=>'Prisijunkite, kad valdytumėte svetainę.',
    'logout'=>'Atsijungti',
    'maintenance'=>'Svetainė laikinai neprieinama dėl atliekamų atnaujinimo darbų.',
    'maintenance_message'=>'Ši svetainė laikinai nepasiekiama, kol atliekami atnaujinimai.',
    'media'=>'Medija',
    'min_read'=>'min. skaitymo',
    'minute_read'=>'{minutes} min. skaitymo',
    'minutes_read'=>'{minutes} min. skaitymo',
    'name'=>'Vardas',
    'new'=>'Naujas',
    'next'=>'Kitas',
    'no_categories'=>'Kategorijų dar nėra.',
    'no_comments'=>'Komentarų dar nėra.',
    'no_content'=>'Paskelbto turinio dar nėra.',
    'no_matching_posts'=>'Rastų paskelbtų įrašų nėra.',
    'no_posts'=>'Paskelbtų įrašų dar nėra.',
    'no_tags'=>'Žymų dar nėra.',
    'not_found'=>'Nerasta',
    'open_page'=>'Atidaryti puslapį',
    'open_post'=>'Atidaryti įrašą',
    'page'=>'puslapis',
    'page_not_found'=>'Puslapis nerastas.',
    'password'=>'Slaptažodis',
    'pending'=>'Laukia patvirtinimo',
    'popular_tags'=>'Populiarios žymos',
    'post'=>'įrašas',
    'post_comment'=>'Skelbti komentarą',
    'posts'=>'Įrašai',
    'previous'=>'Ankstesnis',
    'read_more'=>'Skaityti daugiau',
    'reader'=>'Skaitytojas',
    'remember_me'=>'Prisiminti mane',
    'return_to_homepage'=>'Grįžti į pagrindinį puslapį',
    'rss_feed'=>'RSS srautas',
    'search'=>'Paieška',
    'search_placeholder'=>'Ieškoti įrašų',
    'search_posts'=>'Ieškoti įrašų',
    'sign_in'=>'Prisijungti',
    'subscribe'=>'Prenumeruoti',
    'subscribe_news'=>'Prenumeruoti naujienas',
    'subscribe_success'=>'Dėkojame, kad prenumeruojate!',
    'submit_comment'=>'Pateikti komentarą',
    'too_many_comments'=>'Pateikta per daug komentarų. Bandykite vėliau.',
    'view_website'=>'Peržiūrėti svetainę',
    'welcome_back'=>'Sveiki sugrįžę',
    'your_email_address'=>'Jūsų el. pašto adresas',
    'access_denied_policy'=>'Prieiga uždrausta pagal saugumo politiką.',
    'admin_ip_restricted'=>'Prieiga apribota tik leidžiamiems administratoriaus IP adresams.',
    'admin_login'=>'Administratoriaus prisijungimas',
    'all_galleries'=>'Visos galerijos',
    'back_to_login'=>'Grįžti į prisijungimą',
    'browse_all_photos'=>'Peržiūrėkite visas svetainės nuotraukas',
    'cancel'=>'Atšaukti',
    'change_password'=>'Keisti slaptažodį',
    'emergency_lockdown_message'=>'Veikia avarinis užraktas: neadministratorių prisijungimai išjungti.',
    'enter_valid_email'=>'Įveskite galiojantį el. pašto adresą.',
    'explore_photo_albums'=>'Naršykite nuotraukų albumus ir kolekcijas',
    'forgot_password'=>'Pamiršote slaptažodį',
    'forgot_password_question'=>'Pamiršote slaptažodį?',
    'gallery_not_found'=>'Galerija nerasta',
    'invalid_login'=>'Neteisingi prisijungimo duomenys.',
    'invalid_verification_code'=>'Neteisingas patvirtinimo kodas.',
    'ip_locked_after_failures'=>'Šis IP adresas buvo laikinai užblokuotas dėl pakartotinių nesėkmingų prisijungimų.',
    'ip_locked_message'=>'Šis IP adresas laikinai užblokuotas. Bandykite dar kartą po maždaug {minutes} min.',
    'maintenance_title'=>'Techninė priežiūra',
    'manage_galleries'=>'Tvarkyti galerijas',
    'newsletter'=>'Naujienlaiškis',
    'newsletter_unsubscribed'=>'Jūs buvote atsisakę prenumeratos.',
    'no_photos_in_gallery'=>'Šioje galerijoje dar nėra nuotraukų.',
    'no_photos_uploaded_yet'=>'Dar nėra įkeltų nuotraukų.',
    'open_media_library'=>'Atverti medijos biblioteką',
    'password_changed_success'=>'Slaptažodis pakeistas. Visos ankstesnės sesijos buvo atjungtos. Dabar galite prisijungti.',
    'password_reset_email_sent'=>'Jei tokia paskyra egzistuoja, slaptažodžio atkūrimo laiškas buvo išsiųstas.',
    'photo_collection'=>'Nuotraukų kolekcija',
    'photo_gallery_title'=>'Nuotraukų galerija',
    'photo_galleries_title'=>'Nuotraukų galerijos',
    'read_only_mode_message'=>'Svetainės pakeitimai laikinai išjungti administratoriaus nustatymu.',
    'read_only_mode_title'=>'Veikia tik skaitymo režimas',
    'reset_link_invalid'=>'Ši atkūrimo nuoroda negalioja, baigėsi jos galiojimo laikas arba ji jau panaudota.',
    'reset_password'=>'Atkurti slaptažodį',
    'return_to_galleries'=>'Grįžti į nuotraukų galerijas',
    'return_to_website'=>'Grįžti į svetainę',
    'send_reset_link'=>'Siųsti atkūrimo nuorodą',
    'too_many_failed_attempts'=>'Per daug nesėkmingų bandymų. Bandykite vėliau.',
    'turnstile_failed'=>'Cloudflare Turnstile saugumo patikra nepavyko. Bandykite dar kartą.',
    'verification_email_failed'=>'Nepavyko išsiųsti patvirtinimo laiško. Patikrinkite el. pašto nustatymus arba susisiekite su svetainės savininku.',
    'verification_expired'=>'Patvirtinimo laikas baigėsi. Prisijunkite iš naujo.',
    'verify'=>'Patvirtinti',
    'verify_login'=>'Patvirtinti prisijungimą',
    'verify_login_intro'=>'Įveskite šešių skaitmenų kodą, išsiųstą jūsų el. pašto adresu.',
    'verification_code'=>'Patvirtinimo kodas',
    'password_help_text'=>'Mažiausiai 8 simboliai. Didesniam saugumui naudokite 12 ar daugiau simbolių.',
    'waf_forbidden'=>'403 Uždrausta - Užklausą užblokavo ERASED žiniatinklio programų ugniasienė (WAF).'
   ];
  } else {
   return [
    'access_description'=>'Viešas, registruoti, nariai arba mokamas',
    'access_level'=>'Prieigos lygis',
    'access_rules'=>'Prieigos taisyklės',
    'account'=>'Paskyra',
    'account_active'=>'Paskyra aktyvi',
    'account_level'=>'Paskyros lygis',
    'account_levels'=>'Paskyrų lygiai',
    'account_levels_help'=>'Peržiūrėkite, ką leidžiama daryti kiekvienam vaidmeniui',
    'actions'=>'Veiksmai',
    'active'=>'Aktyvus',
    'active_administrators'=>'Aktyvūs administratoriai',
    'active_for_visitors'=>'Aktyvus lankytojams',
    'add_language'=>'Pridėti kalbą',
    'add_language_help'=>'Naudokite ISO kalbos kodą, pvz., de, fr, lt, pl arba pt-br.',
    'add_ons'=>'Priedai',
    'admin_default'=>'Skydelio numatytasis',
    'admin_language'=>'Valdymo skydelio kalba',
    'admin_language_help'=>'Naudojama meniui, įrankiams, nustatymams ir sisteminiams pranešimams valdymo skyde.',
    'admin_panel'=>'Valdymo skydelis',
    'admin_text'=>'Skydelio tekstas',
    'all_content'=>'Visas turinys',
    'appearance'=>'Išvaizda',
    'approve'=>'Patvirtinti',
    'audit_log'=>'Audito žurnalas',
    'author'=>'Autorius',
    'back'=>'Atgal',
    'back_accounts'=>'Grįžti į paskyras',
    'backups'=>'Atsarginės kopijos',
    'captcha_active'=>'CAPTCHA aktyvi',
    'captcha_comment_protection'=>'Įjungti CAPTCHA apsaugą komentarų formoje (apsauga nuo šlamšto)',
    'captcha_comment_security'=>'Įjungti CAPTCHA apsaugą komentarų skiltyje (matematinė CAPTCHA arba Cloudflare Turnstile)',
    'captcha_disabled'=>'CAPTCHA išjungta',
    'categories'=>'Kategorijos',
    'categories_tags_language'=>'Kategorijos, žymos ir kalba',
    'changes_published'=>'Pakeitimai paskelbti.',
    'choose_image'=>'Pasirinkti paveikslėlį',
    'close'=>'Uždaryti',
    'cloudflare_integration'=>'Cloudflare Turnstile apsauga nuo šlamšto',
    'comment'=>'Komentaras',
    'comments'=>'Komentarai',
    'comments_heading'=>'Komentarai',
    'content'=>'Turinys',
    'content_intro'=>'Kurkite, peržiūrėkite ir tvarkykite įrašus bei puslapius.',
    'content_metadata'=>'Turinio metaduomenys',
    'create_account'=>'Sukurti paskyrą',
    'create_account_help'=>'Pridėti naują vartotoją, autorių, redaktorių ar administratorių',
    'create_backup'=>'Sukurti duomenų bazės atsarginę kopiją dabar',
    'create_post'=>'Sukurti įrašą',
    'created'=>'Sukurta',
    'custom_css'=>'Pritaikytas CSS',
    'dashboard'=>'Skydelis',
    'dashboard_intro'=>'Jūsų svetainės ir turimų įrankių apžvalga.',
    'date_format'=>'Datos formatas',
    'default_languages'=>'Numatytosios kalbos',
    'delete'=>'Ištrinti',
    'delete_comment_confirm'=>'Ištrinti šį komentarą?',
    'delete_content_confirm'=>'Ištrinti šį turinį?',
    'delete_language_confirm'=>'Ištrinti šią kalbą ir visus jos vertimus?',
    'detect_browser_language'=>'Aptikti naršyklės kalbą',
    'detect_browser_language_help'=>'Naudoja lankytojo naršyklės kalbą prieš jam patiems pasirinkus.',
    'disabled'=>'Išjungta',
    'display_name'=>'Rodomas vardas',
    'draft'=>'Juodraštis',
    'draft_saved'=>'Juodraštis išsaugotas.',
    'edit'=>'Redaguoti',
    'edit_content'=>'Redaguoti turinį',
    'edit_translations'=>'Redaguoti vertimus',
    'email'=>'El. paštas',
    'email_not_published'=>'Neskelbiamas.',
    'email_settings'=>'El. pašto nustatymai',
    'english_fallback'=>'Atsarginė anglų kalba',
    'explanation'=>'Paaiškinimas',
    'export'=>'Eksportuoti',
    'export_language'=>'Eksportuoti kalbos failą',
    'failed_logins'=>'Neteisingi prisijungimai (24 val.)',
    'footer_text'=>'Apatinės juostos tekstas',
    'gallery'=>'Galerija',
    'general_settings'=>'Bendrieji nustatymai',
    'header_logo'=>'Antraštė ir logotipas',
    'homepage_layout'=>'Pagrindinio puslapio išdėstymas',
    'import'=>'Importavimas / Eksportavimas',
    'import_wordpress'=>'Įkelti „WordPress“ eksportą',
    'installed'=>'Įdiegta',
    'installed_languages'=>'Įdiegtos kalbos',
    'installed_languages_help'=>'Aktyvuokite kalbas, peržiūrėkite vertimo eigą, redaguokite tekstą arba eksportuokite vertimų failus.',
    'key'=>'Raktas',
    'language'=>'Kalba',
    'language_code'=>'Kalbos kodas',
    'language_defaults'=>'Numatytosios kalbos',
    'language_defaults_help'=>'Pasirinkite atskiras sąsajos kalbas administratoriams ir svetainės lankytojams.',
    'language_settings'=>'Kalbos nustatymai',
    'language_settings_saved'=>'Kalbos nustatymai išsaugoti.',
    'languages'=>'Kalbos',
    'languages_intro'=>'Valdykite svetainės ir valdymo skydelio kalbas, vertimų aprėptį, numatytuosius nustatymus ir lankytojų elgseną.',
    'leave_password'=>'Palikite tuščią, jei norite išlaikyti dabartinį slaptažodį.',
    'level'=>'Lygis',
    'logo_height'=>'Logotipo aukštis',
    'logo_width'=>'Logotipo plotis',
    'logout'=>'Atsijungti',
    'media'=>'Medija',
    'media_files'=>'Medijos failai',
    'memberships'=>'Narystės',
    'missing'=>'Trūksta',
    'moderation_actions'=>'Moderavimo veiksmai',
    'native_name'=>'Gimtoji kalba',
    'navigation_menu'=>'Navigacijos meniu',
    'new_language_added'=>'Kalba pridėta. Dabar galite išversti jos skydelio ir svetainės tekstą.',
    'new_password'=>'Naujas slaptažodis',
    'new_post'=>'Naujas įrašas',
    'no_comments'=>'Komentarų dar nėra.',
    'no_content_account'=>'Šiai paskyrai nėra pasiekiamo turinio.',
    'no_images'=>'Paveikslėlių dar nėra.',
    'no_users'=>'Vartotojų paskyrų nerasta.',
    'overall_coverage'=>'Bendra vertimo aprėptis',
    'packages'=>'Paketai',
    'pages'=>'Puslapiai',
    'password'=>'Slaptažodis',
    'payments'=>'Mokėjimai',
    'permission_edit_own'=>'Galite redaguoti tik savo įrašus.',
    'permission_required'=>'Reikalingas leidimas',
    'publish_date_timing'=>'Publikavimo data ir laikas',
    'published'=>'Paskelbta',
    'publishing'=>'Publikavimas',
    'publishing_intro'=>'Pasirinkite, ką norite redaguoti. Vienu metu atidaroma tik viena sritis.',
    'publishing_saved'=>'Publikavimo nustatymai išsaugoti.',
    'quick_actions'=>'Greiti veiksmai',
    'recent_accounts'=>'Naujausios paskyros',
    'redirects'=>'Peradresavimai',
    'redirects_help'=>'Peržiūrėti perkeltus ir pakeistus URL',
    'regional_format'=>'Regioninis formatas',
    'revisions'=>'Revizijos',
    'revisions_help'=>'Peržiūrėti išsaugotą redagavimo istoriją',
    'save'=>'Išsaugoti',
    'save_account'=>'Išsaugoti paskyrą',
    'save_changes'=>'Išsaugoti pakeitimus',
    'save_language_settings'=>'Išsaugoti kalbos nustatymus',
    'save_security'=>'Išsaugoti saugumo nustatymus',
    'scheduling'=>'Tvarkaraštis',
    'search'=>'Ieškoti vertimų',
    'security'=>'Saugumas',
    'security_center'=>'Saugumo centras',
    'security_score'=>'Saugumo įvertinimas',
    'seo'=>'SEO',
    'seo_description_short'=>'Paieškos pavadinimas, aprašymas ir kanoninis URL',
    'settings'=>'Nustatymai',
    'settings_saved'=>'Nustatymai išsaugoti.',
    'show_language_switcher'=>'Rodyti kalbos pasirinkimą',
    'show_language_switcher_help'=>'Rodyti kompaktišką pasirinkimą viešojoje navigacijoje.',
    'signed_in_as'=>'Prisijungęs kaip',
    'site_language'=>'Svetainės kalba',
    'site_tagline'=>'Svetainės šūkis',
    'site_title'=>'Svetainės pavadinimas',
    'spam'=>'Šlamštas',
    'status'=>'Būsena',
    'temporary_password'=>'Laikinasis slaptažodis',
    'theme_manager'=>'Temų valdymas',
    'theme_settings'=>'Temos nustatymai',
    'timezone'=>'Laiko juosta',
    'title'=>'Pavadinimas',
    'title_required'=>'Pavadinimas yra privalomas.',
    'tools'=>'Įrankiai',
    'total_content'=>'Iš viso turinio',
    'translated'=>'išversta',
    'translation'=>'Vertimas',
    'translation_editor'=>'Vertimų redaktorius',
    'type'=>'Tipas',
    'upload_new_image'=>'Įkelti naują nuotrauką',
    'user_accounts'=>'Vartotojų paskyros',
    'user_accounts_help'=>'Peržiūrėkite, redaguokite, aktyvuokite ir priskirkite paskyrų lygius',
    'users'=>'Vartotojai',
    'users_intro'=>'Pasirinkite, ką norite valdyti. Vienu metu atidaroma tik viena sritis.',
    'view'=>'Peržiūrėti',
    'view_site'=>'Svetainė',
    'visitor_experience'=>'Lankytojo patirtis',
    'visitor_language'=>'Lankytojo kalbos pasirinkimas',
    'visitor_language_help'=>'Valdykite, kaip lankytojai pasirenka ir prisimena sąsajos kalbą.',
    'website'=>'Svetainė',
    'website_default'=>'Svetainės numatytasis',
    'website_language'=>'Svetainės kalba',
    'website_language_help'=>'Numatytoji kalba viešiems puslapiams ir lankytojams.',
    'website_text'=>'Svetainės tekstas',
    'widgets_sidebars'=>'Valdikliai ir šoninės juostos',
    'wordpress_migration'=>'WordPress migravimas',
    'write_post'=>'Rašyti įrašą',
    'your_own_posts'=>'Jūsų įrašai',
    'your_permissions'=>'Jūsų teisės',
    'sender_identity'=>'Siuntėjo tapatybė',
    'delivery_method'=>'Pristatymo būdas',
    'php_mail_settings'=>'PHP pašto nustatymai',
    'smtp_settings'=>'SMTP nustatymai',
    'delivery_summary'=>'Pristatymo suvestinė',
    'from_name'=>'Siuntėjo vardas',
    'from_address'=>'Siuntėjo el. pašto adresas',
    'reply_to_address'=>'Atsakymo el. pašto adresas',
    'additional_mail_parameters'=>'Papildomi pašto parametrai',
    'return_path_address'=>'Grąžinimo adresas',
    'smtp_host'=>'SMTP serveris',
    'port'=>'Prievadas',
    'encryption'=>'Šifravimas',
    'site_identity'=>'Svetainės tapatybė',
    'regional_settings'=>'Regioniniai nustatymai',
    'publishing_defaults'=>'Publikavimo nustatymai',
    'access_and_maintenance'=>'Prieiga ir priežiūra',
    'allow_public_registration'=>'Leisti viešą registraciją',
    'save_general_settings'=>'Išsaugoti bendruosius nustatymus',
    'save_theme_settings'=>'Išsaugoti temos nustatymus',
    'save_branding_settings'=>'Išsaugoti prekės ženklo nustatymus',
    'save_email_settings'=>'Išsaugoti el. pašto nustatymus',
    'header_layout'=>'Antraštės išdėstymas',
    'show_site_title_next_to_logo'=>'Rodyti svetainės pavadinimą šalia logotipo',
    'no_logo'=>'Be logotipo',
    'upload_another_image'=>'Įkelti kitą paveikslėlį',
    'site_title_used_until_logo'=>'Svetainės pavadinimas bus naudojamas, kol nepasirinksite logotipo.',
    'matte_dark_green'=>'Matinė tamsiai žalia',
    'matte_light_grey'=>'Matinė šviesiai pilka',
    'matte_charcoal_grey'=>'Matinė tamsiai pilka (anglies)',
    'cms_and_website_theme'=>'Sistemos ir svetainės tema',
    'branding_and_identity'=>'Prekės ženklas ir tapatybė',
    'dark_theme_logo'=>'Tamsios temos logotipas',
    'light_theme_logo'=>'Šviesios/pilkos temos logotipas',
    'use_builtin_dark_logo'=>'Naudoti integruotą tamsų logotipą',
    'use_builtin_light_logo'=>'Naudoti integruotą šviesų logotipą',
    'use_builtin_favicon'=>'Naudoti integruotą ikonėlę',
    'builtin_erased_identity'=>'Integruota ERASED tapatybė',
    'custom_uploaded_logos'=>'Įkelti savi logotipai',
    'bulk_action'=>'Masinis veiksmas...',
    'apply_to_selected'=>'Taikyti pasirinktiems',
    'select_all'=>'Pažymėti visus',
    'mark_pending'=>'Žymėti kaip laukiantį',
    'mark_spam'=>'Žymėti kaip šlamštą',
    'payments_and_gateways'=>'Mokėjimai ir vartai',
    'memberships_and_plans'=>'Narystės ir planai',
    'branding_and_logos'=>'Prekės ženklas ir logotipai',
    'homepage_studio'=>'Pagrindinio puslapio studija',
    'apply_selected_site_lang_admin'=>'Taikyti pasirinktą svetainės kalbą valdymo skydeliui',
    'visitor_and_admin_experience'=>'Lankytojų ir administratoriaus patirtis',
    'your_posts'=>'Jūsų įrašai'
   ];
  }
 }
  if($code==='nb'){
   if($group==='site'){
    return [
    'admin'=>'Administrasjon',
    'approved'=>'godkjent',
    'categories'=>'Kategorier',
    'category'=>'Kategori',
    'close'=>'Lukk',
    'comment'=>'Kommentar',
    'comment_captcha_label'=>'Sikkerhetsverifisering',
    'comment_captcha_subtitle'=>'Spam-beskyttelse',
    'comment_captcha_question'=>'Hva er',
    'comment_captcha_answer_placeholder'=>'Ditt svar',
    'comment_captcha_failed'=>'Sikkerhetssjekk mislyktes. Vennligst løs oppgaven og prøv igjen.',
    'comment_moderation_note'=>'Kommentarer sjekkes for spam og vil vises etter godkjenning.',
    'comments'=>'Kommentarer',
    'created'=>'Opprettet',
    'dashboard'=>'Kontrollpanel',
    'download_photo'=>'Last ned bilde',
    'edit'=>'Rediger',
    'email'=>'E-post',
    'email_not_published'=>'Ikke publisert.',
    'empty_region'=>'Tomt område',
    'first_comment'=>'Bli den første til å kommentere.',
    'gallery'=>'Galleri',
    'home'=>'Hjem',
    'join_discussion'=>'Bli med i diskusjonen.',
    'language'=>'Språk',
    'latest_comments'=>'Siste kommentarer',
    'latest_posts'=>'Siste innlegg',
    'login'=>'Logg inn',
    'logout'=>'Logg ut',
    'maintenance'=>'Nettstedet er midlertidig utilgjengelig på grunn av vedlikehold.',
    'min_read'=>'min lesing',
    'minute_read'=>'{minutes} min lesing',
    'minutes_read'=>'{minutes} min lesing',
    'name'=>'Navn',
    'next'=>'Neste',
    'no_categories'=>'Ingen kategorier ennå.',
    'no_content'=>'Ingen publisert innhold ennå.',
    'no_matching_posts'=>'Ingen matchende innlegg funnet.',
    'no_posts'=>'Ingen publiserte innlegg ennå.',
    'no_tags'=>'Ingen emneknagger ennå.',
    'not_found'=>'Ikke funnet',
    'open_page'=>'Åpne side',
    'open_post'=>'Åpne innlegg',
    'page'=>'side',
    'page_not_found'=>'Siden ble ikke funnet.',
    'password'=>'Passord',
    'pending'=>'Venter på godkjenning',
    'popular_tags'=>'Populære emneknagger',
    'post'=>'innlegg',
    'post_comment'=>'Skriv kommentar',
    'posts'=>'Innlegg',
    'previous'=>'Forrige',
    'read_more'=>'Les mer',
    'reader'=>'Leser',
    'remember_me'=>'Husk meg',
    'return_to_homepage'=>'Gå tilbake til forsiden',
    'rss_feed'=>'RSS-strøm',
    'search'=>'Søk',
    'search_placeholder'=>'Søk i innlegg',
    'search_posts'=>'Søk i innlegg',
    'sign_in'=>'Logg inn',
    'subscribe'=>'Abonner',
    'subscribe_news'=>'Abonner på nyheter',
    'subscribe_success'=>'Takk for at du abonnerer!',
    'submit_comment'=>'Send kommentar',
    'view_website'=>'Vis nettsted',
    'welcome_back'=>'Velkommen tilbake',
    'your_email_address'=>'Din e-postadresse',
    'access_denied_policy'=>'Tilgang nektet av sikkerhetspolicy.',
    'admin_ip_restricted'=>'Tilgang er begrenset til godkjente administrator-IP-adresser.',
    'admin_login'=>'Admin-innlogging',
    'all_galleries'=>'Alle gallerier',
    'back_to_login'=>'Tilbake til innlogging',
    'browse_all_photos'=>'Bla gjennom alle bilder på nettstedet',
    'cancel'=>'Avbryt',
    'change_password'=>'Endre passord',
    'comment_posted'=>'Kommentar publisert.',
    'comment_received'=>'Takk. Kommentaren din er mottatt til vurdering.',
    'comment_waiting'=>'Kommentaren er sendt inn og venter på godkjenning.',
    'comments_unavailable'=>'Kommentarer er ikke tilgjengelige.',
    'duplicate_comment'=>'Den kommentaren er allerede mottatt.',
    'emergency_lockdown_message'=>'Nødsperre aktiv: Innlogging for ikke-administratorer er deaktivert.',
    'enter_valid_email'=>'Skriv inn en gyldig e-postadresse.',
    'explore_photo_albums'=>'Utforsk bildealbum og samlinger',
    'forgot_password'=>'Glemt passord',
    'forgot_password_question'=>'Glemt passord?',
    'gallery_not_found'=>'Galleri ikke funnet',
    'invalid_login'=>'Ugyldig innlogging.',
    'invalid_verification_code'=>'Ugyldig bekreftelseskode.',
    'ip_locked_after_failures'=>'Denne IP-adressen er midlertidig sperret etter gjentatte mislykkede innlogginger.',
    'ip_locked_message'=>'Denne IP-adressen er midlertidig sperret. Prøv igjen om omtrent {minutes} minutter.',
    'login_intro'=>'Logg inn for å administrere nettstedet.',
    'maintenance_message'=>'Dette nettstedet er midlertidig utilgjengelig mens oppdateringer utføres.',
    'maintenance_title'=>'Vedlikehold',
    'manage_galleries'=>'Administrer gallerier',
    'newsletter'=>'Nyhetsbrev',
    'newsletter_unsubscribed'=>'Du er nå avmeldt.',
    'no_photos_in_gallery'=>'Ingen bilder i dette galleriet ennå.',
    'no_photos_uploaded_yet'=>'Ingen bilder lastet opp ennå.',
    'open_media_library'=>'Åpne mediebiblioteket',
    'password_changed_success'=>'Passordet er endret. Alle tidligere økter er logget ut. Du kan nå logge inn.',
    'password_reset_email_sent'=>'Hvis kontoen finnes, er en e-post for tilbakestilling av passord sendt.',
    'photo_collection'=>'Bildesamling',
    'photo_gallery_title'=>'Bildegalleri',
    'photo_galleries_title'=>'Bildegallerier',
    'read_only_mode_message'=>'Nettstedsendringer er midlertidig deaktivert av administratorpolicy.',
    'read_only_mode_title'=>'Skrivebeskyttet modus aktiv',
    'reset_link_invalid'=>'Denne tilbakestillingslenken er ugyldig, utløpt eller allerede brukt.',
    'reset_password'=>'Tilbakestill passord',
    'return_to_galleries'=>'Tilbake til bildegallerier',
    'return_to_website'=>'Gå tilbake til nettstedet',
    'send_reset_link'=>'Send tilbakestillingslenke',
    'too_many_comments'=>'For mange kommentarer ble sendt inn. Prøv igjen senere.',
    'too_many_failed_attempts'=>'For mange mislykkede forsøk. Prøv igjen senere.',
    'turnstile_failed'=>'Cloudflare Turnstile-sikkerhetssjekk mislyktes. Vennligst prøv igjen.',
    'verification_email_failed'=>'E-postbekreftelsen kunne ikke sendes. Sjekk e-postinnstillingene eller kontakt nettstedseieren.',
    'verification_expired'=>'Bekreftelsen er utløpt. Vennligst logg inn på nytt.',
    'verify'=>'Bekreft',
    'verify_login'=>'Bekreft innlogging',
    'verify_login_intro'=>'Skriv inn den sekssifrede koden som ble sendt til e-postadressen din.',
    'verification_code'=>'Bekreftelseskode',
    'password_help_text'=>'Minst 8 tegn. For bedre sikkerhet, bruk 12 tegn eller mer.',
    'waf_forbidden'=>'403 Forbudt - Forespørselen ble blokkert av ERASED Web Application Firewall (WAF).'
   ];
   } else {
    return [
    'access_level'=>'Tilgangsnivå',
    'account'=>'Konto',
    'actions'=>'Handlinger',
    'active'=>'Aktiv',
    'active_for_visitors'=>'Aktiv for besøkende',
    'add_language'=>'Legg til språk',
    'add_language_help'=>'Bruk en ISO-språkkode som de, fr, lt, nb, pl eller ua.',
    'admin_default'=>'Standard for kontrollpanel',
    'admin_language'=>'Språk for kontrollpanel',
    'admin_language_help'=>'Brukes for menyer, verktøy, innstillinger og systemmeldinger i kontrollpanelet.',
    'admin_panel'=>'Kontrollpanel',
    'admin_text'=>'Tekst i kontrollpanel',
    'appearance'=>'Utseende',
    'approve'=>'Godkjenn',
    'author'=>'Forfatter',
    'back'=>'Tilbake',
    'backups'=>'Sikkerhetskopier',
    'captcha_active'=>'CAPTCHA aktiv',
    'captcha_comment_protection'=>'Aktiver CAPTCHA-beskyttelse på kommentarskjema (spam-beskyttelse)',
    'captcha_comment_security'=>'Aktiver CAPTCHA-beskyttelse i kommentarfeltet (matematisk CAPTCHA eller Cloudflare Turnstile)',
    'captcha_disabled'=>'CAPTCHA deaktivert',
    'categories'=>'Kategorier',
    'close'=>'Lukk',
    'cloudflare_integration'=>'Cloudflare Turnstile spam-beskyttelse',
    'comment'=>'Kommentar',
    'comments'=>'Kommentarer',
    'comments_heading'=>'Kommentarer',
    'content'=>'Innhold',
    'create_post'=>'Opprett innlegg',
    'created'=>'Opprettet',
    'dashboard'=>'Kontrollpanel',
    'date_format'=>'Datoformat',
    'default_languages'=>'Standardspråk',
    'delete'=>'Slett',
    'delete_language_confirm'=>'Slette dette språket og alle dets oversettelser?',
    'detect_browser_language'=>'Oppdag nettleserspråk',
    'detect_browser_language_help'=>'Bruker besøkendes nettleserspråk automatisk før de velger et.',
    'disabled'=>'Deaktivert',
    'display_name'=>'Visningsnavn',
    'draft'=>'Utkast',
    'edit'=>'Rediger',
    'edit_translations'=>'Rediger oversettelser',
    'email'=>'E-post',
    'email_not_published'=>'Ikke publisert.',
    'email_settings'=>'E-postinnstillinger',
    'export'=>'Eksporter',
    'export_language'=>'Eksporter språkfil',
    'gallery'=>'Galleri',
    'general_settings'=>'Generelle innstillinger',
    'header_logo'=>'Topptekst og logo',
    'homepage_layout'=>'Forsidelayout',
    'import'=>'Import / Eksport',
    'installed'=>'Installert',
    'installed_languages'=>'Installerte språk',
    'installed_languages_help'=>'Aktiver språk, vurder oversettelsesdekning, rediger tekst eller eksporter språkfiler.',
    'language'=>'Språk',
    'language_code'=>'Språkkode',
    'language_defaults'=>'Standardspråk',
    'language_defaults_help'=>'Velg separate grensesnittspråk for administratorer og besøkende.',
    'language_settings'=>'Språkinnstillinger',
    'language_settings_saved'=>'Språkinnstillinger lagret.',
    'languages'=>'Språk',
    'languages_intro'=>'Administrer språk for nettsted og kontrollpanel, oversettelsesdekning og besøkendes språkvalg.',
    'level'=>'Nivå',
    'logout'=>'Logg ut',
    'memberships'=>'Medlemskap',
    'missing'=>'Mangler',
    'native_name'=>'Morsmålsnavn',
    'navigation_menu'=>'Navigasjonsmeny',
    'new_language_added'=>'Språk lagt til. Du kan nå oversette tekst for kontrollpanel og nettsted.',
    'new_post'=>'Nytt innlegg',
    'overall_coverage'=>'Total oversettelsesdekning',
    'packages'=>'Pakker',
    'pages'=>'Sider',
    'password'=>'Passord',
    'payments'=>'Betalinger',
    'published'=>'Publisert',
    'publishing'=>'Publisering',
    'regional_format'=>'Regionalt format',
    'save'=>'Lagre',
    'save_changes'=>'Lagre endringer',
    'save_language_settings'=>'Lagre språkinnstillinger',
    'search'=>'Søk i oversettelser',
    'security'=>'Sikkerhet',
    'settings'=>'Innstillinger',
    'show_language_switcher'=>'Vis språkvelger',
    'show_language_switcher_help'=>'Vis en kompakt språkvelger i den offentlige navigasjonen.',
    'site_language'=>'Nettstedsspråk',
    'spam'=>'Spam',
    'status'=>'Status',
    'theme_manager'=>'Temabehandler',
    'timezone'=>'Tidssone',
    'title'=>'Tittel',
    'translated'=>'oversatt',
    'translation_editor'=>'Oversettelsesbehandler',
    'type'=>'Type',
    'upload_new_image'=>'Last opp nytt bilde',
    'user_accounts'=>'Brukerkontoer',
    'users'=>'Brukere',
    'view'=>'Vis',
    'view_site'=>'Se nettsted',
    'visitor_experience'=>'Besøkendes opplevelse',
    'visitor_language'=>'Besøkendes språkvalg',
    'visitor_language_help'=>'Styr hvordan besøkende velger og husker grensesnittspråket.',
    'website'=>'Nettsted',
    'website_default'=>'Nettstedets standard',
    'website_language'=>'Nettstedsspråk',
    'website_language_help'=>'Standard språk for offentlige sider og besøkende.',
    'website_text'=>'Nettstedstekst',
    'widgets_sidebars'=>'Widgeter og sidebarer',
    'wordpress_migration'=>'WordPress-migrering',
    'write_post'=>'Skriv innlegg',
    'your_own_posts'=>'Dine egne innlegg',
    'your_permissions'=>'Dine tillatelser',
    'your_posts'=>'Dine innlegg'
   ];
   }
  }
  if($code==='ua'){
   if($group==='site'){
    return [
    'admin'=>'Адміністрування',
    'approved'=>'схвалено',
    'categories'=>'Категорії',
    'category'=>'Категорія',
    'close'=>'Закрити',
    'comment'=>'Коментар',
    'comment_captcha_label'=>'Перевірка безпеки',
    'comment_captcha_subtitle'=>'Захист від спаму',
    'comment_captcha_question'=>'Скільки буде',
    'comment_captcha_answer_placeholder'=>'Ваша відповідь',
    'comment_captcha_failed'=>'Перевірка безпеки не пройдена. Розв\'яжіть завдання та спробуйте знову.',
    'comment_moderation_note'=>'Коментарі перевіряються на наявність спаму та з\'являться після схвалення.',
    'comments'=>'Коментарі',
    'created'=>'Створено',
    'dashboard'=>'Панель керування',
    'download_photo'=>'Завантажити фото',
    'edit'=>'Редагувати',
    'email'=>'Електронна пошта',
    'email_not_published'=>'Не публікується.',
    'empty_region'=>'Порожня область',
    'first_comment'=>'Будьте першим, хто залишить коментар.',
    'gallery'=>'Галерея',
    'home'=>'Головна',
    'join_discussion'=>'Приєднуйтесь до обговорення.',
    'language'=>'Мова',
    'latest_comments'=>'Останні коментарі',
    'latest_posts'=>'Останні записи',
    'login'=>'Увійти',
    'logout'=>'Вийти',
    'maintenance'=>'Сайт тимчасово недоступний через технічне обслуговування.',
    'min_read'=>'хв читання',
    'minute_read'=>'{minutes} хв читання',
    'minutes_read'=>'{minutes} хв читання',
    'name'=>'Ім\'я',
    'next'=>'Наступний',
    'no_categories'=>'Категорій ще немає.',
    'no_content'=>'Опублікованого контенту ще немає.',
    'no_matching_posts'=>'Записи за запитом не знайдені.',
    'no_posts'=>'Опублікованих записів ще немає.',
    'no_tags'=>'Тегів ще немає.',
    'not_found'=>'Не знайдено',
    'open_page'=>'Відкрити сторінку',
    'open_post'=>'Відкрити запис',
    'page'=>'сторінка',
    'page_not_found'=>'Сторінку не знайдено.',
    'password'=>'Пароль',
    'pending'=>'Очікує підтвердження',
    'popular_tags'=>'Популярні теги',
    'post'=>'запис',
    'post_comment'=>'Написати коментар',
    'posts'=>'Записи',
    'previous'=>'Попередній',
    'read_more'=>'Читати далі',
    'reader'=>'Читач',
    'remember_me'=>'Запам\'ятати мене',
    'return_to_homepage'=>'Повернутися на головну',
    'rss_feed'=>'RSS стрічка',
    'search'=>'Пошук',
    'search_placeholder'=>'Шукати записи',
    'search_posts'=>'Шукати записи',
    'sign_in'=>'Увійти',
    'subscribe'=>'Підписатися',
    'subscribe_news'=>'Підписатися на новини',
    'subscribe_success'=>'Дякуємо за підписку!',
    'submit_comment'=>'Надіслати коментар',
    'view_website'=>'Переглянути сайт',
    'welcome_back'=>'З поверненням',
    'your_email_address'=>'Ваша електронна адреса'
   ];
   } else {
    return [
    'access_level'=>'Рівень доступу',
    'account'=>'Обліковий запис',
    'actions'=>'Дії',
    'active'=>'Активний',
    'active_for_visitors'=>'Активно для відвідувачів',
    'add_language'=>'Додати мову',
    'add_language_help'=>'Використовуйте ISO код мови, наприклад de, fr, lt, nb, pl або ua.',
    'admin_default'=>'За замовчуванням у панелі',
    'admin_language'=>'Мова панелі керування',
    'admin_language_help'=>'Використовується для меню, інструментів, налаштувань та системних повідомлень у панелі.',
    'admin_panel'=>'Панель керування',
    'admin_text'=>'Текст панелі',
    'appearance'=>'Оформлення',
    'approve'=>'Схвалити',
    'author'=>'Автор',
    'back'=>'Назад',
    'backups'=>'Резервні копії',
    'captcha_active'=>'CAPTCHA активна',
    'captcha_comment_protection'=>'Увімкнути захист CAPTCHA у формі коментарів (захист від спаму)',
    'captcha_comment_security'=>'Увімкнути захист CAPTCHA у розділі коментарів (математична CAPTCHA або Cloudflare Turnstile)',
    'captcha_disabled'=>'Вимкнено',
    'categories'=>'Категорії',
    'close'=>'Закрити',
    'cloudflare_integration'=>'Захист від спаму Cloudflare Turnstile',
    'comment'=>'Коментар',
    'comments'=>'Коментарі',
    'comments_heading'=>'Коментарі',
    'content'=>'Контент',
    'create_post'=>'Створити запис',
    'created'=>'Створено',
    'dashboard'=>'Панель керування',
    'date_format'=>'Формат дати',
    'default_languages'=>'Мови за замовчуванням',
    'delete'=>'Видалити',
    'delete_language_confirm'=>'Видалити цю мову та всі її переклади?',
    'detect_browser_language'=>'Визначати мову браузера',
    'detect_browser_language_help'=>'Автоматично використовувати мову браузера відвідувача.',
    'disabled'=>'Вимкнено',
    'display_name'=>'Відображуване ім\'я',
    'draft'=>'Чернетка',
    'edit'=>'Редагувати',
    'edit_translations'=>'Редагувати переклади',
    'email'=>'Електронна пошта',
    'email_not_published'=>'Не публікується.',
    'email_settings'=>'Налаштування пошти',
    'export'=>'Експорт',
    'export_language'=>'Експортувати файл мови',
    'gallery'=>'Галерея',
    'general_settings'=>'Загальні налаштування',
    'header_logo'=>'Шапка та логотип',
    'homepage_layout'=>'Конструктор головної сторінки',
    'import'=>'Імпорт / Експорт',
    'installed'=>'Встановлено',
    'installed_languages'=>'Встановлені мови',
    'installed_languages_help'=>'Активуйте мови, переглядайте стан перекладу, редагуйте текст або експортуйте файли перекладу.',
    'language'=>'Мова',
    'language_code'=>'Код мови',
    'language_defaults'=>'Мови за замовчуванням',
    'language_defaults_help'=>'Оберіть окремі мови інтерфейсу для адміністраторів та відвідувачів.',
    'language_settings'=>'Налаштування мови',
    'language_settings_saved'=>'Налаштування мови збережено.',
    'languages'=>'Мови',
    'languages_intro'=>'Керуйте мовами сайту та панелі, станом перекладу та поведінкою вибору мови.',
    'level'=>'Рівень',
    'logout'=>'Вийти',
    'memberships'=>'Членство',
    'missing'=>'Відсутнє',
    'native_name'=>'Рідна назва',
    'navigation_menu'=>'Меню навігації',
    'new_language_added'=>'Мову додано. Тепер ви можете перекласти її текст для панелі та сайту.',
    'new_post'=>'Новий запис',
    'overall_coverage'=>'Загальне покриття перекладу',
    'packages'=>'Пакети',
    'pages'=>'Сторінки',
    'password'=>'Пароль',
    'payments'=>'Платежі',
    'published'=>'Опубліковано',
    'publishing'=>'Публікація',
    'regional_format'=>'Регіональний формат',
    'save'=>'Зберегти',
    'save_changes'=>'Зберегти зміни',
    'save_language_settings'=>'Зберегти налаштування мови',
    'search'=>'Пошук перекладів',
    'security'=>'Безпека',
    'settings'=>'Налаштування',
    'show_language_switcher'=>'Показувати перемикач мов',
    'show_language_switcher_help'=>'Відображати компактний перемикач у публічній навігації.',
    'site_language'=>'Мова сайту',
    'spam'=>'Спам',
    'status'=>'Статус',
    'theme_manager'=>'Менеджер тем',
    'timezone'=>'Часовий пояс',
    'title'=>'Заголовок',
    'translated'=>'перекладено',
    'translation_editor'=>'Редактор перекладу',
    'type'=>'Тип',
    'upload_new_image'=>'Завантажити нове зображення',
    'user_accounts'=>'Облікові записи користувачів',
    'users'=>'Користувачі',
    'view'=>'Переглянути',
    'view_site'=>'Переглянути сайт',
    'visitor_experience'=>'Досвід відвідувача',
    'visitor_language'=>'Вибір мови відвідувачем',
    'visitor_language_help'=>'Керуйте тим, як відвідувачі обирають та запам\'ятовують мову інтерфейсу.',
    'website'=>'Сайт',
    'website_default'=>'Сайт за замовчуванням',
    'website_language'=>'Мова сайту',
    'website_language_help'=>'Мова за замовчуванням для публічних сторінок та відвідувачів.',
    'website_text'=>'Текст сайту',
    'widgets_sidebars'=>'Віджети та бічні панелі',
    'wordpress_migration'=>'Міграція з WordPress',
    'write_post'=>'Написати запис',
    'your_own_posts'=>'Ваші власні записи',
    'your_permissions'=>'Ваші дозволи',
    'your_posts'=>'Ваші записи'
   ];
   }
  }
 return [];
}
function language_dir(string $language): string {
 $language=preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/i',$language)?strtolower($language):'en';
 if($language==='uk')$language='ua';
 return ROOT.'/storage/languages/'.$language;
}
function ensure_language_files(?string $language=null): void {
 $languages=[];
 if($language!==null&&trim($language)!=='') $languages[]=$language;
 else{
  $languages[]='en';
  try{foreach(db()->query('SELECT code FROM languages') as $row)$languages[]=(string)($row['code']??'');}catch(Throwable $e){}
 }
 $languages=array_values(array_unique(array_filter(array_map(static function($v){
  $c=strtolower(trim((string)$v));
  return $c==='uk'?'ua':$c;
 },$languages))));
 foreach($languages as $code){
  $dir=language_dir($code);
  if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Could not create language directory.');
  foreach(['site','admin'] as $group){
   $defaults=erased_master_translation_defaults($group);
   $langDefaults=erased_master_language_defaults($code,$group);
   $file=$dir.'/'.$group.'.json';
   if(!is_file($file)){
    $initial=[];
    foreach($defaults as $k=>$def){
     $initial[$k]=$code==='en'?$def:($langDefaults[$k]??'');
    }
    if(file_put_contents($file,json_encode($initial,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",LOCK_EX)===false){error_log('ERASED CMS: Failed to write language file: '.$file);}
   }else{
    $existing=json_decode((string)file_get_contents($file),true);
    if(!is_array($existing))$existing=[];
    $updated=false;
    foreach($defaults as $k=>$def){
     if(!array_key_exists($k,$existing)||trim((string)$existing[$k])===''){
      $val=$code==='en'?$def:($langDefaults[$k]??'');
      if($val!==''){
       $existing[$k]=$val;
       $updated=true;
      }elseif(!array_key_exists($k,$existing)){
       $existing[$k]='';
       $updated=true;
      }
     }
    }
    if($updated){
     ksort($existing);
     if(file_put_contents($file,json_encode($existing,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",LOCK_EX)===false){error_log('ERASED CMS: Failed to update language file: '.$file);}
    }
   }
  }
 }
}
function active_languages(): array {
 try{
  $q=db()->query("SELECT code,name,native_name,is_default,is_active,is_rtl FROM languages WHERE is_active=1 ORDER BY is_default DESC,name ASC");
  $rows=$q->fetchAll();
  if(is_array($rows)&&$rows)return $rows;
 }catch(Throwable $e){}
 $english=language_catalog()['en'];
 return [['code'=>'en','name'=>$english['name'],'native_name'=>$english['native'],'is_default'=>1,'is_active'=>1,'is_rtl'=>0]];
}
function save_translation_file(string $language,string $group,array $values): void {
 $group=$group==='admin'?'admin':'site';
 ensure_language_files($language);
 $file=language_dir($language).'/'.$group.'.json';
 $json=json_encode($values,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 if($json===false||file_put_contents($file,$json."\n",LOCK_EX)===false)throw new RuntimeException('Could not save translation file.');
}
/**
 * The one code path for fully removing a language: its `languages` row, its
 * `storage/languages/{code}/` files, and resetting any setting that pointed
 * at it. Shared by the manual admin delete action and a language pack's
 * uninstall(removeData:true) lifecycle hook, so the two can never drift.
 */
function erased_delete_language_completely(string $code): void {
 $code=strtolower(trim($code));
 if($code===''||$code==='en')throw new RuntimeException('English is the built-in fallback language and cannot be deleted.');
 if($code===(string)setting('admin_language','en'))set_setting('admin_language','en');
 if($code===(string)setting('site_language','en'))set_setting('site_language','en');
 db()->prepare('DELETE FROM languages WHERE code=?')->execute([$code]);
 $dir=language_dir($code);
 if(is_dir($dir)){
  foreach(scandir($dir)?:[] as $file){
   if($file==='.'||$file==='..')continue;
   $target=$dir.'/'.$file;
   if(is_file($target)||is_link($target))@unlink($target);
  }
  @rmdir($dir);
 }
}
function current_language(bool $admin=false): string {
 $syncAdmin=setting('admin_sync_site_language','1')==='1';
 if($admin&&!$syncAdmin){
  $fallback=setting('admin_language','en');
  $sessionKey='admin_language';
  $cookieKey='erased_admin_language';
 }else{
  $fallback=setting('site_language','en');
  $sessionKey='site_language';
  $cookieKey='erased_language';
 }
 $language=trim((string)($_SESSION[$sessionKey]??$_COOKIE[$cookieKey]??$fallback));
 if(!preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/i',$language))$language='en';
 $language=strtolower($language);
 if($language==='uk')$language='ua';
 $_SESSION[$sessionKey]=$language;
 return $language;
}
function translation_data(string $language,string $group='site'): array {
 $group=$group==='admin'?'admin':'site';
 $language=preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})?$/i',$language)?strtolower($language):'en';
 if($language==='uk')$language='ua';
 $defaults=erased_master_translation_defaults($group);
 $langDefaults=erased_master_language_defaults($language,$group);
 $file=ROOT.'/storage/languages/'.$language.'/'.$group.'.json';
 $data=is_file($file)?(json_decode((string)file_get_contents($file),true)?:[]):[];
 $merged=array_merge($defaults,$langDefaults,$data);
 foreach($merged as $k=>$v){
  if(trim((string)$v)===''&&isset($langDefaults[$k])&&trim((string)$langDefaults[$k])!==''){
   $merged[$k]=$langDefaults[$k];
  }
 }
 return $merged;
}
/**
 * Scans core PHP source for real tr('key') / tr('key','group') call sites, so
 * translation coverage is measured against what's actually rendered instead of
 * the full master dictionary - a 2026-08-13 audit found 84% of dictionary keys
 * were never called by tr() anywhere, making the old 100%-of-dictionary number
 * honest but meaningless. Also records, per key, which file it was first found
 * in (used to group the translation editor into real feature sections).
 * Best-effort: a handful of dynamically-built tr() calls with no literal quoted
 * key (string concatenation) won't be detected and will read as "not live" even
 * though they are - disclosed, not silently assumed away.
 * @return array{site:array<string,string>,admin:array<string,string>} key => originating file path
 */
function erased_live_translation_keys(): array {
 static $cache=null;
 if($cache!==null)return $cache;
 $root=defined('ROOT')?ROOT:dirname(__DIR__);
 $files=[];
 if(is_file($root.'/app/bootstrap.php'))$files[]=$root.'/app/bootstrap.php';
 foreach(glob($root.'/public/*.php')?:[] as $f)$files[]=$f;
 foreach(glob($root.'/routes/*.php')?:[] as $f)$files[]=$f;
 $appDir=$root.'/app';
 if(is_dir($appDir)){
  $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir,FilesystemIterator::SKIP_DOTS));
  foreach($iterator as $fileInfo){
   if($fileInfo->isFile()&&strtolower($fileInfo->getExtension())==='php')$files[]=$fileInfo->getPathname();
  }
 }
 $site=[];$admin=[];
 foreach($files as $file){
  // php_strip_whitespace(), not file_get_contents() - strips comments first so a
  // docblock mentioning tr('example') in prose can never be mistaken for a real call.
  $content=(string)@php_strip_whitespace($file);
  if($content===''||!str_contains($content,'tr('))continue;
  $rel=str_starts_with($file,$root.'/')?substr($file,strlen($root)+1):$file;
  $len=strlen($content);$pos=0;
  while(($idx=strpos($content,'tr(',$pos))!==false){
   $prevChar=$idx>0?$content[$idx-1]:'';
   if(ctype_alnum($prevChar)||$prevChar==='_'){$pos=$idx+3;continue;}
   $depth=1;$i=$idx+3;
   while($i<$len&&$depth>0){
    if($content[$i]==='(')$depth++;elseif($content[$i]===')')$depth--;
    $i++;
   }
   $argsStr=substr($content,$idx+3,max(0,$i-$idx-4));
   $pos=$i;
   if(!preg_match_all("/'([a-zA-Z0-9_]+)'/",$argsStr,$m)||$m[1]===[])continue;
   $keys=$m[1];$group='site';
   if(count($keys)>1){
    $last=$keys[count($keys)-1];
    if($last==='admin'||$last==='site'){$group=$last;array_pop($keys);}
   }
   foreach($keys as $k){
    if($group==='admin'){if(!isset($admin[$k]))$admin[$k]=$rel;}
    else{if(!isset($site[$k]))$site[$k]=$rel;}
   }
  }
 }
 $cache=['site'=>$site,'admin'=>$admin];
 return $cache;
}
/** Human-readable section label for a translation key, derived from where it's
 * actually used (falls back to the key's own name for keys not currently live
 * anywhere - see erased_live_translation_keys()). */
function erased_translation_key_section(string $key,string $group,?string $originFile): string {
 $sections=[
  'routes/auth.php'=>'Login &amp; Authentication',
  'routes/gallery.php'=>'Photo Galleries',
  'routes/newsletter.php'=>'Newsletter',
  'app/LayoutStudio/LayoutStudioAdminScreen.php'=>'Layout Studio',
  'app/HomepageLayout.php'=>'Homepage Sections',
  'app/Admin/DashboardOpsDeck.php'=>'Admin Dashboard',
 ];
 if($originFile!==null){
  if(isset($sections[$originFile]))return $sections[$originFile];
  if(str_starts_with($originFile,'app/Packages/'))return 'Package Manager';
  if(str_starts_with($originFile,'app/Admin/'))return 'Admin Panel';
  if($originFile==='public/index.php')return $group==='admin'?'Admin Panel':'Public Website';
  if($originFile==='routes/admin.php')return 'Admin Panel';
  if($originFile==='routes/public.php')return 'Public Website';
 }
 $prefixMap=[
  'comment_'=>'Comments','waf_'=>'Security','security_'=>'Security','ip_locked'=>'Security',
  'password_'=>'Login &amp; Authentication','verify'=>'Login &amp; Authentication','login'=>'Login &amp; Authentication',
  'admin_login'=>'Login &amp; Authentication','forgot_password'=>'Login &amp; Authentication','reset_'=>'Login &amp; Authentication',
  'turnstile'=>'Login &amp; Authentication','invalid_login'=>'Login &amp; Authentication','too_many_failed'=>'Login &amp; Authentication',
  'gallery'=>'Photo Galleries','photo_'=>'Photo Galleries','no_photos'=>'Photo Galleries',
  'newsletter'=>'Newsletter','subscribe'=>'Newsletter','unsubscri'=>'Newsletter',
  'maintenance'=>'Site Status','read_only'=>'Site Status','emergency_lockdown'=>'Site Status','access_denied'=>'Site Status','admin_ip_restricted'=>'Site Status',
 ];
 foreach($prefixMap as $needle=>$section){
  if(str_contains($key,$needle))return $section;
 }
 return $group==='admin'?'Admin Panel':'Public Website';
}
function erased_language_completion(string $code): array {
 $code=strtolower(trim($code));
 if($code==='uk')$code='ua';
 $live=erased_live_translation_keys();
 $adminBase=array_intersect_key(erased_master_translation_defaults('admin'),$live['admin']);
 $siteBase=array_intersect_key(erased_master_translation_defaults('site'),$live['site']);
 $total=count($adminBase)+count($siteBase);
 if($code==='en')return ['done'=>$total,'total'=>$total,'pct'=>100];
 $adminOwn=json_decode((string)@file_get_contents(language_dir($code).'/admin.json'),true)?:[];
 $siteOwn=json_decode((string)@file_get_contents(language_dir($code).'/site.json'),true)?:[];
 $tech=['SEO','CAPTCHA','RSS','URL','ID','PHP MAIL','SMTP','TLS','SSL','CSV','TXT','IBAN','BIC','WXR','ERASED'];
 $countDone=static function(array $own,array $base)use($tech): int {
  $done=0;
  foreach($base as $k=>$eng){
   $val=trim((string)($own[$k]??''));
   if($val==='')continue;
   $e=trim((string)$eng);
   if(in_array(strtoupper($val),$tech,true)||in_array(strtoupper($e),$tech,true)||$val!==$e){
    $done++;
   }
  }
  return $done;
 };
 $done=$countDone($siteOwn,$siteBase)+$countDone($adminOwn,$adminBase);
 $pct=$total?min(100,(int)round($done/$total*100)):0;
 return ['done'=>$done,'total'=>$total,'pct'=>$pct];
}
function tr(string $key,string $group='site'): string {
 static $cache=[];
 $group=$group==='admin'?'admin':'site';
 $language=current_language($group==='admin');
 $cacheKey=$language.':'.$group;
 if(!isset($cache[$cacheKey]))$cache[$cacheKey]=translation_data($language,$group);
 if(array_key_exists($key,$cache[$cacheKey])&&trim((string)$cache[$cacheKey][$key])!=='')return (string)$cache[$cacheKey][$key];
 $fallbackKey='en:'.$group;
 if(!isset($cache[$fallbackKey]))$cache[$fallbackKey]=translation_data('en',$group);
 if(array_key_exists($key,$cache[$fallbackKey])&&trim((string)$cache[$fallbackKey][$key])!=='')return (string)$cache[$fallbackKey][$key];
 $defaults=erased_master_translation_defaults($group);
 if(isset($defaults[$key]))return $defaults[$key];
 return str_replace('_',' ',ucfirst($key));
}
function reading_time(string $html): int {
 $text=trim(preg_replace('/\s+/u',' ',strip_tags($html))??'');
 if($text==='')return 1;
 preg_match_all('/[\p{L}\p{N}]+(?:[’\'\-][\p{L}\p{N}]+)*/u',$text,$matches);
 $words=count($matches[0]??[]);
 return max(1,(int)ceil($words/200));
}
function flash(string $type,string $message): void {
 $_SESSION['flashes']??=[];
 $_SESSION['flashes'][]=['type'=>$type,'message'=>$message];
}
function take_flashes(): array {
 $flashes=$_SESSION['flashes']??[];
 unset($_SESSION['flashes']);
 if(!is_array($flashes))return [];
 $out=[];
 foreach($flashes as $flash){
  if(!is_array($flash))continue;
  $type=(string)($flash['type']??'notice');
  $message=(string)($flash['message']??'');
  if($message==='')continue;
  $out[]=['type'=>$type,'message'=>$message];
 }
 return $out;
}
function e(string|int|null $v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
/**
 * For a value embedded in a single-quoted JS string literal that itself
 * sits inside an HTML attribute (the onclick="location.href='...'" pattern
 * used throughout the admin rail) - e() alone is NOT enough here, because
 * the browser HTML-decodes the attribute (turning e()'s &#039; back into a
 * real ') before that decoded text is parsed as JS, silently undoing the
 * escaping and letting a quote in the value break out of the JS string.
 * addslashes() first (escaping ' and \ for the JS-string-literal context)
 * then e() (for the HTML-attribute context) closes that gap - found via a
 * v0.8-dev security audit, see docs/STATUS.md.
 */
function e_js_str(string|int|null $v): string{return e(addslashes((string)$v));}
function security_log(string $event,array $context=[],string $level='info'): void{try{(new \Erased\Security\SecurityLogger())->log($event,$context,$level);}catch(Throwable $e){}}
function csrf(): string{$_SESSION['csrf']??=bin2hex(random_bytes(32));return (string)$_SESSION['csrf'];}
function verify_csrf(): void{$provided=(string)($_POST['csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??''));$expected=(string)($_SESSION['csrf']??'');if($expected===''||$provided===''||!hash_equals($expected,$provided)){security_log('csrf.validation_failed',['path'=>(string)($_SERVER['REQUEST_URI']??'')],'warning');http_response_code(419);header('Content-Type: text/plain; charset=UTF-8');exit('Invalid CSRF token');}}
function logged_in(): bool{return isset($_SESSION['user_id']);}
function session_fingerprint(): string{return hash('sha256',substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500));}
// Memoized per request: this is called ~100+ times per admin page load
// (nav-building alone calls can() ~20 times, each resolving current_user()),
// and each uncached call cost 2 SELECTs plus an auth_sessions UPDATE. Session
// state (fingerprint, timeout, version, revocation) never changes mid-request
// via any code path that expects current_user() to observe it immediately, so
// resolving once and reusing the result is safe.
function current_user(): ?array {static $computed=false;static $cached=null;if($computed)return $cached;$computed=true;return $cached=current_user_resolve();}
function current_user_resolve(): ?array {if(!logged_in())return null;if(!isset($_SESSION['session_fingerprint']))$_SESSION['session_fingerprint']=session_fingerprint();if(!hash_equals((string)$_SESSION['session_fingerprint'],session_fingerprint())){security_log('session.fingerprint_mismatch',[],'warning');destroy_auth_session();return null;}$timeout=max(5,min(10080,(int)setting('session_timeout_minutes','30')));$last=(int)($_SESSION['last_activity']??time());if(time()-$last>$timeout*60){destroy_auth_session();flash('error','Your session expired due to inactivity. Please log in again.');return null;}$s=db()->prepare('SELECT * FROM users WHERE id=? AND is_active=1');$s->execute([(int)$_SESSION['user_id']]);$user=$s->fetch();if(!$user){destroy_auth_session();return null;}$stored=(int)($user['session_version']??0);$session=(int)($_SESSION['session_version']??-1);if($session!==$stored){destroy_auth_session();return null;}try{$token=(string)($_SESSION['auth_session_token']??'');if($token===''){destroy_auth_session();return null;}$q=db()->prepare('SELECT id FROM auth_sessions WHERE user_id=? AND token_hash=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([(int)$user['id'],hash('sha256',$token)]);if(!$q->fetchColumn()){destroy_auth_session();return null;}db()->prepare('UPDATE auth_sessions SET last_activity_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE user_id=? AND token_hash=?')->execute([$timeout,(int)$user['id'],hash('sha256',$token)]);}catch(Throwable $e){}$_SESSION['last_activity']=time();return $user;}
function normalized_role(?string $role): string { return match($role){'admin'=>'admin','editor'=>'editor','author','writer'=>'writer','member','user',null,''=>'user',default=>'user'}; }
function role_permissions(string $role): array {$role=normalized_role($role);$base=match($role){'admin'=>['dashboard','content.view','content.create','content.edit.own','content.edit.all','content.publish','content.delete.own','content.delete.all','media.manage','comments.manage','publishing.manage','users.manage','languages.manage','memberships.manage','payments.manage','packages.manage','import.manage','backups.manage','security.manage','settings.manage','core.update'],'editor'=>['dashboard','content.view','content.create','content.edit.own','content.edit.all','content.publish','content.delete.own','content.delete.all','media.manage','comments.manage','publishing.manage'],'writer'=>['dashboard','content.view','content.create','content.edit.own','content.delete.own','media.manage'],default=>['dashboard']};
 // Plugin-declared permissions (package.json 'permissions' field) are
 // granted to admin only - core itself has no per-role permission
 // customization UI, so fine-grained per-role assignment for plugin
 // permissions would be new, larger RBAC scope this doesn't take on.
 if($role!=='admin'||!function_exists('plugin_admin_surface'))return $base;
 return array_values(array_unique(array_merge($base,plugin_admin_surface()->permissionIds())));
}
function can(string $permission): bool { $u=current_user(); return $u && in_array($permission,role_permissions((string)($u['role']??'user')),true); }
function require_permission(string $permission): void { require_login(); if(!can($permission)){http_response_code(403);layout('Permission required','<div class="card"><h1>Permission required</h1><p>Your account level does not allow this action.</p><a class="btn secondary" href="/admin">Back to dashboard</a></div>',true);exit;} }
function erased_package_active(string $packageId): bool { static $cache=[]; if(!array_key_exists($packageId,$cache)){try{$pkg=(new \Erased\Packages\InstalledPackageRepository(db()))->find($packageId);$cache[$packageId]=(bool)($pkg['enabled']??false);}catch(Throwable $e){$cache[$packageId]=false;}} return $cache[$packageId]; }
function is_admin(): bool { return can('users.manage'); }
function require_admin(): void { require_permission('users.manage'); }
function owns_content(array $content): bool { $u=current_user(); return $u && (int)($content['author_id']??0)===(int)$u['id']; }
function can_edit_content(array $content): bool { return can('content.edit.all') || (can('content.edit.own') && owns_content($content)); }
function can_delete_content(array $content): bool { return can('content.delete.all') || (can('content.delete.own') && owns_content($content)); }
function audit(string $action,array $details=[]): void { try{$s=db()->prepare('INSERT INTO audit_log(user_id,event,metadata,ip_address) VALUES(?,?,?,?)');$s->execute([$_SESSION['user_id']??null,$action,json_encode($details,JSON_UNESCAPED_SLASHES),$_SERVER['REMOTE_ADDR']??'']);}catch(Throwable $e){} }
function password_algorithm(): string|int{return defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;}
function secure_password_hash(string $password): string{return password_hash($password,password_algorithm());}
/**
 * Not a real credential - a fixed, valid password_hash() output that
 * routes/auth.php's login handler verifies against when no matching user
 * exists, so that path costs the same as a real password_verify() call
 * instead of returning near-instantly and leaking "this email has no
 * account" via response timing. Found via a v0.8-dev security audit, see
 * docs/STATUS.md.
 */
const ERASED_TIMING_DUMMY_HASH='$argon2id$v=19$m=65536,t=4,p=1$LzFnNFhxUXFhM0hYREdoeA$MFiI6xnEtNnOv/52rywG82v+myNBUH48hmFDBm6nYV4';
function password_policy_errors(string $password): array{$errors=[];$minLength=max(8,(int)setting('password_min_length','8'));if(strlen($password)<$minLength)$errors[]="Use at least {$minLength} characters.";if(setting('password_require_uppercase','1')==='1'&&!preg_match('/[A-Z]/',$password))$errors[]='Add an uppercase letter.';if(setting('password_require_lowercase','0')==='1'&&!preg_match('/[a-z]/',$password))$errors[]='Add a lowercase letter.';if(setting('password_require_number','0')==='1'&&!preg_match('/[0-9]/',$password))$errors[]='Add a number.';if(setting('password_require_symbol','0')==='1'&&!preg_match('/[^A-Za-z0-9]/',$password))$errors[]='Add a symbol.';return $errors;}
function password_policy_recommendations(string $password): array{$recommendations=[];if(strlen($password)<12)$recommendations[]='Use at least 12 characters.';if(!preg_match('/[A-Z]/',$password))$recommendations[]='Add an uppercase letter.';if(!preg_match('/[0-9]/',$password))$recommendations[]='Add a number.';if(!preg_match('/[^A-Za-z0-9]/',$password))$recommendations[]='Add a symbol.';return $recommendations;}
function password_policy_message(): string{return 'Required: at least 8 characters and one uppercase letter. Recommended: use at least 12 characters, add a number, and add a symbol.';}
function password_is_recommended(string $password): bool{return password_policy_recommendations($password)===[];}
function revoke_user_sessions(int $userId): void{try{$q=db()->prepare('UPDATE users SET session_version=COALESCE(session_version,0)+1 WHERE id=?');$q->execute([$userId]);db()->prepare('UPDATE auth_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$userId]);}catch(Throwable $e){}}
function register_auth_session(int $userId): void{try{$token=bin2hex(random_bytes(32));$_SESSION['auth_session_token']=$token;$_SESSION['last_activity']=time();$_SESSION['session_fingerprint']=session_fingerprint();$q=db()->prepare('INSERT INTO auth_sessions(user_id,token_hash,ip_address,user_agent,last_activity_at,expires_at) VALUES(?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? MINUTE))');$q->execute([$userId,hash('sha256',$token),(string)($_SERVER['REMOTE_ADDR']??''),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),max(5,min(10080,(int)setting('session_timeout_minutes','30')))]);}catch(Throwable $e){}}
function destroy_auth_session(): void{try{if(!empty($_SESSION['auth_session_token']))db()->prepare('UPDATE auth_sessions SET revoked_at=NOW() WHERE token_hash=? AND revoked_at IS NULL')->execute([hash('sha256',(string)$_SESSION['auth_session_token'])]);}catch(Throwable $e){}unset($_SESSION['user_id'],$_SESSION['session_version'],$_SESSION['auth_session_token'],$_SESSION['last_activity'],$_SESSION['session_fingerprint'],$_SESSION['pending_2fa_user_id'],$_SESSION['pending_2fa_challenge'],$_SESSION['csrf']);if(session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);}
function require_login(): void{if(!logged_in())redirect('/login');}
/**
 * When the current request arrived through the ?route= rewrite-independent
 * fallback (public/index.php), every internal redirect needs to keep using
 * that same fallback instead of emitting a clean-URL Location header the
 * host can't route - otherwise the fallback only ever covers the single
 * page it was used on, not any redirect chain leading away from it (e.g.
 * install -> login -> admin).
 */
function redirect(string $path): never{
 if(!empty($GLOBALS['erased_no_rewrite_mode'])){
  $parts=parse_url($path);
  $routePath=(string)($parts['path']??'/');
  $extra=[];
  if(!empty($parts['query']))parse_str($parts['query'],$extra);
  $path='?'.http_build_query(array_merge(['route'=>$routePath],$extra));
 }
 header('Location: '.$path);exit;
}
/**
 * Same as redirect(), but for the handful of admin pages ERASED Studio embeds
 * chrome-free via <iframe src="...?studio_embed=1">: if the current request
 * carries that flag, the redirect target carries it too. Without this, a
 * save-and-redirect back to the same page drops the flag, so the iframe's
 * own navigation loads the *full* chrome (sidebar, header) - nested inside
 * the iframe that's already inside ERASED Studio's own chrome. Saving again
 * from inside that nested copy repeats the same mistake, nesting one more
 * layer each time.
 */
function erased_redirect_preserving_studio_embed(string $path): never{
 if(($_GET['studio_embed']??'')==='1')$path.=(str_contains($path,'?')?'&':'?').'studio_embed=1';
 redirect($path);
}
function slugify(string $v): string{$v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v)?:$v;$v=strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','-',$v)??'','-'));return $v!==''?$v:'item-'.bin2hex(random_bytes(3));}
function unique_slug(PDO $pdo,string $slug,?int $ignoreId=null): string{$base=$slug;$n=2;while(true){$sql='SELECT id FROM content WHERE slug=?'.($ignoreId?' AND id<>?':'').' LIMIT 1';$s=$pdo->prepare($sql);$a=[$slug];if($ignoreId)$a[]=$ignoreId;$s->execute($a);if(!$s->fetch())return $slug;$slug=$base.'-'.$n++;}}
