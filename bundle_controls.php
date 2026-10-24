<?php
function bundle_mapping_key($platform,$item){return 'bundle_v1_'.hash('sha256',strtolower($platform).'|'.norm($item));}
function bundle_components_validate($input){
 if(!is_array($input)||!$input||count($input)>50)throw new Exception('Enter between 1 and 50 component items.');$out=[];$seen=[];
 foreach($input as $x){if(!is_array($x))throw new Exception('Invalid bundle item.');$p=active_product($x['product_id']??0);$id=(int)$p['id'];if(isset($seen[$id]))throw new Exception('Duplicate bundle product. Combine its quantity on one item line.');$seen[$id]=true;$out[]=['product_id'=>$id,'per_bundle'=>whole_number($x['per_bundle']??'','Quantity',1)];}return $out;
}
function bundle_mapping_get($platform,$item){$v=json_decode(setting(bundle_mapping_key($platform,$item),''),true);return is_array($v)?($v['components']??null):null;}
function bundle_mapping_save($platform,$item,$components){$components=bundle_components_validate($components);$key=bundle_mapping_key($platform,$item);$old=setting($key,'');$value=['platform'=>$platform,'description'=>$item,'components'=>$components,'saved_by'=>current_user()['id'],'saved_at'=>date('Y-m-d H:i:s')];set_setting($key,json_encode($value));audit('Save bundle mapping','Bundle',$key,json_encode(['before'=>json_decode($old,true),'after'=>$value],JSON_UNESCAPED_UNICODE));}
function bundle_review_components($platform,$r){
 $raw=$r['components']??null;if($raw===null&&empty($r['product_id']))$raw=bundle_mapping_get($platform,$r['item']);
 if($raw!==null)return bundle_components_validate($raw);
 $id=(int)($r['product_id']??0);if(!$id)$id=(int)find_product_mapping($platform,$r['item'],$r['external_id']??'');
 if(!$id)throw new Exception('Unmapped: select the matching Product Master item or bundle components.');return bundle_components_validate([['product_id'=>$id,'per_bundle'=>1]]);
}
function bundle_editor($r){
 $components=$r['components']??[['product_id'=>$r['product_id']??0,'per_bundle'=>1]];$out='<div data-bundle-editor><p class="small muted">For each uploaded unit, enter the number of pieces included for each product. Include free items.</p><div data-bundle-lines>';
 foreach($components as $i=>$x)$out.='<div class="card stack" data-bundle-line><label>Product</label>'.picker('components['.$i.'][product_id]','Search product or SKU...',true,$x['product_id'],false).'<label>Quantity<input type="number" min="1" step="1" name="components['.$i.'][per_bundle]" value="'.(int)$x['per_bundle'].'" required></label>'.($i>0?'<button type="button" class="btn ghost sm" data-remove-line>Remove Item</button>':'').'</div>';
 return $out.'</div><button type="button" class="btn ghost sm" data-add-bundle>+ Add Item Line</button><label class="ack"><input type="checkbox" name="save_bundle" value="1"> Save as Bundle Mapping for this platform and description (replaces its previous mapping)</label></div>';
}
