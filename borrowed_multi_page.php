<?php
echo '<link rel="stylesheet" href="assets/borrowed_multi.css"><script src="assets/borrowed_multi.js" defer></script>';
echo '<div class="page-head"><div><h1>Borrowed Items</h1><p>One borrow reference, multiple products, and a separate outstanding balance for every line.</p></div></div>';
if(!borrow_multiple_ready()){
 echo '<div class="alert warn">Run the Multi Product Borrowed Items upgrade before issuing new transactions. Existing records and stocks are preserved.</div>';
 if(current_user()['role']==='Admin')echo '<a class="btn primary" href="upgrade_borrowed_multi.php">Open Multi Product Upgrade</a>';
 return;
}
function borrow_form_fields($action,$id){echo '<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return" value="borrowed&id='.(int)$id.'"><input type="hidden" name="csrf" value="'.e(csrf_token()).'">';}
function borrow_line_options($lines){foreach($lines as $line)if(borrow_outstanding($line)>0)echo '<option value="'.(int)$line['id'].'" data-source="'.e($line['source_platform']).'" data-outstanding="'.borrow_outstanding($line).'">'.e($line['name'].' · '.$line['master_sku'].' · '.$line['source_platform'].' · '.borrow_outstanding($line).' outstanding').'</option>';}
$id=(int)($_GET['id']??0);
if($id){
 try{$lines=borrow_group($id);}catch(Throwable $e){echo '<div class="alert danger">'.e($e->getMessage()).'</div>';return;}
 $first=$lines[0];$ref=$first['batch_ref']?:$first['borrow_ref'];$issued=$returned=$converted=$outstanding=0;
 foreach($lines as $line){$issued+=(int)$line['qty'];$returned+=(int)$line['returned_qty'];$converted+=(int)$line['converted_qty'];$outstanding+=borrow_outstanding($line);}
 $status=$outstanding>0?($first['due_date']<date('Y-m-d')?'Overdue':'Outstanding'):($converted?'Closed / Converted':'Returned');
 ?>
 <a class="back" href="?page=borrowed">← All Borrowed Transactions</a>
 <div class="card"><h2><?=e($ref)?></h2><p><b>Borrower:</b> <?=e($first['borrower'])?> · <b>Issued:</b> <?=e($first['issue_date'])?> · <b>Due:</b> <?=e($first['due_date'])?> · <?=e($status)?></p>
 <p><?=e($first['reason'])?></p><div class="mini-kpis">
 <?php foreach(['Product Lines'=>count($lines),'Issued pcs'=>$issued,'Returned pcs'=>$returned,'Converted pcs'=>$converted,'Outstanding pcs'=>$outstanding] as $label=>$value):?><div><span><?=e($label)?></span><strong><?=$value?></strong></div><?php endforeach;?></div></div>
 <div class="card table-wrap"><h3>Borrowed Product Lines</h3><table class="table"><thead><tr><th>Product / Master SKU</th><th>Source Platform</th><th>Issued</th><th>Returned</th><th>Converted</th><th>Outstanding</th><th>Status</th></tr></thead><tbody>
 <?php foreach($lines as $line):?><tr><td><b><?=e($line['name'])?></b><br><?=e($line['master_sku'])?></td><td><?=e($line['source_platform'])?></td><td><?=(int)$line['qty']?></td><td><?=(int)$line['returned_qty']?></td><td><?=(int)$line['converted_qty']?></td><td><b><?=borrow_outstanding($line)?></b></td><td><?=e(borrow_outstanding($line)>0&&$line['due_date']<date('Y-m-d')?'Overdue':$line['status'])?></td></tr><?php endforeach;?></tbody></table></div>
 <?php if($outstanding>0&&can_action('borrow_return')):?>
 <div class="grid two"><div class="card"><h3>Record Partial / Full Return</h3><p>Select the product being returned. Good quantity restores its original source platform; damaged and For Repackaging quantities enter their separate restricted views.</p>
 <form method="post" action="actions.php" class="stack" data-borrow-line-action data-confirm="Post the selected product return? Review the good and damaged quantities first.">
 <?php borrow_form_fields('borrow_return',$id);?><input type="hidden" name="reference_no" value="<?=e(next_ref('BOR-RETURN'))?>">
 <label>Product Line<select name="borrow_id" data-product-select required><?php borrow_line_options($lines);?></select></label><p class="borrow-line-note muted"></p>
 <label>Return Date<input type="date" name="txn_date" value="<?=date('Y-m-d')?>" required></label>
 <div class="grid two"><label>Good Quantity<input type="number" name="good_qty" min="0" value="0" step="1" required></label><label>Damaged / Leak Quantity<input type="number" name="damaged_qty" min="0" value="0" step="1" required></label></div>
 <label>For Repackaging Quantity<input type="number" name="repackaging_qty" min="0" value="0" step="1" required></label><label>Remarks<textarea name="reason" required></textarea></label><button class="btn primary">Submit Selected Product Return</button></form></div>
 <div class="card"><h3>Permanent Conversion</h3><p>Admin approval closes the selected outstanding quantity. Stock was deducted when issued and is not deducted again.</p>
 <form method="post" action="actions.php" class="stack" data-borrow-line-action data-confirm="Request Admin approval for the selected product quantity?">
 <?php borrow_form_fields('borrow_request_conversion',$id);?><label>Product Line<select name="borrow_id" data-product-select required><?php borrow_line_options($lines);?></select></label><p class="borrow-line-note muted"></p>
 <label>Quantity<input type="number" name="qty" min="1" step="1" required></label><label>Reason<textarea name="reason" required></textarea></label><button class="btn secondary">Request Admin Approval</button></form></div></div>
 <?php endif;
 $ids=array_column($lines,'id');$placeholders=implode(',',array_fill(0,count($ids),'?'));
 $q=db()->prepare('SELECT ev.*,p.name product_name,b.source_platform,u.name processed_by,a.name approved_name FROM borrowed_events ev JOIN borrowed_items b ON b.id=ev.borrow_id JOIN products p ON p.id=b.product_id LEFT JOIN users u ON u.id=ev.user_id LEFT JOIN users a ON a.id=ev.approved_by WHERE ev.borrow_id IN ('.$placeholders.') ORDER BY ev.id DESC');$q->execute($ids);
 ?>
 <div class="card table-wrap"><h3>Borrow Transaction History</h3><table class="table"><thead><tr><th>Date / Reference</th><th>Product / Platform</th><th>Action</th><th>Quantity / Condition</th><th>Processed By</th><th>Status / Approval</th><th>Remarks</th></tr></thead><tbody>
 <?php foreach($q as $event):?><tr id="borrow-event-<?=(int)$event['id']?>"><td><?=e($event['txn_date'])?><br><?=e($event['reference_no'])?></td><td><?=e($event['product_name'])?><br><?=e($event['source_platform'])?></td><td><?=e($event['event_type'])?></td><td><?=(int)$event['qty']?> pcs<?php if($event['event_type']==='Returned'):?><br><?=(int)$event['good_qty']?> good / <?=(int)$event['damaged_qty']?> damaged / <?=(int)($event['repackaging_qty']??0)?> for repackaging<?php endif;?></td><td><?=e($event['processed_by'])?></td><td><?=e($event['status'])?><br><?=e($event['approved_name'])?></td><td><?=e($event['reason'])?>
 <?php if($event['status']==='Pending'&&current_user()['role']==='Admin'):?>
 <form method="post" action="actions.php" class="stack" data-confirm="Approve this product conversion? No second stock deduction will occur."><?php borrow_form_fields('borrow_approve_conversion',$id);?><input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><button class="btn primary sm">Approve Conversion</button></form>
 <form method="post" action="actions.php" class="stack"><?php borrow_form_fields('borrow_reject_conversion',$id);?><input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input name="reason" placeholder="Reason for rejection" required><button class="btn ghost sm">Reject</button></form>
 <?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
 <?php
}else{
 workflow_form('Borrow Issue');
 $status=$_GET['borrow_status']??'Outstanding';if(!in_array($status,['All','Outstanding','Overdue','Closed'],true))$status='Outstanding';?>
 <div class="card"><form class="inline-form"><input type="hidden" name="page" value="borrowed"><label>Status<select name="borrow_status"><?php foreach(['All','Outstanding','Overdue','Closed'] as $s):?><option <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></label><button class="btn secondary">Filter</button><a class="btn ghost" href="?page=borrowed&borrow_status=All">Clear Filters</a></form></div>
 <?php
 $remaining='SUM(b.qty-b.returned_qty-b.converted_qty)';
 $having=$status==='Outstanding'?' HAVING '.$remaining.'>0':($status==='Overdue'?' HAVING '.$remaining.'>0 AND MIN(b.due_date)<CURDATE()':($status==='Closed'?' HAVING '.$remaining.'=0':''));
 $page=max(1,min(100000,(int)($_GET['borrow_page']??1)));
 $sql='SELECT MIN(b.id) id,COALESCE(b.batch_ref,b.borrow_ref) transaction_ref,MIN(b.borrower) borrower,MIN(b.issue_date) issue_date,MIN(b.due_date) due_date,COUNT(*) line_count,SUM(b.qty) qty,SUM(b.returned_qty) returned_qty,SUM(b.converted_qty) converted_qty,'.$remaining.' outstanding,GROUP_CONCAT(DISTINCT b.source_platform ORDER BY b.source_platform SEPARATOR ", ") platforms FROM borrowed_items b GROUP BY COALESCE(b.batch_ref,b.borrow_ref)'.$having.' ORDER BY MAX(b.id) DESC LIMIT 51 OFFSET '.(($page-1)*50);
 $rows=db()->query($sql)->fetchAll();$more=count($rows)>50;$rows=array_slice($rows,0,50);
 ?>
 <div class="card table-wrap"><h3>Borrow Transaction Register</h3><table class="table"><thead><tr><th>Reference / Issue Date</th><th>Borrower</th><th>Product Lines / Platforms</th><th>Issued</th><th>Returned</th><th>Converted</th><th>Outstanding</th><th>Due / Status</th></tr></thead><tbody>
 <?php foreach($rows as $row):$rowStatus=$row['outstanding']>0?($row['due_date']<date('Y-m-d')?'Overdue':'Outstanding'):($row['converted_qty']>0?'Closed / Converted':'Returned');?><tr><td><a href="?page=borrowed&id=<?=(int)$row['id']?>"><?=e($row['transaction_ref'])?></a><br><?=e($row['issue_date'])?></td><td><?=e($row['borrower'])?></td><td><?=(int)$row['line_count']?> product line(s)<br><?=e($row['platforms'])?></td><td><?=(int)$row['qty']?></td><td><?=(int)$row['returned_qty']?></td><td><?=(int)$row['converted_qty']?></td><td><b><?=(int)$row['outstanding']?></b></td><td><?=e($row['due_date'])?><br><?=e($rowStatus)?></td></tr><?php endforeach;?>
 <?php if(!$rows):?><tr><td colspan="8">No borrowed transactions for this filter.</td></tr><?php endif;?></tbody></table>
 <?php $countSql=substr($sql,0,strrpos($sql,' ORDER BY MAX(b.id)'));echo rs8_pagination(rs8_list_total('SELECT COUNT(*) FROM ('.$countSql.') borrowed_groups'),($page-1)*50,50,array_replace($_GET,['page'=>'borrowed','borrow_status'=>$status]),'borrow_page',true);?></div>
 <?php
}
