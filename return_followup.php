<?php
// Inspection / claims are an append-only journal. Actual return receiving owns stock movements.
function rf_url($platform,$order){return '?'.http_build_query(['page'=>'return_followup','platform'=>$platform,'order'=>$order]);}
function rf_require($write=false){if(!access_page_allowed('return_followup')||($write&&!can_action('return_confirm')))throw new Exception('Return follow-up access required.');if(!$write&&can_action('return_confirm'))rf_sync_approved_claims();}
function rf_target($input){$pl=stock_platform($input['platform']??'');$no=trim((string)($input['order']??''));if($no===''||mb_strlen($no)>100)throw new Exception('Enter a valid Order ID.');$o=om_find($pl,om_shops()[$pl],$no);if($o)$no=$o['order_no'];$refs=or_numbers($pl,$no);$marks=implode(',',array_fill(0,count($refs),'?'));$args=array_merge([$pl],$refs);$receipts=om_query('SELECT r.*,b.batch_ref,b.status batch_status,p.master_sku,p.name FROM posted_returns r JOIN return_batches b ON b.id=r.batch_id JOIN products p ON p.id=r.product_id WHERE r.platform=? AND r.original_ref IN ('.$marks.') AND r.reversed=0 AND b.status=\'Posted\' ORDER BY r.id',$args)->fetchAll();$wrong=ix_cases_ready()?om_query('SELECT * FROM return_investigations WHERE platform=? AND order_no IN ('.$marks.') ORDER BY id',$args)->fetchAll():[];$legacy=[];foreach(om_query("SELECT i.id,i.batch_id,i.product_id,p.master_sku,p.name,i.qty,i.source_platform FROM inflows i JOIN products p ON p.id=i.product_id WHERE i.reference_no IN (".$marks.") AND i.type='Customer Return - Good' AND (i.source_platform=? OR COALESCE(i.source_platform,'')='') AND i.reversed_by_request IS NULL",array_merge($refs,[$pl])) as $r){if(($r['source_platform']??'')===''){ $metadata=om_query('SELECT result_json FROM inflow_batches WHERE id=?',[$r['batch_id']])->fetchColumn();if((json_decode($metadata?:'{}',true)['return_platform']??null)!==$pl)continue;}$legacy[]=$r+['destination'=>'Sellable · '.$pl,'condition'=>'Good Item'];}foreach(om_query("SELECT n.id,n.product_id,p.master_sku,p.name,n.qty,n.condition_type FROM non_sellable n JOIN products p ON p.id=n.product_id WHERE n.platform=? AND n.order_id IN (".$marks.") AND n.origin='Customer Return' AND n.reversed_by_request IS NULL",$args) as $r)$legacy[]=$r+['destination'=>'Non-Sellable','condition'=>$r['condition_type']];if(!$o&&!$receipts&&!$wrong&&!$legacy)throw new Exception('Order or physically received return case not found.');return ['platform'=>$pl,'order'=>$no,'o'=>$o?:null,'receipts'=>$receipts,'wrong'=>$wrong,'legacy'=>$legacy,'key'=>hash('sha256',$pl.'|'.$no)];}
function rf_journal(){if(!isset($GLOBALS['RF_JOURNAL'])){$GLOBALS['RF_JOURNAL']=[];foreach(om_query("SELECT e.*,u.name actor FROM om_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.event_type='Return follow-up workflow' ORDER BY e.id") as $e){$d=json_decode($e['details_json'],true)?:[];if(isset($d['key']))$GLOBALS['RF_JOURNAL'][$d['key']]=['event_id'=>(int)$e['id'],'recorded_at'=>$e['created_at'],'actor'=>$e['actor']]+$d;}}return $GLOBALS['RF_JOURNAL'];}
function rf_claim_settled($saved){return ($saved['claims']??'')==='Approved'||(($saved['claims']??'')==='Closed'&&($saved['payment_outcome']??'')==='Seller Paid / Compensated');}
function rf_order_claim_settled($o){return rf_claim_settled(rf_journal()[hash('sha256',$o['platform'].'|'.$o['order_no'])]??[]);}
function rf_settle_approved_order($t,$reason){
 $o=$t['o'];if(!$o)return;$r=oa_reported($o);$hasReceived=(bool)($t['receipts']||$t['legacy']||$t['wrong']);$resolution=$hasReceived?'Receipt Linked':'Refund Only';
 if($o['monitoring']==='Resolved'&&$o['aftersales_resolution']===$resolution&&empty($o['exception_text']))return;
 om_query("UPDATE om_orders SET status_changed_at=IF(platform_status<>?,NOW(),status_changed_at),platform_status=?,monitoring='Resolved',aftersales_resolution=?,exception_text='',revision=revision+1 WHERE id=?",[$r['status'],$r['status'],$resolution,$o['id']]);
 om_query('UPDATE om_alerts SET resolved=1 WHERE order_id=?',[$o['id']]);om_event($o['id'],null,'Approved claim automatically resolved',['reason'=>$reason,'payment_outcome'=>'Seller Paid / Compensated','case_status'=>'Resolved','stock_changed'=>false,'actual_receipts_retained'=>$hasReceived]);
}
function rf_sync_approved_claims(){
 static $running=false;if($running||!can_action('return_confirm'))return;$running=true;
 try{foreach(rf_journal() as $saved){if(!rf_claim_settled($saved))continue;$o=om_find($saved['platform'],om_shops()[$saved['platform']]??'',$saved['order']);if(!$o)continue;if($o['monitoring']==='Resolved'&&$o['aftersales_resolution']!=='Return Expected'&&($saved['case_status']??'')==='Resolved')continue;
 process_transaction(function()use($saved,$o){om_order($o['id'],true);unset($GLOBALS['RF_JOURNAL']);$t=rf_target($saved);$latest=rf_journal()[$t['key']]??[];if(!rf_claim_settled($latest))return;rf_settle_approved_order($t,'Existing approved claim: paid / compensated; automatic closure.');
 if(($latest['case_status']??'')!=='Resolved'){ $d=$latest;unset($d['event_id'],$d['actor'],$d['recorded_at']);$d['previous']=['inspection'=>$latest['inspection'],'claims'=>$latest['claims']];$d['case_status']='Resolved';$d['payment_outcome']='Seller Paid / Compensated';$d['entry_mode']='Existing approved claim automatically resolved; original approval and evidence retained.';if(!$t['receipts']&&!$t['legacy']&&!$t['wrong']){$d['inspection']='Not Required — No Physical Return';$d['resolution']='No Physical Return Required';}om_event($o['id'],null,'Return follow-up workflow',$d);unset($GLOBALS['RF_JOURNAL']);audit('Resolve existing approved claim','Return',$t['order'],'Automatic paid claim resolution; no stock change.');}
 });
 }}finally{$running=false;}
}
function rf_state($t){$saved=rf_journal()[$t['key']]??[];$o=$t['o'];$received=array_sum(array_column($t['receipts'],'qty'))+array_sum(array_column($t['legacy'],'qty'));$none=$received===0&&!$t['wrong']&&(rf_claim_settled($saved)||($o&&in_array($o['aftersales_resolution']??'',['Refund Only','No Return Required','No Return'],true)));$complete=$o&&$received>0&&oa_received_complete($o);$inspection=$saved['inspection']??'Awaiting Actual Return';if($received>0||$t['wrong'])$inspection='Inspected';elseif($none)$inspection='Not Required — No Physical Return';$receiving=$none?'No Physical Return':($t['wrong']?'Wrong Item Received — For Investigation':($complete?'Fully Received':($received>0?(rf_claim_settled($saved)?'Received — Claim Resolved':($o?'Partially Received':'Received — Original Order Not Recorded')):(in_array($inspection,['For Inspection','Inspected'],true)?$inspection.' — Receipt Not Yet Posted':'Awaiting Actual Return'))));return ['event_id'=>$saved['event_id']??0,'inspection'=>$inspection,'claims'=>$saved['claims']??'Not Yet Assessed','remarks'=>$saved['remarks']??'','attachment'=>$saved['attachment']??null,'receiving'=>$receiving,'received_qty'=>$received,'saved'=>$saved];}
function rf_claims(){return ['Not Yet Assessed','For Filing of Claim','Claim Filed','Approved','Rejected','Closed','Not Applicable'];}
function rf_case_actions(){return ['keep'=>'Update Remarks / Attachment','accept_return'=>'Accept Return — Customer Reason Valid','accept_return_close'=>'Accept Return & Close — Received Items Complete','accept_short_return'=>'Accept Short-Shipped Return & Close','for_inspection'=>'Item Received — For Inspection','inspected'=>'Inspection Completed','for_filing'=>'For Filing of Claim','claim_filed'=>'Claim Filed','claim_approved'=>'Claim Approved','approved_no_return'=>'Claim Approved — No Return; Seller Paid','claim_rejected'=>'Claim Rejected','close_claim'=>'Close Claims Follow-up'];}
function rf_case_action_visible($action,$t,$s){
 if($action==='approved_no_return')return false;if(rf_claim_settled($s['saved'])&&$action!=='keep')return false;
 if(!empty($s['saved']['return_closed'])&&in_array($action,['accept_return','accept_return_close','accept_short_return'],true))return false;if($s['receiving']==='No Physical Return'&&in_array($action,['for_inspection','inspected','for_filing','claim_filed','claim_rejected','accept_return','accept_return_close','accept_short_return'],true))return false;
 if(in_array($action,['accept_return_close','accept_short_return'],true)&&(!$t['receipts']&&!$t['legacy']))return false;if($action==='accept_short_return'&&!$t['o'])return false;if($action==='for_inspection'&&$s['inspection']!=='Awaiting Actual Return')return false;
 if($action==='inspected'&&$s['inspection']==='Inspected')return false;
 if($action==='claim_rejected'&&!in_array($s['claims'],['Claim Filed','Approved','Rejected'],true))return false;
 if($action==='close_claim'&&in_array($s['claims'],['Not Yet Assessed','Closed'],true))return false;
 if($action==='approved_no_return'&&(!$t['o']||$t['o']['posting']!=='Posted'||$t['receipts']||$t['legacy']||$t['wrong']))return false;
 return true;
}
function rf_case_input($input,$s){
 if(!isset($input['case_action']))return $input;
 $action=$input['case_action'];if(!is_string($action)||!array_key_exists($action,rf_case_actions()))throw new Exception('Select a valid case outcome.');
 $input['inspection']=$s['inspection'];$input['claims']=$s['claims'];$input['resolution']='keep';
 if($action==='for_inspection')$input['inspection']='For Inspection';elseif($action==='inspected')$input['inspection']='Inspected';
 else{$claim=['for_filing'=>'For Filing of Claim','claim_filed'=>'Claim Filed','claim_approved'=>'Approved','approved_no_return'=>'Approved','claim_rejected'=>'Rejected','close_claim'=>'Closed'];if(isset($claim[$action]))$input['claims']=$claim[$action];}
 if(in_array($action,['accept_return','accept_return_close','accept_short_return'],true))$input['claims']='Not Applicable';if($action==='accept_short_return'&&!empty($input['short_shipment_ack']))$input['return_complete_ack']=1;if($action==='approved_no_return'){$input['resolution']='no_return_paid';$input['inspection']='Awaiting Actual Return';}
 return $input;
}
function rf_has_claim_filing($t){
 foreach(om_query("SELECT details_json FROM om_events WHERE event_type='Return follow-up workflow' AND order_id <=> ? ORDER BY id DESC",[$t['o']['id']??null]) as $row){$d=json_decode($row['details_json'],true)?:[];if(($d['key']??'')===$t['key']&&in_array($d['claims']??'',['Claim Filed','Approved','Rejected'],true))return true;}
 return false;
}
function rf_confirm_no_return_paid($t,$remarks){
 $o=$t['o'];if(!$o||$o['posting']!=='Posted')throw new Exception('Confirm the actual dispatch before recording a paid no-return resolution.');
 if($t['receipts']||$t['legacy']||$t['wrong'])throw new Exception('This case already has physically received items. Review its receiving history instead of declaring no physical return.');
 $reported=oa_reported($o);if(!oa_deferred($reported['status']))throw new Exception('This order has no return / refund resolution to complete.');
 om_query("UPDATE om_orders SET status_changed_at=IF(platform_status<>?,NOW(),status_changed_at),platform_status=?,monitoring='Resolved',aftersales_resolution='Refund Only',exception_text='',revision=revision+1 WHERE id=?",[$reported['status'],$reported['status'],$o['id']]);
 om_event($o['id'],null,'Claim approved — no physical return',['resolution'=>'No Physical Return Required','payment_outcome'=>'Seller Paid / Compensated','reason'=>$remarks,'physical_delta'=>0,'sellable_delta'=>0]);
 audit('Resolve approved claim without physical return','Return',$t['order'],$remarks.'; seller paid / compensated; no stock change.');
 om_query('UPDATE om_alerts SET resolved=1 WHERE order_id=?',[$o['id']]);
}
function rf_save($input,$file=null){rf_require(true);return process_transaction(function()use($input,$file){$t=rf_target($input);if($t['o'])om_order($t['o']['id'],true);else {foreach(array_merge($t['receipts'],$t['legacy']) as $r)om_query('SELECT id FROM products WHERE id=? FOR UPDATE',[$r['product_id']])->fetch();}if(!$t['o']&&!$t['receipts']&&$t['wrong'])om_query('SELECT id FROM return_investigations WHERE id=? FOR UPDATE',[$t['wrong'][0]['id']])->fetch();unset($GLOBALS['RF_JOURNAL']);$t=rf_target($input);$s=rf_state($t);if((int)($input['revision']??-1)!==$s['event_id'])throw new Exception('Follow-up changed. Refresh before saving; no stock changed.');$reported=$t['o']?oa_reported($t['o'])['status']:null;if(!$t['receipts']&&!$t['wrong']&&!$t['legacy']&&!in_array($reported,['Return / Refund','Failed Delivery','Cancelled'],true))throw new Exception('This order has no return / refund follow-up or actual receipt.');$input=rf_case_input($input,$s);$inspection=$input['inspection']??'';$claims=$input['claims']??'';
 if($s['receiving']==='No Physical Return'&&$inspection==='Not Required — No Physical Return')$inspection='Awaiting Actual Return';
 $resolution=$input['resolution']??'keep';if(!in_array($resolution,['keep','no_return_paid'],true))throw new Exception('Choose a valid case resolution.');
 $wasNoReturn=($s['saved']['resolution']??'')==='No Physical Return Required'&&!$t['receipts']&&!$t['legacy']&&!$t['wrong'];if($claims==='Approved'&&$t['o']&&$t['o']['posting']==='Posted'&&!$t['receipts']&&!$t['legacy']&&!$t['wrong']){$resolution='no_return_paid';$input['no_return_paid_ack']=1;}$noReturnPaid=$resolution==='no_return_paid'||$wasNoReturn;
 if($noReturnPaid){
  if(!in_array($claims,$wasNoReturn?['Approved','Closed']:['Approved'],true))throw new Exception('Seller Paid / Compensated requires a confirmed approved claim.');
  if($claims!=='Approved'&&!$wasNoReturn&&empty($input['no_return_paid_ack']))throw new Exception('Confirm no physical return is expected and the seller is paid / compensated.');
  if($t['receipts']||$t['legacy']||$t['wrong'])throw new Exception('Physically received items already exist. Review the actual receiving history.');
  $inspection='Awaiting Actual Return';
 }
 if(!in_array($inspection,['Awaiting Actual Return','For Inspection','Inspected'],true)||!in_array($claims,rf_claims(),true))throw new Exception('Select valid inspection and claims statuses.');if($s['receiving']==='No Physical Return'&&$inspection!=='Awaiting Actual Return')throw new Exception('This order was resolved without a physical return. Review the order resolution first.');if(!$noReturnPaid&&$s['inspection']==='Inspected'&&$inspection!=='Inspected')throw new Exception('Existing posted receipts / wrong-item custody retain their inspected history.');if($inspection!=='Awaiting Actual Return'&&$s['inspection']==='Awaiting Actual Return'&&empty($input['physical_ack']))throw new Exception('Confirm the item is physically received before inspection. This does not post stock.');if(!$noReturnPaid&&$inspection==='Awaiting Actual Return'&&$s['inspection']==='For Inspection')throw new Exception('Do not remove an actual physical receipt acknowledgement. Record a correction in remarks.');if($claims==='Rejected'&&!in_array($s['claims'],['Claim Filed','Approved','Rejected'],true))throw new Exception('Record Claim Filed before a claim outcome.');if($s['claims']==='Not Yet Assessed'&&$claims==='Closed')throw new Exception('Record the claim assessment before closing it.');$remarks=process_reason($input['remarks']??'');if(mb_strlen($remarks)>4000)throw new Exception('Remarks are limited to 4000 characters.');$attachment=$s['attachment'];if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){if(($file['error']??-1)!==UPLOAD_ERR_OK)throw new Exception('Attachment upload failed.');validate_upload_file($file,['pdf','png','jpg','jpeg']);$attachment=store_upload($file,'return_claims','RETURN');}if($noReturnPaid){if(!$wasNoReturn)rf_confirm_no_return_paid($t,$remarks);$inspection='Not Required — No Physical Return';}$d=['key'=>$t['key'],'platform'=>$t['platform'],'order'=>$t['order'],'inspection'=>$inspection,'claims'=>$claims,'remarks'=>$remarks,'attachment'=>$attachment,'previous'=>['inspection'=>$s['inspection'],'claims'=>$s['claims']],'stock_effect'=>'No stock change; physical receiving and condition posting are separate.'];$accepted=rf_accept_return_outcome($t,$input,$remarks);if($accepted)$d=array_replace($d,$accepted);if($claims==='Approved'||rf_claim_settled($s['saved'])){$d['case_status']='Resolved';$d['payment_outcome']='Seller Paid / Compensated';}if($noReturnPaid){$d['resolution']='No Physical Return Required';$d['payment_outcome']='Seller Paid / Compensated';$d['no_return_confirmed_by']=current_user()['id'];}
 if($claims==='Approved'&&!in_array($s['claims'],['Claim Filed','Approved','Rejected'],true)&&!rf_has_claim_filing($t)){
 // One submission records both milestones; dates in remarks are not fabricated from encoding time.
 $d['save_group']=bin2hex(random_bytes(16));$filed=$d;$filed['claims']='Claim Filed';$filed['entry_mode']='Filing recorded together with approval';
 om_event($t['o']['id']??null,null,'Return follow-up workflow',$filed);$d['related_filing_event_id']=(int)db()->lastInsertId();
 $d['previous']=['inspection'=>$inspection,'claims'=>'Claim Filed'];$d['entry_mode']='Approval recorded together with filing';
 }om_event($t['o']['id']??null,null,'Return follow-up workflow',$d);unset($GLOBALS['RF_JOURNAL']);if(rf_claim_settled($d))rf_settle_approved_order(rf_target($input),'Claim Approved means seller paid / compensated; automatic resolution.');audit('Update return inspection / claims','Return',$t['order'],$inspection.'; '.$claims.'; '.$remarks.'; no stock change.');return $t;});}
function rf_physical_pending($o){return $o&&(oc_return_pending($o)||($o['posting']!=='Posted'&&oa_reported($o)['status']==='Return / Refund'&&$o['monitoring']!=='Resolved'));}
function rf_followup_pending($t,$physicalPending=false){
 $s=rf_state($t);$claimsPending=in_array($s['claims'],['For Filing of Claim','Claim Filed','Rejected'],true);
 if(rf_claim_settled($s['saved']))return false;
 return $physicalPending||$claimsPending||$s['inspection']==='For Inspection';
}
function rf_queue(){
 if(!om_ready())return [];$rows=[];$seen=[];$journal=rf_journal();
 foreach(om_query('SELECT * FROM om_orders ORDER BY fulfillment_date,id') as $o){
  $key=hash('sha256',$o['platform'].'|'.$o['order_no']);$saved=$journal[$key]??[];
  $pending=rf_physical_pending($o);
  if(!$pending&&!in_array($saved['claims']??'',['For Filing of Claim','Claim Filed','Approved','Rejected'],true)&&($saved['inspection']??'')!=='For Inspection')continue;
  $t=rf_target(['platform'=>$o['platform'],'order'=>$o['order_no']]);$seen[$key]=true;
  if(rf_followup_pending($t,$pending))$rows[]=['target'=>$t,'state'=>rf_state($t),'reported'=>oa_reported($o)['status']];
 }
 foreach($journal as $key=>$saved)if(!isset($seen[$key])&&in_array($saved['claims'],['For Filing of Claim','Claim Filed','Approved','Rejected'],true)){
  $t=rf_target($saved);$seen[$key]=true;if(rf_followup_pending($t))$rows[]=['target'=>$t,'state'=>rf_state($t),'reported'=>'Actual return'];
 }
 if(ix_cases_ready())foreach(om_query('SELECT DISTINCT platform,order_no FROM return_investigations') as $c){
  $key=hash('sha256',$c['platform'].'|'.$c['order_no']);if(isset($seen[$key])||($journal[$key]['claims']??'')==='Closed')continue;
  $t=rf_target(['platform'=>$c['platform'],'order'=>$c['order_no']]);if(($journal[$key]['claims']??'')==='Approved'&&!rf_followup_pending($t))continue;
  $rows[]=['target'=>$t,'state'=>rf_state($t),'reported'=>'Wrong Item Received'];$seen[$key]=true;
 }
 return $rows;
}
function rf_notification_tasks(){if(!can_action('return_confirm')||!access_page_allowed('return_followup'))return [];$n=count(rf_queue());return $n?[['title'=>'ICO return / refund — receiving, inspection or claims follow-up','count'=>$n,'url'=>'index.php?page=return_followup']]:[];}
function rf_link($pl,$no){return '<a class="btn ghost sm" href="'.e(rf_url($pl,$no)).'">Inspection / Claims</a>';}
function rf_evidence_path($event){rf_require();$r=om_query("SELECT details_json FROM om_events WHERE id=? AND event_type='Return follow-up workflow'",[(int)$event])->fetch();$d=json_decode($r['details_json']??'',true)?:[];$root=realpath(__DIR__.'/uploads/return_claims');$path=realpath(__DIR__.'/'.($d['attachment']??''));if(!$root||!$path||!is_file($path)||!str_starts_with(strtolower(str_replace('\\','/',$path)),strtolower(str_replace('\\','/',$root)).'/'))throw new Exception('Attachment unavailable.');return $path;}
