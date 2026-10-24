<?php
// Standalone PDF writer: no additional deployment dependencies. Original files are retained.
function preview_pdf_escape($text){
 $text=iconv('UTF-8','Windows-1252//TRANSLIT',(string)$text);
 return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$text===false?'[Text unavailable]':$text);
}
function preview_pdf_text($x,$y,$text,$size=9,$bold=false,$color='0.12 0.18 0.25'){
 return "$color rg BT /".($bold?'F2':'F1')." $size Tf $x $y Td (".preview_pdf_escape($text).") Tj ET\n";
}
function preview_text_width($text,$size=9){
 $s=iconv('UTF-8','Windows-1252//TRANSLIT',(string)$text);$units=0;
 for($i=0;$i<strlen($s?:'');$i++){$c=$s[$i];$units+=str_contains("ilI.,:;'!| ",$c)?0.28:(str_contains('MW@%',$c)?0.9:(ctype_upper($c)?0.68:0.56));}return $units*$size;
}
function preview_wrap($text,$width,$size=9){
 $text=preg_replace('/\s+/u',' ',trim((string)$text));if($text==='')return [''];
 $lines=[];$line='';foreach(preg_split('/\s+/u',$text) as $word){
  if($line!==''&&preview_text_width($line.' '.$word,$size)>$width){$lines[]=$line;$line='';}
  while(preview_text_width($word,$size)>$width){$chars=preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY);$part='';$n=0;foreach($chars as $char){if($part!==''&&preview_text_width($part.$char,$size)>$width)break;$part.=$char;$n++;}$lines[]=$part;$word=implode('',array_slice($chars,$n));}
  $line.=($line!==''?' ':'').$word;
 }if($line!==''||!$lines)$lines[]=$line;return $lines;
}
function workbook_preview_data($path){
 $zip=new ZipArchive();if($zip->open($path)!==true)throw new Exception('Cannot open the original workbook.');
 $xml=simplexml_load_string($zip->getFromName('xl/workbook.xml'));$sheets=[];
 foreach($xml->sheets->sheet as $sheet){$name=(string)$sheet['name'];$rows=XlsxReader::rows($path,$name);$sheets[]=['name'=>$name,'rows'=>$rows];}
 $zip->close();return $sheets;
}
function preview_column_widths($headers,$rows,$cols,$available){
 $weights=[];foreach($cols as $ci){$h=strtolower($headers[$ci]??'');$max=mb_strlen($headers[$ci]??'');foreach(array_slice($rows,0,120) as $row)$max=max($max,mb_strlen((string)($row[$ci]??'')));
  $weight=max(9,min(42,$max));if(preg_match('/description|product name|sku reference|item|variation name/',$h))$weight=max(25,$weight);if(preg_match('/quantity|^qty$|^stock$/',$h))$weight=9;if(preg_match('/^date$/',$h))$weight=14;$weights[]=$weight;
 }$sum=array_sum($weights);return array_map(fn($v)=>$available*$v/max(1,$sum),$weights);
}
function preview_cell_value($value,$header){
 // Only named date columns are converted; IDs and other numeric strings remain unchanged.
 if(preg_match('/\bdate\b/i',(string)$header)&&is_numeric($value)&&(float)$value>=20000&&(float)$value<=100000){$seconds=(int)round(((float)$value-25569)*86400);return gmdate('Y-m-d',$seconds);}
 return (string)$value;
}
function workbook_pdf_bytes($filename,$sheets,$meta=[]){
 $pages=[];$sheetCount=count($sheets);$generated=date('Y-m-d H:i');$left=36;$right=806;$bottom=50;
 foreach($sheets as $sheetIndex=>$sheet){$rows=$sheet['rows'];$headers=array_shift($rows)??[];$colCount=count($headers);foreach($rows as $r)$colCount=max($colCount,count($r));$colCount=max(1,$colCount);
  for($i=0;$i<$colCount;$i++)if(trim((string)($headers[$i]??''))==='')$headers[$i]='Column '.XlsxReader::colLetters($i);
  foreach(array_chunk(range(0,$colCount-1),7) as $panelIndex=>$cols){$widths=preview_column_widths($headers,$rows,$cols,734);array_unshift($widths,36);$headCells=['No.'];foreach($cols as $ci)$headCells[]=$headers[$ci];
   $headLines=[];foreach($headCells as $ci=>$cell)$headLines[]=preview_wrap($cell,$widths[$ci]-14,9);$headHeight=max(30,max(array_map('count',$headLines))*12+14);
   $stream='';$y=0;$hasPage=false;
   $drawCells=function($cells,$top,$height,$header=false,$stripe=false)use($widths,$left){$s='';$x=$left;$fill=$header?'0.10 0.16 0.23':($stripe?'0.96 0.97 0.98':'1 1 1');$s.="$fill rg $left ".($top-$height)." 770 $height re f\n";
    foreach($cells as $ci=>$lines){$w=$widths[$ci];$s.='0.84 0.87 0.90 RG 0.4 w '.$x.' '.($top-$height)." $w $height re S\n";foreach($lines as $li=>$line)$s.=preview_pdf_text($x+7,$top-16-$li*12,$line,9,$header,$header?'1 1 1':'0.12 0.18 0.25');$x+=$w;}return $s;};
   $startPage=function()use(&$stream,&$y,&$hasPage,$drawCells,$headLines,$headHeight,$filename,$sheet,$meta,$generated,$colCount,$cols,$panelIndex,$sheetIndex,$sheetCount,$rows){
    $stream=preview_pdf_text(36,552,'E-commerce Inventory Management System',17,true).preview_pdf_text(36,532,'ORIGINAL UPLOAD | WORKBOOK REPORT',10,true,'0.42 0.47 0.53');
    $stream.="0.92 0.10 0.17 rg 36 518 770 3 re f\n";
    $fileLines=preview_wrap($filename,550,9);$stream.=preview_pdf_text(36,501,'File: '.$fileLines[0],9,true);if(isset($fileLines[1]))$stream.=preview_pdf_text(36,488,$fileLines[1]);
    $stream.=preview_pdf_text(630,501,'Generated: '.$generated,8).preview_pdf_text(630,487,'Records: '.count($rows),8);
    $details='Worksheet: '.$sheet['name'].' ('.($sheetIndex+1).' of '.$sheetCount.')';if($colCount>7)$details.=' | Columns '.XlsxReader::colLetters($cols[0]).'-'.XlsxReader::colLetters(end($cols));
    $stream.=preview_pdf_text(36,469,$details,9,true);$batch=$meta['batch_id']??null;$platform=$meta['platform']??'';$info=($batch?'Batch: #'.str_pad((string)$batch,6,'0',STR_PAD_LEFT).'   ':'').($platform?'Platform: '.$platform.'   ':'').(!empty($meta['uploaded_at'])?'Uploaded: '.$meta['uploaded_at']:'');
    if($info!=='')$stream.=preview_pdf_text(36,452,$info,8);$y=438;$stream.=$drawCells($headLines,$y,$headHeight,true);$y-=$headHeight;$hasPage=true;
   };
   $startPage();
   foreach($rows as $ri=>$row){$cells=[[(string)($ri+1)]];foreach($cols as $ci)$cells[]=preview_wrap(preview_cell_value($row[$ci]??'',$headers[$ci]),$widths[count($cells)]-14,9);
    $lineCount=max(array_map('count',$cells));$offset=0;
    if($lineCount<=24&&max(27,$lineCount*12+12)>$y-$bottom){$pages[]=$stream;$startPage();}
    while($offset<$lineCount){$capacity=(int)floor(($y-$bottom-12)/12);if($capacity<1){$pages[]=$stream;$startPage();$capacity=(int)floor(($y-$bottom-12)/12);}
     $take=min($capacity,$lineCount-$offset);$chunk=[];foreach($cells as $ci=>$lines)$chunk[]=array_slice($lines,$offset,$take);if($offset>0)$chunk[0]=[($ri+1).'+'];$height=max(27,$take*12+12);
     $stream.=$drawCells($chunk,$y,$height,false,$ri%2===1);$y-=$height;$offset+=$take;
     if($offset<$lineCount){$pages[]=$stream;$startPage();}
    }
   }
   if(!$rows)$stream.=preview_pdf_text(48,$y-22,'No data rows recorded.',10);$pages[]=$stream;
  }
 }
 if(!$pages)$pages=[preview_pdf_text(36,552,'No worksheets available.',14)];
 $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
 foreach($pages as $i=>$stream){$pid=5+$i*2;$sid=$pid+1;$kids[]="$pid 0 R";$stream.="0.84 0.87 0.90 RG 36 39 m 806 39 l S\n".preview_pdf_text(36,25,'Original file retained | Workbook formatting may differ | + = continued row.',8,false,'0.42 0.47 0.53').preview_pdf_text(733,25,'Page '.($i+1).' / '.count($pages),8);
  $objects[$pid]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $sid 0 R >>";$objects[$sid]='<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream';
 }
 $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
function stream_workbook_preview($filename,$sheets,$meta=[]){$bytes=workbook_pdf_bytes($filename,$sheets,$meta);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="RS8_Original_Upload_Report.pdf"');header('Content-Length: '.strlen($bytes));echo $bytes;exit;}
