(() => {
 const tree=window.RS8_CATEGORY_TREE || {};
 function populate(main, sub, existing='') {
  const choices=tree[main.value] || [];
  sub.replaceChildren(new Option(choices.length?'Select subcategory':main.value?'No subcategory needed':'Select main category first',''));
  choices.forEach(value=>sub.add(new Option(value,value)));
  if (!main.value && existing) sub.add(new Option(existing+' (existing)',existing));
  sub.value=existing;
  sub.required=choices.length>0;
  sub.disabled=!!main.value && choices.length===0;
 }
 document.querySelectorAll('select[data-main-category]').forEach(main=>{
  const sub=main.closest('.category-pair').querySelector('[data-sub-category]');
  populate(main,sub);
  main.addEventListener('change',()=>populate(main,sub));
 });
 document.querySelectorAll('.edit-product').forEach(button=>button.addEventListener('click',()=>{
  const main=document.getElementById('ep_main_category'),sub=document.getElementById('ep_category');
  if(!main||!sub)return;
  main.value=button.dataset.mainCategory || '';
  populate(main,sub,button.dataset.category || '');
 }));
})();
