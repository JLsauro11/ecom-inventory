/* Full-hide navigation, with separate desktop/mobile preferences for each user. */
(() => {
 'use strict';
 const sidebar=document.getElementById('rs8Sidebar'),toggle=document.querySelector('.sidebar-toggle[data-sidebar-toggle]');
 if(!sidebar||!toggle)return;
 const root=document.documentElement,mobile=window.matchMedia('(max-width:900px)'),backdrop=document.querySelector('[data-sidebar-backdrop]');
 const key=window.RS8_SIDEBAR_KEY||'rs8.sidebar';
 function read(){try{const value=localStorage.getItem(key+(mobile.matches?'.mobile':'.desktop'));if(value==='hidden'||value==='shown')return value==='hidden';}catch{}return mobile.matches;}
 function render(hidden,persist=false){
  const focusedInside=sidebar.contains(document.activeElement);
  if(hidden&&focusedInside)toggle.focus();
  root.classList.toggle('sidebar-hidden',hidden);document.body.classList.remove('menu-open');
  toggle.setAttribute('aria-expanded',String(!hidden));toggle.setAttribute('aria-label',hidden?'Show navigation menu':'Hide navigation menu');toggle.title=hidden?'Show menu':'Hide menu';
  if(backdrop)backdrop.hidden=hidden||!mobile.matches;
  if(persist)try{localStorage.setItem(key+(mobile.matches?'.mobile':'.desktop'),hidden?'hidden':'shown');}catch{}
  window.dispatchEvent(new Event('resize'));
 }
 document.querySelectorAll('[data-sidebar-toggle]').forEach(button=>button.addEventListener('click',()=>render(!root.classList.contains('sidebar-hidden'),true)));
 backdrop?.addEventListener('click',()=>render(true,true));
 sidebar.addEventListener('click',event=>{if(mobile.matches&&event.target.closest('a[href]'))render(true,true);});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!event.defaultPrevented&&mobile.matches&&!root.classList.contains('sidebar-hidden')&&!document.querySelector('dialog[open]')){render(true,true);toggle.focus();}});
 mobile.addEventListener('change',()=>render(read()));
 window.addEventListener('storage',event=>{if(event.key===key+(mobile.matches?'.mobile':'.desktop'))render(read());});
 window.addEventListener('pageshow',()=>render(read()));
 render(read());
})();
