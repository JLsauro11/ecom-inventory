<?php
// Recover a failed receipt in its original batch. Never posts inventory.
function receipt_recovery_digest($b){return hash('sha256',json_encode([$b['id'],$b['status'],$b['attachment_path'],$b['result_json'],$b['error_json']]));}
function receipt_recovery_snapshot(&$meta,$b){
 if(!isset($meta['original_failed_reading']))$meta['original_failed_reading']=['status'=>$b['status'],'errors'=>json_decode($b['error_json']??'[]',true)?:[],'extraction'=>$meta,'preserved_at'=>date('Y-m-d H:i:s')];
}
function receipt_recovery_event(&$meta,$action,$details=[]){$u=current_user();$meta['receipt_recovery_history'][]=$details+['action'=>$action,'user_id'=>$u['id'],'name'=>$u['name'],'at'=>date('Y-m-d H:i:s'),'stock_change'=>0];}
function receipt_retry_reading($id,$digest){
 if(!can_action('receipt_retry_reading'))throw new Exception('Receipt correction access required.');
 return process_transaction(function()use($id,$digest){
  $pdo=db();$q=$pdo->prepare('SELECT * FROM inflow_batches WHERE id=? FOR UPDATE');$q->execute([(int)$id]);$b=$q->fetch();
  if(!$b||$b['batch_type']!=='Receipt PDF'||$b['status']!=='Unsuccessful')throw new Exception('Retry reading is available only for a failed receipt. Existing review corrections are preserved.');
  if(!is_string($digest)||!hash_equals(receipt_recovery_digest($b),$digest))throw new Exception('Receipt changed. Refresh before retrying.');
  foreach(['inflows','receipt_staging_lines'] as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE batch_id=?');$q->execute([$b['id']]);if($q->fetchColumn())throw new Exception('This receipt already has items. Use its existing review or posted history.');}
  $root=realpath(__DIR__.'/uploads');$path=realpath(__DIR__.'/'.($b['attachment_path']??''));
  if(!$root||!$path||!str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$root).'/')||!is_file($path)||strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='pdf')throw new Exception('Original PDF is unavailable. Add the verified items manually from your original document.');
  if($old=inflow_file_previous(hash_file('sha256',$path),(int)$b['id']))throw new InflowDuplicateFile($old);
  $reading=receipt_extract_pdf($path);$parsed=parse_receipt_text($reading['text']);$meta=json_decode($b['result_json']??'[]',true)?:[];receipt_recovery_snapshot($meta,$b);
  $errors=[];$insert=$pdo->prepare('INSERT INTO receipt_staging_lines(batch_id,line_no,raw_item,product_id,source_qty,source_uom,conversion_factor,final_qty,validation_status,validation_message) VALUES(?,?,?,?,?,?,?,?,?,?)');
  foreach($parsed['items'] as $i=>$it){
   $pid=receipt_match_product($it['item']);$factor=receipt_uom_factor($it['source_uom']);$source=(float)$it['source_qty'];$status='Matched';$message='';$qty=null;
   if($source<=0){$status='Invalid Quantity';$message='Check shipped quantity against the original PDF.';}
   elseif($factor===null){$status='Invalid UOM';$message='Enter pc or the actual box size, for example bx(12).';}
   elseif(!$pid){$status='Unmapped';$message='Select the correct Product / SKU from the original receipt.';}
   try{[,$qty]=receipt_quantity($source,$it['source_uom']);}catch(Throwable $e){if($status==='Matched'){$status='Invalid Quantity';$message=$e->getMessage();}}
   if($reading['method']==='OCR'&&$status==='Matched'){$status='Unreadable';$message='Verify the OCR product, quantity and unit against the original PDF.';}
   if($message!=='')$errors[]='Line '.($i+1).': '.$message;
   $insert->execute([$b['id'],$i+1,$it['item'],$pid?:null,$source,$it['source_uom'],$factor,$qty,$status,trim($message.' '.($it['uom_correction']??''))]);
  }
  $count=count($parsed['items']);if(!$count)$errors[]='No valid item lines were detected. Add the receipt items manually below.';
  receipt_recovery_event($meta,'Retry saved PDF',['method'=>$reading['method'],'lines'=>$count,'issues'=>$errors]);
  $meta['extraction_method']=$reading['method'];$meta['reading_diagnostics']=$reading['notes'];$meta['retry_extraction']=$parsed;unset($meta['incoming_allocation']);
  $pdo->prepare('UPDATE inflow_batches SET status=?,result_json=?,error_count=?,error_json=? WHERE id=?')->execute([$count?'To Review':'Unsuccessful',json_encode($meta),count($errors),json_encode($errors),$b['id']]);
  audit('Retry receipt reading','Inflow Batch',$b['batch_ref'],json_encode(['lines'=>$count,'method'=>$reading['method'],'stock_change'=>0]));return $count;
 });
}
function receipt_recovery_panel($b){
 if($b['batch_type']!=='Receipt PDF'||$b['status']!=='Unsuccessful')return;
 if(!can_action('add_receipt_line')&&!can_action('receipt_retry_reading'))return;
 echo '<div class="card"><div class="card-head"><div><h3>Correct This Receipt</h3><p class="muted small">Use the saved PDF again, or enter its items manually. Continue with this same receipt; stock is added only after final confirmation.</p></div><a class="btn secondary" target="_blank" href="download.php?type=receipt&amp;id='.(int)$b['id'].'">Open Original PDF</a></div><div class="inline-form">';
 if(can_action('receipt_retry_reading')){echo '<form method="post" action="actions.php" data-confirm="Read the saved PDF again? No stock will be added.">';workflow_hidden('receipt_retry_reading',['batch_id'=>$b['id'],'digest'=>receipt_recovery_digest($b),'return'=>'receipt_review&id='.$b['id']]);echo '<button class="btn primary">Retry Reading</button></form>';}
 receipt_line_action_assets();$editable=$b;$editable['status']='To Review';receipt_line_actions($editable);echo '</div><p class="muted small">For boxes, enter the actual unit printed on the receipt, such as bx(48) or bx(12). The system converts it to pieces. Check every line before allocation.</p></div>';
}
function receipt_recovery_history($b){
 $meta=json_decode($b['result_json']??'[]',true)?:[];if(empty($meta['receipt_recovery_history']))return;
 echo '<div class="card table-wrap"><details><summary><b>Receipt Reading &amp; Recovery History</b></summary><p class="muted small">Original failed attempt is retained. Recovery actions do not post stock.</p><table class="table"><tr><th>Action</th><th>Details</th><th>Processed By / Date</th></tr>';
 foreach($meta['receipt_recovery_history'] as $event)echo '<tr><td>'.e($event['action']).'</td><td>'.e($event['method']??$event['reason']??'Verified manual item entry').' · '.(int)($event['lines']??1).' lines</td><td>'.e($event['name']).'<br>'.e($event['at']).'</td></tr>';
 echo '</table><p class="muted small">Original issues: '.e(implode(' · ',$meta['original_failed_reading']['errors']??[])).'</p></details></div>';
}
