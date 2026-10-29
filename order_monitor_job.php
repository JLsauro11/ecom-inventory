<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Local scheduled job only.');}
require __DIR__.'/lib.php';if(!om_ready())exit("Order monitoring upgrade is not ready.\n");$lock=inventory_write_lock();try{om_alert_refresh();echo json_encode(['checked_at'=>date(DATE_ATOM),'active_alerts'=>(int)db()->query('SELECT COUNT(*) FROM om_alerts WHERE resolved=0')->fetchColumn()]);}finally{inventory_write_unlock($lock);}
