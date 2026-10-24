<?php
function outflow_global_rule($platform,$returnTarget=null){
 $values=['Lazada'=>setting('default_lazada_pct','20'),'Shopee'=>setting('default_shopee_pct','40'),'TikTok'=>setting('default_tiktok_pct','40')];
 echo '<div class="card rule-card" id="global-allocation-rule"><div class="card-head"><div><div class="eyebrow">GLOBAL RULE</div><h3>Global Allocation Rule</h3><span class="muted small">Default percentages for incoming stock. Saving this rule does not change existing platform inventory.</span></div></div>';
 if(can_action('save_default_allocation')){
  echo '<form method="post" action="actions.php" class="stack" data-confirm="Save the global allocation rule? Existing inventory will remain unchanged."><input type="hidden" name="action" value="save_default_allocation"><input type="hidden" name="return" value="'.e($returnTarget??('outflow&platform='.$platform)).'"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><div class="allocation-inputs">';
  foreach($values as $p=>$value)echo '<label>'.platform_label($p).'<input aria-label="'.e($p).' global percentage" type="number" step="0.01" min="0" max="100" name="'.strtolower($p).'_pct" value="'.e($value).'" required><span>%</span></label>';
  echo '</div><div class="modal-actions"><span class="muted small">Total must be exactly 100%. All incoming good items must be allocated to platforms.</span><button class="btn secondary">Save Global Rule</button></div></form>';
 }else{echo '<div class="mini-kpis">';foreach($values as $p=>$value)echo '<div><span>'.platform_label($p).'</span><strong>'.e($value).'%</strong></div>';echo '</div>';}
 echo '</div>';
}
