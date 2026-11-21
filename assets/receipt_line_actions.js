(()=>{
 'use strict';
 const triggers=[...document.querySelectorAll('[data-receipt-menu]')];
 function hide(menu){if(!menu)return;if(menu.matches(':popover-open'))menu.hidePopover();menu.hidden=true;const t=triggers.find(x=>x.dataset.receiptMenu===menu.id);t?.setAttribute('aria-expanded','false');}
 function hideAll(){triggers.forEach(t=>hide(document.getElementById(t.dataset.receiptMenu)));}
 triggers.forEach(trigger=>{
  const menu=document.getElementById(trigger.dataset.receiptMenu);if(!menu)return;
  trigger.addEventListener('click',()=>{const open=!menu.hidden;hideAll();if(open)return;menu.hidden=false;menu.style.visibility='hidden';if(typeof menu.showPopover==='function')menu.showPopover();const rect=trigger.getBoundingClientRect(),width=menu.getBoundingClientRect().width,height=menu.getBoundingClientRect().height;menu.style.left=Math.max(8,Math.min(rect.right-width,window.innerWidth-width-8))+'px';const below=window.innerHeight-rect.bottom-8;menu.style.top=Math.max(8,Math.min(below>=height?rect.bottom+6:rect.top-height-6,window.innerHeight-height-8))+'px';menu.style.visibility='';trigger.setAttribute('aria-expanded','true');menu.querySelector('button')?.focus({preventScroll:true});});
  menu.addEventListener('toggle',e=>{if(e.newState==='closed'){menu.hidden=true;trigger.setAttribute('aria-expanded','false');}});
  menu.addEventListener('keydown',e=>{const buttons=[...menu.querySelectorAll('button')],i=buttons.indexOf(document.activeElement);if(['ArrowDown','ArrowUp','Home','End'].includes(e.key)){e.preventDefault();const next=e.key==='Home'?0:e.key==='End'?buttons.length-1:(i+(e.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length;buttons[next]?.focus();}if(e.key==='Escape'){e.preventDefault();hide(menu);trigger.focus();}});
  menu.querySelectorAll('[data-receipt-dialog]').forEach(button=>button.addEventListener('click',()=>{const dialog=document.getElementById(button.dataset.receiptDialog);hide(menu);if(!dialog)return;dialog.receiptReturnFocus=trigger;if(!dialog.open)dialog.showModal();}));
 });
 document.querySelectorAll('.receipt-line-dialog').forEach(dialog=>{
  dialog.querySelectorAll('[data-receipt-close]').forEach(b=>b.addEventListener('click',()=>dialog.close()));
  dialog.addEventListener('close',()=>dialog.receiptReturnFocus?.focus());
  dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();});
 });
 document.addEventListener('click',e=>{if(!e.target.closest('.receipt-line-actions'))hideAll();});
 window.addEventListener('resize',hideAll);document.addEventListener('wheel',e=>{if(!e.target.closest('.receipt-line-action-menu'))hideAll();},{passive:true});
})();

