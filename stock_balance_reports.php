<?php
function report_is_as_of($key){return in_array($key,['stocks','restricted','platforms','stock_balance_detail'],true);}
function report_public_filters($f){if(report_is_as_of($f['report']??'')){unset($f['from'],$f['to']);}else unset($f['as_of']);return $f;}
function report_period_label($f){return report_is_as_of($f['report']??'')?'As Of '.$f['as_of']:$f['from'].' — '.$f['to'];}
function report_readable_filters($f){$labels=['q'=>'Search','brand'=>'Brand','main_category'=>'Main Category','subcategory'=>'Subcategory','platform'=>'Platform'];$text=[];if(isset($f['balance_filter'])&&$f['balance_filter']!=='all')$text[]='Quantity Filter: '.(sb_filter_labels()[$f['balance_filter']]??'All Balances');foreach($labels as $k=>$label)if(($f[$k]??'')!=='')$text[]=$label.': '.$f[$k];if(!empty($f['product_id'])){$q=db()->prepare('SELECT master_sku,name FROM products WHERE id=?');$q->execute([$f['product_id']]);if($p=$q->fetch())$text[]='Product: '.$p['master_sku'].' — '.$p['name'];}return implode(' · ',$text)?:'All matching items';}
function report_export_rows($r){return !empty($r['totals_row'])?array_merge($r['rows'],[$r['totals_row']]):$r['rows'];}
function stock_balance_breakdown($pid,$date){sb_detail_page($pid,$date);}
