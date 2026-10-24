(() => {
 const bind = () => {
  const dialog = document.getElementById('inflowSearchDialog');
  if (!dialog) return;
  const form = dialog.querySelector('form');
  const error = dialog.querySelector('.inflow-search-validation');
  let trigger;
  document.querySelectorAll('[data-open-inflow-search]').forEach(button => button.addEventListener('click', () => {
   trigger = button;
   form.reset();
   error.hidden = true;
   dialog.showModal();
   form.elements.inflow_q.focus();
  }));
  const close = () => { dialog.close(); trigger?.focus(); };
  dialog.querySelectorAll('[data-close-inflow-search]').forEach(button => button.addEventListener('click', close));
  dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  form.addEventListener('submit', event => {
   const from = form.elements.date_from.value, to = form.elements.date_to.value;
   if (from && to && from > to) {
    event.preventDefault(); error.textContent = 'From Date must be on or before To Date.'; error.hidden = false; form.elements.date_to.focus();
   }
  });
 };
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
