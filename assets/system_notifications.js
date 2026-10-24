setInterval(async()=>{
 if(document.hidden)return;
 const bell=document.getElementById('systemNotifications');if(!bell||bell.open)return;
 try{const response=await fetch('notifications.php?mode=bell',{credentials:'same-origin',cache:'no-store'});if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))return;const data=await response.json();if(!bell.open&&typeof data.html==='string')bell.outerHTML=data.html;}catch{/* Keep the last known notification state while offline. */}
},30000);
