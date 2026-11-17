<?php
/**
 * DermAve IMS - Products page (read-only list)
 *
 * v2 database: quantity and expiry come from the item's batches.
 *   qty    = total of its active batches (v_item_stock.total_qty)
 *   expiry = the soonest expiry among batches that still have stock (next_expiry)
 * An item that does not track expiry, or has no stock in any batch, has no expiry
 * date, so it only gets a stock badge. Manufacturing dates now belong to individual
 * batches, so they are no longer shown here (see the Barcode page for batches).
 */
$pageTitle  = 'Products';
$activePage = 'products';
$pageScript = 'table-filter.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

$products = get_products();
?>

  <main class="content">
    <h1>Products</h1>
    <p class="subtitle">Products, brands, dosage, stock and the next expiry date</p>

    <div class="toolbar">
      <input type="search" id="tableSearch" placeholder="Search product, brand or type...">
      <select id="statusFilter" aria-label="Filter by status">
        <option value="all">All statuses</option>
        <option value="expired">Expired</option>
        <option value="soon">Expiring soon</option>
        <option value="low">Low stock</option>
        <option value="out">Out of stock</option>
        <option value="ok">OK</option>
      </select>
    </div>

    <div class="table-wrap">
      <table class="table table--fit js-filter-table" id="productsTable">
        <thead>
          <tr>
            <th>Product Name</th>
            <th>Type</th>
            <th>Brand</th>
            <th>Dosage</th>
            <th>Next Expiry</th>
            <th class="num">Price</th>
            <th class="num">Quantity</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <?php
              // A product can have an expiry warning AND a stock warning, so it may get two badges.
              // No expiry date (not tracked, or nothing in stock) means no expiry badge.
              if (!empty($p['expiry'])) {
                  [$expClass, $expLabel] = product_status($p['expiry']);
              } else {
                  [$expClass, $expLabel] = ['ok', 'OK'];
              }
              $stockLevel = supply_status((int) $p['qty'], (int) $p['min']);

              $badges = [];
              if ($expClass !== 'ok') {
                  $badges[] = [$expClass, $expLabel];
              }
              if ($stockLevel === 'out') {
                  $badges[] = ['out', 'Out of Stock'];
              } elseif ($stockLevel === 'low') {
                  $badges[] = ['low', 'Low Stock'];
              }
              if (!$badges) {
                  $badges[] = ['ok', 'OK'];
              }
              $statusAttr = implode(' ', array_column($badges, 0));
            ?>
            <tr data-status="<?= $statusAttr ?>">
              <td><?= htmlspecialchars($p['item']) ?></td>
              <td><?= htmlspecialchars($p['type'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['brand'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['dosage'] ?: '—') ?></td>
              <td class="nowrap"><?= table_date($p['expiry'] ?? null) ?></td>
              <td class="num"><?= $p['price'] !== null ? '₱' . number_format($p['price'], 2) : '—' ?></td>
              <td class="num"><?= (int) $p['qty'] ?><?= !empty($p['unit']) ? ' ' . htmlspecialchars($p['unit']) : '' ?></td>
              <td>
                <?php foreach ($badges as [$bClass, $bLabel]): ?>
                  <span class="badge badge--<?= $bClass ?>"><?= htmlspecialchars($bLabel) ?></span>
                <?php endforeach; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No products found.</p>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>