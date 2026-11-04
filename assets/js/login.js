/**
 * DermAve IMS - login page behaviour
 * - Show / Hide password
 * - Inline validation message (no alert pop-ups)
 * - "Forgot?" popup: admins can set a new password; everyone else is told
 *   to contact the system administrator
 */
(function () {
  const form = document.getElementById('loginForm');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const toggle = document.getElementById('togglePassword');
  const clientError = document.getElementById('clientError');
  const serverError = document.getElementById('serverError');
  const loginNotice = document.getElementById('loginNotice');

  /* ---------- show / hide password ---------- */
  toggle?.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
  });

  /* ---------- inline validation ---------- */
  form?.addEventListener('submit', (e) => {
    if (!username.value.trim() || !password.value) {
      e.preventDefault();
      if (serverError) serverError.hidden = true;
      if (loginNotice) loginNotice.hidden = true;
      clientError.textContent = 'Please enter your username and password.';
      clientError.hidden = false;
      (username.value.trim() ? password : username).focus();
    }
  });

  /* ---------- forgot password popup ---------- */
  const modal = document.getElementById('forgotModal');
  const forgotBtn = document.getElementById('forgotBtn');
  const fgForm = document.getElementById('fgForm');
  if (!modal || !forgotBtn || !fgForm) return;

  const fgError = document.getElementById('fgError');
  const fgIntro = document.getElementById('fgIntro');
  const fgStep2 = document.getElementById('fgStep2');
  const fgUser = document.getElementById('fgUsername');
  const fgCode = document.getElementById('fgCode');
  const fgNew = document.getElementById('fgNew');
  const fgConfirm = document.getElementById('fgConfirm');
  const fgSubmit = document.getElementById('fgSubmit');
  const fgCsrf = document.getElementById('fgCsrf').value;
  const MIN = 8;

  let step = 1; // 1 = enter username, 2 = admin enters code + new password

  function showFg(msg, isNotice) {
    fgError.textContent = msg;
    fgError.hidden = !msg;
    fgError.classList.toggle('alert--success', false);
    fgError.dataset.notice = isNotice ? '1' : '';
  }

  function resetPopup() {
    step = 1;
    fgStep2.hidden = true;
    fgUser.readOnly = false;
    fgIntro.textContent = 'Enter your username to continue.';
    fgSubmit.textContent = 'Continue';
    fgCode.value = fgNew.value = fgConfirm.value = '';
    showFg('');
  }

  function openPopup() {
    resetPopup();
    fgUser.value = username.value.trim();
    modal.hidden = false;
    fgUser.focus();
  }

  function closePopup() {
    modal.hidden = true;
  }

  forgotBtn.addEventListener('click', openPopup);
  modal.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', closePopup));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closePopup();
  });

  async function post(fields) {
    const body = new URLSearchParams({ csrf: fgCsrf, ...fields });
    try {
      const r = await fetch('forgot_api.php', { method: 'POST', body });
      return await r.json();
    } catch (e) {
      return { ok: false, message: 'Could not reach the server. Is Apache running?' };
    }
  }

  fgForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    showFg('');

    const name = fgUser.value.trim();
    if (!name) return showFg('Please enter your username.');

    fgSubmit.disabled = true;

    if (step === 1) {
      const res = await post({ action: 'check', username: name });
      fgSubmit.disabled = false;
      if (res.ok && res.admin) {
        step = 2;
        fgUser.readOnly = true;
        fgStep2.hidden = false;
        fgIntro.textContent = 'Enter the administrator recovery code and choose a new password.';
        fgSubmit.textContent = 'Change password';
        fgCode.focus();
      } else {
        showFg(res.message || 'Something went wrong.', !!res.notice);
      }
      return;
    }

    // step 2: validate in the browser first, then send
    if (!fgCode.value) { fgSubmit.disabled = false; return showFg('Please enter the recovery code.'); }
    if (!fgNew.value) { fgSubmit.disabled = false; return showFg('Please enter a new password.'); }
    if (fgNew.value.length < MIN) { fgSubmit.disabled = false; return showFg('Password must be at least ' + MIN + ' characters long.'); }
    if (fgNew.value !== fgConfirm.value) { fgSubmit.disabled = false; return showFg('The passwords do not match.'); }

    const res = await post({
      action: 'reset', username: name, code: fgCode.value, password: fgNew.value, confirm: fgConfirm.value,
    });
    fgSubmit.disabled = false;

    if (res.ok) {
      closePopup();
      if (serverError) serverError.hidden = true;
      clientError.hidden = true;
      loginNotice.textContent = res.message;
      loginNotice.hidden = false;
      username.value = name;
      password.value = '';
      password.focus();
    } else {
      showFg(res.message || 'Something went wrong.');
    }
  });
})();
