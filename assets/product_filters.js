(() => {
 const tree=window.RS8_CATEGORY_TREE||{};
 const brand=value=>(window.RS8_BRAND_ALIASES||{})[String(value||'').trim().toLowerCase().replace(/\s+/g,' ')]||'Others';
 function bind(root){
  const main=root.querySelector('[data-filter-main]'),sub=root.querySelector('[data-filter-sub]'),b=root.querySelector('[data-filter-brand]');
  if(!main||!sub||root.dataset.filtersBound)return;root.dataset.filtersBound='1';
  // Picker filters are local UI controls, not submitted product tags.
  const picker=root.classList.contains('product-picker');
  if(picker){b.removeAttribute('name');main.removeAttribute('name');sub.removeAttribute('name');root.rs8ProductMatches=p=>(!b.value||brand(p.brand)===b.value)&&(!main.value||(main.value==='__unassigned'?!p.main_category:p.main_category===main.value))&&(!sub.value||p.category===sub.value);}
  const refresh=()=>{if(picker){const hidden=root.querySelector('input[type=hidden]');const input=root.querySelector('.product-search');if(hidden?.value){const p=(window.RS8_PRODUCTS||[]).find(p=>String(p.id)===hidden.value);if(p&&!root.rs8ProductMatches(p)){hidden.value='';input.value='';}}root.rs8RefreshPicker?.();}};
  main.addEventListener('change',()=>{const choices=main.value?(tree[main.value]||[]):Object.values(tree).flat();sub.replaceChildren(new Option('All Subcategories',''));choices.forEach(value=>sub.add(new Option(value,value)));refresh();});sub.addEventListener('change',refresh);b?.addEventListener('change',refresh);
  root.querySelector('[data-clear-picker-filters]')?.addEventListener('click',()=>{
   b.value='';main.value='';sub.replaceChildren(new Option('All Subcategories',''));Object.values(tree).flat().forEach(value=>sub.add(new Option(value,value)));
   const input=root.querySelector('.product-search'),hidden=root.querySelector('input[type=hidden]');if(input)input.value='';if(hidden)hidden.value='';refresh();input?.focus();
  });
 }
 window.rs8BindProductFilters=bind;
 document.querySelectorAll('.product-picker').forEach(bind);
 document.querySelectorAll('form.inline-form').forEach(form=>{if(!form.closest('.product-picker'))bind(form);});
 const template=document.querySelector('.picker-filters');if(template)window.RS8_PICKER_FILTER_HTML=template.outerHTML;
})();
