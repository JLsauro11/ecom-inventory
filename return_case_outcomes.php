<?php
function rf_accept_return_outcome($t,$input,$remarks){
 $action=$input['case_action']??'';if(!in_array($action,['accept_return','accept_return_close','accept_short_return'],true)){if($action==='approved_no_return')return [];return array_intersect_key(rf_state($t)['saved'],array_flip(['return_decision','claims_required','return_closed','accepted_received_totals','unshipped_quantities','stock_reconciliation']));}
 $out=['return_decision'=>'Accepted — Customer reason valid','claims_required'=>false];
 if($action==='accept_return')return $out;
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
