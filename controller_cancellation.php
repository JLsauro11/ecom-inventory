<?php
// Manual status permission is separate from registration, file uploads and reference edits.
function ic_cancel_role(){return access_profile()==='Inventory Controller';}
function ic_manual_require($o,$status){
 if(om_allowed()){om_require();return;}
 if(!ic_cancel_role()||!om_ready())throw new Exception('Manual order status access required.');
 sp_assert_manual($o,$status);
}
function ic_manual_batch_require($kind,$meta,$rows){
 if(!ic_cancel_role()){om_require();return;}
 if($kind!=='Status'||($meta['manual']??false)!==true||!$rows)throw new Exception('Inventory Controller may update an existing order manually. File uploads and order registration require Operations Lead or Admin.');
 $orders=array_values(array_unique(array_map(fn($r)=>(string)($r['order']??''),$rows)));
 if(count($orders)!==1||$orders[0]==='')throw new Exception('Manual status updates cover one existing order only.');
 $o=om_find($meta['platform'],$meta['shop'],$orders[0]);if(!$o)throw new Exception('Register the original order before updating its status.');
 foreach($rows as $row){$status=$row['status']??'';if(!empty($row['booking_only'])||!in_array($status,['To Ship','Shipped','Delivered','Completed','Cancelled','Failed Delivery','Return / Refund'],true))throw new Exception('Choose a supported manual order status.');ic_manual_require($o,$status);}
}
