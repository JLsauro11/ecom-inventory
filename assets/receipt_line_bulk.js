(()=>{
 const form=document.getElementById('receiptBulkRemove');if(!form)return;
 const all=form.querySelector('[data-rl-select-all]'),count=form.querySelector('[data-rl-selected-count]'),button=form.querySelector('[data-rl-remove-selected]');
 const boxes=[...document.querySelectorAll('[data-rl-select][form="receiptBulkRemove"]')];
 function sync(){const n=boxes.filter(b=>b.checked).length;count.textContent=n+' selected';button.disabled=n===0;all.checked=boxes.length>0&&n===boxes.length;all.indeterminate=n>0&&n<boxes.length;all.disabled=boxes.length===0;form.elements.selection_count.value=String(n);return n;}
 all.addEventListener('change',()=>{boxes.forEach(b=>b.checked=all.checked);sync();});boxes.forEach(b=>b.addEventListener('change',sync));
 form.addEventListener('submit',e=>{const n=sync();if(!n){e.preventDefault();e.stopImmediatePropagation();return;}form.dataset.confirm='Remove '+n+' selected extracted receipt line'+(n===1?'':'s')+'? They will not be posted. The original lines and shared reason will remain in history.';},true);
 sync();
})();
