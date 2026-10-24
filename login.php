<?php
require __DIR__.'/lib.php';if(logged()){header('Location: index.php');exit;}$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();login_rate_update($_POST['username']??'','check');
  $st=db()->prepare('SELECT * FROM users WHERE username=?');$st->execute([trim($_POST['username']??'')]);$r=$st->fetch();
  if(!$r||!password_verify($_POST['password']??'',$r['password_hash'])){login_rate_update($_POST['username']??'','failure');$err='Invalid username or password.';}
  elseif(($r['approval_status']??'Approved')==='Pending')$err='Your account is Pending Approval. Please wait for an administrator to approve your registration.';
  elseif(($r['approval_status']??'Approved')==='Rejected')$err='Your registration was rejected. Please contact the administrator.';
  elseif(!(int)($r['active']??1))$err='Your account is inactive. Please contact the administrator.';
  else{login_rate_update($_POST['username']??'','success');session_regenerate_id(true);$_SESSION['uid']=$r['id'];$_SESSION['name']=$r['name'];$_SESSION['role']=$r['role'];header('Location:'.(!empty($r['must_change_password'])?'account_password.php':'index.php'));exit;}
 }catch(Throwable $e){$err=safe_error_message($e);}
}
require_once __DIR__.'/login_a_shell.php';login_a_open('Welcome back.','Sign in to manage your inventory.');
if($err)echo '<div class="auth-feedback error" role="alert">'.e($err).'</div>';
?><form method="post" class="form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label for="login_username">Username<input id="login_username" name="username" autocomplete="username" maxlength="80" placeholder="Enter your username" value="<?=e($_POST['username']??'')?>" required autofocus></label><label for="login_password">Password<div class="password"><input id="login_password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required><button class="show" type="button" data-auth-toggle aria-controls="login_password" aria-label="Show password" aria-pressed="false">Show</button></div></label><button class="signin" type="submit">Sign In <span class="arrow" aria-hidden="true">→</span></button></form><div class="auth-secondary-actions"><a class="auth-secondary-button" href="forgot_password.php">Forgot Password</a><a class="auth-secondary-button" href="signup.php">Sign Up</a></div><?php login_a_close(); ?>
