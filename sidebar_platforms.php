<?php
function sidebar_platform_inventory($page){
 $active=$page==='platform_inventory';$selected=$_GET['platform']??'Lazada';if(!in_array($selected,['Lazada','Shopee','TikTok','Website'],true))$selected='Lazada';
 echo '<details class="sidebar-platforms" data-sidebar-platforms data-current="'.($active?'true':'false').'" open><summary><span class="sidebar-platform-icon" aria-hidden="true">▦</span><span class="sidebar-platform-title">Platform Inventory</span><span class="sidebar-platform-chevron" aria-hidden="true">›</span></summary><div class="sidebar-platform-links">';
 foreach(['Lazada','Shopee','TikTok','Website'] as $platform){$current=$active&&$selected===$platform;$label=$platform==='Website'?'Website Selling':$platform;$mark=$platform==='Website'?'<span class="sidebar-website-symbol" aria-hidden="true">◎</span>':'<img src="assets/logos/'.strtolower($platform).'.svg" alt="" width="28" height="20">';echo '<a class="sidebar-platform-link'.($current?' active':'').'" href="?page=platform_inventory&amp;platform='.$platform.'"'.($current?' aria-current="page"':'').'><span class="sidebar-platform-mark">'.$mark.'</span><span class="sidebar-platform-name">'.e($label).'</span></a>';}
 echo '</div></details>';
}
