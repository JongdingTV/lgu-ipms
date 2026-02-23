(function () {
  const path = (window.location.pathname || '').replace(/\\/g, '/');
  if (!path.endsWith('/super-admin/employee_accounts.php')) return;

  document.addEventListener('submit', function (event) {
    const form = event.target && event.target.closest('form');
    if (!form) return;
    const btn = form.querySelector('[data-confirm-delete]');
    if (!btn) return;
    const message = btn.getAttribute('data-confirm-delete') || 'Are you sure?';
    if (!window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);
})();

