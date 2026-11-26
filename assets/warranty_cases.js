(() => {
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('wc-form')) return;
    const button = event.submitter || form.querySelector('button[data-confirm]');
    const message = button?.dataset.confirm;
    if (!message || typeof window.rs8Confirm !== 'function') return;
    event.preventDefault();
    window.rs8Confirm(message, () => form.submit());
  });
})();
