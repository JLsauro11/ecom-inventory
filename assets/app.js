(function(){
 const products=window.RS8_PRODUCTS||[];
 const escapeText=value=>String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
 function bindPicker(root){window.rs8EnhanceProductPicker(root);}
 document.querySelectorAll('.product-picker').forEach(bindPicker);
 window.rs8BindProductPicker=bindPicker;

 function beautifyFileInputs(){
  document.querySelectorAll('input[type=file]').forEach(input=>{
   if(input.dataset.rs8Ready)return; input.dataset.rs8Ready='1';
   input.classList.add('native-file-input');
   const btn=document.createElement('button');btn.type='button';btn.className='file-select-btn';btn.disabled=input.disabled;
   const accept=(input.getAttribute('accept')||'').toLowerCase();
   btn.innerHTML='<span class="file-icon">↥</span><span class="file-label">'+(accept.includes('pdf')?'Upload PDF':'Select File')+'</span>';
   input.insertAdjacentElement('afterend',btn);
   btn.addEventListener('click',()=>input.click());
   input.addEventListener('change',()=>{const name=input.files&&input.files[0]?input.files[0].name:'';btn.classList.toggle('selected',!!name);btn.querySelector('.file-label').textContent=name|| (accept.includes('pdf')?'Upload PDF':'Select File');});
  });
 }
 beautifyFileInputs();

 document.querySelectorAll('form[action="actions.php"]').forEach(f=>{if(!f.dataset.confirm){const a=f.querySelector('input[name=action]')?.value||'';const labels={edit_user:'Save these user account details and role?',reset_user_password:'Reset this user password?',edit_product:'Save these Product Master details?',add_product:'Create this new Product Master item?',upload_beginning:'Validate this Beginning Balance file and open its review? No stocks will change yet.',save_beginning:'Submit and apply these beginning balance quantities? Stocks and platform allocations will update immediately.',resolve_receipt_line:'Validate this corrected product, shipped quantity and UOM?'};if(labels[a])f.dataset.confirm=labels[a];}});
 document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{e.preventDefault();rs8Confirm(f.dataset.confirm||'Do you want to proceed?',()=>f.submit());}));
 document.querySelectorAll('[data-auto-dismiss]').forEach(el=>setTimeout(()=>el.classList.add('fade'),6500));
 document.querySelectorAll('.edit-product').forEach(b=>b.addEventListener('click',()=>{const d=document.getElementById('productDialog');['id','name','brand','category','variant','low'].forEach(k=>{const el=document.getElementById('ep_'+k);if(el)el.value=b.dataset[k]||'';});d.showModal();}));
 function recalcRow(row){const q=parseFloat(row.querySelector('.source-qty')?.value||0),u=(row.querySelector('.source-uom')?.value||'').toLowerCase().replace(/\s+/g,'');let f=1,m=u.match(/^bx\((\d+)\)$/);if(m)f=parseInt(m[1],10);else if(!['pc','pcs','piece','pieces'].includes(u))f=0;row.querySelector('.converted').innerHTML=f?`<b>${Math.round(q*f)} pcs</b>`:'<span class="num-red">Review UOM</span>';}
 document.addEventListener('input',e=>{if(e.target.matches('.source-qty,.source-uom'))recalcRow(e.target.closest('tr'));});
 window.rs8Confirm=function(message,onYes,options={}){let ov=document.getElementById('rs8Confirm');if(!ov){ov=document.createElement('dialog');ov.id='rs8Confirm';ov.className='confirm-overlay';ov.innerHTML='<div class="confirm-card"><div class="confirm-icon">!</div><h3>Confirm action</h3><p></p><div class="confirm-actions"><button type="button" class="btn ghost" data-no>Cancel</button><button type="button" class="btn primary" data-yes>Proceed</button></div></div>';document.body.appendChild(ov);}ov.querySelector('h3').textContent=options.title||'Confirm action';ov.querySelector('[data-yes]').textContent=options.confirmLabel||'Proceed';ov.querySelector('p').textContent=message;ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-label',options.title||'Confirm action');const previousFocus=document.activeElement;ov.classList.add('open');if(!ov.open)ov.showModal();const close=()=>{ov.classList.remove('open');if(ov.open)ov.close();if(previousFocus?.isConnected)previousFocus.focus();};ov.oncancel=e=>{e.preventDefault();close();};ov.onkeydown=e=>{if(e.key==='Escape'){e.preventDefault();close();}if(e.key==='Tab'){const first=ov.querySelector('[data-no]'),last=ov.querySelector('[data-yes]');if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}};ov.querySelector('[data-no]').focus();ov.querySelector('[data-no]').onclick=close;ov.querySelector('[data-yes]').onclick=()=>{close();onYes&&onYes();};};
 window.addReceiptLine=function(){const table=document.getElementById('receiptLines');if(!table)return;const tr=document.createElement('tr');tr.innerHTML=`<td><input value="Manual line"></td><td><div class="product-picker">${window.RS8_PICKER_FILTER_HTML||''}<input class="product-search" autocomplete="off" placeholder="Search matching product..." data-target="line_product_id[]"><input type="hidden" name="line_product_id[]" required><div class="product-results"></div></div></td><td><input class="source-qty" type="number" step="0.01" min="0" name="line_source_qty[]" value="0" required></td><td><input class="source-uom" name="line_uom[]" value="pc" required></td><td class="converted"><b>0 pcs</b></td><td><input name="line_remarks[]"></td><td><button type="button" class="icon-btn" onclick="this.closest('tr').remove()">×</button></td>`;table.appendChild(tr);bindPicker(tr.querySelector('.product-picker'));window.rs8BindProductFilters?.(tr.querySelector('.product-picker'));};
})();

// V4 User Management modals
(document.querySelectorAll('.user-edit')||[]).forEach(b=>b.addEventListener('click',()=>{
 const d=document.getElementById('userEditDialog');if(!d)return;
 document.getElementById('ue_id').value=b.dataset.id||'';
 document.getElementById('ue_name').value=b.dataset.name||'';
 document.getElementById('ue_username').value=b.dataset.username||'';
 document.getElementById('ue_role').value=b.dataset.role||'Viewer / Management';d.showModal();
}));
(document.querySelectorAll('.user-reset')||[]).forEach(b=>b.addEventListener('click',()=>{
 const d=document.getElementById('userResetDialog');if(!d)return;
 document.getElementById('ur_id').value=b.dataset.id||'';
 document.getElementById('ur_name').textContent=b.dataset.name||'';d.showModal();
}));

// V4 result prompts: mirror server feedback as polished temporary notifications.
(function(){
 const a=document.querySelector('.alert[data-auto-dismiss]');if(!a)return;
 const t=document.createElement('div');
 t.className='system-toast '+(a.classList.contains('danger')?'danger':a.classList.contains('warn')?'warn':a.classList.contains('success')?'success':'info');
 t.innerHTML='<div class="toast-mark">'+(t.classList.contains('success')?'✓':t.classList.contains('danger')?'!':t.classList.contains('warn')?'!':'i')+'</div><div class="toast-copy">'+a.innerHTML+'</div><button type="button" aria-label="Close">×</button>';
 document.body.appendChild(t);requestAnimationFrame(()=>t.classList.add('show'));
 const close=()=>{t.classList.remove('show');setTimeout(()=>t.remove(),220)};t.querySelector('button').onclick=close;setTimeout(close,5200);
})();
// Add anti-forgery tokens to every action form, including review corrections.
document.querySelectorAll('form').forEach(f=>{if(f.method.toLowerCase()==='post' && window.RS8_CSRF && !f.querySelector('input[name=csrf]')){const i=document.createElement('input');i.type='hidden';i.name='csrf';i.value=window.RS8_CSRF;f.appendChild(i);}});

// Native select options retain their original values; show the selected logo beside them.
document.querySelectorAll('select[name="platform"],select[name="source_platform"],select[name="from_platform"],select[name="to_platform"]').forEach(select=>{
 const files={Lazada:'lazada.svg',Shopee:'shopee.svg',TikTok:'tiktok.svg'};
 if(!Array.from(select.options).some(option=>files[option.value]))return;
 const wrap=document.createElement('div');wrap.className='platform-select';
 const brand=document.createElement('span');brand.className='platform-brand';
 const img=document.createElement('img');img.width=64;img.height=24;brand.appendChild(img);
 select.parentNode.insertBefore(wrap,select);wrap.append(brand,select);
 const update=()=>{const file=files[select.value];brand.hidden=!file;if(file){img.src='assets/logos/'+file;img.alt=select.value;}else{img.removeAttribute('src');img.alt='';}};
 select.addEventListener('change',update);update();
});

document.querySelectorAll('a[data-confirm-logout]').forEach(link=>link.addEventListener('click',event=>{
 if(!window.RS8_CSRF)return;event.preventDefault();
 window.rs8Confirm('Gusto mo bang ituloy ang pag-logout sa system?',()=>{
  const form=document.createElement('form');form.method='post';form.action=link.getAttribute('href');
  const token=document.createElement('input');token.type='hidden';token.name='csrf';token.value=window.RS8_CSRF;
  form.appendChild(token);document.body.appendChild(form);form.submit();
 },{title:'Confirm Logout',confirmLabel:'Ituloy ang Logout'});
}));

// Keep password visibility local to each field, without changing its value or validation.
document.querySelectorAll('.account-menu').forEach(menu=>{
 document.addEventListener('click',event=>{if(menu.open&&!menu.contains(event.target))menu.open=false;});
 menu.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu.open){event.preventDefault();menu.open=false;menu.querySelector('summary').focus();}});
 menu.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{menu.open=false;}));
});
document.querySelectorAll('input[type="password"]').forEach((input,index)=>{
 const label=input.closest('label');const fieldName=label?.textContent.trim()||input.placeholder||'password';
 if(!input.id){let id='rs8-password-'+(index+1);while(document.getElementById(id))id+='-field';input.id=id;}
 const wrap=document.createElement('div');wrap.className='password-input-wrap';
 if(label){const field=document.createElement('div');field.className='password-field';label.parentNode.insertBefore(field,label);field.appendChild(label);label.htmlFor=input.id;field.appendChild(wrap);wrap.appendChild(input);}
 else{input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);}
 const toggle=document.createElement('button');toggle.type='button';toggle.className='password-toggle';toggle.setAttribute('aria-controls',input.id);wrap.appendChild(toggle);
 const update=()=>{const shown=input.type==='text';toggle.textContent=shown?'Hide':'Show';toggle.setAttribute('aria-label',(shown?'Hide':'Show')+' '+fieldName);toggle.setAttribute('aria-pressed',String(shown));};
 toggle.addEventListener('click',()=>{input.type=input.type==='password'?'text':'password';update();});
 input.form?.addEventListener('reset',()=>{input.type='password';update();});update();
});

(() => {const form=document.getElementById('manualInflowForm');if(!form)return;const condition=form.querySelector('[name=item_condition]'),platform=form.querySelector('[name=destination_platform]'),qty=form.querySelector('[name=source_qty]'),uom=form.querySelector('[name=source_uom]');const update=()=>{const damaged=condition.value!=='Good Item';document.getElementById('inflowDestinationLabel').textContent='Original Order Platform';const raw=Number(qty.value),unit=uom.value.trim().toLowerCase();const pack=unit.match(/^bx\((\d+)\)$/);const factor=pack?Number(pack[1]):(['pc','pcs','piece','pieces'].includes(unit)?1:0);const count=raw*factor;const valid=raw>0&&factor>0&&Number.isInteger(count);const destination=platform.value;const text=damaged?'Destination: Non-Sellable / '+(condition.value==='For Repackaging'?'RAW / For Packaging':'Damaged / Leak')+' (automatic). Sellable inventory unchanged. Return platform is tracking only.':'Good items restore '+destination+' only after the original posted order and remaining quantity are validated.';document.getElementById('inflowStockResult').textContent=(valid?'Physical +'+count+' pcs; '+(damaged?'Non-Sellable +'+count+' pcs. ':'Sellable +'+count+' pcs. '):'')+text;};[condition,platform,qty,uom].forEach(el=>el.addEventListener('input',update));update();})();

document.querySelectorAll('[data-cancel-inflow-form]').forEach(button=>button.addEventListener('click',()=>{rs8Confirm('Cancel this inflow form? Entered details and selected files will be discarded. No inventory will be added.',()=>{const entry=new URLSearchParams(window.location.search).get('entry')||'manual';window.location.assign('?page=inflow&entry='+encodeURIComponent(entry));});}));

(() => {const source=document.querySelector('form [name="source_platform"]');if(!source)return;const platform=source.form.querySelector('[name="platform"]');const sync=()=>{const direct=['Shopee','Lazada','TikTok'].includes(platform.value);source.required=!direct;if(direct)source.value=platform.value;source.disabled=direct;};platform.addEventListener('change',sync);sync();})();

// Back navigates only to safe display URLs; it never replays a POST request.
(() => {
  const buttons=[...document.querySelectorAll('[data-system-back]')];
  if(!buttons.length)return;
  const home=new URL('index.php?page=dashboard',location.href);
  const key='rs8.navigation.v1:'+home.pathname;
  const allowed=new Set(['index.php','account.php','account_password.php','update_check.php','assign_categories.php']);
  const safe=value=>{try{const u=new URL(value,location.href);return u.origin===home.origin&&u.pathname.slice(0,u.pathname.lastIndexOf('/')+1)===home.pathname.slice(0,home.pathname.lastIndexOf('/')+1)&&allowed.has(u.pathname.split('/').pop())?u.href:null;}catch{return null;}};
  const current=safe(location.href);if(!current)return;
  let previous=null;
  function refresh(){
    let stack=[];
    try{const stored=JSON.parse(sessionStorage.getItem(key)||'[]');if(Array.isArray(stored))stack=stored.map(safe).filter(Boolean);}catch{}
    // First visit can safely use a same-system referrer, never an action endpoint.
    if(!stack.length){const ref=safe(document.referrer);if(ref&&ref!==current)stack.push(ref);}
    const last=stack.lastIndexOf(current);
    if(last>=0)stack=stack.slice(0,last+1);else stack.push(current);
    stack=stack.slice(-80);previous=stack.length>1?stack[stack.length-2]:null;
    try{sessionStorage.setItem(key,JSON.stringify(stack));}catch{}
    buttons.forEach(button=>{button.href=previous||home.href;button.title=previous?'Back to previous page':'Back to Dashboard';});
  }
  refresh();window.addEventListener('pageshow',refresh);
})();
