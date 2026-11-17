<?php
/**
 * DermAve IMS - Stock page
 * Stock In / Stock Out for products and supplies, plus a log of recent movements.
 * The actual change is made by stock_move() (includes/stock.php) through stock_api.php.
 *
 * v2: stock lives in batches. The pop-up asks for a batch on Stock In
 * (existing batch number, or a new one with an expiry date) and offers an
 * optional batch on Stock Out (default: earliest expiry first).
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

// Data the pop-up needs: reasons for In / Out, and each item's active batches
$stockData = ['reasons' => ['in' => [], 'out' => []], 'batches' => new stdClass()];
if ($dbOk) {
    foreach (['in', 'out'] as $dir) {
        $stockData['reasons'][$dir] = array_column(get_movement_reasons($pdo, $dir), 'name');
    }

    $batches = [];
    $rows = $pdo->query(
        "SELECT b.id, b.item_id, b.batch_number, b.expiry_date, b.qty_on_hand
           FROM item_batches b
           JOIN items i ON i.id = b.item_id
          WHERE b.status = 'active' AND i.status = 'active'
          ORDER BY (b.expiry_date IS NULL), b.expiry_date, b.id"
    )->fetchAll();
    foreach ($rows as $b) {
        $batches[$b['item_id']][] = [
            'id'  => (int) $b['id'],
            'no'  => $b['batch_number'],
            'qty' => (int) $b['qty_on_hand'],
            'exp' => $b['expiry_date'],
        ];
    }
    if ($batches) {
        $stockData['batches'] = $batches;
    }
}

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
              data-tracks="<?= (int) $r['tracks_batches'] ?>"
              data-status="<?= $lvl ?>"
            >
              <td><strong><?= htmlspecialchars($r['name']) ?></strong><?= $r['dosage'] ? ' <span class="muted small">' . htmlspecialchars($r['dosage']) . '</span>' : '' ?></td>
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
            <th>Batch</th>
            <th>Type</th>
            <th class="num">Qty</th>
            <th class="num">Balance</th>
            <th>Reason</th>
            <th>By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m): ?>
            <?php $isIn = (int) $m['quantity_change'] > 0; ?>
            <tr>
              <td class="nowrap"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($m['created_at']))) ?></td>
              <td><?= htmlspecialchars($m['item_name']) ?></td>
              <td><?= htmlspecialchars($m['batch_number'] ?? '—') ?></td>
              <td><span class="badge badge--<?= $isIn ? 'ok' : 'low' ?>"><?= $isIn ? 'Stock in' : 'Stock out' ?></span></td>
              <td class="num"><?= $isIn ? '+' : '−' ?><?= (int) $m['quantity'] ?></td>
              <td class="num"><?= (int) $m['balance_after'] ?></td>
              <td><?= htmlspecialchars($m['reason']) ?></td>
              <td><?= htmlspecialchars($m['user_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$movements): ?>
            <tr><td colspan="8" class="muted">No movements recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Reasons and batches for the pop-up (read by stock.js) -->
    <script type="application/json" id="stockData"><?= json_encode($stockData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

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

          <!-- Stock IN of a batch-tracked item: which batch -->
          <div id="m_batchIn" hidden>
            <label>Batch number
              <input type="text" id="m_batch" maxlength="80" list="m_batchList" autocomplete="off"
                     placeholder="Pick an existing batch or type a new one">
              <datalist id="m_batchList"></datalist>
              <span class="ac__hint" id="m_batchHint"></span>
            </label>
            <div id="m_newBatch" hidden>
              <label>Manufacturing date (optional)
                <input type="date" id="m_mfg">
              </label>
              <label>Expiry date
                <input type="date" id="m_exp">
              </label>
            </div>
          </div>

          <!-- Stock OUT of a batch-tracked item: optional batch -->
          <label id="m_batchOut" hidden>Batch
            <select id="m_batchSel"></select>
          </label>

          <label>Quantity
            <input type="number" id="m_qty" min="1" step="1" required>
          </label>
          <label>Reason
            <select id="m_reason" required></select>
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