<?php
function category_assignment_for($p){
 $tree=product_category_tree();$main=$p['main_category']??'';$sub=$p['category']??'';
 if($main!=='Unassigned'&&isset($tree[$main])&&(($tree[$main]&&in_array($sub,$tree[$main],true))||(!$tree[$main]&&$sub===$main)))return [$main,$sub,'Keep manually assigned category'];
 $name=strtolower($p['name']);
 // Specific components precede broader assembly names.
 $rules=[
  ['ignition coil','Performance Parts','Ignition Coil'],['superhead','Engine','Cylinder Head'],['cvt set oil seal','CVT','Oil Seal'],['oil pump','Engine','Oil Pump'],
  ['warranty card','Merchandise','Warranty Card'],['shirt|jersey|longsleeve','Merchandise','Shirt / Jersey'],['\bcap\b.*bag','Merchandise','Cap / Bag Set'],['umbrella','Merchandise','Umbrella'],['tarpaulin','Merchandise','Tarpaulin'],
  ['luminapro.*switch','Lighting & Electrical','Switch'],['luminapro.*horn','Lighting & Electrical','Horn'],['luminapro','Lighting & Electrical','LED / Driving Light'],
  ['tire sealant|coolant|cleaner|degreaser|cutting fluid|max protector|brake fluid','Liquid Essentials','Liquid Essentials'],
  ['\b\d{1,2}\s*w\b|synthetic|syntethic|sae\s*40|gear oil|long ride formula','Oils','Oils'],
  ['camshaft bearing','Engine','Camshaft Bearing'],['crankshaft bearing','Engine','Crankshaft Bearing'],['valve retainer','Engine','Valve Retainer'],['valve.*shims','Engine','Valve Shims'],['valve seal','Engine','Valve Seal'],['valve spring','Engine','Valve Spring'],['\bvalves?\b','Engine','Engine Valve'],
  ['camgear bolt|cylinder head nut|head cover fittings','Engine','Engine Fasteners'],['cam\s*gear','Engine','Cam Gear'],['camshaft|supercam','Engine','Supercam'],['superhead','Engine','Cylinder Head'],['super touring set','Engine','Engine Upgrade Kit'],['stroker kit','Engine','Stroker Kit'],['piston kit','Engine','Piston Kit'],['block kit','Engine','Block Kit'],['head gasket','Engine','Head Gasket'],['tappet screw','Engine','Tappet Screw'],['head cover','Engine','Head Cover'],['manual tensioner','Engine','Timing Chain Tensioner'],['rocker arm.*piston pin','Engine','Rocker Arm / Piston Pin Set'],['rocker arm shaft','Engine','Rocker Arm Shaft'],['rocker arm','Engine','Rocker Arm'],['\bckp\b','Engine','Crankshaft Position Sensor (CKP)'],['\btps\b','Engine','Throttle Body Position (TPS)'],['manual isc','Engine','Manual ISC'],['injector','Engine','Injector'],['throttle body','Engine','Throttle Body'],['connecting rod','Engine','Connecting Rod'],['crankshaft','Engine','Crankshaft'],['radiator','Engine','Radiator'],
  ['pulley set','CVT','Pulley Set'],['air scoop','CVT','Air Scoop'],['pulley bushing','CVT','Pulley Bushing'],['pulley damper','CVT','Pulley Damper'],['pulley washer','CVT','Pulley Washer'],['drive face','CVT','Drive Face'],['back plate','CVT','Back Plate'],['locking nut','CVT','Locking Nut'],['clutch guide','CVT','Clutch Guide'],['clutch bell|azoo.*bell','CVT','Clutch Bell'],['clutch (assembly|assy)','CVT','Clutch Assembly'],['clutch shoe','CVT','Clutch Shoe'],['center spring','CVT','Center Spring'],['clutch spring','CVT','Clutch Spring'],['roller ball','CVT','Roller Ball'],['torque drive','CVT','Torque Drive'],['\bbelt\b','CVT','Drive Belt'],['cvt set','CVT','CVT Set'],
  ['af bung','Performance Parts','Bung Sensor'],['brake shoe','Performance Parts','Brake Shoe'],['disc pad','Performance Parts','Disc Pad'],['fuel filter','Performance Parts','Fuel Filter'],['air filter','Performance Parts','Air Filter'],['starter','Performance Parts','Starter Motor'],['heat guard','Performance Parts','Heat Guard'],['paower box|power box','Performance Parts','Performance Kit'],['redlak','Performance Parts','Redlak Canister'],['stainless.*button bolt','Accessories','Fasteners']
 ];
 foreach($rules as [$pattern,$m,$s])if(preg_match('~'.$pattern.'~i',$name))return [$m,$s,'Product description: '.$s];
 // A precise legacy tag is safe where the name does not identify a finer component.
 foreach($tree as $m=>$subs)if(in_array($sub,$subs,true))return [$m,$sub,'Existing subcategory'];
 if($sub==='Oil / Lubricant')return ['Oils','Oils','Existing oil tag'];
 if($sub==='Cleaner / Chemical')return ['Liquid Essentials','Liquid Essentials','Existing chemical tag'];
 return [null,null,'Needs manual review'];
}
function category_assignment_plan($rows){
 $plan=[];foreach($rows as $p){[$main,$sub,$reason]=category_assignment_for($p);$plan[]=['id'=>(int)$p['id'],'sku'=>$p['master_sku'],'name'=>$p['name'],'old_main'=>$p['main_category']??null,'old_sub'=>$p['category'],'main'=>$main,'sub'=>$sub,'reason'=>$reason];}return $plan;
}
function category_assignment_digest($rows){return hash('sha256',json_encode(array_map(fn($p)=>[$p['id'],$p['master_sku'],$p['name'],$p['main_category']??null,$p['category']],$rows)));}
function apply_category_assignments($digest){
 $pdo=db();$pdo->beginTransaction();try{
  $rows=$pdo->query('SELECT * FROM products ORDER BY id FOR UPDATE')->fetchAll();
  if(!hash_equals(category_assignment_digest($rows),$digest))throw new Exception('Products changed since preview. Reload and review the current assignments.');
  $plan=category_assignment_plan($rows);$changed=0;
  foreach($plan as $r){if(!$r['main']||!$r['sub'])throw new Exception('Some products need manual review. No assignments were changed.');product_category_selection($r['main'],$r['sub']);}
  $update=$pdo->prepare('UPDATE products SET main_category=?,category=? WHERE id=?');
  foreach($plan as $r){if($r['main']===$r['old_main']&&$r['sub']===$r['old_sub'])continue;$update->execute([$r['main'],$r['sub'],$r['id']]);audit('Assign product category','Product',(string)$r['id'],json_encode(['before'=>[$r['old_main'],$r['old_sub']],'after'=>[$r['main'],$r['sub']]]));$changed++;}
  $pdo->commit();return $changed;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

