<?php
// A shared reference groups independently tracked product/platform lines.
function borrow_multiple_ready(){
 global $CFG;
 $q=db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='borrowed_items' AND COLUMN_NAME='batch_ref'");
 $q->execute([$CFG['db_name']]);return (bool)$q->fetchColumn();
}
function migrate_borrow_multiple(){
 if(!process_ready()||setting('complete_update_version','')!=='5.3')throw new Exception('Install and run the V5.3 upgrade before this update.');
 if(!borrow_multiple_ready())db()->exec('ALTER TABLE borrowed_items ADD COLUMN batch_ref VARCHAR(100) NULL');
 $q=db()->query("SHOW INDEX FROM borrowed_items WHERE Key_name='borrow_batch_ref'");
 if(!$q->fetch())db()->exec('ALTER TABLE borrowed_items ADD INDEX borrow_batch_ref(batch_ref)');
 set_setting('borrow_multiple_version','5.3.2');
}
function borrow_issue_multiple($input){
 if(!can_action('borrow_issue'))throw new Exception('Your account cannot issue borrowed stock.');
 if(!borrow_multiple_ready())throw new Exception('Run the Multi Product Borrowed Items upgrade first.');
 return process_transaction(function()use($input){
  $items=$input['items']??null;
  if(!is_array($items)||count($items)<1||count($items)>300)throw new Exception('Use up to 100 products (300 automatic platform source lines).');
  $date=process_date($input['txn_date']??'');$due=(string)($input['due_date']??'');$d=DateTime::createFromFormat('!Y-m-d',$due);
  if(!$d||$d->format('Y-m-d')!==$due||$due<$date)throw new Exception('Due date must be on or after the issue date.');
  $borrower=trim($input['borrower']??'');if($borrower===''||strlen($borrower)>150)throw new Exception('Enter the borrower name (up to 150 characters).');
  $reason=process_reason($input['reason']??'');$ref=trim($input['borrow_ref']??'');
  if(!preg_match('/^[A-Za-z0-9_-]{6,90}$/D',$ref))throw new Exception('Invalid borrow reference. Refresh the form.');
  $lines=[];$seen=[];$pids=[];
  foreach(array_values($items) as $i=>$item){
   if(!is_array($item))throw new Exception('Invalid product line.');
   $pid=whole_number($item['product_id']??'','Line '.($i+1).' product',1);
   $platform=stock_platform($item['source_platform']??'');$qty=whole_number($item['qty']??'','Line '.($i+1).' quantity',1);
   $key=$pid.'|'.$platform;if(isset($seen[$key]))throw new Exception('The same product and source platform appear more than once. Combine their quantities in one line.');
   $seen[$key]=true;$pids[$pid]=$pid;$lines[]=['product_id'=>$pid,'platform'=>$platform,'qty'=>$qty];
  }
  // Deterministic locks also protect cumulative changes to a shared product.
  sort($pids,SORT_NUMERIC);$active=db()->prepare('SELECT id FROM products WHERE id=? AND active=1 FOR UPDATE');
  foreach($pids as $pid){$active->execute([$pid]);if(!$active->fetchColumn())throw new Exception('Select an active product for every line.');check_platform_alignment($pid);}
  $prior=db()->prepare('SELECT id FROM borrowed_items WHERE batch_ref=? OR borrow_ref=? FOR UPDATE');$prior->execute([$ref,$ref]);
  if($prior->fetchColumn())throw new Exception('This borrow reference has already been issued. No additional stock was deducted.');
  $uid=current_user()['id'];$ids=[];$negative=false;
  foreach($lines as $i=>$line){
   $pid=$line['product_id'];$platform=$line['platform'];$qty=$line['qty'];$lineRef=$ref.'-L'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT);
   db()->prepare('INSERT INTO borrowed_items(borrow_ref,batch_ref,product_id,source_platform,borrower,qty,issue_date,due_date,reason,user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$lineRef,$ref,$pid,$platform,$borrower,$qty,$date,$due,$reason,$uid]);$id=(int)db()->lastInsertId();$ids[]=$id;
   db()->prepare('INSERT INTO outflows(txn_date,platform,order_id,product_id,source_item,qty,type,dedupe_key,remarks,user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$date,$platform,$ref,$pid,'Borrowed: '.$borrower,$qty,'Borrowed Issue',hash('sha256','borrow|'.$lineRef),$lineRef.'; '.$reason,$uid]);$out=(int)db()->lastInsertId();
   db()->prepare('UPDATE borrowed_items SET outflow_id=? WHERE id=?')->execute([$out,$id]);
   $lineNegative=change_platform_stock($platform,$pid,-$qty,$ref,$date);$negative=$lineNegative||$negative;check_platform_alignment($pid);
   db()->prepare('INSERT INTO borrowed_events(borrow_id,event_type,qty,reference_no,reason,user_id,txn_date) VALUES(?,?,?,?,?,?,?)')->execute([$id,'Issued',$qty,$lineRef,$reason,$uid,$date]);
  }
  audit('Issue borrowed products','Borrow',$ref,count($lines).' product/platform lines issued to '.$borrower.'; due '.$due.'; all lines committed together.');
  return ['id'=>$ids[0],'ids'=>$ids,'batch_ref'=>$ref,'negative'=>$negative];
 });
}
function borrow_group($id){
 $first=borrow_get($id);if(empty($first['batch_ref']))return [$first];
 $q=db()->prepare('SELECT b.*,p.name,p.master_sku FROM borrowed_items b JOIN products p ON p.id=b.product_id WHERE b.batch_ref=? ORDER BY b.id');$q->execute([$first['batch_ref']]);return $q->fetchAll();
}
