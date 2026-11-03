<?php
$pageTitle  = 'Products';
$activePage = 'products';
$pageScript = 'table-filter.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

$products = get_products();
?>

  <main class="content">
    <h1>Products</h1>
    <p class="subtitle">Products, brands, dosage and expiry dates &mdash; placeholder data</p>

    <div class="toolbar">
      <input type="search" id="tableSearch" placeholder="Search product, brand or type...">
      <select id="statusFilter" aria-label="Filter by status">
        <option value="all">All statuses</option>
        <option value="expired">Expired</option>
        <option value="soon">Expiring soon</option>
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
            <th>Manufacturing Date</th>
            <th>Expiration Date</th>
            <th class="num">Price</th>
            <th class="num">Quantity</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <?php [$statusClass, $statusLabel] = product_status($p['expiry']); ?>
            <tr data-status="<?= $statusClass ?>">
              <td><?= htmlspecialchars($p['item']) ?></td>
              <td><?= htmlspecialchars($p['type'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['brand'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['dosage'] ?: '—') ?></td>
              <td class="nowrap"><?= table_date($p['mfg']) ?></td>
              <td class="nowrap"><?= table_date($p['expiry']) ?></td>
              <td class="num"><?= $p['price'] !== null ? '₱' . number_format($p['price'], 2) : '—' ?></td>
              <td class="num"><?= (int) $p['qty'] ?></td>
              <td><span class="badge badge--<?= $statusClass ?>"><?= $statusLabel ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No products found.</p>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
