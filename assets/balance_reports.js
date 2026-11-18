(() => {
 'use strict';
 document.querySelectorAll('[data-sb-size]').forEach(select=>select.addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('per_page',select.value);url.searchParams.set('offset','0');location.assign(url.href);}));
 const form=document.querySelector('[data-sb-filters]');if(!form)return;
 form.querySelectorAll('[data-sb-date]').forEach(button=>button.addEventListener('click',()=>{form.elements.as_of.value=button.dataset.sbDate;}));
 form.elements.main_category.addEventListener('change',()=>{const main=form.elements.main_category.value,sub=form.elements.subcategory,prior=sub.value,tree=window.RS8_CATEGORY_TREE||{},values=main?(tree[main]||[]):Object.values(tree).flat();sub.replaceChildren(new Option('All Subcategories',''));[...new Set(values)].forEach(value=>sub.add(new Option(value,value)));if(values.includes(prior))sub.value=prior;});
})();
