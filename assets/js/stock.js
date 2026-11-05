/**
 * DermAve IMS - Stock page
 * - Product / Supply buttons + search
 * - "In" / "Out" buttons on each row open a small form
 * - Saved through stock_api.php, then the page reloads with a message
 */
(function () {
  const table = document.getElementById('stockTable');
  if (!table) return;

  const tbody = table.querySelector('tbody');
  const searchInput = document.getElementById('tableSearch');
  const noResults = document.getElementById('noResults');
  const typeTabs = document.querySelectorAll('#typeTabs [data-type]');
  const flash = document.getElementById('flash');

  const modal = document.getElementById('moveModal');
  const form = document.getElementById('moveForm');
  const title = document.getElementById('moveTitle');
  const itemLabel = document.getElementById('moveItem');
  const errBox = document.getElementById('moveError');
  const saveBtn = document.getElementById('m_save');
  const idField = document.getElementById('m_id');
  const typeField = document.getElementById('m_type');
  const qtyField = document.getElementById('m_qty');
  const reasonField = document.getElementById('m_reason');
  const reasonList = document.getElementById('reasonList');
  const csrf = document.getElementById('m_csrf').value;

  const REASONS = {
    in: ['Restock', 'Returned', 'Stock count correction'],
    out: ['Used in treatment', 'Damaged', 'Expired', 'Lost', 'Stock count correction'],
  };

  /* ---------- type buttons + search ---------- */
  let currentType = 'product';
  try {
    const saved = sessionStorage.getItem('stockType');
    if (saved === 'product' || saved === 'supply') currentType = saved;
  } catch (e) {}

  function applyAll() {
    const text = (searchInput?.value || '').trim().toLowerCase();
    let visible = 0;
    tbody.querySelectorAll('tr').forEach((row) => {
      const show =
        row.dataset.item_type === currentType &&
        (!text || row.textContent.toLowerCase().includes(text));
      row.hidden = !show;
      if (show) visible++;
    });
    if (noResults) noResults.hidden = visible > 0;
  }

  function setType(type) {
    currentType = type;
    try { sessionStorage.setItem('stockType', type); } catch (e) {}
    typeTabs.forEach((b) => {
      const on = b.dataset.type === type;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    applyAll();
  }

  typeTabs.forEach((b) => b.addEventListener('click', () => setType(b.dataset.type)));
  searchInput?.addEventListener('input', applyAll);

  /* ---------- flash message after reload ---------- */
  try {
    const msg = sessionStorage.getItem('stockFlash');
    if (msg && flash) {
      flash.textContent = msg;
      flash.hidden = false;
      sessionStorage.removeItem('stockFlash');
      setTimeout(() => (flash.hidden = true), 5000);
    }
  } catch (e) {}

  /* ---------- modal ---------- */
  function showError(msg) {
    errBox.textContent = msg;
    errBox.hidden = !msg;
  }

  function openModal(row, type) {
    form.reset();
    showError('');
    idField.value = row.dataset.id;
    typeField.value = type;
    title.textContent = type === 'in' ? 'Stock in' : 'Stock out';
    const unit = row.dataset.unit ? ' ' + row.dataset.unit : '';
    itemLabel.textContent = row.dataset.name + ' — currently ' + row.dataset.qty + unit;
    qtyField.max = type === 'out' ? row.dataset.qty : '';
    reasonList.innerHTML = '';
    REASONS[type].forEach((r) => {
      const o = document.createElement('option');
      o.value = r;
      reasonList.appendChild(o);
    });
    modal.hidden = false;
    qtyField.focus();
  }

  function closeModal() {
    modal.hidden = true;
  }

  tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-move');
    if (!btn) return;
    openModal(btn.closest('tr'), btn.dataset.move);
  });
  modal.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  /* ---------- save ---------- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showError('');

    const qty = parseInt(qtyField.value, 10);
    if (!qty || qty < 1 || String(qty) !== qtyField.value.trim()) {
      return showError('Quantity must be a whole number of at least 1.');
    }
    if (!reasonField.value.trim()) return showError('Please give a reason.');

    saveBtn.disabled = true;
    const body = new URLSearchParams({
      csrf,
      action: 'move',
      item_id: idField.value,
      type: typeField.value,
      qty: String(qty),
      reason: reasonField.value.trim(),
    });

    let res;
    try {
      const r = await fetch('stock_api.php', { method: 'POST', body });
      if (r.status === 401) {
        location.href = 'login.php?expired=1';
        return;
      }
      res = await r.json();
    } catch (x) {
      res = { ok: false, error: 'Could not reach the server. Is Apache running?' };
    }
    saveBtn.disabled = false;

    if (res.ok) {
      try { sessionStorage.setItem('stockFlash', res.message); } catch (x) {}
      location.reload();
    } else {
      showError(res.error || 'Something went wrong.');
    }
  });

  setType(currentType);
})();
