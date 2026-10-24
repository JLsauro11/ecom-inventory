<?php
function default_product_category_tree(){return [
 'CVT'=>['Pulley Set','Clutch Bell','Clutch Assembly','Clutch Shoe','Center Spring','Roller Ball','Torque Drive','Clutch Spring','Pulley Bushing','Locking Nut','Air Scoop','Drive Face','Pulley Washer','Back Plate','Pulley Damper','Drive Belt','CVT Set','Clutch Guide','Oil Seal'],
 'Engine'=>['Block Kit','Head Gasket','Piston Kit','Engine Valve','Valve Seal','Valve Spring','Supercam','Cam Gear','Throttle Body','Throttle Body Position (TPS)','Manual ISC','Injector','Connecting Rod','Oil Pump','Carburetor','Crankshaft','Gearings','Tappet Screw','Head Cover','Radiator','Cylinder Head','Camshaft Bearing','Crankshaft Bearing','Valve Retainer','Valve Shims','Timing Chain Tensioner','Rocker Arm','Rocker Arm Shaft','Rocker Arm / Piston Pin Set','Stroker Kit','Engine Upgrade Kit','Engine Fasteners','Crankshaft Position Sensor (CKP)'],
 'Performance Parts'=>['Bung Sensor','Fuel Pump','Fuel Filter','Air Filter','Ignition Coil','Brake Shoe','Disc Pad','Starter Motor','Exhaust','Performance Kit','Redlak Canister','Heat Guard'],
 'Oils'=>[], 'Liquid Essentials'=>[],
 'Lighting & Electrical'=>['LED / Driving Light','Horn','Switch'],
 'Merchandise'=>['Shirt / Jersey','Cap / Bag Set','Umbrella','Tarpaulin','Warranty Card'],
 'Accessories'=>['Fasteners']
 ];}
function product_category_tree(){if(function_exists('complete_update_ready')&&complete_update_ready()){$tree=[];foreach(db()->query('SELECT main_name,sub_name FROM managed_categories WHERE active=1 ORDER BY id') as $c){if(!isset($tree[$c['main_name']]))$tree[$c['main_name']]=[];if($c['sub_name']!=='')$tree[$c['main_name']][]=$c['sub_name'];}return $tree;}return default_product_category_tree();}
function product_categories_ready(){
 static $ready=null;if($ready!==null)return $ready;global $CFG;
 $s=db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='products' AND COLUMN_NAME='main_category'");$s->execute([$CFG['db_name']]);return $ready=(bool)$s->fetchColumn();
}
function product_category_selection($main,$sub,$existing=null){
 if(!product_categories_ready())throw new Exception('Ask the administrator to run the updated V5 upgrade before saving categories.');
 $main=trim((string)$main);$sub=trim((string)$sub);$tree=product_category_tree();
 if($main===''&&$existing&&empty($existing['main_category'])&&$sub===$existing['category'])return [null,$sub];
 if(!array_key_exists($main,$tree))throw new Exception('Select a valid Main Category.');
 if(!$tree[$main]){if($sub!==''&&$sub!==$main)throw new Exception('This Main Category has no subcategories.');return [$main,$main];}
 if(!in_array($sub,$tree[$main],true))throw new Exception('Select a Subcategory belonging to the selected Main Category.');
 return [$main,$sub];
}
function product_category_fields($edit=false){
 $prefix=$edit?'ep_':'ap_';$opts='<option value="">'.($edit?'Unassigned / Existing category':'Select main category').'</option>';
 foreach(product_category_tree() as $main=>$subs)$opts.='<option value="'.e($main).'">'.e($main).'</option>';
 return '<div class="row category-pair"><label>Main Category<select name="main_category" id="'.$prefix.'main_category" data-main-category'.($edit?'':' required').'>'.$opts.'</select></label><label>Subcategory<select name="category" id="'.$prefix.'category" data-sub-category><option value="">Select main category first</option></select></label></div>';
}
