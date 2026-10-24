<?php
require_once __DIR__.'/order_columns.php';
require_once __DIR__.'/order_columns.php';
function read_order_rows_v5($platform,$path){
 $raw=order_review_rows($platform,$path);$lines=[];$errors=[];
 foreach($raw as $r){try{if($r['order']===''||$r['item']==='')throw new Exception('Order ID and product SKU/description are required.');$r['qty']=whole_number($r['qty'],'Quantity',1);if($r['date']===''||strtotime($r['date'])===false)throw new Exception('A valid order date is required.');unset($r['product_id']);$lines[]=$r;}catch(Throwable $e){$errors[]='Row '.$r['row'].': '.$e->getMessage();}}
 return [$lines,$errors];
}
function import_orders_v5($platform,$file,$uid){return stage_order_upload($platform,$file,$uid);}
function import_error_details($batch){$errors=json_decode($batch['error_json']??'[]',true)?:[];$q=db()->prepare("SELECT order_id,source_item,qty,status FROM unmapped_imports WHERE batch_id=?");$q->execute([$batch['id']]);foreach($q as $r)$errors[]='Unmapped item: '.$r['order_id'].' / '.$r['source_item'].' / '.$r['qty'].' pcs; current mapping status: '.$r['status'];return $errors;}
function order_import_review_html($batch){
 $result=json_decode($batch['result_json']??'[]',true)?:[];$warnings=$result['stock_warnings']??[];$duplicates=$result['duplicate_details']??[];
 $html='<div class="card"><h3>Upload Results</h3><div class="mini-kpis">';
 foreach(['Rows Read'=>'rows_read','Posted Orders'=>'imported_count','Unmapped'=>'unmapped_count','Duplicates Skipped'=>'duplicate_count','Errors'=>'error_count'] as $label=>$key)$html.='<div><span>'.e($label).'</span><strong>'.(int)($batch[$key]??0).'</strong></div>';
 $html.='</div>';if(!empty($batch['error_count'])||!empty($batch['unmapped_count']))$html.='<a class="btn secondary" href="?page=import_detail&id='.(int)$batch['id'].'">Review Errors &amp; Unmapped Items</a>';$html.='</div>';
 if($warnings){$html.='<div class="card table-wrap"><h3>Stock Shortage / Allocation Review</h3><p class="muted">These orders were posted. Negative sellable balances appear as Reconcile in Current Stocks. Source platform stock may also be negative. Each warning records its transaction date and posting time; review the affected balances.</p><table class="table"><tr><th>Transaction Date</th><th>Posted At</th><th>Platform</th><th>Order ID</th><th>Product</th><th>Total Before</th><th>Qty Out</th><th>Total After</th><th>Platform Before</th><th>Platform After</th></tr>';foreach($warnings as $r)$html.='<tr><td>'.e($r['date']??'').'</td><td>'.e($result['submitted_at']??$batch['created_at']??'').'</td><td>'.e($batch['platform']).'</td><td>'.e($r['order']).'</td><td>'.e($r['item']).'</td><td>'.(int)$r['before'].'</td><td>'.(int)$r['qty'].'</td><td><b>'.(int)$r['after'].'</b></td><td>'.(int)($r['platform_before']??0).'</td><td>'.(int)$r['platform_after'].'</td></tr>';$html.='</table></div>';}
 if($duplicates){$html.='<div class="card table-wrap"><h3>Duplicates Skipped</h3><p class="muted">Previously posted orders were skipped without another stock deduction. The upload is Complete when all other rows are posted; only unresolved items make it Incomplete.</p><table class="table"><tr><th>Order ID</th><th>Product</th><th>Qty Skipped</th></tr>';foreach($duplicates as $r)$html.='<tr><td>'.e($r['order']).'</td><td>'.e($r['item']).'</td><td>'.(int)$r['qty'].'</td></tr>';$html.='</table></div>';}
 return $html;
}

require_once __DIR__.'/order_review_v51.php';

