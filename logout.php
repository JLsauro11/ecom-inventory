<?php
require __DIR__.'/lib.php';
if(!logged()){header('Location: login.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{verify_csrf();}catch(Throwable $e){http_response_code(403);exit('Logout confirmation expired. Go back and try again.');}
 audit('Logout','User',(string)current_user()['id'],'User logged out.');
 $_SESSION=[];session_destroy();header('Location: login.php');exit;
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirm Logout | E-commerce Inventory Management System</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/typography.css?v=5.4.6-b"></head><body class="auth-bg"><div class="auth-card"><img class="auth-logo" src="assets/logos/rs8.png" alt="RS8"><h1>Confirm Logout</h1><p>Gusto mo bang ituloy ang pag-logout sa system?</p><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="modal-actions"><a class="btn ghost" href="index.php">Cancel</a><button class="btn primary">Ituloy ang Logout</button></div></form></div></body></html>
