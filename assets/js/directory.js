/**
 * DermAve IMS - Directory page behaviour
 * - Product / Machine / Supply buttons (each shows its own columns)
 * - Search
 * - Clickable column headers (sort + filter menus)
 * - Click a row to select it (Ctrl/Cmd + click to select several) → enables Edit / Archive
 * - Add / Edit modal + Archive / Restore, saved to MySQL through directory_api.php
 */

(function () {
  const table = document.getElementById('directoryTable');
  if (!table) return;

  const tbody = table.querySelector('tbody');
  const searchInput = document.getElementById('tableSearch');
  const noResults = document.getElementById('noResults');
  const btnEdit = document.getElementById('btnEdit');
  const btnArchive = document.getElementById('btnArchive');
  const btnAdd = document.getElementById('btnAdd');
  const modal = document.getElementById('itemModal');
  const form = document.getElementById('itemForm');
  const modalTitle = document.getElementById('modalTitle');
  const typeTabs = document.querySelectorAll('#typeTabs [data-type]');
  const nameLabel = document.getElementById('nameLabel');

  const TYPES = ['product', 'machine', 'supply'];
  const NAME_LABELS = { product: 'Item Name', machine: 'Machine Name', supply: 'Item Name' };

  // Which type is showing (remembered across the page reload after a save)
  let currentType = 'product';
  try {
    const saved = sessionStorage.getItem('dirType');
    if (TYPES.includes(saved)) currentType = saved;
  } catch (e) {}

  // Active filters: { colName: value | null }
  const filters = {};
  // Active sort: { col, dir: 'asc'|'desc' }
  let sort = { col: null, dir: 'asc' };

  /* ---------- helpers ---------- */
  function visibleRows() {
    return Array.from(tbody.querySelectorAll('tr')).filter((r) => !r.hidden);
  }

  function selectedRows() {
    return visibleRows().filter((r) => r.classList.contains('is-selected'));
  }

  function clearSelection() {
    tbody.querySelectorAll('tr.is-selected').forEach((r) => r.classList.remove('is-selected'));
  }

  function updateActionButtons() {
    const n = selectedRows().length;
    btnEdit.disabled = n !== 1;
    btnArchive.disabled = n === 0;
    // If every selected row is already archived, the button restores them instead
    btnArchive.textContent = archiveMode() === 'restore' ? 'Restore' : 'Archive';
  }

  // 'restore' when everything selected is archived, otherwise 'archive'
  function archiveMode() {
    const rows = selectedRows();
    return rows.length && rows.every((r) => r.dataset.status === 'archived') ? 'restore' : 'archive';
  }

  function applyAll() {
    const text = (searchInput?.value || '').trim().toLowerCase();
    let visible = 0;

    tbody.querySelectorAll('tr').forEach((row) => {
      // only the rows of the chosen type (Product / Machine / Supply)
      const matchesType = row.dataset.item_type === currentType;

      // text search across whole row
      const matchesText = !text || row.textContent.toLowerCase().includes(text);

      // column filters
      let matchesFilters = true;
      for (const [col, val] of Object.entries(filters)) {
        if (val == null) continue;
        if ((row.dataset[col] || '') !== val) {
          matchesFilters = false;
          break;
        }
      }

      const show = matchesType && matchesText && matchesFilters;
      row.hidden = !show;
      if (!show) row.classList.remove('is-selected');
      if (show) visible++;
    });

    // sort
    if (sort.col) {
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const col = sort.col;
      const dir = sort.dir === 'asc' ? 1 : -1;
      const th = table.querySelector(`th[data-col="${col}"]`);
      const type = th?.dataset.type || 'text';

      rows.sort((a, b) => {
        let va = a.dataset[col] ?? '';
        let vb = b.dataset[col] ?? '';
        if (type === 'number') {
          return (Number(va) - Number(vb)) * dir;
        }
        if (type === 'date') {
          const da = va ? new Date(va).getTime() : 0;
          const db = vb ? new Date(vb).getTime() : 0;
          return (da - db) * dir;
        }
        return String(va).localeCompare(String(vb), undefined, { sensitivity: 'base' }) * dir;
      });
      rows.forEach((r) => tbody.appendChild(r));
    }

    if (noResults) noResults.hidden = visible > 0;
    updateActionButtons();
  }

  /* ---------- type buttons ---------- */
  // Category filter menu: only list categories that exist for the chosen type
  function syncCategoryMenu() {
    const present = new Set(
      Array.from(tbody.querySelectorAll('tr'))
        .filter((r) => r.dataset.item_type === currentType)
        .map((r) => r.dataset.category)
    );
    table.querySelectorAll('th[data-col="category"] [data-action="filter"]').forEach((b) => {
      b.style.display = present.has(b.dataset.value) ? '' : 'none';
    });
  }

  function setType(type) {
    if (!TYPES.includes(type)) return;
    currentType = type;
    try { sessionStorage.setItem('dirType', type); } catch (e) {}

    // button highlight + which columns show (CSS reads data-view)
    typeTabs.forEach((b) => {
      const on = b.dataset.type === type;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    table.dataset.view = type;
    if (nameLabel) nameLabel.textContent = NAME_LABELS[type];

    // start fresh: no selection, no column filters
    clearSelection();
    Object.keys(filters).forEach((k) => delete filters[k]);
    table.querySelectorAll('.th-menu').forEach((menu) => {
      menu.hidden = true;
      menu.querySelectorAll('[data-action="filter"]').forEach((b) => b.classList.remove('active'));
      menu.querySelector('[data-action="filter-all"]')?.classList.add('active');
    });

    syncCategoryMenu();
    applyAll();
  }

  typeTabs.forEach((b) => b.addEventListener('click', () => setType(b.dataset.type)));

  /* ---------- search ---------- */
  searchInput?.addEventListener('input', applyAll);

  /* ---------- row selection (click a row) ---------- */
  tbody.addEventListener('click', (e) => {
    const row = e.target.closest('tr');
    if (!row || row.hidden) return;

    if (e.ctrlKey || e.metaKey) {
      // Ctrl/Cmd + click: add or remove this row from the selection
      row.classList.toggle('is-selected');
    } else {
      // plain click: select only this row (click it again to unselect)
      const onlyThis = row.classList.contains('is-selected') && selectedRows().length === 1;
      clearSelection();
      if (!onlyThis) row.classList.add('is-selected');
    }
    updateActionButtons();
  });

  /* ---------- column header menus ---------- */
  table.querySelectorAll('th.sortable, th.filterable').forEach((th) => {
    const menu = th.querySelector('.th-menu');
    if (!menu) return;

    th.addEventListener('click', (e) => {
      // ignore clicks inside the open menu itself (handled below)
      if (e.target.closest('.th-menu')) return;

      // close other menus
      table.querySelectorAll('.th-menu').forEach((m) => {
        if (m !== menu) m.hidden = true;
      });
      menu.hidden = !menu.hidden;
    });

    menu.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      e.stopPropagation();

      const action = btn.dataset.action;
      const col = th.dataset.col;

      if (action === 'sort-asc' || action === 'sort-desc') {
        sort = { col, dir: action === 'sort-asc' ? 'asc' : 'desc' };
        // visual active state on sort buttons
        menu.querySelectorAll('[data-action^="sort"]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
      } else if (action === 'filter-all') {
        filters[col] = null;
        menu.querySelectorAll('[data-action="filter"], [data-action="filter-all"]').forEach((b) =>
          b.classList.remove('active')
        );
        btn.classList.add('active');
      } else if (action === 'filter') {
        filters[col] = btn.dataset.value;
        menu.querySelectorAll('[data-action="filter"], [data-action="filter-all"]').forEach((b) =>
          b.classList.remove('active')
        );
        btn.classList.add('active');
      }

      menu.hidden = true;
      applyAll();
    });
  });

  // close menus when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('th.sortable, th.filterable')) {
      table.querySelectorAll('.th-menu').forEach((m) => (m.hidden = true));
    }
  });

  /* ---------- server calls ---------- */
  const csrf = document.getElementById('f_csrf')?.value || '';

  async function callApi(fields) {
    const body = new URLSearchParams();
    body.set('csrf', csrf);
    for (const [k, v] of Object.entries(fields)) {
      if (Array.isArray(v)) v.forEach((x) => body.append(k + '[]', x));
      else body.set(k, v);
    }
    let res, json;
    try {
      res = await fetch('directory_api.php', { method: 'POST', body });
      if (res.status === 401) {
        // session ended: go back to the login page
        location.href = 'login.php?expired=1';
        return { ok: false, errors: ['Your session has ended. Please sign in again.'] };
      }
      json = await res.json();
    } catch (err) {
      return { ok: false, errors: ['Could not reach the server. Is Apache running?'] };
    }
    if (!json.ok && !json.errors) json.errors = [json.error || 'Something went wrong.'];
    return json;
  }

  // Remember a message across the page reload, then show it
  function reloadWithMessage(msg) {
    try { sessionStorage.setItem('dirFlash', msg); } catch (e) {}
    location.reload();
  }

  const flash = document.getElementById('flash');
  try {
    const msg = sessionStorage.getItem('dirFlash');
    if (msg && flash) {
      flash.textContent = msg;
      flash.hidden = false;
      sessionStorage.removeItem('dirFlash');
      setTimeout(() => (flash.hidden = true), 5000);
    }
  } catch (e) {}

  /* ---------- modal helpers ---------- */
  const formError = document.getElementById('formError');
  const btnSave = document.getElementById('btnSave');
  const typeSelect = document.getElementById('f_item_type');
  const FIELDS = [
    'name', 'brand', 'category', 'subcategory', 'dosage', 'quantity', 'unit', 'min_stock',
    'price', 'batch_number', 'manufacturing_date', 'expiration_date',
    'last_maintenance', 'next_maintenance', 'notes',
  ];

  // Show only the fields that apply to the chosen type
  function syncTypeFields() {
    const type = typeSelect.value;
    form.querySelectorAll('[data-for]').forEach((el) => {
      el.hidden = !el.dataset.for.split(' ').includes(type);
    });
  }
  typeSelect?.addEventListener('change', syncTypeFields);

  function showFormErrors(list) {
    formError.innerHTML = '';
    list.forEach((m) => {
      const d = document.createElement('div');
      d.textContent = m;
      formError.appendChild(d);
    });
    formError.hidden = list.length === 0;
  }

  function openModal(mode, row) {
    modal.hidden = false;
    modalTitle.textContent = mode === 'edit' ? 'Edit item' : 'Add item';
    form.reset();
    showFormErrors([]);
    document.getElementById('f_id').value = '';

    if (mode === 'edit' && row) {
      let item = {};
      try { item = JSON.parse(row.dataset.item || '{}'); } catch (e) {}
      document.getElementById('f_id').value = item.id || row.dataset.id || '';
      typeSelect.value = item.item_type || 'product';
      FIELDS.forEach((f) => {
        const el = document.getElementById('f_' + f);
        if (el) el.value = item[f] ?? '';
      });
    } else {
      // Add: start with the type that is currently showing
      typeSelect.value = currentType;
    }
    syncTypeFields();
    document.getElementById('f_name').focus();
  }

  function closeModal() {
    modal.hidden = true;
  }

  modal?.querySelectorAll('[data-close]').forEach((el) => {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
  });

  btnAdd?.addEventListener('click', () => openModal('add'));

  btnEdit?.addEventListener('click', () => {
    const rows = selectedRows();
    if (rows.length === 1) openModal('edit', rows[0]);
  });

  /* ---------- archive / restore ---------- */
  btnArchive?.addEventListener('click', async () => {
    const rows = selectedRows();
    if (!rows.length) return;

    const mode = archiveMode();
    const targets = rows.filter((r) => (mode === 'restore') === (r.dataset.status === 'archived'));
    const verb = mode === 'restore' ? 'Restore' : 'Archive';
    if (!confirm(`${verb} ${targets.length} selected item(s)?`)) return;

    btnArchive.disabled = true;
    const res = await callApi({ action: mode, ids: targets.map((r) => r.dataset.id) });
    if (res.ok) {
      reloadWithMessage(res.message);
    } else {
      alert(res.errors.join('\n'));
      updateActionButtons();
    }
  });

  /* ---------- save (add / edit) ---------- */
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    showFormErrors([]);
    btnSave.disabled = true;

    const fields = Object.fromEntries(new FormData(form).entries());
    fields.action = 'save';
    const res = await callApi(fields);

    btnSave.disabled = false;
    if (res.ok) {
      // after the reload, show the type that was just saved
      try { sessionStorage.setItem('dirType', fields.item_type); } catch (err) {}
      reloadWithMessage(res.message);
    } else {
      showFormErrors(res.errors);
    }
  });

  // initial
  setType(currentType);
})();
