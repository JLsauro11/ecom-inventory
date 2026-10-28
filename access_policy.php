<?php
function access_profile($role=null){return access_role_profile($role??current_user()['role']);}
function access_is_viewer($role=null){return access_profile($role)==='Viewer / Management';}
function access_operational_manager(){return in_array(access_profile(),['Admin','Operations Lead'],true);}
function access_report_allowed($report,$role=null){return !access_is_viewer($role)||in_array($report?:'overview',['overview','stocks','platforms','daily','product_master'],true);}
function access_report_catalog(){$out=[];foreach(report_catalog() as $group=>$reports){$allowed=array_filter($reports,fn($label,$key)=>access_report_allowed($key),ARRAY_FILTER_USE_BOTH);if($allowed)$out[$group]=$allowed;}return $out;}
function access_page_allowed($page,$role=null){$profile=access_profile($role);if($profile==='Admin')return true;if(in_array($page,['admin_requests','admin_request','settings','backups','day_closing','fresh_start','brand_sku'],true))return false;if(access_is_viewer($role))return in_array($page,['dashboard','stocks','platform_inventory','reports'],true);return true;}
function access_count_action_allowed($action,$role=null){$profile=access_profile($role);if($profile==='Admin')return true;if(!in_array($profile,['Auditor','Operations Lead'],true))return false;return in_array($action,['create','manual_create','manual_save','upload','save_review','final_create','final_upload','final_manual','final_submit','final_cancel','guided_validate','guided_submit','guided_cancel','guided_refresh'],true);}
function access_request_allowed($script,$method,$get=[],$post=[],$role=null){
 $profile=access_profile($role);if($profile==='Admin')return true;$script=basename($script);$method=strtoupper($method);
 if(str_starts_with($script,'upgrade_')||in_array($script,['backup_download.php','backup_auto.php','assign_categories.php','brand_sku_update.php','item_code_update.php'],true))return false;
 if($script==='index.php')return $method==='GET'&&access_page_allowed($get['page']??'dashboard',$role)&&(($get['page']??'')!=='reports'||access_report_allowed($get['report']??'overview',$role));
 if(in_array($script,['inventory_count.php','inventory_count_legacy_endpoint.php'],true))return $method==='GET'?!access_is_viewer($role):access_count_action_allowed($post['action']??'',$role);
 if($script==='report.php')return $method==='GET'&&access_report_allowed($get['report']??'overview',$role);
 if(in_array($script,['account.php','account_password.php','notifications.php'],true))return true;
 if($script==='actions.php')return $method==='POST'; // Every action is checked by can_action before dispatch.
 if(access_is_viewer($role))return $method==='GET'&&$script==='platform_inventory_export.php';
 return true;
}
function access_enforce_request(){$allowed=access_request_allowed($_SERVER['SCRIPT_NAME']??'',$_SERVER['REQUEST_METHOD']??'GET',$_GET,$_POST);if($allowed&&current_user()['role']!=='Admin'&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&in_array(basename($_SERVER['SCRIPT_NAME']??''),['inventory_count.php','inventory_count_legacy_endpoint.php'],true)&&!empty($_POST['batch_id'])){try{$allowed=(int)count_batch((int)$_POST['batch_id'])['created_by']===current_user()['id'];}catch(Throwable $e){$allowed=false;}}if($allowed)return;http_response_code(403);header('Cache-Control: private, no-store');exit('<h1>Access restricted</h1><p>Your role does not have access to this page or action.</p><a href="index.php">Return to Dashboard</a>');}
function access_assert_admin_approval($createdBy){if(current_user()['role']!=='Admin')throw new Exception('Only an Admin can approve this request.');if((int)$createdBy===current_user()['id'])throw new Exception('A different Admin must approve your own request.');}
function access_count_approve($id,$digest,$ack){return process_transaction(function()use($id,$digest,$ack){$b=count_batch($id);access_assert_admin_approval($b['created_by']);if(!$ack)throw new Exception('Review and acknowledge the final adjustment.');$plan=final_count_plan($id);if(!hash_equals($plan['digest'],(string)$digest))throw new Exception('Stock or validation changed. Reopen the latest request.');if($b['status']==='Submitted')final_count_review($id,$b['revision'],true);final_count_post($id,$digest,true);});}
