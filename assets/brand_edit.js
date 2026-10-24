(() => {
 const select = document.getElementById('ep_brand');
 const field = document.getElementById('editNewBrandField');
 const input = document.getElementById('editNewBrandName');
 if (!select || !field || !input) return;
 const sync = () => {
  const isNew = select.value === '__new_brand';
  field.hidden = !isNew;
  input.disabled = !isNew;
  input.required = isNew;
 };
 select.addEventListener('change', () => { sync(); if (!field.hidden) input.focus(); });
 document.querySelectorAll('.edit-product').forEach(button => button.addEventListener('click', () => {
  input.value = '';
  queueMicrotask(sync);
 }));
 sync();
})();
