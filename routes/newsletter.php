<?php
declare(strict_types=1);

if($path==='/subscribe'){
  if($_SERVER['REQUEST_METHOD']!=='POST')redirect('/');verify_csrf();$email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('error',tr('enter_valid_email'));redirect(safe_return('/'));}
  $token=bin2hex(random_bytes(32));$q=db()->prepare("INSERT INTO newsletter_subscribers(email,status,token,subscribed_at,unsubscribed_at) VALUES(?,'active',?,NOW(),NULL) ON DUPLICATE KEY UPDATE status='active',token=VALUES(token),subscribed_at=NOW(),unsubscribed_at=NULL");$q->execute([$email,$token]);flash('success',tr('subscribe_success'));redirect(safe_return('/'));
 }
if($path==='/unsubscribe'){$token=trim((string)($_GET['token']??''));if(preg_match('/^[a-f0-9]{64}$/',$token)){$q=db()->prepare("UPDATE newsletter_subscribers SET status='unsubscribed',unsubscribed_at=NOW() WHERE token=?");$q->execute([$token]);}layout(tr('newsletter'),'<div class="card"><h1>'.e(tr('newsletter')).'</h1><p>'.e(tr('newsletter_unsubscribed')).'</p></div>');exit;}
