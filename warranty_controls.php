<?php
// Links use the existing transactional settings store; no database upgrade is needed.
// Match the unique posting token as well as the row ID so old links cannot attach
// to reused IDs after an authorized inventory reset.
function warranty_links($activeOnly=true){
 $sql="SELECT o.*,s.value_text FROM outflows o JOIN settings s ON s.key_name=CONCAT('warranty_outflow_',o.id) WHERE o.type='Warranty Replacement'";
 if($activeOnly)$sql.=' AND o.reversed_by_request IS NULL';
 $links=[];foreach(db()->query($sql) as $o){$v=json_decode($o['value_text'],true);if(!$v||($v['reference']??'')!==$o['order_id']||($v['posting_token']??'')!==$o['source_item'])continue;$links[]=['outflow'=>$o,'link'=>$v];}return $links;
}
function warranty_used($returnId){$n=0;foreach(warranty_links() as $l)if((int)$l['link']['return_id']===(int)$returnId)$n+=(int)$l['outflow']['qty'];return $n;}
function warranty_return($id,$lock=false){
 $q=db()->prepare("SELECT r.*,b.batch_ref,b.status batch_status,p.name,p.master_sku FROM posted_returns r JOIN return_batches b ON b.id=r.batch_id JOIN products p ON p.id=r.product_id WHERE r.id=?".($lock?' FOR UPDATE':''));$q->execute([(int)$id]);$r=$q->fetch();
 if(!$r||$r['reversed']||$r['batch_status']!=='Posted'||str_starts_with($r['batch_ref'],'CAN-')||(int)$r['damaged_qty']<1||!$r['non_sellable_id'])throw new Exception('Select a posted Damage / Leak customer return.');
 $r['replaced']=warranty_used($id);$r['remaining']=(int)$r['damaged_qty']-$r['replaced'];return $r;
}
function warranty_validate($input,$lock=false){
 if(!can_action('manual_outflow'))throw new Exception('Your account cannot issue warranty replacements.');
 $r=warranty_return($input['return_id']??0,$lock);$date=process_date($input['txn_date']??'');require_open_inventory_date($date);if($date<$r['txn_date'])throw new Exception('Replacement date cannot precede the received return.');
 $qty=whole_number($input['qty']??'','Quantity',1);if($qty>$r['remaining'])throw new Exception('Replacement exceeds the remaining eligible quantity: '.$r['remaining'].' pcs.');
 $pid=(int)($input['product_id']??0);$q=db()->prepare('SELECT * FROM products WHERE id=? AND active=1'.($lock?' FOR UPDATE':''));$q->execute([$pid]);$p=$q->fetch();if(!$p)throw new Exception('Select an active replacement product.');
 $source=stock_platform($input['source_platform']??'');$ref=trim($input['order_id']??'');if($ref===''||strlen($ref)>100)throw new Exception('Enter a warranty reference of up to 100 characters.');
 $q=db()->prepare("SELECT COUNT(*) FROM outflows WHERE type='Warranty Replacement' AND order_id=? AND reversed_by_request IS NULL");$q->execute([$ref]);if($q->fetchColumn())throw new Exception('This warranty reference was already posted. Use a separate reference for a partial replacement.');
 $remarks=process_reason($input['remarks']??'');check_platform_alignment($pid);
 return ['return'=>$r,'product'=>$p,'input'=>['return_id'=>(int)$r['id'],'txn_date'=>$date,'qty'=>$qty,'product_id'=>$pid,'source_platform'=>$source,'order_id'=>$ref,'remarks'=>$remarks],'stock'=>stock_row($pid),'platform_qty'=>platform_qty($source,$pid)];
}
function warranty_digest($a){return hash('sha256',json_encode([$a['input'],$a['return'],$a['product'],$a['stock'],$a['platform_qty']]));}
function warranty_stage($input){$a=warranty_validate($input);$token=bin2hex(random_bytes(24));$_SESSION['warranty_reviews'][$token]=['input'=>$a['input'],'digest'=>warranty_digest($a),'uid'=>current_user()['id'],'expires'=>time()+3600];return $token;}
function warranty_confirm($token,$ack){
 if(!can_action('manual_outflow'))throw new Exception('Your account cannot issue warranty replacements.');if(!$ack)throw new Exception('Review and acknowledge the replacement before posting.');
 $v=$_SESSION['warranty_reviews'][$token]??null;if(!$v||$v['expires']<time()||(int)$v['uid']!==(int)current_user()['id'])throw new Exception('Review expired or already submitted. Start a new warranty replacement.');
 $id=process_transaction(function()use($v,$token){$a=warranty_validate($v['input'],true);if(!hash_equals($v['digest'],warranty_digest($a)))throw new Exception('Stock or return eligibility changed. Go back and review the replacement again.');$i=$a['input'];$r=$a['return'];$sourceItem='Warranty posting: '.$token;
  db()->prepare('INSERT INTO outflows(txn_date,platform,order_id,product_id,source_item,qty,type,dedupe_key,remarks,user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$i['txn_date'],$i['source_platform'],$i['order_id'],$i['product_id'],$sourceItem,$i['qty'],'Warranty Replacement',hash('sha256','warranty|'.$token),'Return '.$r['batch_ref'].' / line #'.$r['id'].'; Original order '.$r['original_ref'].' ('.$r['platform'].'); '.$i['remarks'],current_user()['id']]);$oid=(int)db()->lastInsertId();
  set_setting('warranty_outflow_'.$oid,json_encode(['return_id'=>(int)$r['id'],'return_batch_id'=>(int)$r['batch_id'],'return_reference'=>$r['batch_ref'],'returned_product_id'=>(int)$r['product_id'],'original_order'=>$r['original_ref'],'original_platform'=>$r['platform'],'reference'=>$i['order_id'],'posting_token'=>$sourceItem]));
  change_platform_stock($i['source_platform'],$i['product_id'],-$i['qty'],$i['order_id'],$i['txn_date']);check_platform_alignment($i['product_id']);audit('Post warranty replacement','Outflow',(string)$oid,$i['order_id'].'; '.$i['qty'].' pcs; Return '.$r['batch_ref'].' / #'.$r['id'].'; defective stock remains Non-Sellable.');return $oid;
 });unset($_SESSION['warranty_reviews'][$token]);return $id;
}
