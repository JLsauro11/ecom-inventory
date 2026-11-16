(() => {
 'use strict';
 const box=document.querySelector('[data-bb-products]');
 if(box){
  const rows=[...box.querySelectorAll('[data-bb-row]')],select=box.querySelector('[data-bb-size]'),summary=box.querySelector('[data-bb-summary]'),buttons=box.querySelector('[data-bb-buttons]'),scroll=box.querySelector('.bb-product-scroll'),form=box.querySelector('[data-bb-form]');
  let size=Number(select.value),page=Number(new URL(location.href).searchParams.get('bb_product_page'))||1,dirty=false,submitting=false;
  function render(){const pages=Math.max(1,Math.ceil(rows.length/size));page=Math.min(pages,Math.max(1,page));rows.forEach((r,i)=>r.hidden=i<(page-1)*size||i>=page*size);summary.textContent=(rows.length?`${(page-1)*size+1}–${Math.min(page*size,rows.length)} of ${rows.length} products`:'0 products')+` · Page ${page} of ${pages}`;buttons.replaceChildren();
   const add=(n,label,disabled=false)=>{const b=document.createElement('button');b.type='button';b.className='btn ghost pagination-button'+(n===page&&label===String(n)?' is-current':'');b.textContent=label;b.disabled=disabled;if(label===String(n)&&n===page)b.setAttribute('aria-current','page');b.onclick=()=>{page=n;render();scroll.scrollTop=0;};buttons.append(b);};
   add(page-1,'Previous',page===1);const nums=[...new Set([1,pages,...Array.from({length:5},(_,i)=>page+i-2).filter(n=>n>=1&&n<=pages)])].sort((a,b)=>a-b);let last=0;nums.forEach(n=>{if(n>last+1){const gap=document.createElement('span');gap.textContent='…';gap.className='pagination-gap';buttons.append(gap);}add(n,String(n));last=n;});add(page+1,'Next',page===pages);
   const url=new URL(location.href);url.searchParams.set('bb_product_page',String(page));url.searchParams.set('bb_products_size',String(size));history.replaceState(history.state,'',url);
  }
  select.onchange=()=>{size=Number(select.value);page=1;render();scroll.scrollTop=0;};render();
  if(form){form.addEventListener('input',()=>{dirty=true;box.querySelector('[data-bb-dirty]').textContent='Unsaved quantities · Includes all product pages.';});form.addEventListener('invalid',event=>{const row=event.target.closest('[data-bb-row]');if(row){page=Math.floor(rows.indexOf(row)/size)+1;render();requestAnimationFrame(()=>event.target.focus());}},true);form.addEventListener('submit',event=>{queueMicrotask(()=>{submitting=!event.defaultPrevented;});});window.addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});}
 }
 document.querySelectorAll('[data-bb-size="bb_history_size"]').forEach(select=>select.addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('bb_history_size',select.value);url.searchParams.set('bb_history_offset','0');location.assign(url);}));
})();
