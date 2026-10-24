<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Scheduled backup is a local command only.');}
require __DIR__.'/lib.php';$_SESSION['uid']=0;$_SESSION['role']='Admin';$_SESSION['name']='Scheduled Backup';$lock=null;try{if(!complete_update_ready())throw new Exception('Run the current upgrade first.');$lock=inventory_write_lock();$id=backup_automatic_run();echo $id?'Backup complete #'.$id: 'No backup due';}catch(Throwable $e){fwrite(STDERR,safe_error_message($e));exit(1);}finally{inventory_write_unlock($lock);}
