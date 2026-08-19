<?php
declare(strict_types=1);

/** Authentication routes extracted from public/index.php. */

if($path==='/forgot-password'){$message='';if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$email=strtolower(trim((string)($_POST['email']??'')));$ip=(string)($_SERVER['REMOTE_ADDR']??'');try{$q=db()->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE (email=? OR ip_address=?) AND created_at>DATE_SUB(NOW(),INTERVAL 60 MINUTE)");$q->execute([$email,$ip]);$limited=(int)$q->fetchColumn()>=5;db()->prepare('INSERT INTO password_reset_requests(email,ip_address) VALUES(?,?)')->execute([$email,$ip]);if(!$limited&&filter_var($email,FILTER_VALIDATE_EMAIL)){$q=db()->prepare('SELECT id,email FROM users WHERE email=? AND is_active=1 LIMIT 1');$q->execute([$email]);if($u=$q->fetch()){$raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);db()->prepare('DELETE FROM password_reset_tokens WHERE user_id=? OR expires_at<NOW()')->execute([(int)$u['id']]);db()->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))')->execute([(int)$u['id'],$hash]);$url=erased_base_url().'/reset-password?token='.$raw;$html='<p>A password reset was requested for your account.</p><p><a href="'.e($url).'">Reset your password</a></p><p>This link expires in 60 minutes.</p>';erased_mail_send($email,'Reset your '.setting('site_name','ERASED CMS').' password',$html);}}}catch(Throwable $e){}$message=tr('password_reset_email_sent');}layout(tr('forgot_password'),'<div class="card login-card"><h1>'.e(tr('forgot_password')).'</h1>'.($message?'<div class="notice success">'.e($message).'</div>':'').'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>'.e(tr('email')).'<input type="email" name="email" required></label><button>'.e(tr('send_reset_link')).'</button></form><p><a href="/login">'.e(tr('back_to_login')).'</a></p></div>');exit;}
if($path==='/reset-password'){$raw=(string)($_GET['token']??$_POST['token']??'');$hash=hash('sha256',$raw);$q=db()->prepare('SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([$hash]);$row=$q->fetch();if($_SERVER['REQUEST_METHOD']==='POST'&&$row){verify_csrf();$password=(string)($_POST['password']??'');if($errors=password_policy_errors($password)){flash('error',implode(' ',$errors));redirect('/reset-password?token='.urlencode($raw));}db()->beginTransaction();db()->prepare('UPDATE users SET password_hash=?,session_version=COALESCE(session_version,0)+1 WHERE id=?')->execute([secure_password_hash($password),(int)$row['user_id']]);db()->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([(int)$row['user_id']]);db()->prepare('DELETE FROM login_two_factor_challenges WHERE user_id=?')->execute([(int)$row['user_id']]);db()->commit();audit('auth.password_reset',['user_id'=>(int)$row['user_id']]);flash('success',tr('password_changed_success'));redirect('/login');}$body=$row?'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="token" value="'.e($raw).'"><label>'.e(tr('new_password')).erased_password_help().'<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><button>'.e(tr('change_password')).'</button></form>':'<div class="notice error">'.e(tr('reset_link_invalid')).'</div>';layout(tr('reset_password'),'<div class="card login-card"><h1>'.e(tr('reset_password')).'</h1>'.$body.'</div>');exit;}
if($path==='/login/verify'){if(logged_in())redirect('/admin');$error='';$uid=(int)($_SESSION['pending_2fa_user_id']??0);$challenge=(int)($_SESSION['pending_2fa_challenge']??0);if(!$uid||!$challenge)redirect('/login');if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$code=preg_replace('/\D/','',(string)($_POST['code']??''));$q=db()->prepare('SELECT * FROM login_two_factor_challenges WHERE id=? AND user_id=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([$challenge,$uid]);$c=$q->fetch();if(!$c||((int)$c['attempts'])>=5){destroy_auth_session();$error=tr('verification_expired');}else{$ok=hash_equals((string)$c['code_hash'],hash('sha256',$code));db()->prepare('UPDATE login_two_factor_challenges SET attempts=attempts+1'.($ok?',used_at=NOW()':'').' WHERE id=?')->execute([$challenge]);if($ok){$q=db()->prepare('SELECT * FROM users WHERE id=? AND is_active=1 LIMIT 1');$q->execute([$uid]);if($u=$q->fetch())erased_complete_login($u);}else$error=tr('invalid_verification_code');}}layout(tr('verify_login'),'<div class="card login-card"><h1>'.e(tr('verify_login')).'</h1><p>'.e(tr('verify_login_intro')).'</p>'.($error?'<div class="notice error">'.e($error).'</div>':'').'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><label>'.e(tr('verification_code')).'<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><button>'.e(tr('verify')).'</button></form><p><a href="/logout">'.e(tr('cancel')).'</a></p></div>');exit;}
if($path==='/login'){
 if(logged_in())redirect('/admin');$error='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  if(erased_cloudflare_turnstile_enabled()&&!erased_verify_cloudflare_turnstile()){
   $error=tr('turnstile_failed');
  }else{
   $email=strtolower(trim((string)($_POST['email']??'')));$ip=erased_client_ip();
   if($lock=erased_ip_lockout_status($ip)){
    $until=strtotime((string)$lock['locked_until']);$minutes=max(1,(int)ceil(($until-time())/60));$error=str_replace('{minutes}',(string)$minutes,tr('ip_locked_message'));
    erased_security_event('auth.blocked_locked_ip','warning',['ip'=>$ip,'email'=>$email,'locked_until'=>$lock['locked_until']]);
   }else{
    $limit=max(3,(int)setting('rate_limit_login','8'));$window=max(1,(int)setting('ip_lockout_window_minutes','15'));
    $q=db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE (email=? OR ip_address=?) AND successful=0 AND created_at>DATE_SUB(NOW(),INTERVAL ? MINUTE)");$q->execute([$email,$ip,$window]);
    if((int)$q->fetchColumn()>=$limit){$error=tr('too_many_failed_attempts');erased_security_event('auth.rate_limited','warning',['ip'=>$ip,'email'=>$email]);}
    else{
     $q=db()->prepare('SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1');$q->execute([$email]);$u=$q->fetch();$password=(string)($_POST['password']??'');
     // v0.8-dev security audit: password_verify() is a deliberately slow,
     // constant-time comparison - but only *calling* it is constant-time.
     // The old `$u && password_verify(...)` short-circuited past it entirely
     // when no matching user exists, making "no such account" measurably
     // faster than "wrong password" and letting an attacker enumerate valid
     // emails by timing alone (the error message itself is already
     // identical for both cases). Always run password_verify() against a
     // fixed dummy hash in the no-user case so both paths cost the same.
     $ok=password_verify($password,$u?(string)$u['password_hash']:ERASED_TIMING_DUMMY_HASH)&&$u;
     $a=db()->prepare('INSERT INTO login_attempts(email,ip_address,successful) VALUES(?,?,?)');$a->execute([$email,$ip,$ok?1:0]);if(!$ok){try{db()->prepare('INSERT INTO login_history(user_id,email,ip_address,user_agent,successful,failure_reason) VALUES(?,?,?,?,0,?)')->execute([$u?(int)$u['id']:null,$email,$ip,substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),'invalid_credentials']);}catch(Throwable $e){}}
     if($ok){
      erased_clear_login_failures($ip);erased_security_event('auth.login_success','info',['ip'=>$ip,'email'=>$email],$u);
      if(password_needs_rehash((string)$u['password_hash'],password_algorithm()))db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([secure_password_hash($password),(int)$u['id']]);
      if((int)($u['two_factor_enabled']??0)===1&&normalized_role((string)$u['role'])==='admin'){if(erased_start_two_factor($u))redirect('/login/verify');$error=tr('verification_email_failed');}else erased_complete_login($u);
     }else{
      erased_security_event('auth.login_failed','notice',['ip'=>$ip,'email'=>$email]);$newLock=erased_record_login_failure($ip,$email);$error=$newLock?tr('ip_locked_after_failures'):tr('invalid_login');
     }
    }
   }
  }
 }
 layout(tr('admin_login'),'<div class="card login-card"><h1>'.e(tr('admin_login')).'</h1>'.($error?'<div class="notice error">'.e($error).'</div>':'').'<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="email" name="email" placeholder="'.e(tr('email')).'" autocomplete="username" required><input type="password" name="password" placeholder="'.e(tr('password')).'" autocomplete="current-password" required>'.erased_cloudflare_turnstile_widget().'<button>'.e(tr('login')).'</button></form><p><a href="/forgot-password">'.e(tr('forgot_password_question')).'</a></p></div>');exit;
}
if($path==='/logout'){destroy_auth_session();session_destroy();redirect('/login');}
