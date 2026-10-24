<?php
function inventory_brand_options($products=[]){return array_merge(['RS8','SRF','Oils & Liquid Essentials','Lumina Pro','Merchandise','Others'],managed_brand_names());}
function inventory_filter_brand($brand){
 return inventory_brand_aliases()[norm($brand)]??'Others';
}
function inventory_brand_select($products,$selected){
 $html='<label>Brand<select name="brand" data-filter-brand><option value="">All Brands</option>';
 foreach(inventory_brand_options() as $b)$html.='<option value="'.e($b).'"'.($selected===$b?' selected':'').'>'.e($b).'</option>';return $html.'</select></label>';
}
function inventory_brand_products($products,$selected){return $selected===''?$products:array_values(array_filter($products,fn($p)=>inventory_filter_brand($p['brand'])===$selected));}
function inventory_category_select($main,$sub){
 $html='<label>Main Category<select name="main_category" data-filter-main><option value="">All Main Categories</option>';
 foreach(product_category_tree() as $m=>$subs)$html.='<option value="'.e($m).'"'.($main===$m?' selected':'').'>'.e($m).'</option>';
 $html.='<option value="__unassigned"'.($main==='__unassigned'?' selected':'').'>Unassigned</option></select></label><label>Subcategory<select name="subcategory" data-filter-sub><option value="">All Subcategories</option>';
 $choices=$main!==''?(product_category_tree()[$main]??[]):array_merge(...array_values(product_category_tree()));
 foreach($choices as $s)$html.='<option value="'.e($s).'"'.($sub===$s?' selected':'').'>'.e($s).'</option>';return $html.'</select></label>';
}
function inventory_filtered_products($products){
 global $selectedBrand,$selectedMain,$selectedSub;
 return array_values(array_filter($products,fn($p)=>($selectedBrand===''||inventory_filter_brand($p['brand'])===$selectedBrand)&&($selectedMain===''||($selectedMain==='__unassigned'?empty($p['main_category']):($p['main_category']??'')===$selectedMain))&&($selectedSub===''||$p['category']===$selectedSub)));
}
function inventory_filter_condition($alias='p'){
 global $selectedBrand,$selectedMain,$selectedSub;$pdo=db();$conditions=[];
 $brand="CASE LOWER(TRIM($alias.brand))";foreach(inventory_brand_aliases() as $key=>$value)$brand.=' WHEN '.$pdo->quote($key).' THEN '.$pdo->quote($value);$brand.=" ELSE 'Others' END";
 if(($selectedBrand??'')!=='')$conditions[]=$brand.'='.$pdo->quote($selectedBrand);
 if(($selectedMain??'')!=='')$conditions[]=$selectedMain==='__unassigned'?"COALESCE($alias.main_category,'')=''":"$alias.main_category=".$pdo->quote($selectedMain);
 if(($selectedSub??'')!=='')$conditions[]="$alias.category=".$pdo->quote($selectedSub);return $conditions?implode(' AND ',$conditions):'1=1';
}
function inventory_filter_pages(){return ['stocks','products','beginning','platform_inventory','inflow_batch','outflow_batch','platform_file_batch','allocation_override_detail','reconciliation_detail'];}
function inventory_filter_sql($sql){
 global $page;if(!in_array($page??'',inventory_filter_pages(),true)||!preg_match('/\b(?:JOIN|FROM) products p\b/i',$sql))return $sql;
 $condition=inventory_filter_condition('pf');if($condition==='1=1')return $sql;return preg_replace_callback('/\b(JOIN|FROM) products p\b/i',fn($m)=>$m[1].' (SELECT pf.* FROM products pf WHERE '.$condition.') p',$sql);
}
function inventory_product_picker_filters(){return '<div class="picker-filters">'.inventory_brand_select([], '').inventory_category_select('','').'<button type="button" class="btn ghost sm" data-clear-picker-filters>Clear Filters</button></div>';}
function inventory_clear_filter_link(){
 $context=array_intersect_key($_GET,array_flip(['page','platform','history','id','batch']));
 return '<a class="btn ghost" href="?'.e(http_build_query($context)).'">Clear Filters</a>';
}
function inventory_filter_toolbar($page){
 global $selectedBrand,$selectedMain,$selectedSub;
 echo '<div class="toolbar card compact"><form method="get" class="inline-form product-list-filters"><input type="hidden" name="page" value="'.e($page).'">';
 foreach(['platform','q','status','history','id','batch'] as $key)if(isset($_GET[$key])&&is_string($_GET[$key]))echo '<input type="hidden" name="'.e($key).'" value="'.e($_GET[$key]).'">';
 echo inventory_brand_select([],$selectedBrand).inventory_category_select($selectedMain,$selectedSub).'<button class="btn secondary">Filter</button>'.inventory_clear_filter_link().'</form></div>';
}
