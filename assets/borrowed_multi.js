(() => {
 const form=document.getElementById('borrowIssueForm'),lines=document.getElementById('borrowLines');
 if(form&&lines){
  let index=1,posting=false;
  const template=lines.firstElementChild.cloneNode(true);
  const updateRemove=()=>lines.querySelectorAll('[data-remove-borrow-line]').forEach(b=>b.disabled=lines.children.length===1);
  document.getElementById('addBorrowLine').addEventListener('click',()=>{
   if(lines.children.length>=100){window.rs8Confirm('A borrow transaction can contain up to 100 product lines.');return;}
   const row=template.cloneNode(true);
   row.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace('items[0]',`items[${index}]`);el.value='';});
   row.querySelector('.product-search').value='';row.querySelector('.product-results').replaceChildren();row.querySelector('.product-results').classList.remove('open');
   row.querySelector('.product-search').dataset.target=`items[${index}][product_id]`;index++;
   lines.append(row);window.rs8BindProductPicker(row.querySelector('.product-picker'));updateRemove();row.querySelector('.product-search').focus();
  });
  lines.addEventListener('click',e=>{const b=e.target.closest('[data-remove-borrow-line]');if(b&&lines.children.length>1){b.closest('[data-borrow-product-line]').remove();updateRemove();}});
  form.addEventListener('reset',()=>{setTimeout(()=>{while(lines.children.length>1)lines.lastElementChild.remove();lines.querySelector('.product-results').classList.remove('open');updateRemove();},0);});
  form.addEventListener('submit',e=>{
   e.preventDefault();if(posting||!form.reportValidity())return;
   const rows=[],seen=new Set();
   for(const line of lines.children){
    const pid=line.querySelector('input[type=hidden]').value,product=(window.RS8_PRODUCTS||[]).find(p=>String(p.id)===pid);
    const source=line.querySelector('select').value,quantity=line.querySelector('input[type=number]').value;
    if(!product){const input=line.querySelector('.product-search');input.setCustomValidity('Select a product from the search results.');input.reportValidity();input.addEventListener('input',()=>input.setCustomValidity(''),{once:true});input.addEventListener('focus',()=>input.setCustomValidity(''),{once:true});return;}
    const key=`${pid}|${source}`;if(seen.has(key)){window.rs8Confirm('Combine duplicate product and source platform quantities into one line.');return;}seen.add(key);
    rows.push({product,source,quantity});
   }
   const dialog=document.createElement('dialog');dialog.className='borrow-review-dialog';dialog.setAttribute('aria-label','Final Borrow Confirmation');
   const title=document.createElement('h2');title.textContent='Final Borrow Confirmation';dialog.append(title);
   const info=document.createElement('p');info.className='borrow-review-info';
   info.textContent=`Borrower: ${form.elements.borrower.value}\nIssue Date: ${form.elements.txn_date.value}   •   Due Date: ${form.elements.due_date.value}\nReason: ${form.elements.reason.value}`;dialog.append(info);
   const wrap=document.createElement('div');wrap.className='table-wrap';const table=document.createElement('table');table.className='table';const head=table.createTHead().insertRow();
   ['Product / Master SKU','Source Platform','Quantity'].forEach(text=>{const th=document.createElement('th');th.textContent=text;head.append(th);});
   const body=table.createTBody();for(const row of rows){const tr=body.insertRow();[`${row.product.name}\n${row.product.master_sku}`,row.source,`${row.quantity} pcs`].forEach(text=>{const td=tr.insertCell();td.textContent=text;td.style.whiteSpace='pre-line';});}
   wrap.append(table);dialog.append(wrap);
   const note=document.createElement('p');note.textContent='Confirming issues all product lines together and deducts each source platform once. Negative balances will be warned and recorded.';dialog.append(note);
   const actions=document.createElement('div');actions.className='modal-actions';const back=document.createElement('button');back.type='button';back.className='btn ghost';back.textContent='Back to Edit';back.onclick=()=>dialog.close();
   const confirm=document.createElement('button');confirm.type='button';confirm.className='btn primary';confirm.textContent='Confirm and Issue Products';confirm.onclick=()=>{if(posting)return;posting=true;confirm.disabled=true;form.submit();};actions.append(back,confirm);dialog.append(actions);
   dialog.addEventListener('close',()=>{dialog.remove();form.querySelector('button[type=submit]').focus();});document.body.append(dialog);dialog.showModal();back.focus();
  });updateRemove();
 }
 document.querySelectorAll('[data-borrow-line-action]').forEach(form=>{
  const select=form.querySelector('select[name=borrow_id]');const update=()=>{const option=select.selectedOptions[0];if(!option)return;form.querySelector('.borrow-line-note').textContent=`Source: ${option.dataset.source} • Outstanding: ${option.dataset.outstanding} pcs`;form.querySelectorAll('input[type=number]').forEach(input=>input.max=option.dataset.outstanding);};select.addEventListener('change',update);update();
 });
})();
