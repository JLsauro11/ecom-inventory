<?php
function role_filtered_html($html){
 if(current_user()['role']==='Admin')return $html;
 $html=preg_replace_callback('/<form\b[^>]*>.*?<\/form>/si',function($m){if(!preg_match('/name="action"\s+value="([A-Za-z0-9_]+)"/',$m[0],$a))return $m[0];$action=$a[1];if(str_starts_with($action,'batch_')){$context=[];foreach(['kind','batch_id'] as $key)if(preg_match('/name="'.$key.'"\s+value="([^"]*)"/',$m[0],$v))$context[$key]=html_entity_decode($v[1],ENT_QUOTES,'UTF-8');$action=workflow_action_permission($action,$context);if($action==='')return '';}if(str_contains($m[0],'action="inventory_count.php"'))return access_count_action_allowed($action)?$m[0]:'';return (in_array($action,['save_profile','change_password'],true)||can_action($action))?$m[0]:'';},$html);
 if(!can_action('add_product'))$html=preg_replace('/<button\b[^>]*onclick="[^\"]*addProductDialog[^\"]*"[^>]*>.*?<\/button>/si','',$html);
 if(!can_action('edit_product'))$html=preg_replace('/<button\b[^>]*class="[^\"]*edit-product[^\"]*"[^>]*>.*?<\/button>/si','',$html);
 if(!can_action('manage_category'))$html=preg_replace('/<button\b[^>]*data-category-(?:add|edit)[^>]*>.*?<\/button>/si','',$html);
 $html=preg_replace_callback('/<a\b([^>]*?)href="([^"]+)"([^>]*)>.*?<\/a>/si',function($m){$url=html_entity_decode($m[2],ENT_QUOTES,'UTF-8');$parts=parse_url($url);$path=basename($parts['path']??'index.php');parse_str($parts['query']??'',$params);if(in_array($path,['index.php','report.php','download.php','batch_document.php','inventory_count.php','backup_download.php'],true)&&!access_request_allowed($path,'GET',$params,[],current_user()['role']))return '';return $m[0];},$html);
 return $html;
}
