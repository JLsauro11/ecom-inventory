<?php
function role_filtered_html($html){
 if(current_user()['role']==='Admin')return $html;
 $html=preg_replace_callback('/<form\b[^>]*>.*?<\/form>/si',function($m){if(!preg_match('/name="action"\s+value="([A-Za-z0-9_]+)"/',$m[0],$a))return $m[0];$action=$a[1];if(str_contains($m[0],'action="inventory_count.php"'))return can_action('upload_inventory_count')?$m[0]:'';return (in_array($action,['save_profile','change_password'],true)||can_action($action))?$m[0]:'';},$html);
 if(!can_action('add_product'))$html=preg_replace('/<button\b[^>]*onclick="[^\"]*addProductDialog[^\"]*"[^>]*>.*?<\/button>/si','',$html);
 if(!can_action('edit_product'))$html=preg_replace('/<button\b[^>]*class="[^\"]*edit-product[^\"]*"[^>]*>.*?<\/button>/si','',$html);
 return $html;
}
