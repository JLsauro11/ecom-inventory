<?php
function receipt_line_digest($r){$data=[];foreach(['id','batch_id','line_no','raw_item','product_id','source_qty','source_uom','conversion_factor','final_qty','validation_status','validation_message'] as $key)$data[$key]=$r[$key]??null;return hash('sha256',json_encode($data));}
function remove_extracted_receipt_line($batchId,$lineId,$digest,$reason){return remove_extracted_receipt_lines($batchId,[(int)$lineId=>$digest],$reason);}
function receipt_remove_selection($values,$expected){
 if(!is_array($values)||!$values||count($values)>5000)throw new Exception('Select 1 to 5000 receipt lines.');
 if(!is_scalar($expected)||!ctype_digit((string)$expected)||(int)$expected!==count($values))throw new Exception('The selected-line list was incomplete. Refresh and select the lines again.');
 $selected=[];foreach($values as $value){if(!is_string($value)||!preg_match('/^([1-9][0-9]*):([a-f0-9]{64})$/D',$value,$m)||isset($selected[(int)$m[1]]))throw new Exception('Invalid or repeated receipt line selection.');$selected[(int)$m[1]]=$m[2];}return $selected;
}
function remove_extracted_receipt_lines($batchId,$selected,$reason){
 if(!can_action('remove_receipt_lines'))throw new Exception('Receipt correction access required.');$reason=process_reason($reason);
 if(!is_array($selected)||!$selected||count($selected)>5000)throw new Exception('Select 1 to 5000 receipt lines.');
 foreach($selected as $id=>$digest)if(!is_int($id)||$id<1||!is_string($digest)||!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new Exception('Invalid receipt line selection.');
 return process_transaction(function()use($batchId,$selected,$reason){
  $q=db()->prepare('SELECT * FROM inflow_batches WHERE id=? FOR UPDATE');$q->execute([(int)$batchId]);$b=$q->fetch();
  if(!$b||$b['batch_type']!=='Receipt PDF'||$b['status']!=='To Review')throw new Exception('Only an unconfirmed receipt can have extracted lines removed. Posted or closed records cannot be changed.');
  $q=db()->prepare('SELECT COUNT(*) FROM inflows WHERE batch_id=?');$q->execute([$b['id']]);if($q->fetchColumn())throw new Exception('This receipt already has posted stock. Use the approved correction process.');
  $ids=array_keys($selected);$marks=implode(',',array_fill(0,count($ids),'?'));$params=array_merge([$b['id']],$ids);
  $q=db()->prepare('SELECT * FROM receipt_staging_lines WHERE batch_id=? AND id IN ('.$marks.') ORDER BY line_no FOR UPDATE');$q->execute($params);$lines=$q->fetchAll();
  if(count($lines)!==count($selected))throw new Exception('A selected line changed, was removed or belongs to another receipt. Refresh the review.');
  // Validate the complete selection before changing any row, including stale requests.
  foreach($lines as $line)if(!hash_equals(receipt_line_digest($line),$selected[(int)$line['id']]))throw new Exception('A selected line changed. Refresh the review; no lines were removed.');
  $meta=json_decode($b['result_json']??'[]',true)?:[];if(!isset($meta['receipt_original_errors']))$meta['receipt_original_errors']=json_decode($b['error_json']??'[]',true)?:[];
  $group=bin2hex(random_bytes(12));$at=date('Y-m-d H:i:s');foreach($lines as $line)$meta['removed_receipt_lines'][]=['line'=>$line,'reason'=>$reason,'removed_by'=>current_user()['id'],'removed_name'=>current_user()['name'],'removed_at'=>$at,'physical_delta'=>0,'removal_group'=>$group];
  unset($meta['incoming_allocation']);db()->prepare('DELETE FROM receipt_staging_lines WHERE batch_id=? AND id IN ('.$marks.')')->execute($params);
  $q=db()->prepare('SELECT line_no,validation_status,validation_message FROM receipt_staging_lines WHERE batch_id=? ORDER BY line_no');$q->execute([$b['id']]);$remaining=$q->fetchAll();$errors=[];
  foreach($remaining as $row)if($row['validation_status']!=='Matched')$errors[]='Line '.$row['line_no'].': '.($row['validation_message']?:$row['validation_status']);
  if(!$remaining)$errors[]='No active receipt lines remain. Cancel the upload or upload a corrected original file.';
  db()->prepare('UPDATE inflow_batches SET result_json=?,error_count=?,error_json=? WHERE id=?')->execute([json_encode($meta),count($errors),json_encode($errors),$b['id']]);
  audit(count($lines)===1?'Remove extracted receipt line':'Remove extracted receipt lines','Inflow Batch',$b['batch_ref'],json_encode(['line_ids'=>$ids,'line_numbers'=>array_column($lines,'line_no'),'reason'=>$reason,'physical_delta'=>0,'allocation_review_required'=>true,'removal_group'=>$group]));return (int)$b['id'];
 });
}
function receipt_bulk_remove_toolbar($b){
 if($b['status']!=='To Review'||!can_action('remove_receipt_lines'))return;
 echo '<form id="receiptBulkRemove" method="post" action="actions.php" class="inline-form" style="margin:12px 0;gap:12px;flex-wrap:wrap" data-confirm="Remove the selected receipt lines? They will not be posted; original lines and reason remain in history.">';workflow_hidden('remove_receipt_lines',['batch_id'=>$b['id'],'selection_count'=>0,'return'=>'receipt_review&id='.$b['id']]);
 echo '<label class="ack"><input type="checkbox" data-rl-select-all> Select All</label><span class="muted small" data-rl-selected-count aria-live="polite">0 selected</span><label style="flex:1;min-width:180px">Shared reason<input name="reason" minlength="3" maxlength="2000" placeholder="Duplicate / incorrectly read lines" required></label><button type="submit" class="btn danger sm" data-rl-remove-selected disabled>Remove Selected Lines</button></form><script src="assets/receipt_line_bulk.js?v=5.9.47" defer></script>';
}
function receipt_bulk_line_checkbox($b,$r){if($b['status']!=='To Review'||!can_action('remove_receipt_lines'))return '';return '<input type="checkbox" form="receiptBulkRemove" name="selected_lines[]" value="'.(int)$r['id'].':'.receipt_line_digest($r).'" data-rl-select aria-label="Select extracted line '.(int)$r['line_no'].'"> ';}
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
