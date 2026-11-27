<?php
echo '<link rel="stylesheet" href="assets/operations_controls.css?v=5.9.69">';
function correction_stock_effects($plan){
 $rows=[];foreach($plan['platforms']??[] as $m){$p=stock_row($m['pid']);$rows[]=[$p['name']??'Archived Product',$m['platform'],(int)$m['delta']];}
 foreach($plan['nonsellable_outflows']??[] as $r)$rows[]=['Non-Sellable','Restricted',+(int)$r['qty']];
 foreach($plan['non_sellable']??[] as $r)$rows[]=['Non-Sellable','Restricted',-(int)$r['qty']];
 echo '<details class="correction-effects"><summary>View '.count($rows).' stock effect(s)</summary><table class="table correction-effects-table"><tr><th>Product</th><th>Location</th><th>Change</th></tr>';
 foreach($rows as [$name,$location,$delta])echo '<tr><td>'.e($name).'</td><td>'.e($location).'</td><td class="correction-quantity">'.sprintf('%+d',$delta).' pcs</td></tr>';
 if(!$rows)echo '<tr><td colspan="3">No platform or restricted-stock changes.</td></tr>';echo '</table></details>';
}
