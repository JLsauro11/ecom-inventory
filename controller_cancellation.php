<?php
// ICO may cancel an undispatched order; upload and other status permissions stay separate.
function ic_cancel_role(){return access_profile()==='Inventory Controller';}
function ic_cancel_available($o){
 if(!ic_cancel_role()||!om_ready())return false;
 try{sp_assert_unshipped($o);}catch(Throwable $e){return false;}
 return $o['platform_status']!=='Cancelled'||(int)om_query('SELECT COALESCE(SUM(qty),0) FROM om_reservations WHERE order_id=?',[$o['id']])->fetchColumn()>0;
}
function ic_manual_require($o,$status){
 if(om_allowed()){om_require();return;}
 if(!ic_cancel_role()||$status!=='Cancelled'||!ic_cancel_available($o))throw new Exception('Inventory Controller may only cancel orders before dispatch. Dispatched returns must be received in Inflow.');
}
function ic_manual_apply($saved){
 ic_manual_require(om_order($saved['order_id']),$saved['input']['status']);
 if((int)$saved['user_id']!==current_user()['id']||strtotime($saved['snapshot'])<time()-900)throw new Exception('Preview expired. Review the order again.');
 return process_transaction(function()use($saved){
  $o=om_order($saved['order_id'],true);unset($GLOBALS['OM_RESERVED'],$GLOBALS['OA_REPORTS']);
  $fresh=oi_manual_plan($o['id'],$saved['input'],$saved['snapshot']);
  if(!hash_equals($saved['digest'],$fresh['digest']))throw new Exception('Order or stock changed. Review again before confirming.');
  sp_release_cancel($o,null,'Confirmed Inventory Controller manual cancellation');
  $seen=max($saved['snapshot'],$o['last_seen_at']??'');$watermark=max($saved['snapshot'],$o['last_snapshot']??'');
  om_query("UPDATE om_orders SET platform_status='Cancelled',raw_status='Manual: Cancelled',monitoring='Resolved',exception_text='',status_changed_at=NOW(),last_seen_at=?,last_snapshot=?,revision=revision+1 WHERE id=?",[$seen,$watermark,$o['id']]);
  oa_record_report($o,'Cancelled',$saved['snapshot'],null);
  om_event($o['id'],null,'Manual status updated',['source'=>'Inventory Controller manual cancellation','before_status'=>$fresh['before_status'],'after_status'=>'Cancelled','reported_status'=>'Cancelled','actual_event_date'=>$fresh['input']['event_date'],'reason'=>$fresh['input']['reason'],'action'=>'Cancel / release','effects'=>$fresh['effects'],'physical_delta'=>0,'stock_changed'=>false]);
  audit('Manual order status update','Order Monitoring',$o['order_no'],$fresh['before_status'].' -> Cancelled; '.$fresh['input']['reason'].'; reservation released; physical stock unchanged.');om_alert_refresh();
  return ['order'=>om_order($o['id']),'action'=>'Cancel / release','upload_id'=>null];
 });
}
