<?php
// One column contract for the universal template and existing platform exports.
function order_file_columns($rows,$platform){
 if(!in_array($platform,['Shopee','Lazada','TikTok'],true))throw new Exception('Select a supported order platform.');
 foreach($rows as $rn=>$row){
  $h=array_map('norm',$row);
  $find=function($names)use($h){foreach($names as $name){$i=array_search($name,$h,true);if($i!==false)return $i;}return null;};
  $date=$find(['date']);$order=$find(['order id','ordernumber','order number']);
  $item=$find(['product sku / description','sku reference no.','seller sku','sellersku']);$qty=$find(['quantity']);
  if($date===null||$order===null||$item===null)continue;
  $legacySingleUnit=$platform==='Lazada'&&in_array('ordernumber',$h,true)&&in_array('sellersku',$h,true)&&$qty===null;
  if($qty===null&&!$legacySingleUnit)continue;
  foreach([['date'],['order id','ordernumber','order number'],['product sku / description','sku reference no.','seller sku','sellersku'],['quantity']] as $names){
   if(count(array_filter($h,fn($v)=>in_array($v,$names,true)))>1)throw new Exception('Multiple columns describe the same order field. Use one Date, Order ID, Product SKU / Description and Quantity column.');
  }
  return ['header'=>$rn,'date'=>$date,'order'=>$order,'item'=>$item,'qty'=>$qty,'variation'=>$find(['variation name'])];
 }
 throw new Exception('Required order columns are missing. Use Date, Order ID, Product SKU / Description and Quantity, or a supported platform export. Date is required. Download the standard order template from Outflow.');
}
