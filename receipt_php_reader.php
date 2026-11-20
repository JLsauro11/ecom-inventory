<?php
// Text-PDF fallback for hosting without shell commands. No remote service or stock writes.
function receipt_pdf_join_rows($text){
 $out=[];$pending='';$table=false;
 foreach(preg_split('/\R/',str_replace("\xC2\xA0",' ',$text)) as $line){
  $clean=trim(preg_replace('/\s+/u',' ',$line));
  if(preg_match('/\b(?:item|description)\b.*\b(?:ordered|units|shipped)\b/i',$clean)){$table=true;$pending='';}
  elseif(preg_match('/^(?:checked|verified|received|prepared|signature|total|page\b|\d+\s+of\s+\d+)/i',$clean)){$table=false;$pending='';}
  elseif($table&&preg_match('/^(\S+)\s+(bx\s*\(\s*\d+\s*\)|pc|pcs|piece|pieces|pe|pec)\s+(\S+)$/i',$clean)){
   if($pending!=='')$line=$pending.' '.$clean;
   $pending='';
  }
  elseif($table&&preg_match('/^.+?\s+\S+\s+(?:bx\s*\(\s*\d+\s*\)|pc|pcs|piece|pieces|pe|pec)\s+\S+$/i',$clean)){$pending='';}
  elseif($table&&$clean!==''){
   // Repeated printed product descriptions are one item; wrapped model suffixes are retained.
   if($pending===''||preg_match('/^(?:rs[8b]|srf|nanomoly|bossj|lumina)\b/i',$clean))$pending=$clean;
   elseif($clean!==$pending)$pending.=' '.$clean;
  }
  $out[]=$line;
 }
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
