<?php
// Legacy stored roles remain valid; all new assignments use the current name.
function role_label($role){return $role==='E-Commerce'?'Operations Lead':($role==='Viewer'?'Viewer / Management':$role);}
function custom_access_roles(){return json_decode(setting('custom_access_roles_v1','{}'),true)?:[];}
function access_role_names(){return array_merge(['Admin','Inventory Controller','Operations Lead','Auditor','Viewer / Management'],array_keys(custom_access_roles()));}
function access_role_profile($role){$role=role_label($role);if(in_array($role,['Admin','Inventory Controller','Operations Lead','Auditor','Viewer / Management'],true))return $role;$roles=custom_access_roles();$profile=$roles[$role]['profile']??'';return in_array($profile,['Inventory Controller','Operations Lead','Auditor','Viewer / Management'],true)?$profile:'Viewer / Management';}
function role_options_html($selected='Viewer / Management'){$html='';foreach(access_role_names() as $role)$html.='<option value="'.e($role).'"'.(role_label($selected)===$role?' selected':'').'>'.e($role).'</option>';return $html;}
function create_access_role($name,$profile){
 if(current_user()['role']!=='Admin')throw new Exception('Only an Admin can create access roles.');
 $name=trim(preg_replace('/\s+/u',' ',(string)$name));
 if(!preg_match('/^[A-Za-z][A-Za-z0-9 &()\/-]{1,39}$/D',$name))throw new Exception('Role name must be 2-40 characters and start with a letter.');
 if(!in_array($profile,['Inventory Controller','Operations Lead','Auditor','Viewer / Management'],true))throw new Exception('Select a valid access profile.');
 $pdo=db();$pdo->beginTransaction();try{
  $pdo->exec("INSERT IGNORE INTO settings(key_name,value_text) VALUES('custom_access_roles_v1','{}')");
  $q=$pdo->query("SELECT value_text FROM settings WHERE key_name='custom_access_roles_v1' FOR UPDATE");$roles=json_decode($q->fetchColumn(),true)?:[];
  foreach(array_merge(['Admin','Inventory Controller','Operations Lead','E-Commerce','Auditor','Viewer','Viewer / Management'],array_keys($roles)) as $existing)if(strcasecmp($existing,$name)===0)throw new Exception('This role name already exists.');
  $roles[$name]=['profile'=>$profile,'created_by'=>current_user()['id'],'created_at'=>date(DATE_ATOM)];
  set_setting('custom_access_roles_v1',json_encode($roles));audit('Create access role','Settings',$name,'Access profile: '.$profile);$pdo->commit();
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
 return $name;
}
function access_roles_panel(){
 if(current_user()['role']!=='Admin')return;
 echo '<div class="card" id="access-roles"><h3>Access Roles</h3><p>Create a role before approving a registration, then select it in Access Role below. Only an Admin can create or assign roles.</p><form method="post" action="actions.php" class="stack"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="create_access_role"><input type="hidden" name="return" value="settings"><div class="row"><label>New Role Name<input name="role_name" maxlength="40" placeholder="Example: Warehouse Supervisor" required></label><label>Access Profile<select name="access_profile"><option>Viewer / Management</option><option>Auditor</option><option>Operations Lead</option><option>Inventory Controller</option></select></label></div><p class="muted small">Auditor: checking, all business downloads, and inventory adjustment requests. Operations Lead: full operational access without final approvals or account administration. Inventory Controller: inflow, outflow and Non-Sellable records. Viewer / Management: summary balances and reports. Admin approvals and account management remain Admin-only. A custom role inherits its selected profile.</p><button class="btn primary">Create Role</button></form><table class="table"><tr><th>Role</th><th>Access Profile</th></tr>';
 foreach(access_role_names() as $role)echo '<tr><td>'.e($role).'</td><td>'.e(access_role_profile($role)).'</td></tr>';echo '</table></div>';
}
function role_profile_can_action($role,$action){
 $role=role_label($role);if(str_starts_with($action,'om_'))return $action==='om_receive'?role_profile_can_action($role,'return_confirm'):in_array($role,['Admin','Operations Lead'],true);if(in_array($action,['import_orders','save_order_review','confirm_order_review','cancel_order_review'],true)&&function_exists('om_ready')&&om_ready()&&!in_array($role,['Admin','Operations Lead'],true))return false;if($role==='Admin')return true;
 if(in_array($action,['cancelled_order_lookup','cancelled_order_review'],true)||str_starts_with($action,'receiving_'))$action='return_confirm';
 if(in_array($action,['replace_beginning_upload','cancel_beginning_upload'],true))$action='upload_beginning';
 if($role==='Auditor')return in_array($action,['upload_inventory_count','reversal_request'],true);
 $transactions=['inflow_supplier','return_upload','return_save_row','return_confirm','return_cancel','add_inflow','add_nonsellable','cancel_inflow_upload','cancel_order_review','confirm_bulk_inflow','confirm_manual_inflow','confirm_order_review','confirm_receipt','correct_receipt_header','import_orders','manual_outflow','resolve_receipt_line','save_inflow_allocation','save_order_review','repack_stage','repack_post','repack_refresh','repack_cancel','packaging_receive_raw','packaging_update','nonsellable_out','reversal_request'];
 if($role==='Inventory Controller')return in_array($action,$transactions,true);
 if($role==='Operations Lead')return in_array($action,array_merge($transactions,['add_product','edit_product','set_product_status','upload_thresholds','confirm_thresholds','manage_brand','manage_category','map_alias','save_default_allocation','save_product_allocation','run_allocation','align_platform_balances','transfer_platform','generate_platform_file','borrow_issue','borrow_return','borrow_request_conversion','import_recon','recon_correct','recon_confirm','recon_cancel','negative_assign','negative_resolve','upload_inventory_count','upload_beginning']),true);
 return false;
}
function can_role_action($role,$action){return role_profile_can_action(access_role_profile($role),$action);}
function workflow_action_permission($action,$context){
 if(str_starts_with($action,'batch_')){
  $kind=$context['kind']??'';
  if($action!=='batch_prepare'){
   try{$kind=workflow_batch((int)($context['batch_id']??0))['import_type'];}catch(Throwable $e){return '';}
  }
  $action=['Manual Outflow'=>'manual_outflow','Borrow Issue'=>'borrow_issue','Non-Sellable Release'=>'nonsellable_out','Platform Transfer'=>'transfer_platform'][$kind]??'';
 }
 return $action;
}
function can_action($action){
 $action=workflow_action_permission($action,$_POST);if($action==='')return false;
 return can_role_action(current_user()['role']??'Viewer / Management',$action);
}
function operational_users($action){return array_values(array_filter(db()->query("SELECT id,name,role FROM users WHERE active=1 AND approval_status='Approved' ORDER BY name")->fetchAll(),fn($u)=>can_role_action($u['role'],$action)));}
