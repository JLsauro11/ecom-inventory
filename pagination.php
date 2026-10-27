<?php
/** Shared read-only list navigation; query filters are preserved verbatim. */
function rs8_pagination($total,$offset=0,$size=50,$params=null,$key='offset',$pageBased=false,$note=''){
 $total=max(0,(int)$total);$size=max(1,(int)$size);$offset=max(0,(int)$offset);
 $pages=max(1,(int)ceil($total/$size));$current=intdiv($offset,$size)+1;
 $params=$params??$_GET;unset($params[$key]);
 $url=function($page)use($params,$key,$size,$pageBased){return '?'.http_build_query(array_replace($params,[$key=>$pageBased?$page:($page-1)*$size]));};
 $html='<nav class="rs8-pagination" data-server-pagination aria-label="Table pages"><span class="pagination-summary">'.($total?number_format(min($offset+1,$total)).'–'.number_format(min($offset+$size,$total)).' of '.number_format($total).' records':'0 records').' · Page '.$current.' of '.$pages.'</span><div class="pagination-buttons">';
 $link=function($n,$label,$active=false)use($url){return '<a class="btn ghost pagination-button'.($active?' is-current':'').'" href="'.e($url($n)).'"'.($active?' aria-current="page"':'').'>'.e($label).'</a>';};
 $html.=$current>1?$link($current-1,'Previous'):'<span class="btn ghost pagination-button is-disabled" aria-disabled="true">Previous</span>';
 $numbers=[1,$pages];for($n=max(1,min($current,$pages)-2);$n<=min($pages,max(1,$current)+2);$n++)$numbers[]=$n;
 $numbers=array_unique($numbers);sort($numbers);$last=0;foreach($numbers as $n){if($n>$last+1)$html.='<span class="pagination-gap" aria-hidden="true">…</span>';$html.=$link($n,(string)$n,$n===$current);$last=$n;}
 $html.=$current<$pages?$link($current+1,'Next'):'<span class="btn ghost pagination-button is-disabled" aria-disabled="true">Next</span>';
 return $html.'</div>'.($note!==''?'<span class="pagination-note">'.e($note).'</span>':'').'</nav>';
}
function rs8_list_total($sql,$params=[]){$stmt=db()->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn();}
