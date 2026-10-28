<?php
function role_filtered_html($html){
 if(current_user()['role']==='Admin')return $html;
 $html=preg_replace_callback('/<form\b[^>]*>.*?<\/form>/si',function($m){if(!preg_match('/name="action"\s+value="([A-Za-z0-9_]+)"/',$m[0],$a))return $m[0];$action=$a[1];if(str_starts_with($action,'batch_')){$context=[];foreach(['kind','batch_id'] as $key)if(preg_match('/name="'.$key.'"\s+value="([^"]*)"/',$m[0],$v))$context[$key]=html_entity_decode($v[1],ENT_QUOTES,'UTF-8');$action=workflow_action_permission($action,$context);if($action==='')return '';}if(str_contains($m[0],'action="inventory_count.php"'))return can_action('upload_inventory_count')?$m[0]:'';return (in_array($action,['save_profile','change_password'],true)||can_action($action))?$m[0]:'';},$html);
 if(!can_action('add_product'))$html=preg_replace('/<button\b[^>]*onclick="[^\"]*addProductDialog[^\"]*"[^>]*>.*?<\/button>/si','',$html);
 if(!can_action('edit_product'))$html=preg_replace('/<button\b[^>]*class="[^\"]*edit-product[^\"]*"[^>]*>.*?<\/button>/si','',$html);
 return $html;
}
