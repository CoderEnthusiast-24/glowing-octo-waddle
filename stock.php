<?php
/**
 * DermAve IMS - Stock page
 * Stock In / Stock Out for products and supplies, plus a log of recent movements.
 * The actual change is made by stock_move() (includes/stock.php) through stock_api.php.
 */

$pageTitle  = 'Stock';
$activePage = 'stock';
$pageScript = 'stock.js';
$extraCss   = ['stock.css'];

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/stock.php';
require_once __DIR__ . '/includes/header.php';

$pdo  = db();
$dbOk = $pdo !== null;

// Token the JavaScript sends back with every request
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Machines cannot be moved in or out, so only products and supplies are listed
$items = array_values(array_filter(
    get_directory_items(),                       // active items only
    fn($r) => $r['item_type'] !== 'machine'
));

$movements = $dbOk ? get_recent_movements($pdo, 25) : [];

$levelLabels = ['out' => 'Out of Stock', 'low' => 'Low Stock', 'ok' => 'In Stock'];
?>

  <main class="content">
    <h1>Stock</h1>
    <p class="subtitle">Stock in / stock out for products and supplies &mdash; every change is logged</p>

    <?php if (!$dbOk): ?>
      <div class="alert">
        <strong>Not connected to the database.</strong> Stock in / out is turned off.<br>
        <span class="small">Reason: <?= htmlspecialchars(db_error() ?? 'unknown') ?></span>
      </div>
    <?php endif; ?>
    <div class="alert alert--success" id="flash" hidden></div>

    <div class="stock-bar">
      <div class="type-tabs" id="typeTabs" role="group" aria-label="Item type">
        <button type="button" class="btn btn--ghost type-tab is-active" data-type="product" aria-pressed="true">Product</button>
        <button type="button" class="btn btn--ghost type-tab" data-type="supply" aria-pressed="false">Supply</button>
      </div>

      <div class="stock-bar__actions">
        <button type="button" class="btn btn--primary js-move" data-move="in"<?= $dbOk ? '' : ' disabled' ?>>In</button>
        <button type="button" class="btn btn--ghost js-move" data-move="out"<?= $dbOk ? '' : ' disabled' ?>>Out</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table table--fit" id="stockTable">
        <thead>
          <tr>
            <th>Item Name</th>
            <th>Category</th>
            <th class="num">In Stock</th>
            <th class="num">Min</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $r): ?>
            <?php
              $qty = (int) $r['quantity'];
              $min = (int) ($r['min_stock'] ?? 0);
              $lvl = supply_status($qty, $min);
            ?>
            <tr
              data-id="<?= (int) $r['id'] ?>"
              data-item_type="<?= htmlspecialchars($r['item_type']) ?>"
              data-name="<?= htmlspecialchars($r['name']) ?>"
              data-qty="<?= $qty ?>"
              data-unit="<?= htmlspecialchars($r['unit'] ?? '') ?>"
              data-status="<?= $lvl ?>"
            >
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
              <td class="num"><span class="qty-num"><?= $qty ?></span><?= $r['unit'] ? ' ' . htmlspecialchars($r['unit']) : '' ?></td>
              <td class="num"><?= $r['min_stock'] !== null ? $min : '—' ?></td>
              <td><span class="badge badge--<?= $lvl ?>"><?= $levelLabels[$lvl] ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No items found.</p>
    </div>

    <h2 class="section-title" style="margin-top:32px">Recent movements</h2>
    <div class="table-wrap">
      <table class="table table--fit">
        <thead>
          <tr>
            <th>When</th>
            <th>Item</th>
            <th>Type</th>
            <th class="num">Qty</th>
            <th class="num">Balance</th>
            <th>Reason</th>
            <th>By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m): ?>
            <tr>
              <td class="nowrap"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($m['created_at']))) ?></td>
              <td><?= htmlspecialchars($m['item_name']) ?></td>
              <td><span class="badge badge--<?= $m['movement_type'] === 'in' ? 'ok' : 'low' ?>"><?= $m['movement_type'] === 'in' ? 'Stock in' : 'Stock out' ?></span></td>
              <td class="num"><?= $m['movement_type'] === 'in' ? '+' : '−' ?><?= (int) $m['quantity'] ?></td>
              <td class="num"><?= (int) $m['balance_after'] ?></td>
              <td><?= htmlspecialchars($m['reason']) ?></td>
              <td><?= htmlspecialchars($m['user_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$movements): ?>
            <tr><td colspan="7" class="muted">No movements recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Stock in / out modal -->
    <div class="modal" id="moveModal" hidden>
      <div class="modal__backdrop" data-close></div>
      <div class="modal__card">
        <h2 id="moveTitle">Stock in</h2>
        <div class="alert" id="moveError" hidden></div>
        <form id="moveForm" novalidate>
          <input type="hidden" id="m_csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
          <input type="hidden" id="m_id">
          <input type="hidden" id="m_type">

          <label>Name
            <div class="ac">
              <input type="text" id="m_name" maxlength="255" autocomplete="off" required
                     role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="m_suggest"
                     placeholder="Start typing an item name…">
              <div class="ac__list" id="m_suggest" role="listbox" hidden></div>
            </div>
            <span class="ac__hint" id="m_hint"></span>
          </label>
          <label>Quantity
            <input type="number" id="m_qty" min="1" step="1" required>
          </label>
          <label>Reason
            <input type="text" id="m_reason" maxlength="150" list="reasonList" required autocomplete="off">
            <datalist id="reasonList"></datalist>
          </label>

          <div class="form-actions">
            <button type="button" class="btn btn--ghost" data-close>Cancel</button>
            <button type="submit" class="btn btn--primary" id="m_save">Save</button>
          </div>
        </form>
      </div>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
