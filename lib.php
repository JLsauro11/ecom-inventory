<?php
require_once __DIR__.'/security_controls.php';
require_once __DIR__.'/pagination.php';
security_start_session();
$CFG=require __DIR__.'/config.php';
date_default_timezone_set($CFG['timezone']);

function db($withDb=true){ static $pdo=null; global $CFG; if($pdo && $withDb)return $pdo; $dsn='mysql:host='.$CFG['db_host'].';port='.(int)($CFG['db_port']??3306).';charset=utf8mb4'.($withDb?';dbname='.$CFG['db_name']:''); $x=new PDO($dsn,$CFG['db_user'],$CFG['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); if($withDb)$pdo=$x; return $x; }
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function norm($s){$s=mb_strtolower(trim((string)$s));$s=str_replace(['’','–','—'],["'",'-','-'],$s);$s=preg_replace('/\s+/u',' ',$s);return $s;}
function flash($msg,$type='success'){$_SESSION['flash']=[$msg,$type];}
function getFlash(){$f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f;}
function logged(){return !empty($_SESSION['uid']);}
function require_login(){if(logged()){$q=db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$_SESSION['uid']]);$r=$q->fetch();if(!$r||!(int)($r['active']??1)||($r['approval_status']??'Approved')!=='Approved'){$_SESSION=[];}else{$_SESSION['name']=$r['name'];$_SESSION['role']=$r['role'];if(!empty($r['must_change_password'])&&!in_array(basename($_SERVER['SCRIPT_NAME']??''),['account_password.php','logout.php','upgrade_v5.php'],true)){header('Location: account_password.php');exit;}}}if(!logged()){header('Location: login.php');exit;}}
function current_user(){return ['id'=>(int)($_SESSION['uid']??0),'name'=>$_SESSION['name']??'','role'=>role_label($_SESSION['role']??'')];}
function setting($k,$d=null){$st=db()->prepare('SELECT value_text FROM settings WHERE key_name=?');$st->execute([$k]);$r=$st->fetch();return $r?$r['value_text']:$d;}
function set_setting($k,$v){$st=db()->prepare('INSERT INTO settings(key_name,value_text) VALUES(?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text)');$st->execute([$k,(string)$v]);}
function audit($action,$entity='System',$ref='',$details=''){ $u=current_user();$st=db()->prepare('INSERT INTO activity_log(user_id,action_type,entity_type,entity_ref,details) VALUES(?,?,?,?,?)');$st->execute([$u['id'],$action,$entity,$ref,$details]); }
function next_ref($prefix){return $prefix.'-'.date('Ymd-His').'-'.str_pad((string)random_int(1,999),3,'0',STR_PAD_LEFT);}

require_once __DIR__.'/access_roles.php';


function store_upload($file,$subdir,$prefix='FILE'){
 if(empty($file['tmp_name']))return null;
 validate_upload_file($file,['xlsx','pdf','png','jpg','jpeg','csv']);
 $dir=__DIR__.'/uploads/'.trim($subdir,'/').'/'.date('Y/m');
 if(!is_dir($dir))mkdir($dir,0775,true);
 $ext=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));
 $safe=preg_replace('/[^A-Za-z0-9_.-]/','_',basename($file['name']??'upload'));
 $name=$prefix.'_'.date('Ymd_His').'_'.substr(hash('sha256',microtime(true).random_int(1,999999)),0,8).'_'.$safe;
 $dest=$dir.'/'.$name;
 if(!move_uploaded_file($file['tmp_name'],$dest))throw new Exception('Could not save uploaded file.');
 return str_replace(__DIR__.'/','',$dest);
}
function brand_options(){return inventory_brand_options();}
function category_options(){return db()->query("SELECT name FROM categories WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);}
function status_badge_class($status){$s=strtolower((string)$status);if(str_contains($s,'fail')||str_contains($s,'error')||$s==='unsuccessful')return 'danger';if(str_contains($s,'incomplete')||str_contains($s,'review'))return 'warn';if(str_contains($s,'success')||str_contains($s,'complete')||$s==='confirmed'||$s==='matched')return 'ok';if(str_contains($s,'fail')||str_contains($s,'error')||$s==='unsuccessful')return 'danger';if(str_contains($s,'incomplete')||str_contains($s,'review')||str_contains($s,'pending'))return 'warn';return 'info';}

function master_sku($brand,$category,$id){$a=preg_replace('/[^A-Z0-9]/','',strtoupper($brand));$b=preg_replace('/[^A-Z0-9]/','',strtoupper($category));return substr($a,0,4).'-'.substr($b,0,4).'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);}

function stock_row($pid){
 $sql="SELECT p.id,p.master_sku,p.name,p.brand,p.category,p.low_stock_threshold,
 COALESCE(b.qty,0) beginning,
 COALESCE((SELECT SUM(qty) FROM inflows i WHERE i.product_id=p.id),0) inflow,
 COALESCE((SELECT SUM(qty) FROM outflows o WHERE o.product_id=p.id),0) outflow,
 COALESCE((SELECT SUM(qty) FROM non_sellable n WHERE n.product_id=p.id AND n.sellable_deducted=1),0) condition_out,
 COALESCE((SELECT SUM(qty) FROM non_sellable n WHERE n.product_id=p.id),0)-COALESCE((SELECT SUM(qty) FROM nonsellable_outflows no WHERE no.product_id=p.id),0) nonsellable
 FROM products p LEFT JOIN beginning_balances b ON b.product_id=p.id WHERE p.id=?";
 $st=db()->prepare($sql);$st->execute([(int)$pid]);$r=$st->fetch();
 if($r){$r['current']=(int)$r['beginning']+(int)$r['inflow']-(int)$r['outflow']-(int)$r['condition_out'];$r['nonsellable']=max(0,(int)$r['nonsellable']);$r['physical']=$r['current']+$r['nonsellable'];$r['allocated']=platform_allocated_total($pid);$r['unallocated']=$r['current']-$r['allocated'];}
 return $r;
}
function detect_status($qty,$threshold=10){$threshold=$threshold===null?10:max(0,(int)$threshold);if($qty<0)return ['Reconcile','danger'];if($qty==0)return ['Out of Stock','danger'];if($qty<=$threshold)return ['Low Stock','warn'];return ['In Stock','ok'];}
function platform_allocated_total($pid){$st=db()->prepare('SELECT COALESCE(SUM(qty),0) q FROM platform_stock WHERE product_id=?');$st->execute([(int)$pid]);return (int)$st->fetchColumn();}
function platform_qty($platform,$pid){$st=db()->prepare('SELECT qty FROM platform_stock WHERE platform=? AND product_id=?');$st->execute([$platform,(int)$pid]);return (int)($st->fetchColumn()?:0);}
function set_platform_qty($platform,$pid,$qty){stock_platform($platform);$st=db()->prepare('INSERT INTO platform_stock(platform,product_id,qty) VALUES(?,?,?) ON DUPLICATE KEY UPDATE qty=VALUES(qty)');$before=platform_qty($platform,(int)$pid);$st->execute([$platform,(int)$pid,(int)$qty]);if(function_exists('record_stock_event'))record_stock_event($platform,(int)$pid,$before,(int)$qty);}
function adjust_platform_qty($platform,$pid,$delta){$cur=platform_qty($platform,$pid);$new=$cur+(int)$delta;if($new<0&&$delta<0)throw new Exception("Insufficient {$platform} allocation. Available: {$cur}.");set_platform_qty($platform,$pid,$new);return $new;}
function validate_allocation_total($pid,$newPlatformQty=null,$platform=null){$s=stock_row($pid);$total=0;foreach(['Lazada','Shopee','TikTok'] as $p){$q=platform_qty($p,$pid);if($p===$platform && $newPlatformQty!==null)$q=(int)$newPlatformQty;$total+=$q;}if($total!==$s['current'])throw new Exception('Platform allocation must equal current sellable stock. Sellable: '.$s['current'].', requested allocation total: '.$total.'.');return true;}

function product_options(){return db()->query("SELECT id,master_sku,name,brand,category,main_category,variant,COALESCE(low_stock_threshold,10) low_stock_threshold FROM products WHERE active=1 ORDER BY brand,name")->fetchAll();}
function product_json(){return json_encode(array_map(function($p){$p['brand']=inventory_filter_brand($p['brand']);return $p;},product_options()),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);}

class XlsxReader{
 public static function xml($source){if(!is_string($source)||stripos($source,'<!DOCTYPE')!==false||stripos($source,'<!ENTITY')!==false)throw new Exception('Workbook XML is invalid or contains unsupported entities.');$d=new DOMDocument();$previous=libxml_use_internal_errors(true);try{if(!$d->loadXML($source,LIBXML_NONET))throw new Exception('Workbook XML could not be read.');}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}return new DOMXPath($d);}
 public static function rows($path,$sheetName=null){
  validate_xlsx_container($path);$z=new ZipArchive();if($z->open($path)!==true)throw new Exception('Cannot open XLSX file.');
  try{$shared=[];$sx=$z->getFromName('xl/sharedStrings.xml');if($sx){$x=self::xml($sx);foreach($x->query('//*[local-name()="si"]') as $si){$text='';foreach($x->query('.//*[local-name()="t"]',$si) as $t)$text.=$t->textContent;$shared[]=$text;}}
   $wb=self::xml($z->getFromName('xl/workbook.xml'));$rels=self::xml($z->getFromName('xl/_rels/workbook.xml.rels'));$map=[];foreach($rels->query('//*[local-name()="Relationship"]') as $r)if($r->getAttribute('TargetMode')!=='External')$map[$r->getAttribute('Id')]=$r->getAttribute('Target');
   $sheetXml=null;foreach($wb->query('//*[local-name()="sheets"]/*[local-name()="sheet"]') as $sheet){if($sheetName&&strcasecmp($sheet->getAttribute('name'),$sheetName)!==0)continue;$rid='';foreach($sheet->attributes as $a)if($a->localName==='id')$rid=$a->value;$target=ltrim($map[$rid]??'','/');if(str_contains($target,'..')||str_contains($target,':'))throw new Exception('Unsupported worksheet path.');if(!str_starts_with($target,'xl/'))$target='xl/'.$target;$sheetXml=$z->getFromName($target);if($sheetXml)break;}
   if(!$sheetXml)throw new Exception('Worksheet not found.');$xml=self::xml($sheetXml);$out=[];$seen=0;$cells=0;
   foreach($xml->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){if(++$seen>10000)throw new Exception('Limit each workbook to 10,000 rows.');$arr=[];foreach($xml->query('./*[local-name()="c"]',$row) as $c){if(++$cells>1000000)throw new Exception('Workbook contains too many cells.');if(!preg_match('/^([A-Z]+)[1-9][0-9]*$/D',$c->getAttribute('r'),$m))throw new Exception('Invalid workbook cell reference.');$col=self::colIndex($m[1]);if($col>255)throw new Exception('Limit upload sheets to 256 columns.');$type=$c->getAttribute('t');$value='';if($type==='inlineStr'){foreach($xml->query('.//*[local-name()="t"]',$c) as $t)$value.=$t->textContent;}else{$v=$xml->query('./*[local-name()="v"]',$c)->item(0);$value=$v?$v->textContent:'';if($type==='s')$value=$shared[(int)$value]??'';}$arr[$col]=$value;}if($arr){$dense=[];for($i=0;$i<=max(array_keys($arr));$i++)$dense[]=$arr[$i]??'';$out[]=$dense;}}
   return $out;
  }finally{$z->close();}
 }
 static function colIndex($letters){$n=0;foreach(str_split($letters) as $ch)$n=$n*26+(ord($ch)-64);return $n-1;}
 static function colLetters($index){$index++;$s='';while($index>0){$index--;$s=chr(65+($index%26)).$s;$index=intdiv($index,26);}return $s;}
}
function excel_date($v){if(is_numeric($v)&&(float)$v>30000){$unix=((float)$v-25569)*86400;return gmdate('Y-m-d',(int)$unix);}$t=strtotime((string)$v);return $t?date('Y-m-d',$t):date('Y-m-d');}
function find_header_row($rows,$requiredCandidates){foreach($rows as $ri=>$row){$n=array_map('norm',$row);foreach($requiredCandidates as $candidate){$ok=true;foreach($candidate as $needle){if(!in_array(norm($needle),$n,true)){$ok=false;break;}}if($ok)return $ri;}}return null;}
function find_product_mapping($platform,$source,$externalId=null){if($mapped=product_code_lookup($source))return $mapped;$pdo=db();if($externalId){$st=$pdo->prepare('SELECT product_id FROM platform_aliases WHERE platform=? AND external_id=? LIMIT 1');$st->execute([$platform,(string)$externalId]);if($r=$st->fetch())return (int)$r['product_id'];}$n=norm($source);$st=$pdo->prepare('SELECT product_id FROM platform_aliases WHERE platform=? AND normalized_alias=?');$st->execute([$platform,$n]);if($r=$st->fetch())return (int)$r['product_id'];$st=$pdo->prepare('SELECT id FROM products WHERE master_sku=? AND active=1');$st->execute([trim((string)$source)]);if($r=$st->fetch())return (int)$r['id'];$st=$pdo->prepare('SELECT id FROM products WHERE normalized_name=? AND active=1');$st->execute([$n]);if($r=$st->fetch())return (int)$r['id'];return null;}

function parse_platform_order_file($platform,$path){$rows=XlsxReader::rows($path);if(!$rows)return [];$headerRow=find_header_row($rows,$platform==='Lazada'?[['date','ordernumber','sellersku']]:[['date','order id','quantity']]);if($headerRow===null)$headerRow=0;$h=array_map('norm',$rows[$headerRow]);$idx=[];foreach($h as $i=>$x)$idx[$x]=$i;$out=[];
 foreach(array_slice($rows,$headerRow+1) as $r){if(!array_filter($r,fn($x)=>$x!==''))continue;
  if($platform==='Shopee'){$date=excel_date($r[$idx['date']]??'');$order=trim($r[$idx['order id']]??'');$item=trim($r[$idx['sku reference no.']]??($r[$idx['variation name']]??''));$qty=(int)($r[$idx['quantity']]??0);$external=trim($r[$idx['sku reference no.']]??'');}
  elseif($platform==='TikTok'){$date=excel_date($r[$idx['date']]??'');$order=trim($r[$idx['order id']]??'');$item=trim($r[$idx['seller sku']]??'');$qty=(int)($r[$idx['quantity']]??0);$external=trim($r[$idx['seller sku']]??'');}
  else{$date=excel_date($r[$idx['date']]??'');$order=trim($r[$idx['ordernumber']]??'');$item=trim($r[$idx['sellersku']]??'');$qty=1;$external=trim($r[$idx['sellersku']]??'');}
  if(!$order||!$item||$qty<=0)continue;$key=$date.'|'.$order.'|'.norm($item);if(!isset($out[$key]))$out[$key]=['date'=>$date,'order'=>$order,'item'=>$item,'external_id'=>$external,'qty'=>0];$out[$key]['qty']+=$qty;
 }
 return array_values($out);
}

function platform_inventory_layout($platform,$path){$rows=XlsxReader::rows($path);$headerRow=null;$qtyHeader='';$itemHeader='';$externalHeader='';
 if($platform==='Lazada'){$headerRow=find_header_row($rows,[['product id','sellersku'],['product id','seller sku']]);$qtyHeaderPrefix='rs8 warehouse';$itemHeader='sellersku';$externalHeader='sku.skuid';}
 elseif($platform==='TikTok'){$headerRow=find_header_row($rows,[['product id','sku id','quantity'],['product id','seller sku']]);$qtyHeader='quantity';$itemHeader='seller sku';$externalHeader='sku id';}
 else{$headerRow=find_header_row($rows,[['product id','product name','stock'],['product id','sku','stock']]);$qtyHeader='stock';$itemHeader='sku';$externalHeader='variation id';}
 if($headerRow===null)throw new Exception('Could not detect platform inventory header.');$h=array_map('norm',$rows[$headerRow]);$idx=[];foreach($h as $i=>$x)$idx[$x]=$i;
 if($platform==='Lazada'){foreach($h as $i=>$x)if(str_starts_with($x,$qtyHeaderPrefix)){$qtyIndex=$i;$qtyHeader=$rows[$headerRow][$i];break;}if(!isset($qtyIndex))throw new Exception('Lazada warehouse stock column not found.');}else{$qtyIndex=$idx[$qtyHeader]??null;if($qtyIndex===null)throw new Exception('Stock quantity column not found.');}
 $itemIndex=$idx[$itemHeader]??null;$externalIndex=$idx[$externalHeader]??null;if($itemIndex===null)throw new Exception('Platform SKU column not found.');return compact('rows','headerRow','qtyIndex','itemIndex','externalIndex','qtyHeader');
}

function generate_platform_inventory_file($platform,$sourcePath,$destPath){$layout=platform_inventory_layout($platform,$sourcePath);$rows=$layout['rows'];$headerRow=$layout['headerRow'];$changes=[];$unmapped=[];
 foreach(array_slice($rows,$headerRow+1,null,true) as $ri=>$row){$item=trim($row[$layout['itemIndex']]??'');if($item==='')continue;$external=$layout['externalIndex']!==null?trim($row[$layout['externalIndex']]??''):null;$pid=find_product_mapping($platform,$item,$external);if(!$pid){$unmapped[]=['row'=>$ri+1,'item'=>$item,'external'=>$external];continue;}$qty=platform_qty($platform,$pid);$oldQty=(int)($row[$layout['qtyIndex']]??0);$changes[$ri+1]=['qty'=>$qty,'previous_qty'=>$oldQty,'product_id'=>$pid,'item'=>$item,'external'=>$external];}
 copy($sourcePath,$destPath);xlsx_set_numeric_cells($destPath,$platform,$layout['headerRow']+1,$layout['qtyIndex'],$changes);return ['changes'=>$changes,'unmapped'=>$unmapped];
}
function xlsx_set_numeric_cells($path,$platform,$headerExcelRow,$qtyIndex,$changes){$z=new ZipArchive();if($z->open($path)!==true)throw new Exception('Cannot open XLSX for writing.');$wb=simplexml_load_string($z->getFromName('xl/workbook.xml'));$rels=simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'));$map=[];foreach($rels->Relationship as $rel)$map[(string)$rel['Id']]=(string)$rel['Target'];$target=null;foreach($wb->sheets->sheet as $s){$name=(string)$s['name'];$want=($platform==='Lazada'?'template':($platform==='TikTok'?'Template':'Sheet1'));if(strcasecmp($name,$want)!==0)continue;$attrs=$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$target=ltrim($map[(string)$attrs['id']],'/');if(!str_starts_with($target,'xl/'))$target='xl/'.$target;break;}if(!$target){$z->close();throw new Exception('Platform worksheet not found.');}$xml=$z->getFromName($target);$dom=new DOMDocument();$dom->preserveWhiteSpace=false;$dom->formatOutput=false;$dom->loadXML($xml);$xp=new DOMXPath($dom);$xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$col=XlsxReader::colLetters($qtyIndex);
 foreach($changes as $excelRow=>$info){$ref=$col.$excelRow;$cell=$xp->query("//m:c[@r='{$ref}']")->item(0);if(!$cell){$rowNode=$xp->query("//m:row[@r='{$excelRow}']")->item(0);if(!$rowNode)continue;$cell=$dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main','c');$cell->setAttribute('r',$ref);$rowNode->appendChild($cell);}while($cell->firstChild)$cell->removeChild($cell->firstChild);$v=$dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main','v',(string)$info['qty']);$cell->appendChild($v);$cell->removeAttribute('t');}
 $z->addFromString($target,$dom->saveXML());$z->close();
}

function allocation_percentages($pid){$pdo=db();$st=$pdo->prepare('SELECT lazada_pct,shopee_pct,tiktok_pct FROM allocation_rules WHERE product_id=?');$st->execute([(int)$pid]);if($r=$st->fetch()){$v=array_values(array_map('floatval',$r));if(min($v)>=0&&max($v)<=100&&abs(array_sum($v)-100)<0.0000001)return $v;}return [(float)setting('default_lazada_pct','20'),(float)setting('default_shopee_pct','40'),(float)setting('default_tiktok_pct','40')];}
function allocate_integer($total,$pcts){$raw=[];$base=[];$sum=0;$pctTotal=max(0,min(100,array_sum($pcts)));$target=(int)round($total*$pctTotal/100);foreach($pcts as $k=>$pct){$raw[$k]=$total*$pct/100;$base[$k]=(int)floor($raw[$k]);$sum+=$base[$k];}$remain=max(0,$target-$sum);$fractions=[];foreach($raw as $k=>$v)$fractions[$k]=$v-floor($v);arsort($fractions);foreach(array_keys($fractions) as $k){if($remain<=0)break;$base[$k]++;$remain--;}return $base;}
function run_full_allocation($userId,$remarks=''){ $pdo=db();$ref=next_ref('ALLOC');$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO allocation_batches(batch_ref,mode,remarks,user_id) VALUES(?,?,?,?)')->execute([$ref,'Full Rebalance',$remarks,$userId]);$batch=(int)$pdo->lastInsertId();$products=$pdo->query('SELECT id FROM products WHERE active=1 ORDER BY id')->fetchAll();$line=$pdo->prepare('INSERT INTO allocation_lines(batch_id,product_id,sellable_qty,lazada_qty,shopee_qty,tiktok_qty,unallocated_qty) VALUES(?,?,?,?,?,?,?)');foreach($products as $p){$s=stock_row($p['id']);$pcts=allocation_percentages($p['id']);$sumPct=array_sum($pcts);if(abs($sumPct-100)>0.0000001)throw new Exception('Allocation percentages must total 100% for product ID '.$p['id']);$alloc=allocate_integer(abs($s['current']),$pcts);if($s['current']<0)$alloc=array_map(fn($v)=>-$v,$alloc);$l=$alloc[0]??0;$sh=$alloc[1]??0;$t=$alloc[2]??0;$used=$l+$sh+$t;if($used!==$s['current'])throw new Exception('Allocation must equal sellable stock.');foreach(['Lazada'=>$l,'Shopee'=>$sh,'TikTok'=>$t] as $pl=>$qty){$before=platform_qty($pl,$p['id']);set_platform_qty($pl,$p['id'],$qty);record_negative_platform($pl,$p['id'],$before,$qty,$ref,date('Y-m-d'));}$line->execute([$batch,$p['id'],$s['current'],$l,$sh,$t,$s['current']-$used]);}$pdo->commit();audit('Platform allocation','Allocation Batch',$ref,'Full allocation generated.');return [$batch,$ref];}catch(Throwable $e){$pdo->rollBack();throw $e;}}

function receipt_pdf_text($path){return receipt_extract_pdf($path)['text'];}
function pdf_unescape($s){$s=preg_replace_callback('/\\\\([0-7]{1,3})/',fn($m)=>chr(octdec($m[1])),$s);return strtr($s,['\\n'=>"\n",'\\r'=>"\r",'\\t'=>"\t",'\\('=>'(','\\)'=>')','\\\\'=>'\\']);}
function parse_receipt_text($text){
 $out=['reference'=>'','transfer_ref'=>'','date'=>'','source'=>'','items'=>[]];
 if(preg_match('/\bIF\s*\d{4,}\b/i',$text,$m))$out['reference']=strtoupper(preg_replace('/\s/','',$m[0]));
 if(preg_match('/\bTO-[A-Z0-9-]+\b/i',$text,$m))$out['transfer_ref']=strtoupper($m[0]);
 if(preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/',$text,$m)&&checkdate((int)$m[1],(int)$m[2],(int)$m[3]))$out['date']=sprintf('%04d-%02d-%02d',$m[3],$m[1],$m[2]);
 $table=false;$last=null;$continuation=false;
 foreach(preg_split('/\R/',$text) as $line){
  $line=trim(preg_replace('/\s+/u',' ',$line));if($line==='')continue;
  if(preg_match('/\b(?:item|description)\b.*\b(?:ordered|units|shipped)\b/i',$line)){$table=true;$continuation=false;continue;}
  if(preg_match('/^(?:checked|verified|received|prepared|signature|total|page\b)/i',$line)){$table=false;$continuation=false;continue;}
  // Normalize only known receipt unit OCR confusions; never guess quantity digits.
  if(preg_match('/^(.+?)\s+(\S+)\s+(bx\s*\(\s*\d+\s*\)|pc|pcs|piece|pieces|pe|pec)\s+(\S+)\s*$/i',$line,$m)){
   $item=trim($m[1]);$rawUom=strtolower(preg_replace('/\s/','',$m[3]));$uom=in_array($rawUom,['pe','pec'],true)?'pc':$rawUom;$sq=preg_match('/^\d+(?:\.\d+)?$/D',$m[4])?(float)$m[4]:0;
   $factor=receipt_uom_factor($uom);$qty=null;try{[, $qty]=receipt_quantity($sq,$uom);}catch(Throwable $e){}
   $out['items'][]=['item'=>$item,'source_qty'=>$sq,'source_uom'=>$uom,'factor'=>$factor,'qty'=>$qty,'raw_shipped'=>$m[4],'raw_source_uom'=>$rawUom,'uom_correction'=>$uom!==$rawUom?'Receipt unit read as '.$rawUom.'; normalized to pc. Compare with the original receipt.':''];
   $last=count($out['items'])-1;$continuation=true;$table=true;continue;
  }
  // Wrapped model/specification belongs to the preceding numbered row, not the repeated description beneath it.
  if($table&&$last!==null&&$continuation&&!preg_match('/^(?:rs[8b]|srf|nanomoly|bossj)\b/i',$line)&&preg_match('/^(?:nmax|aerox|mio|click|pcx|adv|sniper|\d+\/\d+)/i',$line)){$out['items'][$last]['item'].=' '.$line;$continuation=false;}
  elseif(preg_match('/^(?:rs[8b]|srf|nanomoly|bossj)\b/i',$line))$continuation=false;
 }
 return $out;
}
function receipt_uom_factor($uom){$u=norm($uom);if(in_array($u,['pc','pcs','piece','pieces'],true))return 1;if(preg_match('/^bx\s*\(\s*([1-9]\d*)\s*\)$/i',$u,$m))return (int)$m[1];return null;}

function csv_download($filename,$rows){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');$o=fopen('php://output','w');foreach($rows as $r){foreach($r as &$cell)if(is_string($cell)&&preg_match('/^[=+@\t\r]|^-[^0-9]/',$cell))$cell="'".$cell;unset($cell);fputcsv($o,$r);}fclose($o);exit;}


function receipt_match_product($item){if($mapped=product_code_lookup($item))return $mapped;
 $pdo=db();$n=norm($item);if($n==='')return null;
 $st=$pdo->prepare('SELECT product_id FROM receipt_aliases WHERE normalized_alias=?');$st->execute([$n]);if($x=$st->fetchColumn())return (int)$x;
 $st=$pdo->prepare('SELECT id FROM products WHERE normalized_name=? AND active=1 LIMIT 1');$st->execute([$n]);if($x=$st->fetchColumn())return (int)$x;
 return null;
}

function receipt_batch_validation($batchId){$pdo=db();$st=$pdo->prepare('SELECT validation_status,COUNT(*) c FROM receipt_staging_lines WHERE batch_id=? GROUP BY validation_status');$st->execute([(int)$batchId]);$r=['Matched'=>0,'Unmapped'=>0,'Unreadable'=>0,'Invalid Quantity'=>0,'Invalid UOM'=>0,'Possible Mismatch'=>0,'total'=>0];foreach($st as $x){$r[$x['validation_status']] = (int)$x['c'];$r['total']+=(int)$x['c'];}return $r;}
function apply_product_allocation($pid,$l,$s,$t){$stock=stock_row($pid);if(abs($l+$s+$t-100)>0.0000001)throw new Exception('Product allocation must total 100%.');$alloc=allocate_integer(abs($stock['current']),[(float)$l,(float)$s,(float)$t]);if($stock['current']<0)$alloc=array_map(fn($v)=>-$v,$alloc);$lq=$alloc[0]??0;$sq=$alloc[1]??0;$tq=$alloc[2]??0;if($lq+$sq+$tq>$stock['current'])throw new Exception('Allocation exceeds sellable stock.');foreach(['Lazada'=>$lq,'Shopee'=>$sq,'TikTok'=>$tq] as $pl=>$qty){$before=platform_qty($pl,$pid);set_platform_qty($pl,$pid,$qty);record_negative_platform($pl,$pid,$before,$qty,'Product rule allocation',date('Y-m-d'));}return [$lq,$sq,$tq,max(0,$stock['current']-$lq-$sq-$tq),$stock['current']];}

require_once __DIR__.'/v5_helpers.php';

require_once __DIR__.'/order_import_v5.php';

require_once __DIR__.'/beginning_v5.php';

require_once __DIR__.'/registration.php';

require_once __DIR__.'/product_categories.php';

require_once __DIR__.'/receipt_diagnostics.php';

require_once __DIR__.'/brand_filter.php';
require_once __DIR__.'/inflow_v51.php';
require_once __DIR__.'/inventory_count_helpers.php';

require_once __DIR__.'/inflow_cancellation.php';
require_once __DIR__.'/inflow_allocation.php';

require_once __DIR__.'/platform_balance_controls.php';

require_once __DIR__.'/process_schema.php';
require_once __DIR__.'/borrowed_controls.php';

require_once __DIR__.'/negative_controls.php';
require_once __DIR__.'/count_conditions.php';

require_once __DIR__.'/order_item_summary.php';

require_once __DIR__.'/inflow_guard.php';
require_once __DIR__.'/order_returns.php';
require_once __DIR__.'/inflow_search.php';

require_once __DIR__.'/inflow_reversal.php';

require_once __DIR__.'/category_controls.php';
require_once __DIR__.'/reconciliation_review.php';
require_once __DIR__.'/backup_controls.php';
require_once __DIR__.'/day_controls.php';

require_once __DIR__.'/compact_history.php';

require_once __DIR__.'/packaging_controls.php';

require_once __DIR__.'/repackaging_batches.php';

require_once __DIR__.'/report_exports.php';
require_once __DIR__.'/adjustment_schema.php';
require_once __DIR__.'/inventory_reports.php';
require_once __DIR__.'/adjustment_controls.php';

require_once __DIR__.'/report_view.php';

require_once __DIR__.'/item_code_controls.php';

require_once __DIR__.'/product_status.php';

require_once __DIR__.'/brand_controls.php';

require_once __DIR__.'/brand_sku_controls.php';

require_once __DIR__.'/beginning_review.php';

require_once __DIR__.'/bundle_controls.php';
require_once __DIR__.'/manual_adjustment.php';

require_once __DIR__.'/adjustment_notifications.php';

require_once __DIR__.'/system_notifications.php';

require_once __DIR__.'/password_reset_requests.php';

require_once __DIR__.'/admin_requests.php';

require_once __DIR__.'/warranty_controls.php';
require_once __DIR__.'/cancelled_orders.php';
require_once __DIR__.'/stock_balance_reports.php';
require_once __DIR__.'/final_count_controls.php';
require_once __DIR__.'/batch_workflows.php';
require_once __DIR__.'/batch_pages.php';
