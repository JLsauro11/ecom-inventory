<?php
require __DIR__.'/lib.php';if(logged()){header('Location: index.php');exit;}$error='';$sent=false;
if($_SERVER['REQUEST_METHOD']==='POST')try{verify_csrf();request_password_reset($_POST['username']??'');$sent=true;}catch(Throwable $e){$error=safe_error_message($e);}
require_once __DIR__.'/login_a_shell.php';login_a_open('Forgot password?','Request an Admin-assisted password reset.');
if($error)echo '<div class="auth-feedback error" role="alert">'.e($error).'</div>';
if($sent)echo '<div class="auth-feedback success" role="status">If the account is eligible, a password reset request has been sent to an Admin. Please contact your Admin to verify your identity.</div>';
else{?><p class="auth-help">Enter your username. An Admin will verify your identity before issuing a temporary password.</p><form method="post" class="form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label for="reset_username">Username<input id="reset_username" name="username" maxlength="80" autocomplete="username" placeholder="Enter your username" required></label><button class="signin" type="submit">Request Password Reset <span class="arrow" aria-hidden="true">→</span></button></form><?php }
echo '<div class="auth-secondary-actions single"><a class="auth-secondary-button" href="login.php">Back to Sign In</a></div>';login_a_close();
