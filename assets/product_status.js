(() => {
 const dialog = document.getElementById('productStatusDialog');
 if (!dialog) return;
 document.querySelectorAll('.product-status-button').forEach(button => {
  button.addEventListener('click', () => {
   const active = button.dataset.active === '1';
   const action = active ? 'Set Product Inactive' : 'Reactivate Product';
   dialog.querySelector('form').reset();
   document.getElementById('ps_product').value = button.dataset.productId;
   document.getElementById('ps_expected').value = active ? '1' : '0';
   document.getElementById('ps_active').value = active ? '0' : '1';
   document.getElementById('ps_title').textContent = action;
   document.getElementById('ps_description').textContent = button.dataset.productSku + ' · ' + button.dataset.productName;
   document.getElementById('ps_submit').textContent = action;
   dialog.showModal();
  });
 });
})();
