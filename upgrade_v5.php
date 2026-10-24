<?php
require __DIR__.'/lib.php';
require_login();
if(current_user()['role']!=='Admin'){http_response_code(403);exit('Administrator access required.');}
require __DIR__.'/migration_v5.php';
$msg=$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{verify_csrf();migrate_v5();audit('Database upgrade','System','V5.3','Additive migration completed.');$msg='V5.3 upgrade complete. Existing data, aliases, mappings and history were preserved.';}
 catch(Throwable $e){$err=$e->getMessage();}
}
?><!doctype html><html><head><meta charset="utf-8"><title>V5.3 Upgrade</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/typography.css?v=5.4.6-b"></head><body class="auth-bg"><div class="auth-card install-card"><img class="auth-logo" src="assets/logos/rs8.png"><h1>Version 5.3 Upgrade</h1><p>Back up your files and database first. This upgrade adds missing fields and history tables without replacing records. It is safe to run again.</p><?php if($msg):?><div class="alert success"><?=e($msg)?></div><a class="btn primary" href="update_check.php">Verify Complete Update</a><a class="btn ghost" href="index.php">Open System</a><?php else:?><?php if($err):?><div class="alert danger"><?=e($err)?></div><?php endif;?><form method="post" data-confirm="Apply the V5.3 database upgrade? Existing records will be preserved."><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="btn primary">Run V5.3 Upgrade</button></form><?php endif;?></div><script src="assets/app.js"></script></body></html>
