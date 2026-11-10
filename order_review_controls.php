<?php
// Per-order checks run in review, before any inventory write.
function om_source_date_lines($lines){$out=[];foreach($lines as $line){if(!empty($line['source_date_rows']))foreach($line['source_date_rows'] as $dates)$out[]=array_replace($line,$dates);else $out[]=$line;}return $out;}
function om_date_details($lines,$order=null,$meta=[]){
 $lines=om_source_date_lines($lines);
 $collect=function($key)use($lines){$v=array_values(array_unique(array_filter(array_column($lines,$key))));sort($v,SORT_STRING);return $v;};
 $created=$collect('created');$ships=$collect('ship_at');$delivered=$collect('delivered_at');$fields=$collect('ship_source');
 return ['order_dates'=>$created,'fulfillment_date'=>$order['fulfillment_date']??($meta['fulfillment_date']??''),'shipping_dates'=>$ships,'delivered_dates'=>$delivered,'shipping_source'=>implode(' / ',$fields),'export_time'=>$meta['snapshot']??'','posting_date'=>$ships?substr(max($ships),0,10):substr($meta['snapshot']??'',0,10),'posting_basis'=>$ships?'Platform shipping timestamp':'Export snapshot date; actual shipping timestamp unavailable'];
}
function om_validate_source_dates($lines,$meta,$kind){
 foreach(om_source_date_lines($lines) as $line){
  if(!empty($line['date_errors']))throw new Exception(implode(' · ',$line['date_errors']));
  if(empty($line['created']))throw new Exception('Order '.$line['order'].': order creation date is missing.');
  if($kind==='Register'){
   if(substr($line['created'],0,10)>$meta['fulfillment_date'])throw new Exception('Order '.$line['order'].': Order Date '.substr($line['created'],0,10).' is after Fulfillment Date '.$meta['fulfillment_date'].'.');
   continue;
  }
  $day=substr($line['created'],0,10);
  if($day<$meta['coverage_from']||$day>$meta['coverage_to'])throw new Exception('Order '.$line['order'].': Order Date '.$day.' is outside the declared export coverage '.$meta['coverage_from'].' through '.$meta['coverage_to'].'. Correct the coverage and review again.');
  foreach(['created'=>'Order Date','ship_at'=>'Source Shipping Date','delivered_at'=>'Delivered Date'] as $key=>$label)if(!empty($line[$key])&&$line[$key]>$meta['snapshot'])throw new Exception('Order '.$line['order'].': '.$label.' '.$line[$key].' is after Export Time '.$meta['snapshot'].'. Verify the export time or source field.');
  if(!empty($line['ship_at'])&&$line['ship_at']<$line['created'])throw new Exception('Order '.$line['order'].': Source Shipping Date '.$line['ship_at'].' is before Order Date '.$line['created'].'. Verify the source field.');
  if(!empty($line['delivered_at'])&&!empty($line['ship_at'])&&$line['delivered_at']<$line['ship_at'])throw new Exception('Order '.$line['order'].': Delivered Date is before Source Shipping Date. Verify the source fields.');
 }
}
function om_dispatch_review_date($lines,$order,$meta){
 $d=om_date_details($lines,$order,$meta);
 foreach($d['shipping_dates'] as $ship)if(substr($ship,0,10)<$order['fulfillment_date'])throw new Exception('Order '.$order['order_no'].': Source Shipping Date '.substr($ship,0,10).' is before Fulfillment Date '.$order['fulfillment_date'].'. Order Date '.substr($order['created_order_at'],0,10).' may be earlier; verify the shipping field or correct the recorded fulfillment date.');
 if($d['posting_date']<$order['fulfillment_date'])throw new Exception('Order '.$order['order_no'].': posting date '.$d['posting_date'].' is before Fulfillment Date '.$order['fulfillment_date'].'. Verify the export time.');
 require_open_inventory_date($d['posting_date']);return $d['posting_date'];
}
function om_correct_fulfillment($uploadId,$orderId,$revision,$date,$reason){
 om_require();$reason=process_reason($reason);$date=process_date($date);if($date>date('Y-m-d'))throw new Exception('Fulfillment date cannot be in the future.');
 return process_transaction(function()use($uploadId,$orderId,$revision,$date,$reason){
  $b=om_upload($uploadId);om_owner($b);if($b['kind']!=='Status')throw new Exception('Use the status review to correct a fulfillment date.');
  $o=om_order($orderId,true);if($o['revision']!=$revision)throw new Exception('Order changed. Reopen the review.');
  if($o['platform']!==$b['platform']||$o['shop']!==$b['shop'])throw new Exception('Order does not belong to this upload.');
  $lines=array_values(array_filter($b['rows'],fn($r)=>(string)$r['order']===$o['order_no']));if(!$lines)throw new Exception('Order is not in this source file.');
  if($o['posting']!=='Not Posted'||om_query('SELECT COUNT(*) FROM om_postings WHERE order_id=?',[$orderId])->fetchColumn())throw new Exception('Posted or reversed orders require a linked transaction correction.');
  if(access_profile()!=='Admin'&&(int)$o['owner_id']!==current_user()['id'])throw new Exception('Only the original registration owner or Admin can correct its fulfillment date.');
  $created=$o['created_order_at'];if($date<substr($created,0,10))throw new Exception('Fulfillment Date cannot be earlier than Order Date.');
  if($date===$o['fulfillment_date'])throw new Exception('Enter the verified corrected fulfillment date.');
  require_open_inventory_date($date);
  om_query('INSERT IGNORE INTO om_sessions(platform,shop,fulfillment_date) VALUES(?,?,?)',[$o['platform'],$o['shop'],$date]);$sid=om_query('SELECT id FROM om_sessions WHERE platform=? AND shop=? AND fulfillment_date=?',[$o['platform'],$o['shop'],$date])->fetchColumn();
  om_query("UPDATE om_orders SET fulfillment_date=?,session_id=?,exception_text=NULL,monitoring='Needs Follow-up',revision=revision+1 WHERE id=?",[$date,$sid,$orderId]);
  om_query('UPDATE om_uploads SET revision=revision+1 WHERE id=?',[$uploadId]);
  om_event($orderId,$uploadId,'Fulfillment date corrected',['before'=>$o['fulfillment_date'],'after'=>$date,'reason'=>$reason,'stock_changed'=>false]);audit('Correct order fulfillment date','Order Monitoring',$o['order_no'],$o['fulfillment_date'].' -> '.$date.'; '.$reason.'; no stock changed.');
 });
}
