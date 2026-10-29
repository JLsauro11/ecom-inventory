<?php

require_once __DIR__.'/order_monitor_reader.php';

function om_ready(){if(!array_key_exists('OM_READY',$GLOBALS))$GLOBALS['OM_READY']=setting('order_monitor_version','')==='5.9.0';return $GLOBALS['OM_READY'];}

function om_allowed(){return in_array(access_profile(),['Admin','Operations Lead'],true);}

function om_require(){if(!om_allowed())throw new Exception('Only Admin and Operations Lead can register orders, upload statuses or manage follow-up.');if(!om_ready())throw new Exception('Ask Admin to run the Order Monitoring upgrade.');}

function om_shops(){return ['Shopee'=>'redspeed08philippines','Lazada'=>'REDSPEED PH','TikTok'=>'RS8 Philippines'];}

function om_shop($platform,$shop){stock_platform($platform);if((om_shops()[$platform]??'')!==$shop)throw new Exception('Select the configured shop for this platform.');return $shop;}

function om_query($sql,$args=[]){$q=db()->prepare($sql);$q->execute($args);return $q;}

function om_event($order,$upload,$type,$details){om_query('INSERT INTO om_events(order_id,upload_id,event_type,details_json,user_id) VALUES(?,?,?,?,?)',[$order,$upload,$type,json_encode($details,JSON_UNESCAPED_UNICODE),current_user()['id']?:null]);}

function om_order($id,$lock=false){$r=om_query('SELECT * FROM om_orders WHERE id=?'.($lock?' FOR UPDATE':''),[(int)$id])->fetch();if(!$r)throw new Exception('Order not found.');return $r;}

function om_find($platform,$shop,$no){return om_query('SELECT * FROM om_orders WHERE platform=? AND shop=? AND order_no=?',[$platform,$shop,$no])->fetch();}

function om_migrate(){

 foreach(explode(';',file_get_contents(__DIR__.'/order_monitor_schema.sql')) as $sql)if(trim($sql)!=='')db()->exec($sql);

 if(setting('om_enabled_at','')==='')set_setting('om_enabled_at',date('Y-m-d H:i:s'));

 set_setting('order_monitor_version','5.9.0');$GLOBALS['OM_READY']=true;unset($GLOBALS['OM_RESERVED']);om_link_legacy();

}

function om_link_legacy(){

 // Existing outflows are adopted as Posted, never reserved or posted again.

 foreach(om_shops() as $pl=>$shop){$q=om_query("SELECT order_id,MIN(txn_date) first_date,MIN(created_at) first_at,MIN(user_id) owner FROM outflows WHERE platform=? AND type='Order' AND qty>0 GROUP BY order_id",[$pl]);foreach($q as $r){if(!$r['order_id'])continue;$old=om_find($pl,$shop,$r['order_id']);if($old)continue;

 process_transaction(function()use($pl,$shop,$r){om_query("INSERT IGNORE INTO om_orders(platform,shop,order_no,created_order_at,fulfillment_date,platform_status,posting,monitoring,owner_id,exception_text) VALUES(?,?,?,?,?,'Needs Checking','Posted','Needs Follow-up',?,?)",[$pl,$shop,$r['order_id'],$r['first_date'].' 00:00:00',$r['first_date'],$r['owner']?:current_user()['id'],'Historical outflow linked; verify platform status and source lines.']);$o=om_find($pl,$shop,$r['order_id']);$rows=om_query("SELECT * FROM outflows WHERE platform=? AND order_id=? AND type='Order' AND qty>0",[$pl,$r['order_id']])->fetchAll();if(!array_filter($rows,fn($x)=>$x['reversed_by_request']===null))om_query("UPDATE om_orders SET posting='Reversed' WHERE id=?",[$o['id']]);foreach($rows as $x)om_query('INSERT IGNORE INTO om_postings(order_id,outflow_id) VALUES(?,?)',[$o['id'],$x['id']]);om_event($o['id'],null,'Historical posting linked',['outflow_ids'=>array_column($rows,'id'),'stock_changed'=>false]);});

 }}

}

function om_reserved($pid,$platform=null){if(!om_ready())return 0;if(!isset($GLOBALS['OM_RESERVED'])){$GLOBALS['OM_RESERVED']=[];foreach(db()->query('SELECT r.product_id,o.platform,SUM(r.qty) qty FROM om_reservations r JOIN om_orders o ON o.id=r.order_id WHERE r.qty>0 GROUP BY r.product_id,o.platform') as $r)$GLOBALS['OM_RESERVED'][(int)$r['product_id']][$r['platform']]=(int)$r['qty'];}$v=$GLOBALS['OM_RESERVED'][(int)$pid]??[];return $platform===null?array_sum($v):($v[$platform]??0);}
function om_available($pid,$platform=null){return ($platform===null?(int)stock_row($pid)['current']:platform_qty($platform,$pid))-om_reserved($pid,$platform);}

function om_assert_available($pid,$qty,$platform=null){if(om_ready()&&$qty>om_available($pid,$platform))throw new Exception('Insufficient available stock after order reservations. Product #'.$pid.': '.om_available($pid,$platform).' available.');}

function om_mapping_key($pl,$shop,$r){return hash('sha256',json_encode([$pl,$shop,$r['external'],$r['sku'],$r['name'],$r['variation']]));}

function om_component_check($raw,$allowInactive=false){if(!$allowInactive)return bundle_components_validate($raw);if(!is_array($raw)||!$raw||count($raw)>50)throw new Exception('Enter 1 to 50 components.');$out=[];$seen=[];foreach($raw as $c){$pid=whole_number($c['product_id']??'','Product',1);if(isset($seen[$pid])||!om_query('SELECT COUNT(*) FROM products WHERE id=?',[$pid])->fetchColumn())throw new Exception('Invalid or repeated product component.');$seen[$pid]=true;$out[]=['product_id'=>$pid,'per_bundle'=>whole_number($c['per_bundle']??'','Quantity',1)];}return $out;}
function om_components($pl,$shop,$r,$allowInactive=false){

 if(isset($r['components']))return om_component_check($r['components'],$allowInactive);$key=om_mapping_key($pl,$shop,$r);$v=om_query('SELECT components_json FROM om_mappings WHERE mapping_key=?',[$key])->fetchColumn();if($v)return om_component_check(json_decode($v,true),$allowInactive);

 if($c=bundle_mapping_get($pl,$r['item']))return om_component_check($c,$allowInactive);

 $ids=[];if($r['external']!==''){$q=om_query('SELECT DISTINCT product_id FROM platform_aliases WHERE platform=? AND external_id=?',[$pl,$r['external']]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}

 if(count($ids)>1)throw new Exception('Ambiguous SKU identity. Select the exact product/variation or bundle.');

 if(!$ids){$candidates=array_unique(array_filter([$r['item'],$r['name'],$r['name'].' '.$r['variation']]));foreach($candidates as $c){$q=om_query('SELECT id FROM products WHERE '.($allowInactive?'':'active=1 AND ').'(master_sku=? OR normalized_name=?)',[$c,norm($c)]);$found=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$aliases=om_query('SELECT product_id FROM product_code_aliases WHERE alias_code=?',[$c])->fetchAll(PDO::FETCH_COLUMN);$found=array_merge($found,array_map('intval',$aliases));$q=om_query('SELECT DISTINCT product_id FROM platform_aliases WHERE platform=? AND normalized_alias=?',[$pl,norm($c)]);$found=array_merge($found,array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)));$ids=array_values(array_unique(array_merge($ids,$found)));}}

 if(count($ids)!==1)throw new Exception('Select the matching product or bundle components.');

 // Seller SKU alone is not sufficient for TikTok variants; exact identity must be saved.

 if($pl==='TikTok'&&$r['variation']!==''&&$r['external']!==''&&!om_query('SELECT COUNT(*) FROM platform_aliases WHERE platform=? AND external_id=?',[$pl,$r['external']])->fetchColumn())throw new Exception('Confirm this TikTok SKU ID and variation once, then save the mapping.');

 return om_component_check([['product_id'=>$ids[0],'per_bundle'=>1]],$allowInactive);

}

function om_totals($lines){$tot=[];foreach($lines as $r)foreach($r['components'] as $c){$pid=(int)$c['product_id'];$tot[$pid]=($tot[$pid]??0)+(int)$r['qty']*(int)$c['per_bundle'];}ksort($tot,SORT_NUMERIC);return $tot;}

function om_existing_totals($id){$tot=[];foreach(om_query("SELECT o.product_id,SUM(o.qty) qty FROM om_postings p JOIN outflows o ON o.id=p.outflow_id WHERE p.order_id=? AND o.reversed_by_request IS NULL GROUP BY o.product_id",[$id]) as $r)$tot[(int)$r['product_id']]=(int)$r['qty'];ksort($tot,SORT_NUMERIC);return $tot;}

function om_upload($id){$b=om_query('SELECT * FROM om_uploads WHERE id=?',[(int)$id])->fetch();if(!$b)throw new Exception('Upload not found.');$b['meta']=json_decode($b['meta_json'],true);$b['rows']=json_decode($b['rows_json'],true);return $b;}

function om_meta($kind,$i){$pl=stock_platform($i['platform']??'');$m=['platform'=>$pl,'shop'=>om_shop($pl,$i['shop']??''),'fulfillment_date'=>process_date($i['fulfillment_date']??date('Y-m-d')),'late'=>!empty($i['late']),'reason'=>!empty($i['late'])?process_reason($i['reason']??''):''];

 if($kind==='Status'){$m['snapshot']=om_datetime($i['snapshot']??'',true);if($m['snapshot']>date('Y-m-d H:i:s'))throw new Exception('Export snapshot cannot be in the future.');$m['coverage_from']=process_date($i['coverage_from']??'');$m['coverage_to']=process_date($i['coverage_to']??'');if($m['coverage_from']>$m['coverage_to']||$m['coverage_to']>substr($m['snapshot'],0,10))throw new Exception('Export coverage is invalid.');$m['all_statuses']=!empty($i['all_statuses']);if(empty($i['coverage_ack']))throw new Exception('Confirm the platform, shop, snapshot and export coverage.');}return $m;

}

function om_stage_rows($kind,$meta,$rows,$name,$stored=null,$hash=null,$warnings=[]){om_require();if(!in_array($kind,['Register','Status'],true))throw new Exception('Unknown upload type.');$meta['warnings']=$warnings;om_query('INSERT INTO om_uploads(kind,platform,shop,filename,stored_path,file_hash,meta_json,rows_json,created_by) VALUES(?,?,?,?,?,?,?,?,?)',[$kind,$meta['platform'],$meta['shop'],$name,$stored,$hash,json_encode($meta),json_encode($rows),current_user()['id']]);$id=(int)db()->lastInsertId();om_event(null,$id,'File validated',['kind'=>$kind,'rows'=>count($rows),'stock_changed'=>false]);return $id;}

function om_stage($kind,$input,$file){om_require();$m=om_meta($kind,$input);validate_upload_file($file,['xlsx']);$r=om_parse($m['platform'],$file['tmp_name']);if($kind==='Status')foreach($r['rows'] as $line){$day=substr($line['created'],0,10);if($day<$m['coverage_from']||$day>$m['coverage_to'])throw new Exception('The file contains orders outside the declared creation-date coverage. Correct the coverage before validating.');foreach(['created','ship_at','delivered_at'] as $k)if(!empty($line[$k])&&$line[$k]>$m['snapshot'])throw new Exception('The export contains timestamps after the declared snapshot time. Enter the actual export time.');}$hash=hash_file('sha256',$file['tmp_name']);$old=om_query("SELECT id FROM om_uploads WHERE kind=? AND platform=? AND shop=? AND file_hash=? AND status='Review' AND created_by=? ORDER BY id DESC LIMIT 1",[$kind,$m['platform'],$m['shop'],$hash,current_user()['id']])->fetchColumn();if($old)throw new Exception('This file already has an open review: #'.$old.'. Open Upload History.');$stored=store_upload($file,'order_monitor','ORD');return om_stage_rows($kind,$m,$r['rows'],basename($file['name']),$stored,$hash,$r['warnings']);}

function om_owner($b){om_require();if(access_profile()!=='Admin'&&(int)$b['created_by']!==current_user()['id'])throw new Exception('Only the uploader or Admin may confirm or correct this upload.');if($b['status']!=='Review')throw new Exception('This upload is already confirmed or cancelled.');}

function om_correct($id,$rowNo,$revision,$input){$b=om_upload($id);om_owner($b);if((int)$b['revision']!==(int)$revision)throw new Exception('Review changed. Reopen it.');$c=bundle_components_validate($input['components']??[]);$found=false;foreach($b['rows'] as &$r)if((int)$r['row']===$rowNo){$r['components']=$c;$found=true;if(!empty($input['save_mapping'])){om_query('INSERT INTO om_mappings(mapping_key,platform,shop,source_json,components_json,saved_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE components_json=VALUES(components_json),saved_by=VALUES(saved_by)',[om_mapping_key($b['platform'],$b['shop'],$r),$b['platform'],$b['shop'],json_encode($r),json_encode($c),current_user()['id']]);}break;}unset($r);if(!$found)throw new Exception('Source row not found.');om_query('UPDATE om_uploads SET rows_json=?,revision=revision+1 WHERE id=?',[json_encode($b['rows']),$id]);om_event(null,$id,'Mapping corrected',['row'=>$rowNo,'components'=>$c]);}

function om_plan($b){

 $groups=[];$snapshots=[];$used=[];$m=$b['meta'];foreach($b['rows'] as $r)$groups[$r['order']][]=$r;$out=[];

 foreach($groups as $no=>$lines){$o=om_find($b['platform'],$b['shop'],(string)$no);$errors=[];$statuses=array_unique(array_column($lines,'status'));$status=count($statuses)===1?reset($statuses):'Needs Checking';$af=(bool)array_filter($lines,fn($r)=>!empty($r['aftersales']));$action='';$tot=[];

 try{

 if($b['kind']==='Register'&&$o){$action='Duplicate — skip';}

 elseif($b['kind']==='Status'&&!$o){$action='Excluded — not registered';}

 else{

 foreach($lines as &$line){$line['components']=om_components($b['platform'],$b['shop'],$line,$b['kind']==='Status');}unset($line);$tot=om_totals($lines);

 if($b['kind']==='Register'){

 if($status!=='To Ship')throw new Exception('Only To Ship orders can be registered for fulfillment.');$session=om_query('SELECT * FROM om_sessions WHERE platform=? AND shop=? AND fulfillment_date=?',[$b['platform'],$b['shop'],$m['fulfillment_date']])->fetch();$snapshots['session']=$session?:null;if($session&&$session['status']==='Closed'&&empty($m['late']))throw new Exception('Registration is closed. Use Add Late Order with a reason.');if(!empty($m['late'])&&empty($m['reason']))throw new Exception('Late-order reason required.');

 foreach($tot as $pid=>$qty){$stock=stock_row($pid);$av=om_available($pid,$b['platform'])-($used[$pid]??0);$snapshots['stock'][$pid]=[$stock['current'],platform_qty($b['platform'],$pid),om_reserved($pid),om_reserved($pid,$b['platform'])];if($qty>$av||$qty>om_available($pid)-($used[$pid]??0))throw new Exception('Insufficient unreserved stock for '.$stock['name'].'. Available on '.$b['platform'].': '.$av.'.');}

 foreach($tot as $pid=>$qty)$used[$pid]=($used[$pid]??0)+$qty;$action='Reserve';

 }else{

 $stored=om_query('SELECT * FROM om_lines WHERE order_id=? ORDER BY id',[$o['id']])->fetchAll();if($stored){$old=[];foreach($stored as $l){$src=json_decode($l['source_json'],true);$old[$l['line_key']]=[$src['qty'],$src['item_ids']??[]];}$new=[];foreach($lines as $l)$new[$l['key']]=[$l['qty'],$l['item_ids']??[]];foreach($old as &$v)sort($v[1],SORT_STRING);unset($v);foreach($new as &$v)sort($v[1],SORT_STRING);unset($v);ksort($old);ksort($new);if($old!==$new)throw new Exception('Product lines, quantities or item IDs differ from registration. Review the original export.');$registered=[];foreach($stored as $l)$registered[]=json_decode($l['source_json'],true)+['components'=>json_decode($l['components_json'],true)];if(om_totals($registered)!==$tot)throw new Exception('Product mapping changed after registration.');}

 elseif(om_existing_totals($o['id'])!==$tot)throw new Exception('Historical posted quantities do not match this export. Correct the mapping or investigate the original outflow.');

 foreach($lines as $ln){foreach(['ship_at','delivered_at'] as $timestamp)if(!empty($ln[$timestamp])&&$ln[$timestamp]>$m['snapshot'])throw new Exception('Export snapshot predates an included shipment or delivery timestamp.');}
 if($m['snapshot']<$o['created_at']&&$o['registration_id'])throw new Exception('Export predates fulfillment registration.');if($o['last_snapshot']&&$m['snapshot']<$o['last_snapshot'])throw new Exception('Older export: keep the newer recorded status.');

 $rank=['Needs Checking'=>0,'Unpaid'=>0,'To Ship'=>1,'Shipped'=>2,'Delivered'=>3,'Completed'=>4,'Cancelled'=>5,'Return / Refund'=>4];if($status==='Needs Checking')throw new Exception('Unknown or conflicting item statuses.');if($o['platform_status']==='Cancelled'&&$status!=='Cancelled')throw new Exception('Cancelled order cannot return to an earlier fulfillment status.');if($status!=='Cancelled'&&($rank[$status]??0)<($rank[$o['platform_status']]??0))throw new Exception('Status rollback blocked.');

 if($status==='Cancelled')$action=$o['posting']==='Posted'?'Cancelled after posting — review receipt/reversal':'Cancel / release';

 elseif(in_array($status,['Shipped','Delivered','Completed'],true)&&$o['posting']==='Not Posted'){$action='Dispatch / post';foreach($tot as $pid=>$qty){$stock=stock_row($pid);$held=(int)om_query('SELECT qty FROM om_reservations WHERE order_id=? AND product_id=?',[$o['id'],$pid])->fetchColumn();$snapshots['stock'][$pid]=[$stock['current'],platform_qty($b['platform'],$pid),om_reserved($pid),om_reserved($pid,$b['platform'])];if($held!==$qty||$qty>$stock['current']||$qty>platform_qty($b['platform'],$pid)||om_available($pid)<0||om_available($pid,$b['platform'])<0)throw new Exception('Reservation or current stock requires allocation review before posting.');}}

 elseif($o['posting']==='Reversed'&&$status!=='Cancelled')throw new Exception('Posting was reversed; review the linked correction before any new dispatch.');

 else $action=$status===$o['platform_status']&&!$af?'Unchanged':'Status only';

 }

 }

 }catch(Throwable $ex){$errors[]=$ex->getMessage();$action='Needs Checking';}

 $out[]=['order'=>(string)$no,'order_record'=>$o?:null,'lines'=>$lines,'totals'=>$tot,'status'=>$status,'aftersales'=>$af,'action'=>$action,'errors'=>$errors];

 }

 if($b['kind']==='Status'&&!empty($m['all_statuses'])){foreach(om_query("SELECT * FROM om_orders WHERE platform=? AND shop=? AND DATE(created_order_at) BETWEEN ? AND ? AND (monitoring<>'Resolved' OR aftersales_resolution='Return Expected')",[$b['platform'],$b['shop'],$m['coverage_from'],$m['coverage_to']]) as $o){if(!$o['registration_id']&&!om_query('SELECT COUNT(*) FROM om_lines WHERE order_id=?',[$o['id']])->fetchColumn())continue;if(isset($groups[$o['order_no']])||($o['last_snapshot']&&$o['last_snapshot']>=$m['snapshot'])||($o['registration_id']&&$o['created_at']>$m['snapshot']))continue;$out[]=['order'=>$o['order_no'],'order_record'=>$o,'lines'=>[],'totals'=>[],'status'=>$o['platform_status'],'aftersales'=>false,'action'=>'Missing from covering export','errors'=>[]];}}

 $plan=['orders'=>$out,'stock'=>$snapshots,'revision'=>$b['revision'],'meta'=>$m];$plan['digest']=hash('sha256',json_encode($plan));return $plan;

}

function om_reserve_change($id,$pid,$delta,$type){unset($GLOBALS['OM_RESERVED']);$old=(int)om_query('SELECT qty FROM om_reservations WHERE order_id=? AND product_id=?',[$id,$pid])->fetchColumn();if($old+$delta<0)throw new Exception('Reservation conflict.');om_query('INSERT INTO om_reservations(order_id,product_id,qty) VALUES(?,?,?) ON DUPLICATE KEY UPDATE qty=VALUES(qty)',[$id,$pid,$old+$delta]);om_query('INSERT INTO om_reservation_events(order_id,product_id,delta,event_type,user_id) VALUES(?,?,?,?,?)',[$id,$pid,$delta,$type,current_user()['id']?:null]);}

function om_release_all($id,$type){foreach(om_query('SELECT * FROM om_reservations WHERE order_id=? AND qty>0',[$id])->fetchAll() as $r)om_reserve_change($id,$r['product_id'],-(int)$r['qty'],$type);}

function om_confirm($id,$digest,$ack){

 om_require();if(!$ack)throw new Exception('Review and acknowledge the validation results.');return process_transaction(function()use($id,$digest){$b=om_upload($id);om_owner($b);$p=om_plan($b);if($b['kind']==='Register'&&array_filter($p['orders'],fn($r)=>$r['errors']))throw new Exception('Resolve every registration issue before confirmation, or cancel and correct the source file. No reservation was created.');if(!hash_equals($p['digest'],(string)$digest))throw new Exception('Stock, registration or review changed. Reopen validation before confirming.');$counts=[];$m=$b['meta'];

 foreach($p['orders'] as $r){$action=$r['action'];$counts[$action]=($counts[$action]??0)+1;$o=$r['order_record'];if($action==='Duplicate — skip'||$action==='Excluded — not registered')continue;if($r['errors']){if($o){om_query("UPDATE om_orders SET exception_text=?,monitoring='Needs Follow-up',revision=revision+1 WHERE id=?",[implode(' · ',$r['errors']),$o['id']]);om_event($o['id'],$id,'Conflicting update blocked',$r);}continue;}

 if($b['kind']==='Register'){

 om_query('INSERT IGNORE INTO om_sessions(platform,shop,fulfillment_date) VALUES(?,?,?)',[$b['platform'],$b['shop'],$m['fulfillment_date']]);$sid=om_query('SELECT id FROM om_sessions WHERE platform=? AND shop=? AND fulfillment_date=?',[$b['platform'],$b['shop'],$m['fulfillment_date']])->fetchColumn();

 om_query("INSERT INTO om_orders(platform,shop,order_no,created_order_at,fulfillment_date,session_id,registration_id,owner_id,raw_status) VALUES(?,?,?,?,?,?,?,?,?)",[$b['platform'],$b['shop'],$r['order'],$r['lines'][0]['created'],$m['fulfillment_date'],$sid,$id,$b['created_by'],$r['lines'][0]['raw_status']]);$oid=(int)db()->lastInsertId();foreach($r['lines'] as $line){$c=$line['components'];unset($line['components']);om_query('INSERT INTO om_lines(order_id,line_key,source_json,components_json) VALUES(?,?,?,?)',[$oid,$line['key'],json_encode($line),json_encode($c)]);}foreach($r['totals'] as $pid=>$qty)om_reserve_change($oid,$pid,$qty,'Reserved');om_event($oid,$id,!empty($m['late'])?'Late order registered':'Order registered',['reason'=>$m['reason'],'totals'=>$r['totals']]);continue;

 }

 $oid=(int)$o['id'];if($action==='Missing from covering export'){om_query("UPDATE om_orders SET exception_text='Missing from covering export',monitoring='Missing',revision=revision+1 WHERE id=?",[$oid]);om_event($oid,$id,'Missing from export',['snapshot'=>$m['snapshot'],'last_status'=>$o['platform_status']]);continue;}

 if($action==='Dispatch / post'){

 $date=substr($m['snapshot'],0,10);$ships=array_filter(array_column($r['lines'],'ship_at'));if($ships)$date=substr(max($ships),0,10);if($date<$o['fulfillment_date'])throw new Exception('Dispatch predates fulfillment registration.');require_open_inventory_date($date);

 // A separate ledger batch per order keeps linked reversals scoped to that order.

 om_query("INSERT INTO import_batches(platform,import_type,filename,stored_path,status,rows_read,units,imported_count,result_json,user_id) VALUES(?,'Orders',?,?,'Complete',?,?,1,?,?)",[$b['platform'],$b['filename'],$b['stored_path'],count($r['lines']),array_sum($r['totals']),json_encode(['order_monitor_upload'=>$id,'order_no'=>$r['order'],'dispatch_confirmed'=>true,'source_snapshot'=>$m['snapshot']]),current_user()['id']]);$batch=(int)db()->lastInsertId();foreach($r['totals'] as $pid=>$qty){check_platform_alignment($pid);om_query('INSERT INTO outflows(txn_date,platform,order_id,product_id,source_item,qty,type,import_batch_id,dedupe_key,remarks,user_id) VALUES(?,?,?,?,?,?,\'Order\',?,?,?,?)',[$date,$b['platform'],$r['order'],$pid,'Order monitor #'.$oid,$qty,$batch,hash('sha256','om|'.$oid.'|'.$pid),'Shop '.$b['shop'].'; status upload #'.$id,current_user()['id']]);$outflow=(int)db()->lastInsertId();om_query('INSERT INTO om_postings(order_id,outflow_id) VALUES(?,?)',[$oid,$outflow]);change_platform_stock($b['platform'],$pid,-$qty,$r['order'],$date);om_reserve_change($oid,$pid,-$qty,'Consumed by dispatch');check_platform_alignment($pid);}om_query("UPDATE om_orders SET posting='Posted' WHERE id=?",[$oid]);

 }

 if($action==='Cancel / release')om_release_all($oid,'Released by cancellation');

 $posted=$action==='Dispatch / post'?'Posted':$o['posting'];$exception=$action==='Cancelled after posting — review receipt/reversal'?'Cancelled after posting: verify physical receipt or request a linked reversal.':'';$monitor=in_array($r['status'],['Completed','Delivered','Cancelled'],true)&&!$exception?'Resolved':'Needs Follow-up';

 om_query('UPDATE om_orders SET status_changed_at=IF(platform_status<>?,NOW(),status_changed_at),platform_status=?,raw_status=?,monitoring=?,exception_text=?,last_seen_at=?,last_snapshot=?,aftersales=IF(?=1,\'Return / Refund\',aftersales),revision=revision+1 WHERE id=?',[$r['status'],$r['status'],implode(' | ',array_unique(array_column($r['lines'],'raw_status'))),$monitor,$exception,$m['snapshot'],$m['snapshot'],$r['aftersales']?1:0,$oid]);

 if(!om_query('SELECT COUNT(*) FROM om_lines WHERE order_id=?',[$oid])->fetchColumn()){om_query('UPDATE om_orders SET created_order_at=? WHERE id=?',[$r['lines'][0]['created'],$oid]);foreach($r['lines'] as $line){$c=$line['components'];unset($line['components']);om_query('INSERT INTO om_lines(order_id,line_key,source_json,components_json) VALUES(?,?,?,?)',[$oid,$line['key'],json_encode($line),json_encode($c)]);}}

 om_event($oid,$id,$action,['before_status'=>$o['platform_status'],'after_status'=>$r['status'],'posting'=>$posted,'snapshot'=>$m['snapshot'],'source_lines'=>$r['lines']]);

 }

 om_query("UPDATE om_uploads SET status='Confirmed',confirmed_at=NOW(),result_json=? WHERE id=?",[json_encode(['counts'=>$counts,'validation'=>$p['orders']]),$id]);audit('Confirm '.$b['kind'].' orders','Order Monitoring',(string)$id,json_encode($counts));om_alert_refresh();return $counts;

 });

}

function om_close($platform,$shop,$date){om_require();$shop=om_shop($platform,$shop);$date=process_date($date);om_query('INSERT IGNORE INTO om_sessions(platform,shop,fulfillment_date) VALUES(?,?,?)',[$platform,$shop,$date]);om_query("UPDATE om_sessions SET status='Closed',closed_by=?,closed_at=NOW() WHERE platform=? AND shop=? AND fulfillment_date=? AND status='Open'",[current_user()['id'],$platform,$shop,$date]);om_event(null,null,'Registration closed',['platform'=>$platform,'shop'=>$shop,'date'=>$date]);audit('Close fulfillment registration','Order Monitoring',$date,$platform.' / '.$shop);}

function om_followup($id,$input){om_require();$o=om_order($id);$reason=process_reason($input['reason']??'');$owner=whole_number($input['owner_id']??'','Responsible user',1);$valid=array_column(array_filter(db()->query("SELECT id,role FROM users WHERE active=1 AND approval_status='Approved'")->fetchAll(),fn($u)=>in_array(access_role_profile($u['role']),['Admin','Operations Lead'],true)),'id');if(!in_array($owner,array_map('intval',$valid),true))throw new Exception('Select an active Admin or Operations Lead.');$next=report_date($input['followup_date']??'');if($next<date('Y-m-d'))throw new Exception('Next follow-up cannot be in the past.');om_query("UPDATE om_orders SET owner_id=?,followup_date=?,monitoring='Carried Over',revision=revision+1 WHERE id=?",[$owner,$next,$id]);om_event($id,null,'Follow-up assigned',['previous_owner'=>$o['owner_id'],'owner'=>$owner,'next'=>$next,'reason'=>$reason]);om_alert_refresh();}

function om_aftersales($id,$outcome,$reason){om_require();$o=om_order($id);$reason=process_reason($reason);if(!in_array($outcome,['Return Expected','Refund Only','Receipt Linked'],true))throw new Exception('Choose a return resolution.');if($outcome==='Receipt Linked'&&!(int)om_query('SELECT COUNT(*) FROM posted_returns WHERE platform=? AND original_ref=? AND reversed=0',[$o['platform'],$o['order_no']])->fetchColumn())throw new Exception('No posted physical receipt found. Use Return Orders first.');om_query('UPDATE om_orders SET aftersales_resolution=?,revision=revision+1 WHERE id=?',[$outcome,$id]);if($o['platform_status']==='Cancelled'&&in_array($outcome,['Refund Only','Receipt Linked'],true))om_query("UPDATE om_orders SET monitoring='Resolved',exception_text='' WHERE id=?",[$id]);om_event($id,null,'After-sales follow-up',['resolution'=>$outcome,'reason'=>$reason]);}

function om_reversal_sync($plan){if(!om_ready())return;foreach($plan['outflows']??[] as $r){$id=om_query('SELECT order_id FROM om_postings WHERE outflow_id=?',[$r['id']])->fetchColumn();if($id){om_query("UPDATE om_orders SET posting='Reversed',monitoring=IF(platform_status='Cancelled','Resolved','Needs Follow-up'),exception_text=IF(platform_status='Cancelled','','Linked reversal posted; review order resolution.'),revision=revision+1 WHERE id=?",[$id]);om_release_all($id,'Released by linked reversal');om_event($id,null,'Linked reversal',['outflow_id'=>$r['id'],'reference'=>$plan['reference']]);}}}

function om_alert_settings(){return array_replace(['high_days'=>7,'reminder'=>'17:30','operating'=>[1,2,3,4,5,6],'overrides'=>[]],json_decode(setting('om_alert_settings','{}'),true)?:[]);}

function om_alert_refresh(){if(!om_ready())return;$cfg=om_alert_settings();$today=date('Y-m-d');$now=date('Y-m-d H:i:s');$active=[];$put=function($key,$oid,$owner,$title)use(&$active){$active[]=$key;om_query('INSERT INTO om_alerts(alert_key,order_id,owner_id,title,resolved) VALUES(?,?,?,?,0) ON DUPLICATE KEY UPDATE owner_id=VALUES(owner_id),title=VALUES(title),resolved=0',[$key,$oid,$owner,$title]);};

 foreach(db()->query("SELECT * FROM om_orders WHERE monitoring<>'Resolved' OR aftersales_resolution='Return Expected' OR exception_text<>''") as $o){$age=(int)((strtotime($today)-strtotime($o['fulfillment_date']))/86400);if($o['exception_text']||$age>=(int)$cfg['high_days']||($o['followup_date']&&$o['followup_date']<=$today))$put('order-'.$o['id'],$o['id'],$o['owner_id'],($age>=(int)$cfg['high_days']?'HIGH · ':'').$o['order_no'].' · '.($o['exception_text']?:($o['aftersales_resolution']==='Return Expected'?'Return expected':$o['platform_status'].' pending '.$age.' days')));}

 foreach(db()->query('SELECT DISTINCT o.* FROM om_orders o JOIN om_reservations r ON r.order_id=o.id WHERE r.qty>0') as $held){foreach(om_query('SELECT product_id FROM om_reservations WHERE order_id=? AND qty>0',[$held['id']]) as $line)if(om_available($line['product_id'])<0||om_available($line['product_id'],$held['platform'])<0){$put('shortage-'.$held['id'],$held['id'],$held['owner_id'],'HIGH · Reservation shortage / allocation review · '.$held['order_no']);break;}}
 $operating=$cfg['overrides'][$today]??(in_array((int)date('N'),$cfg['operating'],true)?'Operating':'Closed');if($operating==='Operating'&&date('H:i')>=$cfg['reminder'])foreach(db()->query('SELECT * FROM om_sessions WHERE fulfillment_date='.db()->quote($today)) as $s){$cutoff=date('Y-m-d H:i:s',strtotime($today.' '.$cfg['reminder'].' -30 minutes'));$unchecked=(int)om_query("SELECT COUNT(*) FROM om_orders WHERE session_id=? AND monitoring<>'Resolved' AND (last_seen_at IS NULL OR last_seen_at<?)",[$s['id'],$cutoff])->fetchColumn();if($unchecked>0){$owner=om_query('SELECT owner_id FROM om_orders WHERE session_id=? ORDER BY id LIMIT 1',[$s['id']])->fetchColumn();$put('daily-'.$today.'-'.$s['id'],null,$owner?:null,'Final status update required · '.$s['platform'].' / '.$s['shop'].' · '.$today);}}

 om_query('UPDATE om_alerts SET resolved=1'.($active?' WHERE alert_key NOT IN ('.implode(',',array_fill(0,count($active),'?')).')':''),$active);

}

function om_notification_tasks(){if(!om_ready()||!om_allowed())return [];om_alert_refresh();$n=(int)db()->query('SELECT COUNT(*) FROM om_alerts WHERE resolved=0')->fetchColumn();return $n?[['title'=>'Order monitoring alerts — review required','count'=>$n,'url'=>'index.php?page=order_monitor&view=monitor']]:[];}
function om_return_sync($platform,$no,$batch){
 if(!om_ready())return;$o=om_find($platform,om_shops()[$platform]??'',$no);if(!$o)return;
 $received=(int)om_query('SELECT COALESCE(SUM(qty),0) FROM posted_returns WHERE platform=? AND original_ref=? AND reversed=0',[$platform,$no])->fetchColumn();$posted=array_sum(om_existing_totals($o['id']));
 if($o['platform_status']==='Cancelled'&&$posted>0&&$received>=$posted)om_query("UPDATE om_orders SET monitoring='Resolved',exception_text='',aftersales_resolution='Receipt Linked',revision=revision+1 WHERE id=?",[$o['id']]);
 om_event($o['id'],null,'Physical return receipt linked',['return_batch_id'=>$batch,'received_total'=>$received,'stock_already_posted_by_receiving'=>true]);
}

