<?php
function complete_update_ready(){return setting('complete_update_version','')==='5.3';}
function migrate_complete_controls(){
 $pdo=db();if(setting('managed_categories_seeded','')!=='5.3'){
 foreach(default_product_category_tree() as $main=>$subs)foreach($subs?:[''] as $sub)$pdo->prepare('INSERT IGNORE INTO managed_categories(main_name,sub_name,active) VALUES(?,?,1)')->execute([$main,$sub]);
 foreach($pdo->query('SELECT DISTINCT main_category,category FROM products WHERE main_category IS NOT NULL AND main_category<>\'\'') as $p)$pdo->prepare('INSERT IGNORE INTO managed_categories(main_name,sub_name,active) VALUES(?,?,1)')->execute([$p['main_category'],$p['category']===$p['main_category']?'':$p['category']]);
 set_setting('managed_categories_seeded','5.3');}
 foreach($pdo->query('SELECT main_name,sub_name FROM managed_categories ORDER BY id') as $c)category_sequence($c['main_name'],$c['sub_name']);
 set_setting('complete_update_version','5.3');
}
function category_key($main,$sub){return hash('sha256',trim($main).'|'.trim($sub));}
function category_sequence($main,$sub){
 $key=category_key($main,$sub);$q=db()->prepare('SELECT * FROM category_sequences WHERE category_key=?');$q->execute([$key]);if($row=$q->fetch())return $row;
 $names=['Clutch Bell'=>'CB','Pulley Set'=>'PS','Clutch Assembly'=>'CA','Clutch Shoe'=>'CS','Center Spring'=>'CSP','Roller Ball'=>'RB','Torque Drive'=>'TD','Clutch Spring'=>'CLS','Drive Belt'=>'BELT','Block Kit'=>'BK','Head Gasket'=>'HG','Piston Kit'=>'PK','Oils'=>'OIL','Liquid Essentials'=>'LIQ'];$label=$sub?:$main;$base=$names[$label]??strtoupper(implode('',array_map(fn($w)=>$w[0]??'',preg_split('/[^A-Za-z0-9]+/',$label,-1,PREG_SPLIT_NO_EMPTY))));$base=substr($base?:'PRD',0,12);$prefix=$base;$n=1;
 while(true){$q=db()->prepare('SELECT category_key FROM category_sequences WHERE prefix=?');$q->execute([$prefix]);if(!$q->fetchColumn())break;$prefix=$base.(++$n);}
 $next=1;$q=db()->prepare('SELECT master_sku FROM products WHERE master_sku LIKE ?');$q->execute([$prefix.'-%']);foreach($q as $r)if(preg_match('/^'.preg_quote($prefix,'/').'-(\d+)$/D',$r['master_sku'],$m))$next=max($next,(int)$m[1]+1);
 db()->prepare('INSERT INTO category_sequences(prefix,category_key,next_number) VALUES(?,?,?)')->execute([$prefix,$key,$next]);return ['prefix'=>$prefix,'category_key'=>$key,'next_number'=>$next];
}
function category_next_sku($main,$sub,$reserve=false){
 [$main,$sub]=product_category_selection($main,$sub);$sub=$sub===$main?'':$sub;$row=category_sequence($main,$sub);
 $q=db()->prepare('SELECT * FROM category_sequences WHERE category_key=?'.($reserve?' FOR UPDATE':''));$q->execute([category_key($main,$sub)]);$row=$q->fetch();$n=(int)$row['next_number'];
 do{$sku=$row['prefix'].'-'.str_pad((string)$n,4,'0',STR_PAD_LEFT);$q=db()->prepare('SELECT COUNT(*) FROM products WHERE master_sku=?');$q->execute([$sku]);$exists=(int)$q->fetchColumn();if(!$exists&&item_codes_ready()){$reserved=db()->prepare('SELECT COUNT(*) FROM product_code_aliases WHERE alias_code=?');$reserved->execute([norm($sku)]);$exists=(int)$reserved->fetchColumn();}if($exists)$n++;}while($exists);
 if($reserve){if(!db()->inTransaction())throw new Exception('Product creation must be transactional.');db()->prepare('UPDATE category_sequences SET next_number=? WHERE category_key=?')->execute([$n+1,$row['category_key']]);}return $sku;
}
function product_identity_check($name,$variant,$exclude=0){
 if(trim($name)===''||strlen($name)>255||strlen($variant)>150)throw new Exception('Enter a product description up to 255 characters and variant up to 150.');
 $q=db()->prepare('SELECT id,master_sku,name,variant FROM products WHERE normalized_name=? AND id<>?');$q->execute([norm($name),$exclude]);foreach($q as $p)if(norm($p['variant']??'')===norm($variant))throw new Exception('Duplicate product: '.$p['master_sku'].' — '.$p['name'].'. Use the existing Product Master record.');
 $similar=[];foreach(product_options() as $p){if((int)$p['id']===$exclude)continue;$a=norm($name);$b=norm($p['name']);similar_text($a,$b,$score);if($score>=85)$similar[]=$p['master_sku'].' — '.$p['name'];if(count($similar)>=5)break;}return $similar;
}
function category_manage($input){
 if(current_user()['role']!=='Admin')throw new Exception('Admin access required.');$main=trim($input['main_name']??'');$sub=trim($input['sub_name']??'');if($main===''||strlen($main)>100||strlen($sub)>100)throw new Exception('Main category is required, up to 100 characters.');$id=(int)($input['category_id']??0);$active=empty($input['active'])?0:1;
 process_transaction(function()use($main,$sub,$id,$active){if($id){$q=db()->prepare('SELECT * FROM managed_categories WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new Exception('Category not found.');if(($old['main_name']!==$main||$old['sub_name']!==$sub)||!$active){$q=db()->prepare('SELECT COUNT(*) FROM products WHERE main_category=? AND category=?');$q->execute([$old['main_name'],$old['sub_name']?:$old['main_name']]);if($q->fetchColumn())throw new Exception('This category has assigned products. Reassign them first; existing records and SKUs are preserved.');}db()->prepare('UPDATE managed_categories SET main_name=?,sub_name=?,active=? WHERE id=?')->execute([$main,$sub,$active,$id]);}else db()->prepare('INSERT INTO managed_categories(main_name,sub_name,active) VALUES(?,?,?)')->execute([$main,$sub,$active]);category_sequence($main,$sub);audit('Manage category','Category',$main,$sub.'; '.($active?'Active':'Inactive'));});
}
