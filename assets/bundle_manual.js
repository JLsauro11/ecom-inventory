document.querySelectorAll('[data-bundle-editor],[data-manual-products]').forEach(editor=>{
 const bundle=editor.hasAttribute('data-bundle-editor'),container=editor.querySelector(bundle?'[data-bundle-lines]':'[data-product-lines]');
 function renumber(){container.querySelectorAll(bundle?'[data-bundle-line]':'[data-product-line]').forEach((line,i)=>line.querySelectorAll('[name]').forEach(input=>input.name=input.name.replace(/\[\d+\]/,'['+i+']')));}
 editor.addEventListener('click',e=>{
  if(e.target.closest('[data-add-bundle],[data-add-product]')){const line=container.firstElementChild.cloneNode(true);line.querySelectorAll('input').forEach(input=>{input.value=input.name.includes('per_bundle')?'1':'';});line.querySelectorAll('.product-results').forEach(box=>{box.innerHTML='';box.classList.remove('open');});if(bundle&&!line.querySelector('[data-remove-line]')){const remove=document.createElement('button');remove.type='button';remove.className='btn ghost sm';remove.setAttribute('data-remove-line','');remove.textContent='Remove Item';line.append(remove);}container.append(line);renumber();line.querySelectorAll('.product-picker').forEach(p=>window.rs8BindProductPicker(p));}
  const remove=e.target.closest('[data-remove-line]');if(remove&&container.children.length>1){const line=remove.closest('[data-bundle-line],[data-product-line]');if(line&&(!bundle||line!==container.firstElementChild)){line.remove();renumber();}}
 });
});
document.querySelectorAll('[data-manual-count-row]').forEach(row=>{
 const update=()=>{let filled=true,total=0;row.querySelectorAll('input[type=number]').forEach(input=>{if(input.value==='')filled=false;total+=Number(input.value||0);});row.querySelector('[data-actual-total]').textContent=filled?String(total):'—';row.querySelector('[data-variance]').textContent=filled?String(total-Number(row.dataset.onHand)):'—';};
 row.querySelectorAll('input').forEach(input=>input.addEventListener('input',update));update();
});
