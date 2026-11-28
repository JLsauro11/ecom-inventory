<?php
function restricted_request_profile($b){$saved=$b['workflow']['requester_profile']??'';if($saved!=='')return $saved;$role=om_query('SELECT role FROM users WHERE id=?',[$b['user_id']])->fetchColumn();return access_role_profile($role?:'Admin');}
function restricted_approval_label($b){if(ns_recovery_batch($b)&&restricted_request_profile($b)==='Admin')return 'Admin (self-approval allowed for Return to Sellable)';return in_array(restricted_request_profile($b),['Admin','Operations Lead'],true)?'a different Admin':'Operations Lead or Admin';}
function restricted_can_approve($b){
 $profile=access_profile();if($b['import_type']!=='Non-Sellable Release')return false;if($profile==='Admin'&&ns_recovery_batch($b))return true;if((int)$b['user_id']===(int)current_user()['id'])return false;
 return $profile==='Admin'||($profile==='Operations Lead'&&!in_array(restricted_request_profile($b),['Operations Lead','Admin'],true));
}
function restricted_pending_requests(){
 $out=[];if(!in_array(access_profile(),['Admin','Operations Lead'],true))return $out;
 foreach(om_query("SELECT id FROM import_batches WHERE import_type='Non-Sellable Release' AND status='Submitted' ORDER BY id DESC") as $r){try{$b=workflow_batch($r['id']);if(restricted_can_approve($b))$out[]=$b;}catch(Throwable $e){continue;}}
 return $out;
}
function restricted_approval_panel(){
 if(!in_array(access_profile(),['Admin','Operations Lead'],true))return;$rows=restricted_pending_requests();
 echo '<section class="card table-wrap"><h3>Non-Sellable Requests · Awaiting Approval</h3><p class="muted small">ICO requests: Operations Lead or Admin approves. Operations Lead requests: a different Admin approves. Stock changes only on approval and posting.</p><table class="table"><tr><th>Reference / Date</th><th>Requested By</th><th>Purpose / Quantity</th><th>Action</th></tr>';
 foreach($rows as $b){$w=$b['workflow'];$u=om_query('SELECT name FROM users WHERE id=?',[$b['user_id']])->fetchColumn();echo '<tr><td>'.e($w['data']['ref']).'<br>'.e($w['data']['txn_date']).'</td><td>'.e($u).'</td><td>'.e($w['data']['type']).' · '.array_sum(array_column($w['plan'],'qty')).' pcs</td><td><a class="btn secondary sm" href="?page=stock_batch&id='.$b['id'].'">Review Request</a></td></tr>';}
 if(!$rows)echo '<tr><td colspan="4">No Non-Sellable requests awaiting your approval.</td></tr>';echo '</table></section>';
}
function restricted_record_status($r){
 if($r['reversed_by_request']!==null)return 'Reversed';$remaining=$r['remaining']??max(0,(int)$r['qty']-release_total($r['id']));if(packaging_kind($r['condition_type'])==='damaged'&&packaging_ready()){$recovered=(int)om_query('SELECT COALESCE(SUM(qty),0) FROM packaging_completions WHERE source_id=? AND reversed_by_request IS NULL',[$r['id']])->fetchColumn();if($recovered>0)return $remaining?'Partially Returned to Sellable':($recovered>=(int)$r['qty']?'Returned to Sellable':'Completed / Released');}if(!$remaining)return 'Completed / Released';
 if(packaging_kind($r['condition_type'])==='damaged'&&in_array($r['status'],['For Review','Pending','Recorded'],true))return 'Recorded · Non-Sellable';
 return $r['packaging_state']??$r['status'];
}
function restricted_record_action($r){return packaging_kind($r['condition_type'])==='damaged'&&$r['action_type']==='For Review'?'Held in Non-Sellable':$r['action_type'];}
function csr_restricted_catalog(){
 if(access_profile()!=='CSR')throw new Exception('CSR viewing required.');$q=trim((string)($_GET['q']??''));
 echo '<link rel="stylesheet" href="assets/operations_controls.css?v=5.9.69"><div class="page-head"><div><h1>Non-Sellable · Warranty Source Viewing</h1><p>Check damaged / for-parts items for possible warranty replacement. Viewing does not reserve or release stock.</p></div><a class="btn secondary" href="?page=warranty_cases">Open Warranty Cases</a></div><section class="card"><form method="get" class="inline-form"><input type="hidden" name="page" value="nonsellable"><label>Product / SKU<input name="q" value="'.e($q).'" placeholder="Search product or SKU"></label><button class="btn secondary">Search</button></form></section><section class="card table-wrap"><table class="table"><tr><th>Product / SKU</th><th>Source / Condition</th><th>Physical Units Remaining</th><th>Component Information</th></tr>';
 $rows=[];foreach(om_query('SELECT n.*,p.name,p.master_sku FROM non_sellable n JOIN products p ON p.id=n.product_id WHERE n.qty>0 AND n.reversed_by_request IS NULL ORDER BY n.id DESC') as $r){if(studio_source($r)||(!in_array(packaging_kind($r['condition_type']),['damaged'],true)&&$r['condition_type']!=='Incomplete / For Parts'))continue;if($q!==''&&mb_stripos($r['name'].' '.$r['master_sku'],$q)===false)continue;$left=max(0,(int)$r['qty']-release_total($r['id']));if(!$left)continue;$rows[]=$r;}
 $offset=max(0,(int)($_GET['offset']??0));foreach(array_slice($rows,$offset,20) as $r){$components=[];if(wc_ready())foreach(om_query('SELECT c.component_code,c.capacity,c.used_qty,d.unit_no FROM warranty_donor_components c JOIN warranty_donor_units d ON d.id=c.donor_id WHERE d.non_sellable_id=? ORDER BY d.unit_no,c.component_code',[$r['id']]) as $c)$components[]=$c['component_code'].' · unit '.$c['unit_no'].' · '.max(0,(int)$c['capacity']-(int)$c['used_qty']).' available';echo '<tr><td><b>'.e($r['name']).'</b><br>'.e($r['master_sku']).'</td><td>NS-'.(int)$r['id'].' · '.e($r['condition_type']).'<br>'.e($r['order_id']).'</td><td>'.max(0,(int)$r['qty']-release_total($r['id'])).' unit(s)</td><td>'.e($components?implode('; ',$components):'Component availability requires inspection / compatibility review.').'</td></tr>';}
 if(!$rows)echo '<tr><td colspan="4">No matching damaged / for-parts stock.</td></tr>';echo '</table><p class="muted small">Read only. Operations Lead approves the exact replacement source; ICO confirms actual receiving / release in Warranty Cases.</p>'.rs8_pagination(count($rows),$offset,20,['page'=>'nonsellable','q'=>$q]).'</section>';
}
