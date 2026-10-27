/* Product selectors retain their original IDs, options and submitted field names. */
(() => {
 'use strict';
 const bound=new WeakMap(),live=new Set();let sequence=0;
 const normalize=value=>String(value??'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
 const matches=(text,query)=>normalize(query).split(/\s+/).filter(Boolean).every(word=>normalize(text).includes(word));
 function bind(root,input,box,adapter){
  if(bound.has(root))return;
  const id='rs8-product-options-'+(++sequence);box.id=id;box.replaceChildren();box.setAttribute('role','listbox');
  input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-controls',id);input.setAttribute('aria-expanded','false');input.autocomplete='off';
  if(!input.getAttribute('aria-label'))input.setAttribute('aria-label',adapter.label||'Product');
  box.classList.add('rs8-product-options');
  // A top-layer dropdown avoids clipping inside transaction tables and dialogs.
  if(typeof box.showPopover==='function')box.setAttribute('popover','manual');
  let visible=[],active=-1,open=false,dirty=false;
  function position(){if(!open)return;const rect=input.getBoundingClientRect();const below=window.innerHeight-rect.bottom;const height=Math.min(300,Math.max(below,rect.top)-16);box.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-rect.width-8))+'px';box.style.width=Math.min(rect.width,window.innerWidth-16)+'px';box.style.maxHeight=Math.max(100,height)+'px';box.style.top=(below<160&&rect.top>below?Math.max(8,rect.top-Math.min(box.scrollHeight,height)-5):rect.bottom+5)+'px';}
  function close(){open=false;active=-1;box.classList.remove('open');if(box.matches(':popover-open'))box.hidePopover();input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');}
  function sync(){input.disabled=adapter.disabled();input.required=adapter.required();if(!dirty){input.value=adapter.display();input.setCustomValidity('');}}
  function select(option){adapter.choose(option);dirty=false;input.value=option.label;input.setCustomValidity('');close();input.dispatchEvent(new Event('change',{bubbles:true}));}
  function show(){if(input.disabled)return;const query=!dirty&&input.value===adapter.display()?'':input.value;
   const all=adapter.options().filter(option=>!option.disabled&&matches(option.search||option.label,query));visible=all.slice(0,60);active=-1;box.replaceChildren();
   visible.forEach((option,index)=>{const button=document.createElement('button');button.type='button';button.id=id+'-'+index;button.setAttribute('role','option');button.setAttribute('aria-selected',String(option.value===adapter.value()));
    const title=document.createElement('b');title.textContent=option.label;button.append(title);if(option.meta){const detail=document.createElement('span');detail.textContent=option.meta;button.append(detail);}
    button.addEventListener('mousedown',event=>event.preventDefault());button.addEventListener('click',()=>select(option));box.append(button);
   });
   if(!all.length||all.length>60){const note=document.createElement('div');note.className='rs8-product-search-note';note.textContent=all.length?'Showing 60 of '+all.length+' matches. Type more to narrow the list.':'No matching products.';note.setAttribute('role','status');box.append(note);}
   open=true;box.classList.add('open');input.setAttribute('aria-expanded','true');if(box.hasAttribute('popover')&&!box.matches(':popover-open'))box.showPopover();position();
  }
  root.rs8RefreshPicker=()=>{if(!adapter.value()&&!input.value){dirty=false;input.setCustomValidity('');}if(open||document.activeElement===input)show();};
  input.addEventListener('focus',()=>{sync();show();input.select();});
  input.addEventListener('input',()=>{dirty=true;adapter.clear();input.setCustomValidity(input.value?'Select a product from the matching results.':'');show();});
  input.addEventListener('keydown',event=>{
   if(event.key==='Escape'){event.preventDefault();close();return;}
   if(event.key==='Tab'){close();return;}
   if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(!open)show();if(!visible.length)return;active=active<0?(event.key==='ArrowDown'?0:visible.length-1):(active+(event.key==='ArrowDown'?1:-1)+visible.length)%visible.length;const buttons=box.querySelectorAll('button');buttons.forEach((button,index)=>button.classList.toggle('is-highlighted',index===active));input.setAttribute('aria-activedescendant',buttons[active].id);buttons[active].scrollIntoView({block:'nearest'});}
   if(event.key==='Enter'&&open){event.preventDefault();if(active>=0)select(visible[active]);else if(visible.length===1)select(visible[0]);}
  });
  // Empty optional filters mean All Products; typed text must still resolve to an item.
  input.addEventListener('blur',()=>{if(!input.value&&!adapter.required()){adapter.reset();dirty=false;sync();}});
  const state={root,input,box,close,position,sync,reset:(formReset=false)=>{if(formReset)adapter.resetForm?.();dirty=false;close();sync();}};bound.set(root,state);live.add(state);close();sync();
 }
 function picker(root){if(bound.has(root))return;const input=root.querySelector('.product-search'),hidden=root.querySelector('input[type="hidden"]'),box=root.querySelector('.product-results');if(!input||!hidden||!box)return;
  const initialValue=hidden.value;const products=()=>window.RS8_PRODUCTS||[];const current=()=>products().find(p=>String(p.id)===String(hidden.value));
  bind(root,input,box,{label:'Product / Master SKU',value:()=>hidden.value,required:()=>hidden.required,disabled:()=>hidden.disabled,
   display:()=>current()?.name||'',resetForm:()=>{hidden.value=initialValue;},clear:()=>{hidden.value='';},reset:()=>{hidden.value='';},
   options:()=>products().filter(p=>!root.rs8ProductMatches||root.rs8ProductMatches(p)).map(p=>({value:String(p.id),label:p.name,meta:[p.brand,p.category,p.master_sku].filter(Boolean).join(' · '),search:[p.name,p.brand,p.category,p.master_sku].join(' ')})),
   choose:option=>{hidden.value=option.value;hidden.dispatchEvent(new Event('change',{bubbles:true}));}
  });
  if(root.dataset.filtersBound&&!root.rs8ProductMatches)delete root.dataset.filtersBound;window.rs8BindProductFilters?.(root);
 }
 function native(select){if(bound.has(select))return;let root=select.parentElement.matches('.rs8-select-picker')?select.parentElement:null;
  if(!root){root=document.createElement('div');root.className='rs8-select-picker';select.before(root);root.append(select);}
  if(bound.has(root))return;let input=root.querySelector('.rs8-select-search'),box=root.querySelector('.product-results');
  if(!input){input=document.createElement('input');input.type='text';input.className='rs8-select-search';input.placeholder='Type product name, brand, or Master SKU…';root.prepend(input);}
  if(!box){box=document.createElement('div');box.className='product-results';root.append(box);}
  const label=select.getAttribute('aria-label')||select.closest('label')?.firstChild?.textContent?.trim()||'Product';
  const optionText=()=>select.selectedOptions[0]?.textContent?.trim()||'';
  const defaultOption=()=>Array.from(select.options).find(o=>o.value===''||o.value==='0');
  bind(root,input,box,{label,value:()=>select.value,display:()=>select.value?optionText():select.required?'':optionText(),required:()=>select.required,disabled:()=>select.disabled,
   clear:()=>{select.selectedIndex=-1;},reset:()=>{const option=defaultOption();select.value=option?option.value:'';},
   options:()=>Array.from(select.options).filter(o=>o.value!==''||!select.required).map(o=>({value:o.value,label:o.textContent.trim(),search:o.textContent+' '+(o.dataset.search||''),disabled:o.disabled||Boolean(o.closest('optgroup')?.disabled)})),
   choose:option=>{select.value=option.value;select.dispatchEvent(new Event('input',{bubbles:true}));select.dispatchEvent(new Event('change',{bubbles:true}));}
  });
  bound.set(select,bound.get(root));select.hidden=true;select.addEventListener('change',()=>bound.get(root)?.reset());
  select.addEventListener('invalid',event=>{event.preventDefault();input.setCustomValidity('Select a product from the matching results.');input.focus();input.reportValidity();});
 }
 const selector='select[data-product-select],select[name="product_id"],select[name$="[product_id]"]';
 function scan(node){if(node.nodeType!==1&&node.nodeType!==9)return;if(node.matches?.('.product-picker'))picker(node);node.querySelectorAll?.('.product-picker').forEach(picker);if(node.matches?.(selector))native(node);node.querySelectorAll?.(selector).forEach(native);}
 window.rs8EnhanceProductPicker=picker;window.rs8EnhanceProductSelect=native;
 scan(document);
 new MutationObserver(records=>{records.forEach(record=>{if(record.type==='childList')record.addedNodes.forEach(scan);if(record.target.tagName==='SELECT')bound.get(record.target)?.sync();});for(const state of live)if(!state.root.isConnected){state.close();live.delete(state);}}).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','required']});
 document.addEventListener('click',event=>{for(const state of live)if(!state.root.contains(event.target))state.close();});
 document.addEventListener('focusin',event=>{for(const state of live)if(!state.root.contains(event.target))state.close();});
 document.addEventListener('scroll',()=>live.forEach(state=>state.position()),true);window.addEventListener('resize',()=>live.forEach(state=>state.position()));
 document.addEventListener('reset',event=>setTimeout(()=>live.forEach(state=>{if(event.target.contains(state.root))state.reset(true);}),0));
})();
