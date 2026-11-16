<?php
/** Explanations only. Permission filtering runs before the response is sent. */
function ui_feedback_html($html){return role_filtered_html($html);}
function ui_prompt_catalog(){return [
 'beginning'=>['Beginning Balance is locked','Naka-lock na ang Beginning Balance. Hindi na puwedeng direktang i-edit ang quantity na ito.','Kung may stock discrepancy, gamitin ang Inventory Adjustment at ang kasalukuyang Admin approval process. Mananatili ang original history.'],
 'role'=>['Read-only access','Ang role mo ay may access para tumingin sa page na ito, pero walang pahintulot para baguhin ang mga field na ito.','Makipag-ugnayan sa authorized user kung kailangan ng transaction o correction.'],
 'readonly'=>['Read-only field','Ang field na ito ay hindi direktang nae-edit sa page na ito.','Gamitin ang source record o ang available na transaction workflow. Mananatili ang kasalukuyang validation at permissions.'],
 'calculated'=>['Calculated quantity','Kinukuha ng system ang quantities at variance mula sa recorded movements at selected cutoff.','Tingnan ang Stock Movements o record history. Ang pagbabago ng stock ay dadaan sa tamang transaction; hindi sa report field.'],
 'automatic'=>['Automatic source platform','Ang system ang pumipili ng source ayon sa available stock at kasalukuyang allocation rules.','I-review ang source breakdown bago mag-submit. Hindi maaaring gamitin ang reserved quantity bilang available stock.'],
 'sku'=>['Generated item code','Ang item code ay awtomatikong ginagawa ayon sa SKU format at category sequence.','I-check ang product details. Hindi manu-manong ine-edit ang generated preview.'],
 'validation'=>['Review the validation first','Hindi pa available ang action na ito sa kasalukuyang review.','Tingnan ang validation errors o notice sa page. Itama ang kailangan at i-validate muli. Walang ipo-post sa pagtingin ng paliwanag na ito.'],
 'posted'=>['Final transaction record','Hindi direktang ine-edit ang posted o cancelled transaction. Naka-save ang original history nito.','Tingnan ang record history. Kung kailangan ng correction, sundin ang existing authorized linked correction o reversal process ng transaction.'],
 'approval'=>['Awaiting approval','Ang submitted request ay naka-lock habang hinihintay ang applicable Admin approval.','Admin lang ang final approver. Kung bawal ang self-approval sa request na ito, ibang Admin ang kailangang kumilos. Sundin ang available Return for Correction action kung may mali.'],
 'stock'=>['Available and reserved stock','Hindi lahat ng on-hand quantity ay available: may stock na naka-reserve sa registered orders.','I-review ang available, reserved at requested quantities sa validation. Bawasan ang request o ayusin muna ang kaugnay na reservation. Huwag mag-post nang doble.'],
 'date'=>['Closed inventory date','Hindi puwedeng mag-post sa isang closed inventory date.','Pumili ng open date. Kung kailangang buksan ang closed period, authorized Admin ang gagawa nito kasama ang reason at history.'],
 'duplicate'=>['Existing record / duplicate','Hindi dapat magdagdag ng stock o order registration nang dalawang beses para sa parehong source.','Tingnan ang existing record at validation. Gamitin ang status update o receipt-linking process kung iyon ang kailangan. Huwag gumawa ng panibagong posting para lampasan ang duplicate check.']
 ];}
function ui_prompt_page_notice($page){
 $groups=[
  'beginning'=>['beginning','posted'], 'beginning_review'=>['beginning','validation','posted'],
  'stocks'=>['calculated','stock'], 'platform_inventory'=>['calculated','stock'], 'reports'=>['calculated'],
  'inflow'=>['validation','duplicate','posted'], 'outflow'=>['stock','duplicate','validation','date'],
  'order_monitor'=>['calculated','duplicate'], 'inventory_count'=>['calculated','approval','date'],
  'transfers'=>['stock','posted','date'], 'borrowed'=>['stock','approval','posted'],
  'nonsellable'=>['approval','posted'], 'restricted_release'=>['approval','stock'],
  'stock_batch'=>['approval','posted','stock'], 'manual_receiving'=>['duplicate','posted'],
  'inflow_allocation'=>['calculated','validation','posted'], 'inflow_batch'=>['posted','duplicate'],
  'receipt_review'=>['validation','duplicate'], 'order_return'=>['duplicate','posted'],
  'cancelled_orders'=>['duplicate','posted'], 'warranty'=>['posted','validation'],
  'repackaging_batch'=>['posted','stock'], 'day_closing'=>['date'], 'products'=>['sku']
 ];
 $keys=$groups[$page]??[];if(access_is_viewer()||(!can_action('save_beginning')&&$page==='beginning'))array_unshift($keys,'role');
 if(!$keys)return;$catalog=ui_prompt_catalog();echo '<details class="ui-page-help"><summary>Why is a field or action unavailable?</summary><div class="ui-help-options">';foreach(array_unique($keys) as $key)echo '<button type="button" class="btn ghost sm" data-ui-explain="'.$key.'">'.e($catalog[$key][0]).'</button>';echo '</div><p class="muted small">Read-only values, validation and approval rules protect the transaction history. Select a topic for the next step.</p></details>';
}
function ui_prompt_shell(){
 $config=['page'=>$GLOBALS['page']??'','beginningLocked'=>setting('beginning_locked','0')==='1','catalog'=>ui_prompt_catalog()];
 echo '<dialog class="ui-reason-dialog" id="uiReasonDialog" aria-labelledby="uiReasonTitle" aria-describedby="uiReasonBody uiReasonNext"><button type="button" class="ui-reason-close" data-ui-close aria-label="Close explanation">×</button><span class="ui-reason-icon" aria-hidden="true">ⓘ</span><h2 id="uiReasonTitle"></h2><p id="uiReasonBody"></p><div class="ui-reason-next"><strong>Next step</strong><p id="uiReasonNext"></p></div><div class="modal-actions"><button type="button" class="btn primary" data-ui-close>Understood</button></div></dialog><script type="application/json" id="uiReasonConfig">'.json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
