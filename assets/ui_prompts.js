(() => {
 'use strict';
 const configNode=document.getElementById('uiReasonConfig'),dialog=document.getElementById('uiReasonDialog');if(!configNode||!dialog)return;
 const config=JSON.parse(configNode.textContent);let opener=null;
 function explain(key,origin){const item=config.catalog[key];if(!item)return;opener=origin;document.getElementById('uiReasonTitle').textContent=item[0];document.getElementById('uiReasonBody').textContent=item[1];document.getElementById('uiReasonNext').textContent=item[2];if(!dialog.open)dialog.showModal();}
 dialog.querySelectorAll('[data-ui-close]').forEach(b=>b.addEventListener('click',()=>dialog.close()));dialog.addEventListener('close',()=>{if(opener?.isConnected)opener.focus();});
 document.addEventListener('click',event=>{const b=event.target.closest('[data-ui-explain]');if(b){event.preventDefault();explain(b.dataset.uiExplain,b);}});
 function reason(el){
  if(el.dataset.uiReason)return el.dataset.uiReason;
  if(el.closest('.rs8-pagination,nav,template,[hidden]')||(el.type==='hidden'||el.type==='file')||el.matches('[data-batch-remove],[data-guided-remove]'))return '';
  if(config.page==='beginning'&&config.beginningLocked)return 'beginning';
  if(el.readOnly){if(el.id==='newSkuPreview')return 'sku';return 'readonly';}
  if(el.disabled&&el.name==='source_platform')return 'automatic';
  // Disabled submit controls are validation gates; mode selectors and loading buttons are not locks.
  if(el.disabled&&el.tagName==='BUTTON'&&(el.getAttribute('type')||'submit')==='submit'&&el.closest('form')&&!/loading|please wait|saving|processing/i.test(el.textContent))return 'validation';
  return '';
 }
 const content=document.querySelector('.content');if(!content)return;
 function refresh(){content.querySelectorAll('input,select,textarea,button').forEach(el=>{if(el.closest('#uiReasonDialog')||el.dataset.uiExplain)return;const key=reason(el);const sibling=el.parentElement.matches('.ui-disabled-wrap')?el.parentElement.nextElementSibling:el.nextElementSibling;let trigger=sibling?.matches('[data-ui-field-help]')?sibling:null;
   if(key&&(el.disabled||el.readOnly)){if(!trigger){trigger=document.createElement('button');trigger.type='button';trigger.className='ui-field-help';trigger.dataset.uiFieldHelp='1';trigger.textContent='ⓘ';el.after(trigger);}trigger.dataset.uiExplain=key;trigger.title=config.catalog[key][0];trigger.setAttribute('aria-label','Why unavailable: '+(el.getAttribute('aria-label')||el.name||el.textContent.trim()||config.catalog[key][0]));trigger.hidden=false;
    if(el.disabled&&!el.parentElement.matches('.ui-disabled-wrap')){const wrap=document.createElement('span');wrap.className='ui-disabled-wrap';el.before(wrap);wrap.append(el);wrap.after(trigger);wrap.tabIndex=0;wrap.setAttribute('role','button');wrap.setAttribute('aria-label',trigger.getAttribute('aria-label'));wrap.addEventListener('click',event=>{if(el.disabled&&!event.target.closest('[data-ui-explain]')){event.preventDefault();explain(reason(el),wrap);}});wrap.addEventListener('keydown',event=>{if(el.disabled&&(event.key==='Enter'||event.key===' ')){event.preventDefault();explain(reason(el),wrap);}});}
    if(el.readOnly&&!el.dataset.uiReadBound){el.dataset.uiReadBound='1';el.addEventListener('click',()=>{if(el.readOnly)explain(reason(el),el);});}
   }else if(trigger)trigger.hidden=true;
   if(el.parentElement.matches('.ui-disabled-wrap')){const wrap=el.parentElement;wrap.classList.toggle('is-enabled',!el.disabled);wrap.tabIndex=el.disabled?0:-1;wrap.setAttribute('role',el.disabled?'button':'presentation');}
  });}
 let scheduled=false;const observer=new MutationObserver(()=>{if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;refresh();});});
 refresh();observer.observe(content,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','readonly']});
 // Add an explanation beside existing server errors without replacing their exact details.
 content.querySelectorAll('.alert.danger,.alert.warn').forEach(alert=>{const text=alert.textContent;const key=/duplicate|already.*(?:received|registered|posted)|existing receipt/i.test(text)?'duplicate':/reserved|available stock|insufficient/i.test(text)?'stock':/closed.*date|date.*closed/i.test(text)?'date':/beginning.*locked/i.test(text)?'beginning':/stale|refresh.*review|validat/i.test(text)?'validation':'';if(key){const b=document.createElement('button');b.type='button';b.className='ui-alert-help';b.dataset.uiExplain=key;b.textContent='What should I do?';alert.append(b);}});
})();
