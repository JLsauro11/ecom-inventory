<?php
function bb_size($key){$n=(int)($_GET[$key]??10);return in_array($n,[10,20,30,50],true)?$n:10;}
function bb_size_label($key,$size){$s='<label class="bb-size"><select data-bb-size="'.e($key).'" aria-label="'.($key==='bb_history_size'?'History':'Product').' rows per page">';foreach([10,20,30,50] as $n)$s.='<option value="'.$n.'"'.($size===$n?' selected':'').'>'.$n.'</option>';return $s.'</select><span>Rows per page</span></label>';}
function beginning_products_workspace($products,$locked){
 $editable=!$locked&&can_action('save_beginning');$size=bb_size('bb_products_size');$balances=db()->query('SELECT product_id,qty FROM beginning_balances')->fetchAll(PDO::FETCH_KEY_PAIR);
 echo '<div class="card bb-products" data-bb-products><div class="card-head"><h3>Beginning Balance Products</h3><span>'.count($products).' products</span></div><p class="bb-hint">'.($editable?'Blank quantity: keep existing balance. Enter 0 to set zero. Values stay entered when changing product pages.':($locked?'Locked · Select a quantity to see why it cannot be edited.':'Read-only · Your role cannot change beginning balances.')).'</p>';
 if($editable)echo '<form method="post" action="actions.php" data-bb-form><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="save_beginning"><input type="hidden" name="return" value="beginning">';
 echo '<div class="bb-product-scroll" tabindex="0" role="region" aria-label="Beginning balance products"><table class="table" data-server-paginated="1"><thead><tr><th>Product</th><th>Brand</th><th>Beginning Qty</th></tr></thead><tbody>';
 foreach($products as $p){$qty=(int)($balances[$p['id']]??0);echo '<tr data-bb-row><td>'.e($p['name']).'</td><td>'.e(inventory_filter_brand($p['brand'])).'</td><td><input class="qty-input" aria-label="Beginning quantity for '.e($p['name']).'" type="number" min="0" step="1" value="'.$qty.'" '.($editable?'name="qty['.(int)$p['id'].']"':'disabled data-ui-reason="'.($locked?'beginning':'role').'"').'></td></tr>';}
 if(!$products)echo '<tr><td colspan="3">No matching products.</td></tr>';
 echo '</tbody></table></div><nav class="rs8-pagination" data-bb-pager aria-label="Beginning product pages"><div class="pagination-meta"><span class="pagination-summary" data-bb-summary aria-live="polite">'.count($products).' products</span>'.bb_size_label('bb_products_size',$size).'</div><div class="pagination-buttons" data-bb-buttons></div></nav>';
 if($editable)echo '<div class="bb-submit"><span class="muted small" data-bb-dirty>Submission includes quantities from all product pages.</span><button class="btn primary">Submit &amp; Apply Beginning Balance</button></div></form>';
 echo '</div>';
}
