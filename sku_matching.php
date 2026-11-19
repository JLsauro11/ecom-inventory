<?php
// SKU values determine initial matches. Registered order identity preserves verified components.
function sm_value($r,$platform){$sku=trim((string)($r['sku']??''));return $platform==='Shopee'&&$sku===''?trim((string)($r['parent_sku']??'')):$sku;}
function sm_field($r,$platform){return $platform==='Shopee'?((trim((string)($r['sku']??''))==='')?'Parent SKU Reference No.':'SKU Reference No.'):'Seller SKU';}
function sm_variant($r){return norm($r['variation']??'');}
function sm_source_group($platform,$line){$id=in_array($platform,['TikTok','Website','Lazada'],true)?trim((string)($line['external']??'')):'';$sku=sm_value($line,$platform);return $id!==''?hash('sha256',json_encode(['platform_sku',$id,sm_variant($line)])):($sku!==''?hash('sha256',json_encode(['seller_sku',norm($sku),sm_variant($line)])):$line['key']);}
function sm_identity_match($platform,$a,$b){
 $av=sm_variant($a);$bv=sm_variant($b);if($av!==$bv)return false;
 if(in_array($platform,['TikTok','Website','Lazada'],true)){$aid=trim((string)($a['external']??''));$bid=trim((string)($b['external']??''));if(in_array($platform,['TikTok','Website'],true)&&($aid!==''||$bid!==''))return $aid!==''&&$bid!==''&&$aid===$bid;if($aid!==''&&$bid!=='')return $aid===$bid;}
 $as=sm_value($a,$platform);$bs=sm_value($b,$platform);return $as!==''&&$bs!==''&&norm($as)===norm($bs);
}
function sm_find_saved_line($o,$line,$stored){
 foreach($stored as $saved)if((string)$saved['line_key']===(string)$line['key'])return $saved;
 $matches=[];foreach($stored as $saved){$src=json_decode($saved['source_json'],true)?:[];if(sm_identity_match($o['platform'],$src,$line))$matches[]=$saved;}
 if(count($matches)>1)throw new Exception('Ambiguous SKU identity in this registered order. Review its original variant and item lines.');if($matches)return $matches[0];
 if(or_booking($o)){foreach($stored as $saved){$src=json_decode($saved['source_json'],true)?:[];if(norm($src['name']??'')!==norm($line['name']??'')||sm_variant($src)!==sm_variant($line))continue;$conflict=false;foreach(['sku','external'] as $field)if(!empty($src[$field])&&!empty($line[$field])&&norm($src[$field])!==norm($line[$field]))$conflict=true;if(!$conflict)$matches[]=$saved;}if(count($matches)===1)return $matches[0];}
 return null;
}
function sm_bind_status_lines($o,$lines,$stored){
 $out=[];foreach($lines as $line){$saved=sm_find_saved_line($o,$line,$stored);if($saved){$line['uploaded_line_key']=$line['key'];$line['key']=$saved['line_key'];if(!isset($line['components']))$line['components']=om_component_check(json_decode($saved['components_json'],true),true);}
  $key=$line['key'];if(!isset($out[$key])){$out[$key]=$line;continue;}$old=$out[$key];if(isset($old['components'],$line['components'])&&$old['components']!==$line['components'])throw new Exception('Ambiguous SKU identity: source lines resolve to different bundle components.');$out[$key]['qty']+=(int)$line['qty'];$out[$key]['item_ids']=array_merge($old['item_ids']??[],$line['item_ids']??[]);$out[$key]['tracking_numbers']=array_values(array_unique(array_merge(or_line_tracking([$old]),or_line_tracking([$line]))));$out[$key]['source_date_rows']=array_merge($old['source_date_rows']??[['created'=>$old['created'],'ship_at'=>$old['ship_at']??null,'delivered_at'=>$old['delivered_at']??null]],$line['source_date_rows']??[['created'=>$line['created'],'ship_at'=>$line['ship_at']??null,'delivered_at'=>$line['delivered_at']??null]]);
 }return array_values($out);
}
function sm_saved_mapping($platform,$shop,$line,$allowInactive){
 $cacheKey=$platform.'|'.$shop;if(!isset($GLOBALS['SM_MAPPING_ROWS'][$cacheKey]))$GLOBALS['SM_MAPPING_ROWS'][$cacheKey]=om_query('SELECT source_json,components_json FROM om_mappings WHERE platform=? AND shop=?',[$platform,$shop])->fetchAll();$variants=[];$verified=[];foreach($GLOBALS['SM_MAPPING_ROWS'][$cacheKey] as $saved){$src=json_decode($saved['source_json'],true)?:[];if(!sm_identity_match($platform,$src,$line))continue;$components=json_decode($saved['components_json'],true);if(!is_array($components)||!$components)continue;usort($components,fn($a,$b)=>(int)$a['product_id']<=>(int)$b['product_id']);if(!empty($src['sku_identity_version']))$verified[json_encode($components)]=$components;else $variants[json_encode($components)]=$components;}$variants=$verified?:$variants;
 if(count($variants)>1)throw new Exception('Ambiguous SKU identity: saved mappings disagree. Select the matching product or bundle components for this order.');return $variants?om_component_check(reset($variants),$allowInactive):null;
}
function sm_components($platform,$shop,$line,$allowInactive=false){
 if(isset($line['components']))return om_component_check($line['components'],$allowInactive);
 if($saved=sm_saved_mapping($platform,$shop,$line,$allowInactive))return $saved;
 $sku=sm_value($line,$platform);if($sku==='')throw new Exception('Select the matching product or bundle components. '.sm_field($line,$platform).' is blank; product title is not used to guess the SKU.');
 if($bundle=bundle_mapping_get($platform,$sku))return om_component_check($bundle,$allowInactive);
 $ids=om_query('SELECT id FROM products WHERE '.($allowInactive?'':'active=1 AND ').'(master_sku=? OR normalized_name=?)',[$sku,norm($sku)])->fetchAll(PDO::FETCH_COLUMN);
 $ids=array_merge($ids,om_query('SELECT product_id FROM product_code_aliases WHERE alias_code=?',[$sku])->fetchAll(PDO::FETCH_COLUMN));
 $ids=array_merge($ids,om_query('SELECT product_id FROM platform_aliases WHERE platform=? AND normalized_alias=?',[$platform,norm($sku)])->fetchAll(PDO::FETCH_COLUMN));
 // A platform SKU ID alias is an already verified mapping, never an approximate title match.
 if(in_array($platform,['TikTok','Website'],true)&&!empty($line['external']))$ids=array_merge($ids,om_query('SELECT product_id FROM platform_aliases WHERE platform=? AND external_id=?',[$platform,$line['external']])->fetchAll(PDO::FETCH_COLUMN));
 $ids=array_values(array_unique(array_map('intval',$ids)));if(count($ids)>1)throw new Exception('Ambiguous SKU identity. Select the matching product or bundle components.');
 if(!$ids)throw new Exception('Select the matching product or bundle components. '.sm_field($line,$platform).' has no exact system match; truncated Seller SKU values require manual mapping.');
 return om_component_check([['product_id'=>$ids[0],'per_bundle'=>1]],$allowInactive);
}
function sm_remember_confirmed($platform,$shop,$lines){
 foreach($lines as $line){if(empty($line['components'])||sm_value($line,$platform)==='')continue;if(in_array($platform,['TikTok','Website'],true)&&empty($line['external']))continue;
  $components=$line['components'];usort($components,fn($a,$b)=>(int)$a['product_id']<=>(int)$b['product_id']);$source=$line;$source['sku_identity_version']=1;unset($source['components']);$platformId=in_array($platform,['TikTok','Website','Lazada'],true)?trim((string)($line['external']??'')):'';$identity=[$platform,$shop,$platformId!==''?['platform_sku',$platformId]:['seller_sku',norm(sm_value($line,$platform))],sm_variant($line)];$key=hash('sha256','confirmed-sku|'.json_encode($identity));
  $sql=!empty($line['mapping_is_manual'])?'INSERT INTO om_mappings(mapping_key,platform,shop,source_json,components_json,saved_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_json=VALUES(source_json),components_json=VALUES(components_json),saved_by=VALUES(saved_by)':'INSERT IGNORE INTO om_mappings(mapping_key,platform,shop,source_json,components_json,saved_by) VALUES(?,?,?,?,?,?)';om_query($sql,[$key,$platform,$shop,json_encode($source),json_encode($components),current_user()['id']]);
 }unset($GLOBALS['SM_MAPPING_ROWS'][$platform.'|'.$shop]);
}
