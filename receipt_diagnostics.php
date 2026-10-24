<?php
function receipt_extract_pdf($path){
 global $CFG;$notes=[];$best='';$bestCount=0;
 $run=function($program,$arguments)use(&$notes){
  $lines=[];$code=0;exec('"'.$program.'" '.$arguments,$lines,$code);return $code;
 };
 $exe=trim($CFG['pdftotext_path']??'');
 if($exe&&is_file($exe)){
  $tmp=tempnam(sys_get_temp_dir(),'rs8text');
  try{$code=$run($exe,'-layout '.escapeshellarg($path).' '.escapeshellarg($tmp));$native=$code===0?(string)file_get_contents($tmp):'';
   if(trim($native," \t\n\r\0\x0B\x0C")!==''){$count=count(parse_receipt_text($native)['items']);if($count)return ['text'=>$native,'method'=>'PDF text','notes'=>['Receipt item rows extracted from the PDF text layer.']];$notes[]='PDF text was extracted, but no supported item rows were found.';$best=$native;}
   else $notes[]=$code===0?'No searchable PDF text was found; image OCR is required.':'PDF text extraction failed (exit code '.$code.').';
  }finally{@unlink($tmp);}
 }else $notes[]='PDF text tool is missing or not configured.';
 $ppm=trim($CFG['pdftoppm_path']??'');$ocr=trim($CFG['tesseract_path']??'');
 if(!$ppm||!is_file($ppm)||!$ocr||!is_file($ocr))return ['text'=>$best,'method'=>'Unavailable','notes'=>array_merge($notes,['Image OCR requires installed/configured pdftoppm and Tesseract tools.'])];
 $dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rs8ocr_'.bin2hex(random_bytes(8));
 if(!mkdir($dir))throw new Exception('Could not create temporary OCR folder.');
 try{
  $code=$run($ppm,'-png -r 300 '.escapeshellarg($path).' '.escapeshellarg($dir.'/page'));
  if($code!==0)return ['text'=>$best,'method'=>'OCR failed','notes'=>array_merge($notes,['PDF image conversion failed (exit code '.$code.').'])];
  $pages=glob($dir.'/page-*.png');natsort($pages);$out=[];
  if(!$pages)$notes[]='PDF conversion produced no page images.';
  foreach($pages as $i=>$image){$pageBest='';$countBest=-1;$chosen=0;
   foreach([3,6] as $mode){$base=$dir.'/ocr_'.$i.'_'.$mode;$code=$run($ocr,escapeshellarg($image).' '.escapeshellarg($base).' --psm '.$mode);$txt=$code===0&&is_file($base.'.txt')?(string)file_get_contents($base.'.txt'):'';$count=count(parse_receipt_text($txt)['items']);
    if($count>$countBest){$pageBest=$txt;$countBest=$count;$chosen=$mode;}
    $notes[]='Page '.(count($out)+1).': OCR layout '.$mode.' detected '.$count.' item rows'.($code?'; OCR failed (exit code '.$code.')':'').'.';
   }
   $out[]=$pageBest;$notes[]='Page '.count($out).': selected OCR layout '.$chosen.'.';
  }
  $text=trim(implode("\n",$out));return ['text'=>$text?:$best,'method'=>'OCR','notes'=>$notes];
 }finally{foreach(glob($dir.'/*') as $tmp)@unlink($tmp);@rmdir($dir);}
}
function inflow_error_review_link($batch){
 if(empty($batch['error_count'])&&$batch['status']!=='Unsuccessful')return '';
 return ' <a class="btn danger sm" href="?page=inflow_errors&amp;id='.(int)$batch['id'].'">Review Errors</a>';
}
function save_inflow_upload_failure($action,$message,$user,$batch=0,$path=null){
 $pdo=db();$file=$_FILES[$action==='upload_receipt'?'receipt':'file']['name']??'No file selected';$type=$action==='return_upload'?'Order Return':($action==='upload_receipt'?'Receipt PDF':'Excel Upload');
 if($batch){$q=$pdo->prepare('SELECT COUNT(*) FROM inflow_batches WHERE id=?');$q->execute([(int)$batch]);if(!$q->fetchColumn())$batch=0;}
 if($batch){$pdo->prepare("UPDATE inflow_batches SET status='Unsuccessful',error_count=1,error_json=? WHERE id=?")->execute([json_encode([$message]),$batch]);return $batch;}
 $pdo->prepare('INSERT INTO inflow_batches(batch_ref,batch_type,reference_no,txn_date,attachment_path,original_filename,status,total_qty,error_count,error_json,user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([next_ref('INF-ERR'),$type,substr($file,0,100),date('Y-m-d'),$path,$file,'Unsuccessful',0,1,json_encode([$message]),$user]);$id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE inflow_batches SET source_name=?,result_json=? WHERE id=?')->execute([substr(trim((string)($_POST['supplier']??'')),0,180),json_encode(['upload_action'=>$action,'remarks'=>substr((string)($_POST['remarks']??''),0,2000)]),$id]);return $id;
}
function inflow_error_review($id){
 $s=db()->prepare('SELECT * FROM inflow_batches WHERE id=?');$s->execute([(int)$id]);$b=$s->fetch();
 if(!$b){echo '<div class="alert danger">Inflow batch not found.</div>';return;}
 $errors=json_decode($b['error_json']??'[]',true)?:[];$result=json_decode($b['result_json']??'[]',true)?:[];
 echo '<div class="page-head"><div><a class="back" href="?page=inflow">← Inflow History</a><h1>Inflow Error Review</h1><p>'.e($b['original_filename']?:$b['batch_ref']).'</p></div></div><div class="card"><h3>Saved Upload Details</h3><p>Reference: '.e($b['reference_no']).' · Batch: '.e($b['batch_ref']).' · Status: '.e($b['status']).'</p><p>These details remain available after the notification closes. Recorded issues describe the upload attempt; corrected issues remain here for reference.</p><table class="table"><tr><th>#</th><th>Recorded Error / Review Issue</th></tr>';
 foreach($errors as $i=>$error)echo '<tr><td>'.($i+1).'</td><td>'.e(is_string($error)?$error:json_encode($error)).'</td></tr>';
 if(!$errors)echo '<tr><td colspan="2">No detailed error was saved by the application version used for this upload.</td></tr>';
 echo '</table></div>';
 if(!empty($result['reading_diagnostics'])){echo '<div class="card"><h3>Receipt Reading Diagnostics</h3><p>Method: '.e($result['extraction_method']??'Unknown').'</p><ul>';foreach($result['reading_diagnostics'] as $note)echo '<li>'.e($note).'</li>';echo '</ul></div>';}
 if($b['attachment_path'])echo '<a class="btn secondary" href="download.php?type=receipt&amp;id='.(int)$id.'" target="_blank">Open Original File</a> ';
 echo '<a class="btn primary" href="?page='.(($b['batch_type']==='Receipt PDF'&&$b['status']!=='Confirmed')?'receipt_review':'inflow_batch').'&amp;id='.(int)$id.'">Open Batch / Correct Receipt</a>';
}
