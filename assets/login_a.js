document.querySelectorAll('[data-auth-toggle]').forEach(button=>button.addEventListener('click',()=>{
 const input=document.getElementById(button.getAttribute('aria-controls'));if(!input)return;input.type=input.type==='password'?'text':'password';button.textContent=input.type==='password'?'Show':'Hide';button.setAttribute('aria-label',input.type==='password'?'Show password':'Hide password');button.setAttribute('aria-pressed',String(input.type==='text'));
}));
