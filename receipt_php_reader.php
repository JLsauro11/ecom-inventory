<?php
// Text-PDF fallback for hosting without shell commands. No remote service or stock writes.
function receipt_pdf_unit_pattern(){return '(?:bx(?:\s*\([^\r\n)]{0,30}\))?|boxes?|ctn|cartons?|pc|pcs|piece|pieces|pe|pec|missing)';}
function receipt_pdf_join_rows($text){
 $out=[];$pending='';$table=false;$shippedOnly=false;$quantity=null;$unit=receipt_pdf_unit_pattern();
 foreach(preg_split('/\R/',str_replace("\xC2\xA0",' ',$text)) as $line){
  $clean=trim(preg_replace('/\s+/u',' ',$line));
  // Some PDFs position quantity and unit with separate TJ commands without a literal space.
  $clean=preg_replace('/^(\d[\d,.]*)(bx\s*\(|pcs?\b)/i','$1 $2',$clean);
  if($quantity!==null){
   if(preg_match('/^'.$unit.'$/i',$clean)){$out[]=$pending.' '.$quantity.' '.$clean;$quantity=null;$pending='';continue;}
   // Keep incomplete rows visible for correction instead of silently omitting stock.
   $out[]=$pending.' '.$quantity.' missing';$quantity=null;$pending='';
  }
  if(preg_match('/\b(?:item|description)\b.*(?:ordered|units|shipped)/i',$clean)){$table=true;$pending='';$shippedOnly=!preg_match('/ordered/i',$clean);$line=preg_replace('/(shipped)(units)/i','$1 $2',$clean);}
  elseif(preg_match('/^(?:checked|verified|received|prepared|signature|total|page\b|\d+\s+of\s+\d+)/i',$clean)){$table=false;$pending='';}
  elseif($table&&$shippedOnly&&$pending!==''&&preg_match('/^\d[\d,.]*$/D',$clean)){$quantity=$clean;continue;}
  elseif($table&&$shippedOnly&&$pending!==''&&preg_match('/^'.$unit.'$/i',$clean)){$line=$pending.' 0 '.$clean;$pending='';}
  elseif($table&&preg_match($shippedOnly?'/^(\S+)\s+('.$unit.')$/i':'/^(\S+)\s+('.$unit.')\s+(\S+)$/i',$clean)){
   if($pending!=='')$line=$pending.' '.$clean;
   $pending='';
  }
  elseif($table&&preg_match($shippedOnly?'/^.+?\s+\S+\s+'.$unit.'$/i':'/^.+?\s+\S+\s+'.$unit.'\s+\S+$/i',$clean)){$pending='';}
  elseif($table&&$clean!==''){
   // Repeated printed product descriptions are one item; wrapped model suffixes are retained.
   if($clean===$pending)continue;
   if($pending===''||preg_match('/^(?:rs[8b]|srf|nanomoly|bossj|lumina)\b/i',$clean))$pending=$clean;
   elseif($clean!==$pending)$pending.=' '.$clean;
  }
  $out[]=$line;
 }
 if($quantity!==null)$out[]=$pending.' '.$quantity.' missing';
 return implode("\n",$out);
}
function receipt_php_pdf_text($path){
 $loader=__DIR__.'/third_party/receipt_pdf/vendor/autoload.php';
 if(!is_file($loader))throw new Exception('Bundled PHP PDF reader files are missing. Upload the complete third_party/receipt_pdf folder.');
 if(!function_exists('gzuncompress')||!function_exists('iconv'))throw new Exception('PHP PDF reader requires the zlib and iconv extensions.');
 if(!is_file($path)||filesize($path)>20*1024*1024)throw new Exception('PDF must be an accessible file up to 20 MB.');
 require_once $loader;
 $config=new \Smalot\PdfParser\Config();$config->setRetainImageContent(false);
 $document=(new \Smalot\PdfParser\Parser([],$config))->parseFile($path);$pages=$document->getPages();
 if(count($pages)>100)throw new Exception('PDF exceeds the 100-page receipt limit.');
 $texts=[];foreach($pages as $page){$text=$page->getText();if(strlen($text)>2*1024*1024)throw new Exception('PDF page contains too much text.');$texts[]=receipt_pdf_join_rows($text);}
 return implode("\n\f\n",$texts);
}
