/**
 * DermAve IMS - Stock page
 * - Product / Supply buttons filter the table
 * - "In" / "Out" buttons (top right) open a pop-up asking for name, batch, quantity and reason
 * - The name box suggests items while you type (products and supplies)
 * - Batch-tracked items (all products): Stock In needs a batch number (existing, or a new one
 *   with an expiry date); Stock Out can pick a batch or leave it on "earliest expiry first"
 * - Reasons come from the database (movement_reasons) through #stockData
 * - Saved through stock_api.php, then the page reloads with a message
 */
(function () {
  const table = document.getElementById('stockTable');
  if (!table) return;

  const tbody = table.querySelector('tbody');
  const noResults = document.getElementById('noResults');
  const typeTabs = document.querySelectorAll('#typeTabs [data-type]');
  const flash = document.getElementById('flash');

  const modal = document.getElementById('moveModal');
  const form = document.getElementById('moveForm');
  const title = document.getElementById('moveTitle');
  const errBox = document.getElementById('moveError');
  const saveBtn = document.getElementById('m_save');
  const idField = document.getElementById('m_id');
  const typeField = document.getElementById('m_type');
  const nameField = document.getElementById('m_name');
  const suggest = document.getElementById('m_suggest');
  const hint = document.getElementById('m_hint');
  const qtyField = document.getElementById('m_qty');
  const reasonField = document.getElementById('m_reason');
  const csrf = document.getElementById('m_csrf').value;

  // batch controls
  const batchIn = document.getElementById('m_batchIn');
  const batchField = document.getElementById('m_batch');
  const batchList = document.getElementById('m_batchList');
  const batchHint = document.getElementById('m_batchHint');
  const newBatch = document.getElementById('m_newBatch');
  const mfgField = document.getElementById('m_mfg');
  const expField = document.getElementById('m_exp');
  const batchOut = document.getElementById('m_batchOut');
  const batchSel = document.getElementById('m_batchSel');

  // Reasons + batches printed by stock.php
  let DATA = { reasons: { in: [], out: [] }, batches: {} };
  try {
    DATA = JSON.parse(document.getElementById('stockData').textContent) || DATA;
  } catch (e) {}

  // Every product and supply on the page, for the name suggestions
  const items = Array.from(tbody.querySelectorAll('tr')).map((r) => ({
    id: r.dataset.id,
    name: r.dataset.name,
    type: r.dataset.item_type,
    qty: parseInt(r.dataset.qty, 10) || 0,
    unit: r.dataset.unit || '',
    tracks: r.dataset.tracks === '1',
  }));

  function selectedItem() {
    return items.find((x) => x.id === idField.value) || null;
  }

  function batchesOf(it) {
    return (it && DATA.batches[it.id]) || [];
  }

  function fmtDate(d) {
    if (!d) return 'no expiry';
    const dt = new Date(d + 'T00:00:00');
    return isNaN(dt) ? d : dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function isExpired(d) {
    if (!d) return false;
    const today = new Date();
    const iso = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
    return d < iso;
  }

  /* ---------- product / supply buttons ---------- */
  let currentType = 'product';
  try {
    const saved = sessionStorage.getItem('stockType');
    if (saved === 'product' || saved === 'supply') currentType = saved;
  } catch (e) {}

  function applyType() {
    let visible = 0;
    tbody.querySelectorAll('tr').forEach((row) => {
      const show = row.dataset.item_type === currentType;
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
    applyType();
  }

  typeTabs.forEach((b) => b.addEventListener('click', () => setType(b.dataset.type)));

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

  /* ---------- batch fields (depend on the chosen item and In / Out) ---------- */
  function existingBatchByNumber(it, no) {
    const n = no.trim().toLowerCase();
    return n ? batchesOf(it).find((b) => b.no.toLowerCase() === n) || null : null;
  }

  // Show / hide the "new batch" dates while a batch number is typed
  function updateNewBatch() {
    const it = selectedItem();
    const no = batchField.value.trim();
    const existing = it ? existingBatchByNumber(it, no) : null;
    newBatch.hidden = !no || !!existing;
    batchHint.textContent = existing
      ? 'Existing batch: ' + existing.qty + ' in stock, expires ' + fmtDate(existing.exp)
      : no ? 'New batch' : '';
  }

  function updateBatchUI() {
    const it = selectedItem();
    const tracked = !!it && it.tracks;
    const isIn = typeField.value === 'in';

    batchIn.hidden = !(tracked && isIn);
    batchOut.hidden = !(tracked && !isIn);

    // Stock In: suggest the item's existing batch numbers
    batchList.innerHTML = '';
    if (tracked && isIn) {
      batchesOf(it).forEach((b) => {
        const o = document.createElement('option');
        o.value = b.no;
        batchList.appendChild(o);
      });
    }

    // Stock Out: "automatic" or one specific batch that still has stock
    batchSel.innerHTML = '';
    const auto = document.createElement('option');
    auto.value = '';
    auto.textContent = 'Earliest expiry first (automatic)';
    batchSel.appendChild(auto);
    if (tracked && !isIn) {
      batchesOf(it)
        .filter((b) => b.qty > 0)
        .forEach((b) => {
          const o = document.createElement('option');
          o.value = String(b.id);
          o.textContent = b.no + ' — ' + b.qty + ' left, ' + (isExpired(b.exp) ? 'EXPIRED ' : 'expires ') + fmtDate(b.exp);
          batchSel.appendChild(o);
        });
    }

    updateNewBatch();
  }

  batchField.addEventListener('input', updateNewBatch);

  /* ---------- name autocomplete ---------- */
  let matches = [];
  let active = -1;

  function stockText(it) {
    return it.qty + (it.unit ? ' ' + it.unit : '') + ' in stock';
  }

  function updateHint() {
    const it = selectedItem();
    hint.textContent = it ? stockText(it) : '';
    // For stock out you cannot take more than what is there
    qtyField.max = it && typeField.value === 'out' ? String(it.qty) : '';
    updateBatchUI();
  }

  function closeSuggest() {
    suggest.hidden = true;
    nameField.setAttribute('aria-expanded', 'false');
    active = -1;
  }

  function highlight(i) {
    const nodes = suggest.querySelectorAll('.ac__item');
    nodes.forEach((n, k) => n.classList.toggle('is-active', k === i));
    active = i;
    if (nodes[i]) nodes[i].scrollIntoView({ block: 'nearest' });
  }

  function choose(it) {
    nameField.value = it.name;
    idField.value = it.id;
    closeSuggest();
    updateHint();
    (it.tracks ? (typeField.value === 'in' ? batchField : qtyField) : qtyField).focus();
  }

  function renderSuggest() {
    const q = nameField.value.trim().toLowerCase();
    const pool = items.filter((it) => typeField.value !== 'out' || it.qty > 0);

    // names that start with what was typed first, then names that contain it
    const starts = pool.filter((it) => it.name.toLowerCase().startsWith(q));
    const contains = pool.filter(
      (it) => !it.name.toLowerCase().startsWith(q) && it.name.toLowerCase().includes(q)
    );
    matches = (q ? starts.concat(contains) : pool).slice(0, 8);

    suggest.innerHTML = '';
    if (!matches.length) {
      if (q) {
        const none = document.createElement('div');
        none.className = 'ac__empty';
        none.textContent = 'No matching item';
        suggest.appendChild(none);
        suggest.hidden = false;
        nameField.setAttribute('aria-expanded', 'true');
      } else {
        closeSuggest();
      }
      return;
    }

    matches.forEach((it, i) => {
      const row = document.createElement('div');
      row.className = 'ac__item';
      row.setAttribute('role', 'option');

      const label = document.createElement('span');
      label.textContent = it.name;
      const meta = document.createElement('small');
      meta.textContent = it.type === 'supply' ? 'Supply' : 'Product';
      row.append(label, meta);

      // mousedown (not click) so it fires before the input loses focus
      row.addEventListener('mousedown', (e) => {
        e.preventDefault();
        choose(it);
      });
      row.addEventListener('mousemove', () => highlight(i));
      suggest.appendChild(row);
    });
    suggest.hidden = false;
    nameField.setAttribute('aria-expanded', 'true');
    highlight(-1);
  }

  nameField.addEventListener('input', () => {
    idField.value = ''; // typing again means the old pick no longer applies
    updateHint();
    renderSuggest();
  });
  nameField.addEventListener('focus', renderSuggest);
  nameField.addEventListener('blur', closeSuggest);

  nameField.addEventListener('keydown', (e) => {
    const open = !suggest.hidden && matches.length;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (suggest.hidden) renderSuggest();
      else if (open) highlight((active + 1) % matches.length);
    } else if (e.key === 'ArrowUp' && open) {
      e.preventDefault();
      highlight((active - 1 + matches.length) % matches.length);
    } else if (e.key === 'Enter' && open && active >= 0) {
      e.preventDefault();
      choose(matches[active]);
    } else if (e.key === 'Escape' && !suggest.hidden) {
      e.stopPropagation(); // close the list only, not the whole pop-up
      closeSuggest();
    }
  });

  /* ---------- modal ---------- */
  function showError(msg) {
    errBox.textContent = msg;
    errBox.hidden = !msg;
  }

  function openModal(type) {
    form.reset();
    showError('');
    idField.value = '';
    typeField.value = type;
    title.textContent = type === 'in' ? 'Stock in' : 'Stock out';
    hint.textContent = '';
    qtyField.max = '';
    closeSuggest();

    // reasons for this direction, from the database
    reasonField.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = 'Choose a reason…';
    reasonField.appendChild(ph);
    (DATA.reasons[type] || []).forEach((r) => {
      const o = document.createElement('option');
      o.value = r;
      o.textContent = r;
      reasonField.appendChild(o);
    });

    updateBatchUI();
    modal.hidden = false;
    nameField.focus();
  }

  function closeModal() {
    modal.hidden = true;
  }

  document.querySelectorAll('.js-move').forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.dataset.move));
  });
  modal.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  /* ---------- save ---------- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showError('');

    // If the name was typed in full without picking from the list, still match it
    if (!idField.value) {
      const typed = nameField.value.trim().toLowerCase();
      const exact = items.filter((it) => it.name.toLowerCase() === typed);
      if (exact.length === 1) idField.value = exact[0].id;
    }
    if (!idField.value) return showError('Please choose an item from the suggestions.');

    const it = selectedItem();
    const isIn = typeField.value === 'in';
    const qty = parseInt(qtyField.value, 10);
    if (!qty || qty < 1 || String(qty) !== qtyField.value.trim()) {
      return showError('Quantity must be a whole number of at least 1.');
    }
    if (!reasonField.value) return showError('Please choose a reason.');

    // batch checks
    const fields = {};
    if (it && it.tracks) {
      if (isIn) {
        const no = batchField.value.trim();
        if (!no) return showError('Enter a batch number (pick an existing one or type a new one).');
        fields.batch_number = no;
        if (!existingBatchByNumber(it, no)) {
          if (!expField.value) return showError('A new batch needs an expiry date.');
          if (mfgField.value && expField.value <= mfgField.value) {
            return showError('Expiry date must be after the manufacturing date.');
          }
          fields.mfg_date = mfgField.value;
          fields.expiry_date = expField.value;
        }
      } else if (batchSel.value) {
        fields.batch_id = batchSel.value;
        const b = batchesOf(it).find((x) => String(x.id) === batchSel.value);
        if (b && qty > b.qty) {
          return showError('Not enough stock in batch ' + b.no + ' (' + b.qty + ' left).');
        }
      }
    }
    if (!isIn && it && qty > it.qty) {
      return showError('Not enough stock for ' + it.name + ' (' + stockText(it).replace(' in stock', '') + ' left).');
    }

    saveBtn.disabled = true;
    const body = new URLSearchParams({
      csrf,
      action: 'move',
      item_id: idField.value,
      type: typeField.value,
      qty: String(qty),
      reason: reasonField.value,
      ...fields,
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