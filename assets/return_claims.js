(()=>{
 'use strict';
 document.querySelectorAll('[data-return-claim-form]').forEach(form=>{
  const action=form.querySelector('[name="case_action"]'),help=form.querySelector('[data-case-help]');
  const ack=form.querySelector('[data-no-return-ack]'),confirm=ack?.querySelector('input');
  const physical=form.querySelector('[data-physical-inspection-ack]'),physicalInput=physical?.querySelector('input');
  const complete=form.querySelector('[data-return-complete-ack]'),completeInput=complete?.querySelector('input');
  const shortage=form.querySelector('[data-short-shipment-ack]'),shortageInput=shortage?.querySelector('input');
  if(!action)return;
  const notes={
   keep:'Save remarks or evidence while keeping the current case status.',
   accept_return:'Accept the customer reason without opening a platform claim. Post the actual returned SKU, quantity and condition through Inflow; receiving remains pending until posted.',
   accept_return_close:'The actual received items are already posted. Confirm that they complete this accepted customer return. No further stock is added.',
   accept_short_return:'Close the customer return using only the items already received and posted. Missing units were never received by the customer; reconcile that original dispatch difference separately. Do not receive them again.',
   for_inspection:'Use only when the actual item has arrived. Inspect it, then record received quantities through Inflow.',
   inspected:'Record the inspection result in remarks. Actual stock receiving is a separate Inflow submission.',
   for_filing:'The claim has not been submitted yet. Record what still needs to be filed.',
   claim_filed:'Record that the claim was submitted. Put its actual filing date in remarks.',
   claim_approved:'Claim Approved means seller paid / compensated. Saving automatically resolves the case and removes it from pending follow-ups. Filing and approval history are retained; no stock is added.',
   approved_no_return:'The platform claim is approved, the seller is paid / compensated, and no item will return. Inspection becomes Not Required; the case leaves pending ICO returns. Stock stays deducted.',
   claim_rejected:'Record the platform rejection and the next action in remarks. Actual receiving remains separate.',
   close_claim:'Close the claims follow-up. An outstanding physical return remains pending until it is properly received or resolved.'
  };
  function sync(){
   const noReturn=action.value==='approved_no_return',inspect=['for_inspection','inspected'].includes(action.value);
   const closing=action.value==='accept_return_close',short=action.value==='accept_short_return';
   if(complete)complete.hidden=!closing;if(completeInput)completeInput.required=closing;
   if(shortage)shortage.hidden=!short;if(shortageInput)shortageInput.required=short;
   if(help)help.textContent=notes[action.value]||notes.keep;
   if(ack){ack.hidden=!noReturn;ack.style.display=noReturn?'':'none';}
   if(confirm)confirm.required=noReturn&&form.dataset.noReturnConfirmed!=='1';
   if(physical){physical.hidden=!inspect;physical.style.display=inspect?'':'none';}
   if(physicalInput){physicalInput.disabled=!inspect;if(!inspect)physicalInput.checked=false;}
  }
  action.addEventListener('change',sync);sync();
 });
})();
