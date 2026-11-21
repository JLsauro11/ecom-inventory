<?php
function receipt_line_digest($r){$data=[];foreach(['id','batch_id','line_no','raw_item','product_id','source_qty','source_uom','conversion_factor','final_qty','validation_status','validation_message'] as $key)$data[$key]=$r[$key]??null;return hash('sha256',json_encode($data));}
function remove_extracted_receipt_line($batchId,$lineId,$digest,$reason){
 if(!can_action('remove_receipt_line'))throw new Exception('Receipt correction access required.');$reason=process_reason($reason);
 return process_transaction(function()use($batchId,$lineId,$digest,$reason){
  $q=db()->prepare('SELECT * FROM inflow_batches WHERE id=? FOR UPDATE');$q->execute([(int)$batchId]);$b=$q->fetch();
  if(!$b||$b['batch_type']!=='Receipt PDF'||$b['status']!=='To Review')throw new Exception('Only an unconfirmed receipt can have an extracted line removed. Posted or closed records cannot be changed.');
  $q=db()->prepare('SELECT COUNT(*) FROM inflows WHERE batch_id=?');$q->execute([$b['id']]);if($q->fetchColumn())throw new Exception('This receipt already has posted stock. Use the approved correction process.');
  $q=db()->prepare('SELECT * FROM receipt_staging_lines WHERE id=? AND batch_id=? FOR UPDATE');$q->execute([(int)$lineId,$b['id']]);$line=$q->fetch();
  if(!$line||!hash_equals(receipt_line_digest($line),(string)$digest))throw new Exception('Receipt line changed or was already removed. Refresh the review.');
  $meta=json_decode($b['result_json']??'[]',true)?:[];
  if(!isset($meta['receipt_original_errors']))$meta['receipt_original_errors']=json_decode($b['error_json']??'[]',true)?:[];
  $meta['removed_receipt_lines'][]=['line'=>$line,'reason'=>$reason,'removed_by'=>current_user()['id'],'removed_name'=>current_user()['name'],'removed_at'=>date('Y-m-d H:i:s'),'physical_delta'=>0];
  unset($meta['incoming_allocation']);
  db()->prepare('DELETE FROM receipt_staging_lines WHERE id=? AND batch_id=?')->execute([$line['id'],$b['id']]);
  $q=db()->prepare('SELECT line_no,validation_status,validation_message FROM receipt_staging_lines WHERE batch_id=? ORDER BY line_no');$q->execute([$b['id']]);$remaining=$q->fetchAll();$errors=[];
  foreach($remaining as $row)if($row['validation_status']!=='Matched')$errors[]='Line '.$row['line_no'].': '.($row['validation_message']?:$row['validation_status']);
  if(!$remaining)$errors[]='No active receipt lines remain. Cancel the upload or upload a corrected original file.';
  db()->prepare('UPDATE inflow_batches SET result_json=?,error_count=?,error_json=? WHERE id=?')->execute([json_encode($meta),count($errors),json_encode($errors),$b['id']]);
  audit('Remove extracted receipt line','Inflow Batch',$b['batch_ref'],json_encode(['line_no'=>$line['line_no'],'line_id'=>$line['id'],'raw_item'=>$line['raw_item'],'final_qty'=>$line['final_qty'],'reason'=>$reason,'physical_delta'=>0,'allocation_review_required'=>true]));
  return (int)$b['id'];
 });
}
function receipt_remove_line_form($b,$r){
 if($b['status']!=='To Review'||!can_action('remove_receipt_line'))return;
 echo '<details style="margin-top:8px"><summary class="btn ghost sm">Remove Line</summary><form method="post" action="actions.php" class="stack" style="margin-top:8px" data-confirm="Remove this extracted line from the receipt? It will not be posted. The original line and reason will remain in history.">';
 workflow_hidden('remove_receipt_line',['batch_id'=>$b['id'],'line_id'=>$r['id'],'line_digest'=>receipt_line_digest($r),'return'=>'receipt_review&id='.$b['id']]);
 echo '<label>Reason<input name="reason" minlength="3" maxlength="2000" placeholder="Duplicate / not actually received" required></label><button class="btn danger sm">Remove This Line</button></form></details>';
}
function receipt_removed_history($b){
 $meta=json_decode($b['result_json']??'[]',true)?:[];$removed=$meta['removed_receipt_lines']??[];if(!$removed)return;
 echo '<div class="card table-wrap"><details><summary><b>Removed Receipt Lines · '.count($removed).'</b></summary><p class="muted small">Excluded from this inflow. Original PDF and extraction are retained. No stock was posted by removal. Review platform allocation again before confirmation.</p><table class="table"><tr><th>Original Line</th><th>Receipt Item</th><th>Excluded Qty</th><th>Reason</th><th>Removed By / Date</th></tr>';
 foreach($removed as $entry){$r=$entry['line'];echo '<tr><td>'.(int)$r['line_no'].'</td><td>'.e($r['raw_item']).'</td><td>'.e($r['final_qty']===null?'Unvalidated':number_format((int)$r['final_qty']).' pcs').'</td><td>'.e($entry['reason']).'</td><td>'.e($entry['removed_name']).'<br>'.e($entry['removed_at']).'</td></tr>';}
 echo '</table></details></div>';
}
