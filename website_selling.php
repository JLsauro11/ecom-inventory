<?php
// Website is a separate inventory/order namespace, using the supplied TikTok workbook format.
function website_defaults(){
 $v=[(float)setting('default_lazada_pct','20'),(float)setting('default_shopee_pct','40'),(float)setting('default_tiktok_pct','40')];$w=setting('default_website_pct',null);
 if($w===null&&$v==[20,40,40])return [20.0,35.0,35.0,10.0];
 return array_merge($v,[(float)($w??0)]);
}
function website_allocation_percentages($pid){$q=db()->prepare('SELECT lazada_pct,shopee_pct,tiktok_pct FROM allocation_rules WHERE product_id=?');$q->execute([(int)$pid]);if($r=$q->fetch()){$v=array_values(array_map('floatval',$r));$v[]=(float)setting('website_product_pct_'.(int)$pid,'0');if(min($v)>=0&&max($v)<=100&&abs(array_sum($v)-100)<0.0000001)return $v;throw new Exception('Review the saved product allocation percentages; all four platforms must total 100%.');}return website_defaults();}
function website_allocation_quantity($r){return (int)$r['sellable_qty']-(int)$r['lazada_qty']-(int)$r['shopee_qty']-(int)$r['tiktok_qty']-(int)$r['unallocated_qty'];}
function website_assert_allocation_available($pid,$quantities){foreach($quantities as $platform=>$qty)if(function_exists('om_ready')&&om_ready()&&om_reserved($pid,$platform)>0&&(int)$qty<om_reserved($pid,$platform))throw new Exception('Allocation would consume reserved '.$platform.' stock. Complete/cancel the affected orders or review the allocation first.');}
