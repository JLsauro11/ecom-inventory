/* Shared navigation for complete, already-loaded registers. Server lists keep their own limits. */
(() => {
 'use strict';
 const content=document.querySelector('.content');if(!content)return;
 content.querySelectorAll('[data-server-page-size]').forEach(select=>select.addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('per_page',select.value);url.searchParams.set('offset','0');url.searchParams.set('om_offset','0');if((url.searchParams.get('page')||'dashboard')==='dashboard')url.searchParams.set('activity_offset','0');location.assign(url.href);}));
 const module=new URL(location.href).searchParams.get('page')||'dashboard';const adjustable=['products','platform_inventory'].includes(module);const sizes=module==='platform_inventory'?[10,20,30,50,100]:[10,20,30,50];
 const tables=Array.from(content.querySelectorAll('table.table'));
 // A server pager always follows its table, possibly outside the scroll/card wrapper.
 content.querySelectorAll('[data-server-pagination]').forEach(nav=>{
  const table=tables.filter(t=>Boolean(t.compareDocumentPosition(nav)&Node.DOCUMENT_POSITION_FOLLOWING)).pop();
  if(table)table.dataset.serverPaginated='1';
 });
 tables.forEach((table,index)=>{
  if(adjustable){const headings=Array.from(table.querySelectorAll('th')).map(th=>th.textContent);if(module==='products'&&!headings.some(h=>/master sku/i.test(h)))return;if(module==='platform_inventory'&&!headings.some(h=>/allocated qty/i.test(h)))return;}
  if(table.dataset.serverPaginated||table.closest('form,dialog,template,[role="dialog"]')||table.querySelector('table'))return;
  // Never divide a shared entry form or a correction editor into hidden required fields.
  const controls=Array.from(table.querySelectorAll('input,select,textarea'));
  if(controls.some(el=>!el.closest('form')||!el.closest('tr')?.contains(el.closest('form'))))return;
  const rows=Array.from(table.rows).filter(row=>row.closest('table')===table&&!row.closest('thead,tfoot')&&row.querySelector('td')&&!row.querySelector('td[colspan]'));
  let size=adjustable?(sizes.includes(Number(new URL(location.href).searchParams.get('per_page')))?Number(new URL(location.href).searchParams.get('per_page')):10):50;if(!adjustable&&rows.length<size)return;
  const key='table_page_'+index,url=new URL(location.href);let totalPages=Math.max(1,Math.ceil(rows.length/size));
  let page=Math.min(totalPages,Math.max(1,parseInt(url.searchParams.get(key),10)||1));
  const nav=document.createElement('nav');nav.className='rs8-pagination';nav.setAttribute('aria-label','Table pages');
  // Keep the footer outside horizontal scrolling where a dedicated table wrapper exists.
  const wrapper=table.parentElement.classList.contains('table-wrap')?table.parentElement:null;
  if(wrapper&&!wrapper.classList.contains('card'))wrapper.after(nav);else table.after(nav);
  const summary=document.createElement('span');summary.className='pagination-summary';summary.setAttribute('aria-live','polite');
  const buttons=document.createElement('div');buttons.className='pagination-buttons';const meta=document.createElement('div');meta.className='pagination-meta';meta.append(summary);if(adjustable){const label=document.createElement('label');label.className='pagination-size-label';label.append('Rows per page');const select=document.createElement('select');sizes.forEach(n=>select.add(new Option(String(n),String(n))));select.value=String(size);select.addEventListener('change',()=>{size=Number(select.value);totalPages=Math.max(1,Math.ceil(rows.length/size));page=1;const link=new URL(location.href);link.searchParams.set('per_page',String(size));link.searchParams.set(key,'1');history.replaceState(history.state,'',link.href);render();});label.append(select);meta.append(label);}nav.append(meta,buttons);
  function render(){
   rows.forEach((row,n)=>row.toggleAttribute('data-pagination-hidden',n<(page-1)*size||n>=page*size));
   summary.textContent=(rows.length?((page-1)*size+1):0).toLocaleString()+'–'+Math.min(page*size,rows.length).toLocaleString()+' of '+rows.length.toLocaleString()+' records · Page '+page+' of '+totalPages;
   buttons.replaceChildren();
   function button(n,label,disabled=false){
    const el=document.createElement(disabled?'span':'a');el.className='btn ghost pagination-button';el.textContent=label;
    if(disabled){el.classList.add('is-disabled');el.setAttribute('aria-disabled','true');}
    else{const link=new URL(location.href);link.searchParams.set(key,String(n));el.href=link.href;if(n===page&&/^\d+$/.test(label)){el.classList.add('is-current');el.setAttribute('aria-current','page');}
     el.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();page=n;history.replaceState(history.state,'',link.href);render();nav.querySelector('[aria-current="page"]')?.focus();});}
    buttons.append(el);
   }
   button(page-1,'Previous',page===1);
   const nums=new Set([1,totalPages]);for(let n=Math.max(1,page-2);n<=Math.min(totalPages,page+2);n++)nums.add(n);
   let last=0;[...nums].sort((a,b)=>a-b).forEach(n=>{if(n>last+1){const gap=document.createElement('span');gap.className='pagination-gap';gap.textContent='…';gap.setAttribute('aria-hidden','true');buttons.append(gap);}button(n,String(n));last=n;});
   button(page+1,'Next',page===totalPages);
  }
  render();
 });
})();
