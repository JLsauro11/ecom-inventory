(() => {
 const lines = document.querySelector('[data-unlinked-lines]');
 if (!lines) return;
 const template = lines.firstElementChild.cloneNode(true);
 const renumber = () => Array.from(lines.children).forEach((row, index) => {
  row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/items\[\d+\]/, 'items[' + index + ']'); });
  row.querySelector('[data-unlinked-remove]').disabled = lines.children.length === 1;
 });
 document.querySelector('[data-unlinked-add]').addEventListener('click', () => {
  if (lines.children.length >= 200) return;
  const row = template.cloneNode(true);
  row.querySelectorAll('input').forEach(input => { input.value = ''; input.removeAttribute('id'); });
  row.querySelectorAll('select').forEach(input => { input.selectedIndex = 0; });
  row.querySelectorAll('.product-results').forEach(box => { box.replaceChildren(); box.classList.remove('open'); });
  lines.append(row); renumber();
  row.querySelectorAll('.product-picker').forEach(root => window.rs8BindProductPicker?.(root));
 });
 lines.addEventListener('click', event => {
  const button = event.target.closest('[data-unlinked-remove]');
  if (button && lines.children.length > 1) { button.closest('tr').remove(); renumber(); }
 });
 renumber();
})();
