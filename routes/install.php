<?php
declare(strict_types=1);

if($path==='/install'){if(installed())redirect('/login');$error='';if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$host=trim($_POST['host']??'db');$name=trim($_POST['name']??'erased_cms');$user=trim($_POST['user']??'erased');$pass=$_POST['pass']??'';$email=trim($_POST['email']??'');$admin=$_POST['admin_pass']??'';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($admin)<8)throw new RuntimeException('Use a valid email and a password of at least 8 characters.');$pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);install_schema($pdo);$s=$pdo->prepare("INSERT INTO users(email,password_hash,role,is_active,created_at) VALUES(?,?,'admin',1,NOW())");$s->execute([$email,secure_password_hash($admin)]);
    // v0.8-dev security audit: erased_base_url() falls back to the live
    // request's Host header whenever `site_url` isn't configured, and that
    // fallback is what security-sensitive links (password reset emails,
    // newsletter unsubscribe) are built from - an unconfigured install lets
    // an attacker who controls the Host header on their own /forgot-password
    // request poison the reset link a *different* victim receives by email.
    // The one genuinely trustworthy moment to capture this is right now:
    // whoever is running /install is the site owner, on their real domain.
    // set_setting() isn't usable yet (it goes through the global db()
    // singleton, which reads the config file this very request is about to
    // write) - insert directly through the same raw $pdo the schema/user
    // rows above just used.
    $installScheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
    $installHost=(string)($_SERVER['HTTP_HOST']??'localhost');
    $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['site_url',$installScheme.'://'.$installHost]);$cfg='<?php return '.var_export(['db'=>compact('host','name','user','pass'),'site'=>['name'=>'ERASED CMS']],true).';';if(!is_dir(dirname(CONFIG_FILE))&&!mkdir(dirname(CONFIG_FILE),0770,true)&&!is_dir(dirname(CONFIG_FILE)))throw new RuntimeException('Could not create the storage directory.');if(file_put_contents(CONFIG_FILE,$cfg,LOCK_EX)===false)throw new RuntimeException('Could not write configuration.');redirect('/login?installed=1');}catch(Throwable $e){$error=$e->getMessage();}}layout('Install','<div class="card"><h1>ERASED CMS Installer</h1><p><small>'.e(cms_full_name()).'</small></p>'.($error?'<div class="notice error">'.e($error).'</div>':'').'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>Database host<input name="host" value="db"></label><label>Database name<input name="name" value="erased_cms"></label><label>Database user<input name="user" value="erased"></label><label>Database password<input type="password" name="pass"></label><label>Admin email<input type="email" name="email" value="'.e($old['email']??'').'" required></label><label>Admin password <small>Minimum 8 characters. 12 or more is strongly recommended.</small><input type="password" name="admin_pass" minlength="8" autocomplete="new-password" required></label><button>Install</button></form></div>');exit;}
