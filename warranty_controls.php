<?php
// Links use the existing transactional settings store; no database upgrade is needed.
// Match the unique posting token as well as the row ID so old links cannot attach
// to reused IDs after an authorized inventory reset.
function warranty_links($activeOnly=true){
 $sql="SELECT o.*,s.value_text FROM outflows o JOIN settings s ON s.key_name=CONCAT('warranty_outflow_',o.id) WHERE o.type='Warranty Replacement'";
 if($activeOnly)$sql.=' AND o.reversed_by_request IS NULL';
 $links=[];foreach(db()->query($sql) as $o){$v=json_decode($o['value_text'],true);if(!$v||($v['reference']??'')!==$o['order_id']||($v['posting_token']??'')!==$o['source_item'])continue;$links[]=['outflow'=>$o,'link'=>$v];}return $links;
}
function warranty_used($returnId){$n=0;foreach(warranty_links() as $l)if((int)$l['link']['return_id']===(int)$returnId)$n+=(int)$l['outflow']['qty'];return $n+(function_exists('wc_link_used')?wc_link_used($returnId):0);}
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
 $remarks=process_reason($input['remarks']??'');check_platform_alignment($pid);om_assert_available($pid,$qty);om_assert_available($pid,$qty,$source);
 return ['return'=>$r,'product'=>$p,'input'=>['return_id'=>(int)$r['id'],'txn_date'=>$date,'qty'=>$qty,'product_id'=>$pid,'source_platform'=>$source,'order_id'=>$ref,'remarks'=>$remarks],'stock'=>stock_row($pid),'platform_qty'=>platform_qty($source,$pid)];
}
function warranty_digest($a){return hash('sha256',json_encode([$a['input'],$a['return'],$a['product'],$a['stock'],$a['platform_qty']]));}
function warranty_stage($input){throw new Exception('Create and approve replacements in Warranty Cases. The old warranty entry is read-only.');}
function warranty_confirm($token,$ack){throw new Exception('Use the approved Warranty Case and ICO release. This old review cannot post stock.');}
