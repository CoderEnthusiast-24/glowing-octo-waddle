<?php
/**
 * DermAve IMS - Directory page
 * Unified view of products, machines and supplies.
 * Buttons above the table switch between the three item types;
 * each type shows its own set of columns.
 *
 * v2 database: products + supplies come from `items`, machines from `machines`.
 * get_directory_items() merges both and gives every row an `item_type`.
 * Quantity, expiry and stock are read-only here: they come from batches and
 * are changed on the Stock and Barcode pages (so every change is logged).
 */

$pageTitle  = 'Directory';
$activePage = 'directory';
$pageScript = 'directory.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

// Include archived rows so they can be restored
$items = get_directory_items(['include_archived' => true]);

// Is the page showing real database rows, or the built-in sample data?
$pdo  = db();
$dbOk = $pdo !== null;

// Token the JavaScript sends back with every Add / Edit / Archive request
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Options for the Brand / Category / Unit dropdowns in the form
$brands = $units = $categories = [];
if ($dbOk) {
    try {
        $brands = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();
        $units  = $pdo->query('SELECT id, name FROM units ORDER BY name')->fetchAll();
        // parents first, each followed by its children
        $categories = $pdo->query(
            "SELECT c.id, c.name, c.applies_to, p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.status = 'active'
             ORDER BY COALESCE(p.name, c.name), (c.parent_id IS NOT NULL), c.name"
        )->fetchAll();
    } catch (Exception $e) {
        // leave the dropdowns empty; the page still works for viewing
    }
}

// Category filter menu: the (parent) category names that exist in the data
$categoryOptions = array_values(array_unique(array_filter(array_column($items, 'category'))));
sort($categoryOptions);

// Helper to format dates safely
function fmt_date(?string $d): string
{
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('M d, Y', $ts) : '—';
}
?>

  <main class="content">
    <h1>Directory</h1>
    <p class="subtitle">All clinic items in one place &mdash; products, machines &amp; supplies</p>

    <?php if (!$dbOk): ?>
      <div class="alert">
        <strong>Not connected to the database.</strong> Showing built-in sample data, so Add / Edit / Archive are turned off.<br>
        <span class="small">Reason: <?= htmlspecialchars(db_error() ?? 'unknown') ?></span><br>
        <span class="small">Check that MySQL is running in XAMPP, that <code>dermave_ims_v2</code> exists in phpMyAdmin, and that the DB_* settings in <code>includes/config.php</code> match.</span>
      </div>
    <?php endif; ?>
    <div class="alert alert--success" id="flash" hidden></div>

    <div class="toolbar toolbar--directory">
      <input type="search" id="tableSearch" placeholder="Search name, brand, category, barcode…">

      <div class="toolbar__actions">
        <button type="button" class="btn btn--primary" id="btnAdd"<?= $dbOk ? '' : ' disabled' ?>>Add</button>
        <button type="button" class="btn btn--ghost" id="btnEdit" disabled>Edit</button>
        <button type="button" class="btn btn--ghost" id="btnArchive" disabled>Archive</button>
      </div>
    </div>

    <!-- Item type switch -->
    <div class="type-tabs" id="typeTabs" role="group" aria-label="Item type">
      <button type="button" class="btn btn--ghost type-tab is-active" data-type="product" aria-pressed="true">Product</button>
      <button type="button" class="btn btn--ghost type-tab" data-type="machine" aria-pressed="false">Machine</button>
      <button type="button" class="btn btn--ghost type-tab" data-type="supply" aria-pressed="false">Supply</button>
    </div>

    <div class="table-wrap">
      <!--
        Column rules (hidden with CSS based on data-view):
          product : Item Name, Category, Dosage, Qty, Expiration Date, Status
          supply  : Item Name, Category, Qty, Status
          machine : Machine Name, Category, Status
        Qty and Expiration Date are read from batches (read-only).
      -->
      <table class="table table--fit js-directory-table" id="directoryTable" data-view="product">
        <thead>
          <tr>
            <th class="sortable filterable" data-col="name" data-type="text">
              <span id="nameLabel">Item Name</span> <span class="th-icon">▾</span>
              <div class="th-menu" hidden>
                <button type="button" data-action="sort-asc">Sort A → Z</button>
                <button type="button" data-action="sort-desc">Sort Z → A</button>
              </div>
            </th>
            <th class="sortable filterable" data-col="category" data-type="enum">
              Category <span class="th-icon">▾</span>
              <div class="th-menu" hidden>
                <button type="button" data-action="sort-asc">Sort A → Z</button>
                <button type="button" data-action="sort-desc">Sort Z → A</button>
                <hr>
                <button type="button" data-action="filter-all" class="active">All categories</button>
                <?php foreach ($categoryOptions as $opt): ?>
                  <button type="button" data-action="filter" data-value="<?= htmlspecialchars($opt) ?>">
                    <?= htmlspecialchars($opt) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </th>
            <th class="c-dosage">Dosage</th>
            <th class="sortable c-qty" data-col="quantity" data-type="number">
              Qty <span class="th-icon">▾</span>
              <div class="th-menu" hidden>
                <button type="button" data-action="sort-asc">Sort low → high</button>
                <button type="button" data-action="sort-desc">Sort high → low</button>
              </div>
            </th>
            <th class="sortable c-exp" data-col="date_key" data-type="date">
              Expiration Date <span class="th-icon">▾</span>
              <div class="th-menu" hidden>
                <button type="button" data-action="sort-asc">Soonest first</button>
                <button type="button" data-action="sort-desc">Latest first</button>
              </div>
            </th>
            <th class="sortable filterable" data-col="status" data-type="enum">
              Status <span class="th-icon">▾</span>
              <div class="th-menu" hidden>
                <button type="button" data-action="sort-asc">Sort A → Z</button>
                <button type="button" data-action="sort-desc">Sort Z → A</button>
                <hr>
                <button type="button" data-action="filter-all" class="active">All statuses</button>
                <button type="button" data-action="filter" data-value="active">Active</button>
                <button type="button" data-action="filter" data-value="under_repair">Under repair</button>
                <button type="button" data-action="filter" data-value="retired">Retired</button>
                <button type="button" data-action="filter" data-value="archived">Archived</button>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $row): ?>
            <?php
              $type = $row['item_type'];

              // Only products show an expiration date column (next expiry among their batches)
              $dateKey = $type === 'product' ? ($row['expiration_date'] ?? '') : '';
              $qtyUnit = !empty($row['unit']) ? ' ' . $row['unit'] : '';

              // Status badge: active = quiet, under repair = outline, retired / archived = gold
              $status      = $row['status'];
              $statusLabel = ucfirst(str_replace('_', ' ', $status));
              $badgeClass  = $status === 'active' ? 'ok' : ($status === 'under_repair' ? 'soon' : 'expired');

              // One lowercase string the search box looks through
              $search = strtolower(implode(' ', array_filter([
                  $row['name'] ?? '', $row['brand'] ?? '', $row['category'] ?? '', $row['subcategory'] ?? '',
                  $row['barcode'] ?? '', $row['dosage'] ?? '', $row['model'] ?? '',
                  $row['serial_number'] ?? '', $row['unit_label'] ?? '',
              ])));
            ?>
            <tr
              data-id="<?= (int) $row['id'] ?>"
              data-item_type="<?= htmlspecialchars($type) ?>"
              data-name="<?= htmlspecialchars($row['name']) ?>"
              data-category="<?= htmlspecialchars($row['category'] ?? '') ?>"
              data-dosage="<?= htmlspecialchars($row['dosage'] ?? '') ?>"
              data-quantity="<?= (int) ($row['quantity'] ?? 0) ?>"
              data-date_key="<?= htmlspecialchars((string) $dateKey) ?>"
              data-status="<?= htmlspecialchars($status) ?>"
              data-search="<?= htmlspecialchars($search) ?>"
              data-item="<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), ENT_QUOTES) ?>"
            >
              <td>
                <strong><?= htmlspecialchars($row['name']) ?></strong>
                <?php if ($type === 'machine' && !empty($row['unit_label'])): ?>
                  <span class="muted small">&middot; <?= htmlspecialchars($row['unit_label']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['category'] ?? '—') ?></td>
              <td class="c-dosage"><?= htmlspecialchars($row['dosage'] ?? '—') ?></td>
              <td class="c-qty"><span class="qty-num"><?= (int) ($row['quantity'] ?? 0) ?></span><?= htmlspecialchars($qtyUnit) ?></td>
              <td class="c-exp"><?= htmlspecialchars(fmt_date($row['expiration_date'] ?? null)) ?></td>
              <td><span class="badge badge--<?= $badgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No items match the current filters.</p>
    </div>

    <!-- Add / Edit modal -->
    <div class="modal" id="itemModal" hidden>
      <div class="modal__backdrop" data-close></div>
      <div class="modal__card modal__card--wide">
        <h2 id="modalTitle">Add item</h2>
        <div class="alert" id="formError" hidden></div>
        <form id="itemForm" novalidate>
          <input type="hidden" name="csrf" id="f_csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
          <input type="hidden" name="id" id="f_id">

          <div class="modal__grid">
            <!-- Every type -->
            <label>Type
              <select name="item_type" id="f_item_type" required>
                <option value="product">Product</option>
                <option value="machine">Machine</option>
                <option value="supply">Supply</option>
              </select>
            </label>
            <label>Name *
              <input type="text" name="name" id="f_name" maxlength="255" required>
            </label>

            <label>Brand
              <select name="brand_id" id="f_brand_id">
                <option value="">—</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Category
              <select name="category_id" id="f_category_id">
                <option value="">—</option>
                <?php foreach ($categories as $c): ?>
                  <?php $label = $c['parent_name'] ? $c['parent_name'] . ' › ' . $c['name'] : $c['name']; ?>
                  <option value="<?= (int) $c['id'] ?>" data-applies="<?= htmlspecialchars($c['applies_to']) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <!-- Products and supplies -->
            <label data-for="product supply">Unit
              <select name="unit_id" id="f_unit_id">
                <option value="">—</option>
                <?php foreach ($units as $u): ?>
                  <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label data-for="product">Dosage / Spec
              <input type="text" name="dosage" id="f_dosage" maxlength="120" placeholder="e.g. 500 mg">
            </label>

            <label data-for="product supply">Minimum stock
              <input type="number" name="min_stock" id="f_min_stock" min="0" step="1">
            </label>
            <label data-for="product supply">Price (₱)
              <input type="number" name="price" id="f_price" min="0" step="0.01">
            </label>

            <label data-for="product supply">Barcode
              <input type="text" name="barcode" id="f_barcode" maxlength="64" autocomplete="off">
            </label>

            <label data-for="product supply" style="display:flex;align-items:center;gap:8px">
              <input type="checkbox" name="tracks_batches" id="f_tracks_batches" value="1" style="width:auto;margin:0">
              Track batches
            </label>
            <label data-for="product supply" style="display:flex;align-items:center;gap:8px">
              <input type="checkbox" name="tracks_expiry" id="f_tracks_expiry" value="1" style="width:auto;margin:0">
              Track expiry
            </label>

            <p class="muted small modal__full" data-for="product supply">
              Quantity, batches and expiry dates are not edited here. Use the Stock and Barcode pages so every change is logged.
              Whether an item tracks batches / expiry can only be chosen when it is first added.
            </p>

            <!-- Machines -->
            <label data-for="machine">Unit label
              <input type="text" name="unit_label" id="f_unit_label" maxlength="60" placeholder="e.g. Unit 1">
            </label>
            <label data-for="machine">Model
              <input type="text" name="model" id="f_model" maxlength="120">
            </label>

            <label data-for="machine">Serial number
              <input type="text" name="serial_number" id="f_serial_number" maxlength="120">
            </label>
            <label data-for="machine">Status
              <select name="status" id="f_status">
                <option value="active">Active</option>
                <option value="under_repair">Under repair</option>
                <option value="retired">Retired</option>
              </select>
            </label>

            <label data-for="machine">Purchase date
              <input type="date" name="purchase_date" id="f_purchase_date">
            </label>
            <label data-for="machine">Warranty until
              <input type="date" name="warranty_until" id="f_warranty_until">
            </label>

            <label data-for="machine">Maintenance every (days)
              <input type="number" name="maintenance_interval_days" id="f_maintenance_interval_days" min="1" max="3650" step="1">
            </label>
            <span data-for="machine"></span>

            <label data-for="machine">Last maintenance
              <input type="date" name="last_maintenance" id="f_last_maintenance">
            </label>
            <label data-for="machine">Next maintenance due
              <input type="date" name="next_maintenance" id="f_next_maintenance">
            </label>

            <!-- Every type -->
            <label class="modal__full">Description
              <input type="text" name="description" id="f_description" maxlength="500">
            </label>
            <label class="modal__full">Notes
              <textarea name="notes" id="f_notes" rows="2" maxlength="2000"></textarea>
            </label>
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn--ghost" data-close>Cancel</button>
            <button type="submit" class="btn btn--primary" id="btnSave">Save</button>
          </div>
        </form>
      </div>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>