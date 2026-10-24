(() => {
 const form=document.getElementById('incomingAllocationForm');if(!form)return;
 const rows=[...form.querySelectorAll('[data-incoming-row]')],submit=form.querySelector('#incomingSubmit'),message=form.querySelector('#incomingValidation');
 function refresh(){const totals={incoming:0,Shopee:0,Lazada:0,TikTok:0};let valid=true;
 for(const row of rows){const qty=Number(row.dataset.qty),damaged=row.dataset.damaged==='1';let used=0,ok=true;totals.incoming+=qty;
 for(const p of ['Shopee','Lazada','TikTok']){const input=row.querySelector(`[data-platform="${p}"]`);if(!input)continue;const raw=input.value,n=Number(raw);const good=/^\d+$/.test(raw)&&Number.isSafeInteger(n)&&n>=0&&n<=qty&&(!damaged||n===0);input.setAttribute('aria-invalid',String(!good));ok=ok&&good;used+=good?n:0;totals[p]+=good?n:0;}
 ok=ok&&(damaged?used===0:used===qty);valid=valid&&ok;const badge=row.querySelector('[data-validation]');badge.textContent=ok?(damaged?'Non-Sellable':'✓ Fully Allocated'):(used<qty?'Allocate '+(qty-used)+' more':'Exceeds / Invalid');badge.classList.toggle('invalid',!ok);}
 for(const [k,v] of Object.entries(totals)){form.querySelectorAll(`[data-total="${k}"], [data-summary="${k}"]`).forEach(el=>el.textContent=v.toLocaleString('en-US'));}
 message.textContent=valid?'✓ All good items are fully allocated to platforms.':'Check highlighted quantities. Use whole numbers; platform totals must equal incoming quantity for every good item.';message.classList.toggle('invalid',!valid);if(submit)submit.disabled=!valid;return valid;}
 if(!form.querySelector('[data-platform]'))return;
 form.addEventListener('input',refresh);form.addEventListener('submit',e=>{if(!refresh())e.preventDefault();});
 form.querySelector('#applyIncomingRules')?.addEventListener('click',()=>{if(rows.some(r=>r.dataset.damaged!=='1'&&r.dataset.rulesValid!=='1')){message.textContent='Allocation Rules must total exactly 100%. Correct the rules or enter quantities manually.';message.classList.add('invalid');return;}for(const row of rows){if(row.dataset.damaged==='1')continue;const values=JSON.parse(row.dataset.rules);['Shopee','Lazada','TikTok'].forEach((p,i)=>row.querySelector(`[data-platform="${p}"]`).value=values[i]);}refresh();});refresh();
})();
