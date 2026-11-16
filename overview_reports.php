<?php
/** Read-only roll-forward of the existing posted inventory ledger. */
function overview_categories(){return [
 'beginning'=>'Beginning Balance Added',
 'receipts'=>'Physical Receipts · purchases / actual returns',
 'dispatch'=>'Dispatched Marketplace / Website Orders',
 'releases'=>'Other Physical Releases · manual / borrowed',
 'to_nonsellable'=>'Moved to Non-Sellable',
 'recovered'=>'Recovered to Sellable',
 'disposal'=>'Non-Sellable Disposal',
 'ns_releases'=>'Other Non-Sellable Physical Releases',
 'adjustment'=>'Net Inventory Adjustment',
 'reclassification'=>'Non-Sellable Condition Reclassification',
 'other'=>'Other Posted Movements / Linked Reversals',
 'transfers'=>'Platform Transfers / Redistribution · no warehouse change'
 ];}
function overview_category($event){
 $source=$event['source'];$type=mb_strtolower($event['type']);$s=(int)$event['sellable'];$n=(int)$event['restricted'];
 if($source==='beginning')return 'beginning';
 if(str_contains($type,'reversal'))return 'other';
 if(str_contains($type,'count adjustment'))return 'adjustment';
 if(in_array($source,['transfers','inventory_count'],true)&&!$s&&!$n)return 'transfers';
 if(str_contains($type,'reclassification'))return 'reclassification';
 if(str_contains($type,'ready for sale')&&in_array($source,['inflows','nonsellable_outflows'],true))return 'recovered';
 if($source==='non_sellable'&&$s<0&&$n>0&&$s+$n===0)return 'to_nonsellable';
 if(($source==='inflows'&&$s>0)||($source==='non_sellable'&&$s===0&&$n>0))return 'receipts';
 if($source==='outflows'&&$s<0){
  return $event['type']==='Outflow · Order'&&in_array($event['platform'],['Shopee','Lazada','TikTok','Website'],true)?'dispatch':'releases';
 }
 if($source==='nonsellable_outflows'&&$n<0)return str_contains($type,'disposal')?'disposal':'ns_releases';
 return 'other';
}
function overview_empty_balance(){return ['sellable'=>0,'restricted'=>0,'physical'=>0];}
function overview_empty_rollforward(){return ['opening'=>overview_empty_balance(),'closing'=>overview_empty_balance(),'movements'=>array_fill_keys(array_keys(overview_categories()),overview_empty_balance())];}
function overview_rollforward($events,$from,$to){
 $result=overview_empty_rollforward();
 foreach($events as $event){if($event['date']>$to)continue;
  // Derive physical from the two ledger components, so internal transfers cancel.
  $delta=['sellable'=>(int)$event['sellable'],'restricted'=>(int)$event['restricted'],'physical'=>(int)$event['sellable']+(int)$event['restricted']];
  foreach($delta as $k=>$v)$result['closing'][$k]+=$v;
  if($event['date']<$from){foreach($delta as $k=>$v)$result['opening'][$k]+=$v;continue;}
  $category=overview_category($event);foreach($delta as $k=>$v)$result['movements'][$category][$k]+=$v;
 }
 return $result;
}
function overview_merge_rollforward(&$total,$part){foreach(['opening','closing'] as $key)foreach($part[$key] as $k=>$v)$total[$key][$k]+=$v;foreach($part['movements'] as $category=>$balance)foreach($balance as $k=>$v)$total['movements'][$category][$k]+=$v;}
function overview_matrix_rows($roll,$f){
 $values=fn($b)=>[(int)$b['sellable'],(int)$b['restricted'],(int)$b['physical']];
 $rows=[['Opening Balance · before '.$f['from'],...$values($roll['opening'])]];
 foreach(overview_categories() as $key=>$label)$rows[]=[$label,...$values($roll['movements'][$key])];
 $rows[]=['Ending Balance · '.$f['to'],...$values($roll['closing'])];return $rows;
}
function overview_build_report($f,$products){
 $f['report']='overview';$rows=[];$links=[];$details=[];$total=overview_empty_rollforward();
 foreach($products as $p){$pid=(int)$p['id'];$roll=overview_rollforward(inventory_ledger($pid),$f['from'],$f['to']);overview_merge_rollforward($total,$roll);
  $rows[]=[$p['master_sku'],$p['name'],$roll['opening']['sellable'],$roll['closing']['sellable']-$roll['opening']['sellable'],$roll['closing']['sellable'],$roll['closing']['restricted'],$roll['closing']['physical']];
  $details[]=['product_id'=>$pid,'sku'=>$p['master_sku'],'name'=>$p['name'],'rollforward'=>$roll];
  $links[]='index.php?'.http_build_query(array_replace(report_public_filters($f),['page'=>'reports','report'=>'movement','product_id'=>$pid,'platform'=>'','reference'=>'','movement'=>'']));
 }
 $summary=['Ending Sellable (pcs)'=>$total['closing']['sellable'],'Ending Non-Sellable (pcs)'=>$total['closing']['restricted'],'Total Physical (pcs)'=>$total['closing']['physical']];
 $totalsRow=['','Filtered Total',$total['opening']['sellable'],$total['closing']['sellable']-$total['opening']['sellable'],...array_values($summary)];
 return ['title'=>'Inventory Operations Summary','headers'=>['Master SKU','Product','Opening Sellable (pcs)','Net Sellable Movement (pcs)','Ending Sellable (pcs)','Ending Non-Sellable (pcs)','Total Physical (pcs)'],'rows'=>$rows,'links'=>$links,'summary'=>$summary,'filters'=>$f,'totals_row'=>$totalsRow,'generated'=>date('Y-m-d H:i:s'),'user'=>current_user()['name'],
  'overview'=>['rollforward'=>$total,'details'=>$details],
  'notes'=>[
   'Transaction-date basis; quantities in pieces. Totals include every matching product across all pages. Only posted ledger entries affect stock; order reservations do not deduct physical stock.',
   'Beginning Balance uses its recorded update date and is shown separately from physical receipts. Earlier overwritten beginning balances cannot be reconstructed.',
   'Condition transfers and platform reallocations do not change total physical stock. Returns add stock after actual receipt. Linked inverse reversals remain in the ledger and are included under Other Posted Movements / Linked Reversals.',
   'Opening Balance plus all signed movements equals Ending Balance. Later backdated postings may change a regenerated historical report; saved Daily Closing Records retain their versions.'
  ]];
}
