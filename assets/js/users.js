/**
 * DermAve IMS - Users page (admin): reset a user's password.
 */
(function () {
  const modal = document.getElementById('resetModal');
  const form = document.getElementById('resetForm');
  if (!modal || !form) return;

  const title = document.getElementById('resetTitle');
  const err = document.getElementById('resetError');
  const saveBtn = document.getElementById('r_save');
  const flash = document.getElementById('flash');
  const idField = document.getElementById('r_id');
  const pw = document.getElementById('r_password');
  const pw2 = document.getElementById('r_confirm');
  const csrf = document.getElementById('r_csrf').value;
  const MIN = parseInt(document.body.dataset.minPassword || '8', 10);

  function showError(msg) {
    err.textContent = msg;
    err.hidden = !msg;
  }

  function openModal(id, username) {
    idField.value = id;
    title.textContent = 'Reset password for ' + username;
    pw.value = '';
    pw2.value = '';
    showError('');
    modal.hidden = false;
    pw.focus();
  }

  function closeModal() {
    modal.hidden = true;
  }

  document.querySelectorAll('.js-reset').forEach((b) => {
    b.addEventListener('click', () => openModal(b.dataset.id, b.dataset.username));
  });
  modal.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showError('');

    if (!pw.value) return showError('Please enter a new password.');
    if (pw.value.length < MIN) return showError('Password must be at least ' + MIN + ' characters long.');
    if (pw.value !== pw2.value) return showError('The passwords do not match.');

    saveBtn.disabled = true;
    const body = new URLSearchParams({
      csrf, action: 'reset', id: idField.value, password: pw.value, confirm: pw2.value,
    });

    let res;
    try {
      const r = await fetch('users_api.php', { method: 'POST', body });
      if (r.status === 401) {
        location.href = 'login.php?expired=1';
        return;
      }
      res = await r.json();
    } catch (x) {
      res = { ok: false, message: 'Could not reach the server. Is Apache running?' };
    }
    saveBtn.disabled = false;

    if (res.ok) {
      closeModal();
      flash.textContent = res.message;
      flash.hidden = false;
      setTimeout(() => (flash.hidden = true), 5000);
    } else {
      showError(res.message || 'Something went wrong.');
    }
  });
})();
