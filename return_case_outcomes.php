<?php
function rf_accept_return_outcome($t,$input,$remarks){
 $action=$input['case_action']??'';if(!in_array($action,['accept_return','accept_return_close','accept_short_return'],true)){if($action==='approved_no_return')return [];return array_intersect_key(rf_state($t)['saved'],array_flip(['return_decision','claims_required','return_closed','accepted_received_totals','unshipped_quantities','stock_reconciliation']));}
 $out=['return_decision'=>'Accepted — Customer reason valid','claims_required'=>false];
 if($action==='accept_return'){if(!$t['receipts']&&!$t['legacy'])return $out;if($t['o']&&!oa_received_complete($t['o']))return $out;$action='accept_return_close';$input['return_complete_ack']=1;}
 if(empty($input['return_complete_ack']))throw new Exception('Confirm these posted items complete the customer return and no further item is expected.');
 $totals=[];foreach(array_merge($t['receipts'],$t['legacy']) as $r)$totals[(int)$r['product_id']]=($totals[(int)$r['product_id']]??0)+(int)$r['qty'];
 if(!$totals||array_sum($totals)<=0)throw new Exception('Post the actual received SKU, quantity and condition through Inflow before accepting and closing the return. No stock was added.');
 if(!$t['o'])return $out+['return_closed'=>true];
 $o=$t['o'];if($o['posting']!=='Posted')throw new Exception('Verify the original dispatch before closing its return.');$original=om_existing_totals($o['id']);$missing=[];
 foreach($totals as $pid=>$qty)if(!isset($original[$pid])||$qty>$original[$pid])throw new Exception('Posted receipt quantities do not match the original dispatch. Review the source before closing.');
 foreach($original as $pid=>$qty)if($qty>($totals[$pid]??0))$missing[$pid]=$qty-($totals[$pid]??0);
 if($action==='accept_short_return'&&!$missing)throw new Exception('No short-shipped difference remains. Use Accept Return & Close.');
 if($action==='accept_short_return'&&empty($input['short_shipment_ack']))throw new Exception('Confirm the team short-shipped the order and the missing units were never received by the customer.');
 if(oa_reported($o)['status']!=='Return / Refund')throw new Exception('Verify the platform Return / Refund report before accepting a reduced return scope.');
 $prior=ri_scope($o);ri_save($o,['source'=>$action==='accept_short_return'?'Verified short-shipped customer return':'Accepted customer return — no further return expected','totals'=>$totals,'file_totals'=>$prior['file_totals']??$original,'previous_scope'=>$prior,'snapshot'=>oa_reported($o)['snapshot'],'closed_return'=>true,'verified_by'=>current_user()['id'],'verified_at'=>date('Y-m-d H:i:s'),'reason'=>$remarks,'stock_changed'=>false]);
 oa_receipt_reconcile($o['platform'],$o['order_no']);om_query('UPDATE om_alerts SET resolved=1 WHERE order_id=?',[$o['id']]);
 $out['return_closed']=true;$out['accepted_received_totals']=$totals;
 if($action==='accept_short_return'){$out['return_decision']='Accepted — Team short-shipped order';$out['unshipped_quantities']=$missing;$out['stock_reconciliation']='Verify the units never shipped through an approved stock count / dispatch correction. Do not receive them as returned stock.';}
 om_event($o['id'],null,'Customer return accepted and closed',$out+['reason'=>$remarks,'stock_changed'=>false]);audit('Accept customer return','Return',$t['order'],$out['return_decision'].'; no additional stock credited.');return $out;
}

// A case may be resolved only against actual posted receipt evidence.
function rf_return_accepted_record($saved){return !empty($saved['return_decision'])||!empty($saved['return_closed'])||(($saved['claims']??'')==='Closed'&&($saved['inspection']??'')==='Inspected');}
function rf_accepted_return_totals($t,$saved=null){
 $saved=$saved??(rf_journal()[$t['key']]??[]);if(!rf_return_accepted_record($saved)||!$t['o']||$t['o']['posting']!=='Posted'||(!$t['receipts']&&!$t['legacy']))return null;
 $original=om_existing_totals($t['o']['id']);$scope=ri_scope($t['o']);
 if(!empty($saved['return_closed']))$expected=$saved['accepted_received_totals']??(!empty($scope['closed_return'])?$scope['totals']:$original);
 else{if(!oa_received_complete($t['o']))return null;$expected=$scope['totals']??$original;}
 if(!is_array($expected)||!$expected)return null;$actual=[];foreach(array_merge($t['receipts'],$t['legacy']) as $r)$actual[(int)$r['product_id']]=($actual[(int)$r['product_id']]??0)+(int)$r['qty'];
 foreach($expected as $pid=>$qty)if(!is_numeric($qty)||(int)$qty<=0||(int)$qty>($original[$pid]??0)||(int)$qty>($actual[$pid]??0))return null;
 ksort($expected,SORT_NUMERIC);return $expected;
}
function rf_order_return_completed($o){$saved=rf_journal()[hash('sha256',$o['platform'].'|'.$o['order_no'])]??[];if(!rf_return_accepted_record($saved))return false;return (bool)rf_accepted_return_totals(rf_target(['platform'=>$o['platform'],'order'=>$o['order_no']]),$saved);}
function rf_sync_accepted_returns(){
 static $running=false;if($running||!can_action('return_confirm'))return;$running=true;
 try{foreach(rf_journal() as $saved){if(!rf_return_accepted_record($saved)||rf_claim_settled($saved))continue;$o=om_find($saved['platform'],om_shops()[$saved['platform']]??'',$saved['order']);if(!$o||$o['posting']!=='Posted')continue;if($o['monitoring']==='Resolved'&&$o['aftersales_resolution']==='Receipt Linked'&&empty($o['exception_text'])&&($saved['case_status']??'')==='Resolved')continue;
 process_transaction(function()use($saved,$o){om_order($o['id'],true);unset($GLOBALS['RF_JOURNAL'],$GLOBALS['RI_SCOPES']);$t=rf_target($saved);$latest=rf_journal()[$t['key']]??[];if(rf_claim_settled($latest))return;$totals=rf_accepted_return_totals($t,$latest);if(!$totals)return;$o=$t['o'];$reported=oa_reported($o);if(!oa_deferred($reported['status']))return;
 $prior=ri_scope($o);if($reported['status']==='Return / Refund'&&(empty($prior['closed_return'])||$prior['totals']!=$totals))ri_save($o,['source'=>'Accepted completed return - existing receipt reconciliation','totals'=>$totals,'file_totals'=>$prior['file_totals']??om_existing_totals($o['id']),'previous_scope'=>$prior,'closed_return'=>true,'snapshot'=>$reported['snapshot'],'verified_by'=>current_user()['id'],'verified_at'=>date('Y-m-d H:i:s'),'reason'=>'Existing accepted return is complete against posted receipts.','stock_changed'=>false]);
 om_query("UPDATE om_orders SET status_changed_at=IF(platform_status<>?,NOW(),status_changed_at),platform_status=?,monitoring='Resolved',aftersales_resolution='Receipt Linked',exception_text='',revision=revision+1 WHERE id=?",[$reported['status'],$reported['status'],$o['id']]);om_query('UPDATE om_alerts SET resolved=1 WHERE order_id=?',[$o['id']]);
 if(($latest['case_status']??'')!=='Resolved'||empty($latest['return_closed'])){$d=$latest;unset($d['event_id'],$d['actor'],$d['recorded_at']);$d['related_return_event_id']=$latest['event_id'];$d['case_status']='Resolved';$d['return_decision']=$d['return_decision']??'Completed — Existing closed return';$d['return_closed']=true;$d['accepted_received_totals']=$totals;$d['inspection']='Inspected';$d['entry_mode']='Accepted return automatically reconciled with posted receipts; no stock change.';om_event($o['id'],null,'Return follow-up workflow',$d);unset($GLOBALS['RF_JOURNAL']);}
 om_event($o['id'],null,'Accepted return monitoring reconciled',['case_status'=>'Resolved','receipt_totals'=>$totals,'previous_monitoring'=>$o['monitoring'],'stock_changed'=>false]);audit('Resolve completed accepted return','Return',$o['order_no'],'Received, inspected and accepted; monitoring reconciled with posted receipts. No additional stock.');
 });
 }}finally{$running=false;}
}
function rf_sync_return_resolutions(){rf_sync_approved_claims();rf_sync_accepted_returns();}
