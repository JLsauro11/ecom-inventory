<?php
function adjustment_notification_message($b){
 $status=$b['status'];$creator=trim((string)($b['created_name']??''))?:'a system user';return match($status){
 'Exported'=>['info','Count required','Snapshot created. Enter the First Count, then complete a Recount. No inventory has changed.'],
 'Recount Required'=>['warn','Recount required','First Count saved. Enter a physical Recount before submitting for review. No inventory has changed.'],
 'Recounted'=>['info','Ready for review','Recount saved. Review the results, enter a reason and supporting reference, then submit for approval. No inventory has changed.'],
 'Awaiting Approval'=>['warn','Review & Approval Required','This inventory adjustment was created by '.$creator.' and is ready for final review and approval. Please review the actual counts and platform allocations before approving. Stocks will update only after approval and posting.'],
 'Applied'=>['success','Adjustment posted','The verified counts and platform allocations were applied. Check Current Stocks and Platform Inventory; later transactions may change those balances.'],
 'Error'=>['danger','Correction required','The count has validation errors. Correct the entries or upload the corrected sheet. No inventory has changed.'],
 'Cancelled'=>['info','Adjustment cancelled','This count session is closed. No inventory was changed by this adjustment.'],
 'Reversed'=>['info','Adjustment reversed','A linked reversal was recorded. Review the movement history for the resulting balances.'],
 default=>['info','Adjustment status',$status]
 };}
function adjustment_notification_summary(){
 static $result=null;if($result!==null)return $result;$result=['pending'=>0,'approval'=>0,'review'=>0,'recount'=>0,'errors'=>0,'items'=>[]];
 if(!adjustments_ready()||!can_action('upload_inventory_count'))return $result;
 $q=db()->query("SELECT status,COUNT(*) total FROM inventory_count_batches WHERE workflow_version='5.4.0' AND status IN ('Exported','Recount Required','Recounted','Awaiting Approval','Error') GROUP BY status");
 foreach($q as $row){$n=(int)$row['total'];$result['pending']+=$n;$key=['Awaiting Approval'=>'approval','Recounted'=>'review','Recount Required'=>'recount','Error'=>'errors'][$row['status']]??null;if($key)$result[$key]+=$n;}
 $result['items']=db()->query("SELECT id,batch_ref,status,created_name,reviewed_by,reviewed_name,applied_at FROM inventory_count_batches WHERE workflow_version='5.4.0' AND (status IN ('Exported','Recount Required','Recounted','Awaiting Approval','Error') OR (status='Applied' AND applied_at>=DATE_SUB(NOW(),INTERVAL 1 DAY))) ORDER BY CASE WHEN status='Awaiting Approval' THEN 0 WHEN status='Applied' THEN 2 ELSE 1 END,id DESC LIMIT 8")->fetchAll();return $result;
}
function adjustment_notification_bell(){
 if(!adjustments_ready()||!can_action('upload_inventory_count'))return;$n=adjustment_notification_summary();
 echo '<details class="adjustment-notifications"><summary aria-label="Inventory adjustment notifications: '.$n['pending'].' pending" title="Inventory adjustment notifications"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>'.($n['pending']?'<span class="adjustment-notification-count">'.$n['pending'].'</span>':'').'</summary><div class="adjustment-notification-panel"><h3>Inventory Adjustments</h3><p class="muted small">'.$n['pending'].' pending · Recent posted updates shown for 24 hours</p>';
 if(!$n['items'])echo '<p>No pending or recently posted adjustment updates.</p>';
 foreach($n['items'] as $b){[$cls,$title,$message]=adjustment_notification_message($b);if($b['status']==='Awaiting Approval'&&(int)$b['reviewed_by']===current_user()['id'])$title='Awaiting another Admin';echo '<a class="adjustment-notification-item" href="index.php?page=inventory_count&id='.(int)$b['id'].'"><b>'.e($b['batch_ref']).'</b><span class="badge '.e($cls).'">'.e($title).'</span><small>'.e($message).'</small></a>';}
 echo '<a class="btn ghost" href="index.php?page=inventory_count&tab=review">Open Review & Approval</a></div></details>';
}
function adjustment_record_notification($b){[$cls,$title,$message]=adjustment_notification_message($b);echo '<div class="alert '.e($cls).'" role="status"><strong>'.e($title).'</strong><p>'.e($message).'</p>';if($b['status']==='Awaiting Approval'){echo '<p>Reviewed and submitted by '.e($b['reviewed_name']?:'the reviewer').'.</p>';if((int)($b['reviewed_by']??0)===current_user()['id'])echo '<p>Another Admin must approve this request.</p>';elseif(current_user()['role']==='Admin')echo '<p>Select Approve &amp; Post Adjustment after completing your review.</p>';else echo '<p>Awaiting approval from an authorized Admin.</p>';}if($b['status']==='Applied')echo '<p>Approved by '.e($b['approved_name']?:'Admin').' · '.e($b['applied_at']??'').'</p>';echo '</div>';}
