<?php
function system_notification_audit_filter(){
 $entities=['Outflow','Import','Inflow Batch','Borrow','Transfer','Product','Non-Sellable','Receipt','Reversal','Bundle','Inventory','Allocation Batch','Exception','Packaging','Repackaging','Return','Reconciliation','Threshold Batch','Brand','Category'];if(current_user()['role']==='Admin')$entities=array_merge($entities,['User','Settings','System','Backup','Closing']);
 return "a.entity_type IN (".implode(',',array_map(fn($x)=>db()->quote($x),$entities)).") AND a.action_type NOT IN ('Physical count adjustment','Platform redistribution') AND a.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)";
}
function system_notification_cursor(){return array_replace(['audit'=>0,'count'=>0],json_decode(setting('notification_read_'.current_user()['id'],''),true)?:[]);}
function system_notification_tasks(){
 $tasks=[];$add=function($title,$sql,$url)use(&$tasks){$n=(int)db()->query($sql)->fetchColumn();if($n)$tasks[]=['title'=>$title,'count'=>$n,'url'=>$url];};
 if(adjustments_ready()&&current_user()['role']==='Admin')$add('Adjustment requests awaiting Admin approval',"SELECT COUNT(*) FROM inventory_count_batches WHERE workflow_version IN ('5.4.0','5.4.29') AND status IN ('Submitted','Awaiting Approval')",'index.php?page=admin_requests&request_type=adjustment');
 if(can_action('upload_inventory_count')&&adjustments_ready())$add('Your adjustments needing entry or correction',"SELECT COUNT(*) FROM inventory_count_batches WHERE created_by=".(int)current_user()['id']." AND status IN ('Exported','Validated','Error')",'index.php?page=inventory_count&tab=review');

 if(current_user()['role']==='Admin'){
  $add('Physical releases awaiting separate Admin approval',"SELECT COUNT(*) FROM import_batches WHERE import_type='Non-Sellable Release' AND status='Submitted'",'index.php?page=admin_requests&request_type=physical_release');$resetCount=count(password_reset_requests());if($resetCount)$tasks[]=['title'=>'Password reset requests requiring identity verification','count'=>$resetCount,'url'=>'index.php?page=admin_requests&request_type=password&request_status=Pending%20Review'];
  $add('New users awaiting approval',"SELECT COUNT(*) FROM users WHERE approval_status='Pending'",'index.php?page=admin_requests&request_type=registration&request_status=Pending%20Review');
  $add('Borrow conversions awaiting approval',"SELECT COUNT(*) FROM borrowed_events WHERE status='Pending'",'index.php?page=admin_requests&request_type=borrow&request_status=Awaiting%20Approval');
  $add('Transaction corrections awaiting approval',"SELECT COUNT(*) FROM transaction_reversals WHERE status='Pending'",'index.php?page=admin_requests&request_type=reversal&request_status=Awaiting%20Approval');
 }
 if(can_action('save_order_review'))$add('Order uploads needing review',"SELECT COUNT(*) FROM import_batches WHERE import_type='Orders' AND status='Review'",'index.php?page=imports&history=all&import_type=Orders&import_status=Review');
 $damageUploads=return_pending_damage_uploads();$excluded=$damageUploads?' AND batch_ref NOT IN ('.implode(',',array_map(fn($b)=>db()->quote($b['batch_ref']),$damageUploads)).')':'';
 if(can_action('confirm_receipt')||can_action('confirm_bulk_inflow'))$add('Inflow transactions needing review',"SELECT COUNT(*) FROM inflow_batches WHERE status IN ('To Review','Pending Review','Review')".$excluded,'index.php?page=inflow&history=all&inflow_status=Awaiting%20Review');
 if(can_action('return_confirm')){$mine=array_filter($damageUploads,fn($b)=>(int)$b['created_by']===current_user()['id']);if($mine)$tasks[]=['title'=>'Your Damage / Leak returns needing validation','count'=>count($mine),'url'=>'index.php?page=inflow&history=all&inflow_kind=Order%20Return&inflow_status=Awaiting%20Review&inflow_owner=mine'];}
 if(current_user()['role']==='Admin'){$needsAdmin=0;foreach(db()->query("SELECT * FROM return_batches WHERE status='Review'") as $returnBatch){$analysis=return_analysis($returnBatch);if(return_requires_admin_review($analysis))$needsAdmin++;}if($needsAdmin)$tasks[]=['title'=>'Possible duplicate returns needing Admin review','count'=>$needsAdmin,'url'=>'index.php?page=inflow&history=all&inflow_kind=Order%20Return&inflow_status=Awaiting%20Review'];}
 if(can_action('upload_beginning'))$add('Beginning Balance uploads needing submission or correction',"SELECT COUNT(*) FROM import_batches WHERE import_type='Beginning Balance' AND imported_count=0 AND status IN ('Review','Unsuccessful','Incomplete')",'index.php?page=imports&history=all&import_type=Beginning%20Balance');
 if(can_action('negative_resolve'))$add('Stock exceptions needing attention',"SELECT COUNT(*) FROM stock_exceptions WHERE status='Open'",'index.php?page=stock_exceptions');
 return $tasks;
}
function system_notification_audit_url($event){
 $ref=(string)$event['entity_ref'];$entity=$event['entity_type'];
 if($entity==='Outflow'&&ctype_digit($ref))return 'index.php?page=outflow_batch&id=-'.(int)$ref;
 if($entity==='Import'&&ctype_digit($ref)){$q=db()->prepare('SELECT import_type FROM import_batches WHERE id=?');$q->execute([(int)$ref]);return 'index.php?page='.($q->fetchColumn()==='Orders'?'outflow_batch':'import_detail').'&id='.(int)$ref;}
 if($entity==='Inflow Batch'){if(ctype_digit($ref))return 'index.php?page=inflow_batch&id='.(int)$ref;$q=db()->prepare('SELECT id FROM inflow_batches WHERE batch_ref=?');$q->execute([$ref]);if($id=$q->fetchColumn())return 'index.php?page=inflow_batch&id='.(int)$id;}
 return 'index.php?page='.(['Inflow Batch'=>'inflow','Borrow'=>'borrowed','Transfer'=>'transfers','Product'=>'products','Non-Sellable'=>'nonsellable','Receipt'=>'receipts','Reversal'=>'corrections','Bundle'=>'mapping','User'=>'settings','Inventory'=>'stocks','Allocation Batch'=>'platform_inventory','Exception'=>'stock_exceptions','Packaging'=>'nonsellable&stock_view=packaging','Repackaging'=>'nonsellable&stock_view=packaging','Return'=>'inflow','Reconciliation'=>'reconciliation','Threshold Batch'=>'products','Brand'=>'products','Category'=>'category_manager','Backup'=>'backups','Closing'=>'day_closing'][$entity]??'activity');
}
function system_notification_data(){if(access_is_viewer())return ['tasks'=>[],'pending'=>0,'unread'=>0,'events'=>[],'audit_max'=>0,'count_max'=>0];
 $cursor=system_notification_cursor();$where=system_notification_audit_filter();$tasks=system_notification_tasks();
 $q=db()->prepare('SELECT COUNT(*) FROM activity_log a WHERE '.$where.' AND a.id>?');$q->execute([(int)$cursor['audit']]);$unread=(int)$q->fetchColumn();
 $auditMax=(int)db()->query('SELECT COALESCE(MAX(a.id),0) FROM activity_log a WHERE '.$where)->fetchColumn();
 $q=db()->query('SELECT a.*,u.name processed_by FROM activity_log a LEFT JOIN users u ON u.id=a.user_id WHERE '.$where.' ORDER BY a.id DESC LIMIT 15');$events=[];
 foreach($q as $e)$events[]=['source'=>'audit','id'=>(int)$e['id'],'title'=>$e['action_type'],'reference'=>$e['entity_type']==='Bundle'?'Bundle mapping':$e['entity_ref'],'by'=>$e['processed_by']?:'System','at'=>$e['created_at'],'url'=>system_notification_audit_url($e),'unread'=>(int)$e['id']>(int)$cursor['audit']];
 $countMax=0;
 if(adjustments_ready()){
  $cw="v.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)";$q=db()->prepare('SELECT COUNT(*) FROM count_review_events v WHERE '.$cw.' AND v.id>?');$q->execute([(int)$cursor['count']]);$unread+=(int)$q->fetchColumn();$countMax=(int)db()->query('SELECT COALESCE(MAX(v.id),0) FROM count_review_events v WHERE '.$cw)->fetchColumn();
  $q=db()->query('SELECT v.*,b.batch_ref,u.name processed_by FROM count_review_events v JOIN inventory_count_batches b ON b.id=v.batch_id LEFT JOIN users u ON u.id=v.user_id WHERE '.$cw.' ORDER BY v.id DESC LIMIT 15');
  $labels=['Snapshot created'=>'Inventory count started','First upload'=>'First count saved','Recount upload'=>'Recount saved','Reviewed'=>'Adjustment submitted for approval','Approved and posted'=>'Adjustment approved and posted','Returned for review'=>'Adjustment returned for correction','Cancelled'=>'Adjustment cancelled','Upload errors'=>'Inventory count needs correction','Manual session created'=>'Manual adjustment started','Manual count entry'=>'Manual count updated','Rejected action'=>'Adjustment action could not proceed'];
  foreach($q as $e)$events[]=['source'=>'count','id'=>(int)$e['id'],'title'=>$labels[$e['event_type']]??$e['event_type'],'reference'=>$e['batch_ref'],'by'=>$e['processed_by']?:'System','at'=>$e['created_at'],'url'=>'index.php?page=inventory_count&id='.(int)$e['batch_id'],'unread'=>(int)$e['id']>(int)$cursor['count']];
 }
 usort($events,fn($a,$b)=>strcmp($b['at'],$a['at'])?:$b['id']<=>$a['id']);return ['tasks'=>$tasks,'pending'=>array_sum(array_column($tasks,'count')),'unread'=>$unread,'events'=>array_slice($events,0,20),'audit_max'=>$auditMax,'count_max'=>$countMax];
}
function system_notification_mark_read($audit,$count){
 $data=system_notification_data();$old=system_notification_cursor();$new=['audit'=>max((int)$old['audit'],min(whole_number($audit,'Notification reference'),$data['audit_max'])),'count'=>max((int)$old['count'],min(whole_number($count,'Adjustment notification reference'),$data['count_max']))];set_setting('notification_read_'.current_user()['id'],json_encode($new));
}
function system_notification_read_form($data){
 if(!$data['unread'])return '';return '<form method="post" action="notifications.php"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="audit_through" value="'.$data['audit_max'].'"><input type="hidden" name="count_through" value="'.$data['count_max'].'"><button class="btn ghost sm">Mark updates as read</button></form>';
}
function system_notifications_bell(){
 $data=system_notification_data();$n=$data['unread']+$data['pending'];echo '<details class="adjustment-notifications" id="systemNotifications"><summary aria-label="Notifications: '.$data['unread'].' new updates and '.$data['pending'].' pending tasks" title="Notifications"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>'.($n?'<span class="adjustment-notification-count">'.($n>99?'99+':$n).'</span>':'').'</summary><div class="adjustment-notification-panel"><h3>Notifications</h3><p class="muted small">'.$data['unread'].' new updates · '.$data['pending'].' pending tasks</p>';
 foreach($data['tasks'] as $t)echo '<a class="adjustment-notification-item" href="'.e($t['url']).'"><b>'.e($t['title']).'</b><span class="badge warn">'.$t['count'].' pending</span></a>';
 echo '<h4>Recent Updates</h4>';foreach(array_slice($data['events'],0,6) as $e)system_notification_event_html($e);if(!$data['events'])echo '<p>No updates in the last 7 days.</p>';echo system_notification_read_form($data).'<a class="btn ghost" href="index.php?page=notifications">View All Notifications</a></div></details>';
}
function system_notification_event_html($e){echo '<a class="adjustment-notification-item" href="'.e($e['url']).'"><b>'.($e['unread']?'<span aria-label="New update">● </span>':'').e($e['title']).'</b><span>'.e($e['reference']).'</span><small>'.e($e['by']).' · '.e($e['at']).'</small></a>';}
function system_notifications_page(){
 $data=system_notification_data();echo '<div class="page-head"><div><h1>Notifications</h1><p>Approvals, transaction updates and items needing attention.</p></div>'.system_notification_read_form($data).'</div><div class="grid two"><div class="card"><h3>Pending Tasks — '.$data['pending'].'</h3><p class="muted small">Reading a notification does not approve or post a transaction. Pending tasks remain until processed.</p>';foreach($data['tasks'] as $t)echo '<a class="adjustment-notification-item" href="'.e($t['url']).'"><b>'.e($t['title']).'</b><span class="badge warn">'.$t['count'].' pending</span></a>';if(!$data['tasks'])echo '<p>No pending tasks for your role.</p>';echo '</div><div class="card"><h3>Recent Updates — '.$data['unread'].' new</h3><p class="muted small">Latest 20 updates from the last 7 days. Full records remain in Activity Log and module history.</p>';foreach($data['events'] as $e)system_notification_event_html($e);if(!$data['events'])echo '<p>No recent updates.</p>';echo '</div></div>';
}
