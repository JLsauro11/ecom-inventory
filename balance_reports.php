<?php
/** As-of reporting only. No inventory, allocation, or transaction writes. */
function sb_report_keys(){return ['stocks','platforms','restricted','product_master','stock_balance_detail'];}
function sb_balance_filter($value){return in_array($value,['all','negative','zero','non'],true)?$value:'all';}
function sb_filter_labels(){return ['all'=>'All Balances','negative'=>'Negative Balance','zero'=>'Zero Physical Quantity','non'=>'With Non-Sellable'];}
function sb_matches_balance($balance,$filter){switch($filter){case 'negative':return min($balance['sellable'],$balance['restricted'],$balance['physical'])<0;case 'zero':return $balance['physical']===0;case 'non':return $balance['restricted']>0;default:return true;}}
function sb_summary($balance){return ['Ending Sellable (pcs)'=>(int)$balance['sellable'],'Ending Non-Sellable (pcs)'=>(int)$balance['restricted'],'Total Physical (pcs)'=>(int)$balance['physical']];}
function sb_page_size($value=null){$n=(int)($value??$_GET['per_page']??10);return in_array($n,[10,20,30,50],true)?$n:10;}
function sb_offset($count,$size,$offset=null){return min(intdiv(max(0,(int)($offset??$_GET['offset']??0)),$size)*$size,max(0,(int)(ceil($count/$size)-1)*$size));}
function sb_context($f,$size,$offset){$out=['sb_report'=>$f['report'],'sb_per_page'=>$size,'sb_offset'=>$offset];foreach(['product_id','brand','main_category','subcategory','balance_filter','platform','product_status'] as $key)$out['sb_'.$key]=$f[$key]??'';return $out;}
function sb_return_query($input){
 $report=$input['sb_report']??'stocks';if(!in_array($report,['stocks','platforms','restricted','product_master'],true))$report='stocks';$params=['report'=>$report,'as_of'=>$input['as_of']??date('Y-m-d')];
 foreach(['product_id','brand','main_category','subcategory','balance_filter','platform','product_status'] as $key){$v=$input['sb_'.$key]??'';$params[$key]=is_scalar($v)?$v:'';}
 $f=report_filters($params);return array_replace(report_public_filters($f),['page'=>'reports','per_page'=>sb_page_size($input['sb_per_page']??10),'offset'=>max(0,(int)($input['sb_offset']??0))]);
}
function sb_source_rows($pid,$date){
 $q=db()->prepare('SELECT n.*,COALESCE((SELECT SUM(l.qty) FROM nonsellable_release_links l JOIN nonsellable_outflows o ON o.id=l.release_id WHERE l.non_sellable_id=n.id AND o.txn_date<=?),0) released FROM non_sellable n WHERE n.product_id=? AND n.txn_date<=? AND n.qty>0 ORDER BY n.txn_date,n.id');$q->execute([$date,$pid,$date]);$rows=[];
 foreach($q as $n){if($n['reversed_by_request']){$check=db()->prepare("SELECT DATE(approved_at) FROM transaction_reversals WHERE id=? AND status='Approved'");$check->execute([$n['reversed_by_request']]);$reversed=$check->fetchColumn();if($reversed&&$reversed<=$date)continue;}$remaining=(int)$n['qty']-(int)$n['released'];if($remaining<=0)continue;$rows[]=['id'=>(int)$n['id'],'condition'=>$n['condition_type'],'received'=>$n['txn_date'],'quantity'=>(int)$n['qty'],'released'=>(int)$n['released'],'remaining'=>$remaining,'reference'=>$n['order_id']??''];}
 return $rows;
}
function sb_notes(){return [
 'As Of includes posted transaction dates through the selected date. Today includes entries posted so far. Quantities are in pieces; totals include every matching product across every page.',
 'Sellable includes reserved stock still in the warehouse and is not historical Available to Sell. Current reservations are not mixed into historical totals. Physical = Sellable + Non-Sellable.',
 'Beginning Balance uses its recorded update date. Earlier overwritten balances cannot be reconstructed. Later backdated postings may change a regenerated historical report; saved Daily Closing Records retain their versions.',
 'Inactive products remain included unless a Product Master status filter is applied. Reports do not adjust stocks or allocations.'
 ];}
function sb_build_detail($f,$products){
 if(count($products)!==1||!$f['product_id'])throw new Exception('Select one product for the stock balance breakdown.');$p=$products[0];$pid=(int)$p['id'];$events=inventory_ledger($pid);$start=min($f['as_of'],$events[0]['date']??$f['as_of']);$roll=overview_rollforward($events,$start,$f['as_of']);$running=overview_empty_balance();$rows=[];$links=[];
 foreach($events as $event){if($event['date']>$f['as_of'])continue;$s=(int)$event['sellable'];$n=(int)$event['restricted'];$running['sellable']+=$s;$running['restricted']+=$n;$running['physical']=$running['sellable']+$running['restricted'];$rows[]=[$event['date'],$event['time'],str_replace(['Restricted release','Restricted'],['Non-Sellable Release','Non-Sellable'],$event['type']),$event['ref'],$s,$n,$running['sellable'],$running['restricted'],$running['physical']];$links[]=report_source_url($event,$pid);}
 $sources=sb_source_rows($pid,$f['as_of']);
 return ['title'=>'Stock Balance Breakdown','headers'=>['Date','Recorded At','Movement','Reference','Sellable Change (pcs)','Non-Sellable Change (pcs)','Sellable Balance (pcs)','Non-Sellable Balance (pcs)','Physical Balance (pcs)'],'rows'=>$rows,'links'=>$links,'summary'=>sb_summary($running),'filters'=>$f,'totals_row'=>null,'generated'=>date('Y-m-d H:i:s'),'user'=>current_user()['name'],'notes'=>sb_notes(),'balance_report'=>['product'=>$p,'rollforward'=>$roll,'roll_from'=>$start,'sources'=>$sources,'source_remaining'=>array_sum(array_column($sources,'remaining'))]];
}
function sb_build_report($f,$products){
 $key=$f['report'];if($key==='stock_balance_detail')return sb_build_detail($f,$products);
 $rows=[];$links=[];$details=[];$total=overview_empty_balance();$warnings=[];$sourceRows=[];$platformTotals=[0,0,0,0,0,0,0];
 if($key==='product_master'){
  foreach($products as $p){$rows[]=[$p['master_sku'],$p['name'],inventory_filter_brand($p['brand']),$p['main_category'],$p['category'],$p['variant'],(int)$p['active']?'Active':'Inactive'];$links[]='index.php?page=product_detail&id='.(int)$p['id'];$details[]=['id'=>(int)$p['id'],'active'=>(int)$p['active']];}
  return ['title'=>'Product Master / Item Codes','headers'=>['Master SKU','Product Description','Brand','Main Category','Subcategory','Variant','Status'],'rows'=>$rows,'links'=>$links,'summary'=>[],'filters'=>$f,'totals_row'=>null,'generated'=>date('Y-m-d H:i:s'),'user'=>current_user()['name'],'notes'=>['Current product descriptions and status. Dates and quantity filters do not apply to this master list; it is not a historical product-master snapshot.'],'balance_report'=>['details'=>$details,'warnings'=>[],'sources'=>[]]];
 }
 foreach($products as $p){$pid=(int)$p['id'];$events=inventory_ledger($pid);$start=min($f['as_of'],$events[0]['date']??$f['as_of']);$balance=overview_rollforward($events,$start,$f['as_of'])['closing'];if(!sb_matches_balance($balance,$f['balance_filter']))continue;
  foreach($total as $k=>$v)$total[$k]+=$balance[$k];$details[]=['id'=>$pid,'active'=>(int)$p['active'],'negative'=>min($balance)<0,'balance'=>$balance];
  if($key==='platforms'){$allocations=[];foreach(['Lazada','Shopee','TikTok','Website'] as $platform)$allocations[]=report_platform_balance($pid,$platform,$f['as_of']);$sum=array_sum($allocations);$difference=$balance['sellable']-$sum;$values=[...$allocations,$sum,$balance['sellable'],$difference];foreach($values as $i=>$v)$platformTotals[$i]+=$v;$rows[]=!empty($f['platform'])?[$p['master_sku'],$p['name'],$f['platform'],$allocations[array_search($f['platform'],['Lazada','Shopee','TikTok','Website'],true)]]:[$p['master_sku'],$p['name'],...$values];if(min($allocations)<0||$difference!==0)$warnings[]=$pid;}
  elseif($key==='restricted'){$rows[]=[$p['master_sku'],$p['name'],$balance['restricted']];foreach(sb_source_rows($pid,$f['as_of']) as $source)$sourceRows[]=[$p['master_sku'],$p['name'],'NS-'.$source['id'],$source['condition'],$source['received'],$source['quantity'],$source['released'],$source['remaining'],$source['reference']];}
  else $rows[]=[$p['master_sku'],$p['name'],$balance['sellable'],$balance['restricted'],$balance['physical']];
  $links[]='index.php?page=stock_balance_detail&id='.$pid.'&as_of='.rawurlencode($f['as_of']);
 }
 $headers=$key==='platforms'?['Master SKU','Product','Lazada (pcs)','Shopee (pcs)','TikTok (pcs)','Website (pcs)','Platform Total (pcs)','Sellable (pcs)','Alignment Difference (pcs)']:($key==='restricted'?['Master SKU','Product','Non-Sellable Quantity (pcs)']:['Master SKU','Product','Ending Sellable (pcs)','Ending Non-Sellable (pcs)','Total Physical (pcs)']);
 if($key==='platforms'&&!empty($f['platform']))$headers=['Master SKU','Product','Platform','Platform Qty'];
 $totals=$key==='platforms'?['','Filtered Total',...$platformTotals]:($key==='restricted'?['','Ledger Non-Sellable Total',$total['restricted']]:['','Filtered Total',...array_values($total)]);
 if($key==='platforms'&&!empty($f['platform']))$totals=['','Filtered Total','',$platformTotals[array_search($f['platform'],['Lazada','Shopee','TikTok','Website'],true)]];
 $notes=sb_notes();if($key==='platforms')$notes[]='Historical platform allocations are reconstructed from current allocations and dated stock events. Dates preceding platform-event tracking use the legacy opening allocation. Negative allocations and alignment differences require review; this report does not rebalance stocks.';
 if($key==='restricted')$notes[]='Ledger totals and source-record remaining quantities are shown separately. Legacy unlinked releases can leave individual-source availability unverified; source totals are never forced to match the ledger.';
 return ['title'=>['stocks'=>'Stock Balance','platforms'=>'Platform Inventory','restricted'=>'Non-Sellable'][$key],'headers'=>$headers,'rows'=>$rows,'links'=>$links,'summary'=>sb_summary($total),'filters'=>$f,'totals_row'=>$totals,'generated'=>date('Y-m-d H:i:s'),'user'=>current_user()['name'],'notes'=>$notes,'balance_report'=>['details'=>$details,'warnings'=>$warnings,'sources'=>$sourceRows,'source_remaining'=>array_sum(array_column($sourceRows,7))]];
}
