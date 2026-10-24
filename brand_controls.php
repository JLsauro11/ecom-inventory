<?php
function managed_brand_names(){
 if(!array_key_exists('rs8_managed_brands',$GLOBALS)){$names=json_decode(setting('custom_brand_names','[]'),true);$GLOBALS['rs8_managed_brands']=is_array($names)?array_values(array_filter($names,'is_string')):[];}
 return $GLOBALS['rs8_managed_brands'];
}
function inventory_brand_aliases(){
 $aliases=['nanomoly'=>'Oils & Liquid Essentials','luminapro'=>'Lumina Pro'];
 foreach(inventory_brand_options() as $name)$aliases[norm($name)]=$name;
 return $aliases;
}
function add_managed_brand($input){
 if(!can_action('manage_brand'))throw new Exception('Administrator access required to add a brand.');
 $name=preg_replace('/\s+/u',' ',trim((string)$input));
 if($name===null||mb_strlen($name)<2||mb_strlen($name)>80||preg_match('/[<>\x00-\x1f\x7f]/u',$name))throw new Exception('Enter a brand name of 2–80 characters without markup or control characters.');
 return process_transaction(function()use($name){
 unset($GLOBALS['rs8_managed_brands']);
 if(isset(inventory_brand_aliases()[norm($name)]))throw new Exception('This brand or its existing alias is already registered.');
 $names=managed_brand_names();$names[]=$name;set_setting('custom_brand_names',json_encode($names,JSON_UNESCAPED_UNICODE));
 db()->prepare('INSERT IGNORE INTO brands(name) VALUES(?)')->execute([$name]);
 unset($GLOBALS['rs8_managed_brands']);audit('Add brand','Brand',$name,'New brand available in product forms and brand filters.');return $name;
 });
}
