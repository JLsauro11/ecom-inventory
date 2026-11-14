<?php
// Fresh labels are resolved by stable product ID / Master SKU, never by guessed names.
function export_product_catalog(){
 $byId=[];$codes=[];foreach(db()->query('SELECT id,master_sku,name,brand,main_category,category,variant,active FROM products') as $p){$byId[(int)$p['id']]=$p;$codes[norm($p['master_sku'])][(int)$p['id']]=true;}
 foreach(db()->query('SELECT product_id,alias_code FROM product_code_aliases') as $a)if(isset($byId[(int)$a['product_id']]))$codes[norm($a['alias_code'])][(int)$a['product_id']]=true;
 $byCode=[];foreach($codes as $code=>$ids)if(count($ids)===1)$byCode[$code]=$byId[(int)array_key_first($ids)];return [$byId,$byCode];
}
function export_current_product_details($headers,$rows,$metadata=[]){
 $keys=array_map(fn($h)=>norm((string)$h),$headers);$find=function($labels)use($keys){foreach($labels as $label){$i=array_search($label,$keys,true);if($i!==false)return $i;}return null;};
 $sku=$find(['master sku','item code']);$id=$find(['product id']);$description=$find(['product description','product name','product','item description','description','item']);
 if($sku===null&&$id===null)return [$headers,$rows,$metadata];
 [$byId,$byCode]=export_product_catalog();$fields=[];if($description!==null)$fields[$description]='name';
 foreach(['brand'=>'brand','main category'=>'main_category','subcategory'=>'category','category'=>'category','variant / specification'=>'variant','variant'=>'variant'] as $label=>$field){$i=array_search($label,$keys,true);if($i!==false)$fields[$i]=$field;}
 $original=[];$currentCodes=[];$resolved=[];$changedCols=[];$hasNewCode=false;
 foreach($rows as $n=>$row){$p=null;if($id!==null&&isset($byId[(int)($row[$id]??0)]))$p=$byId[(int)$row[$id]];elseif($sku!==null)$p=$byCode[norm((string)($row[$sku]??''))]??null;if(!$p)continue;$resolved[$n]=$p;
  foreach($fields as $col=>$field){$value=$p[$field]??'';if($field==='brand'&&function_exists('inventory_filter_brand'))$value=inventory_filter_brand($value);if($field==='main_category'&&$value==='')$value='Unassigned';if((string)($row[$col]??'')!==(string)$value){$original[$n][$col]=$row[$col]??'';$changedCols[$col]=true;$rows[$n][$col]=$value;}}
  if($sku!==null&&trim((string)($row[$sku]??''))!==$p['master_sku']){$currentCodes[$n]=$p['master_sku'];$hasNewCode=true;}
 }
 if(!$resolved)return [$headers,$rows,$metadata];
 // Existing SKU and quantity columns retain their positions for count re-upload.
 if($description===null){$headers[]='Product Description';foreach($rows as $n=>&$row)$row[]=$resolved[$n]['name']??'';unset($row);}
 foreach(array_keys($changedCols) as $col){$headers[]='Recorded '.$headers[$col];foreach($rows as $n=>&$row)$row[]=$original[$n][$col]??($row[$col]??'');unset($row);}
 if($hasNewCode){$headers[]='Current Master SKU';foreach($rows as $n=>&$row)$row[]=$resolved[$n]['master_sku']??'';unset($row);}
 $metadata['Product Details']='Current Product Master at download time. Original SKU, quantities and recorded labels are preserved.';return [$headers,$rows,$metadata];
}
function beginning_template_write($path){
 $rows=[];foreach(db()->query('SELECT master_sku,name,brand,category FROM products WHERE active=1 ORDER BY brand,name,id') as $p)$rows[]=[$p['master_sku'],$p['name'],inventory_filter_brand($p['brand']),$p['category'],''];
 report_write_xlsx($path,'Beginning Balance',['Master SKU','Item','Brand','Category','Quantity'],$rows,['Generated'=>date(DATE_ATOM),'Instructions'=>'Current active products. Blank Quantity is skipped and preserves the existing balance. Enter 0 to set zero. Upload and review before submission.'],[4]);
}
function template_product_reference($source,$dest){
 if(!is_file($source)||!copy($source,$dest))throw new Exception('Template unavailable.');$z=new ZipArchive();if($z->open($dest)!==true)throw new Exception('Cannot open template.');
 try{
  $wb=XlsxReader::xml($z->getFromName('xl/workbook.xml'))->document;$rels=XlsxReader::xml($z->getFromName('xl/_rels/workbook.xml.rels'))->document;$types=XlsxReader::xml($z->getFromName('[Content_Types].xml'))->document;
  $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$relNs='http://schemas.openxmlformats.org/package/2006/relationships';$rNs='http://schemas.openxmlformats.org/officeDocument/2006/relationships';$ctNs='http://schemas.openxmlformats.org/package/2006/content-types';$max=0;
  foreach($wb->getElementsByTagNameNS($ns,'sheet') as $sheet){$max=max($max,(int)$sheet->getAttribute('sheetId'));if($sheet->getAttribute('name')==='Current Products')throw new Exception('Template already contains a Current Products sheet.');}
  $sheet=$wb->createElementNS($ns,'sheet');$sheet->setAttribute('name','Current Products');$sheet->setAttribute('sheetId',(string)($max+1));$sheet->setAttributeNS($rNs,'r:id','rIdRS8CurrentProducts');$wb->getElementsByTagNameNS($ns,'sheets')->item(0)->appendChild($sheet);
  $rel=$rels->createElementNS($relNs,'Relationship');$rel->setAttribute('Id','rIdRS8CurrentProducts');$rel->setAttribute('Type',$rNs.'/worksheet');$rel->setAttribute('Target','worksheets/rs8_current_products.xml');$rels->documentElement->appendChild($rel);
  $type=$types->createElementNS($ctNs,'Override');$type->setAttribute('PartName','/xl/worksheets/rs8_current_products.xml');$type->setAttribute('ContentType','application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');$types->documentElement->appendChild($type);
  $rows=[['Master SKU','Product Description','Brand','Main Category','Subcategory']];foreach(db()->query('SELECT master_sku,name,brand,main_category,category FROM products WHERE active=1 ORDER BY brand,name,id') as $p)$rows[]=[$p['master_sku'],$p['name'],inventory_filter_brand($p['brand']),$p['main_category']?:'Unassigned',$p['category']];
  $xml='<worksheet xmlns="'.$ns.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="25" customWidth="1"/><col min="2" max="2" width="65" customWidth="1"/><col min="3" max="5" width="25" customWidth="1"/></cols><sheetData>';
  foreach($rows as $n=>$row){$rn=$n+1;$xml.='<row r="'.$rn.'">';foreach($row as $col=>$value)$xml.='<c r="'.XlsxReader::colLetters($col).$rn.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';$xml.='</row>';}$xml.='</sheetData><autoFilter ref="A1:E'.count($rows).'"/></worksheet>';
  foreach(['xl/workbook.xml'=>$wb->saveXML(),'xl/_rels/workbook.xml.rels'=>$rels->saveXML(),'[Content_Types].xml'=>$types->saveXML(),'xl/worksheets/rs8_current_products.xml'=>$xml] as $name=>$content)if(!$z->addFromString($name,$content))throw new Exception('Cannot update template product reference.');
 }finally{$z->close();}
}
function return_template_write($path){$base=tempnam(sys_get_temp_dir(),'return-base-');try{report_write_xlsx($base,'Order Returns',['Date','Product Description / SKU','Quantity','Platform','Order ID','Item Condition','Receipt Reference','Remarks'],[],['Instructions'=>'Enter actual received quantities. Receipt Reference is required per physical parcel, shared across its product lines. For a return whose original order is absent, use Manual Inflow > Original Order Not in System.']);template_product_reference($base,$path);}finally{if(is_file($base))unlink($base);}}
function stream_current_template($type){
 $names=['beginning_template'=>'Beginning_Balance_Template.xlsx','return_template'=>'Order_Return_Template.xlsx','supplier_inflow_template'=>'Supplier_Inflow_Template.xlsx','order_template'=>'Standard_Order_Upload_Template.xlsx'];if(!isset($names[$type]))throw new Exception('Unknown template.');
 $path=tempnam(sys_get_temp_dir(),'rs8-current-');if($path===false)throw new Exception('Cannot prepare template.');try{if($type==='beginning_template')beginning_template_write($path);elseif($type==='return_template')return_template_write($path);else template_product_reference(__DIR__.'/templates/'.$names[$type],$path);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$names[$type].'"');header('Cache-Control: private, no-store, no-cache, max-age=0');header('Pragma: no-cache');readfile($path);}finally{if(is_file($path))unlink($path);}exit;
}
