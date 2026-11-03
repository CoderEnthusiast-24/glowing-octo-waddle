<?php
$pageTitle  = 'Supplies';
$activePage = 'supplies';
$pageScript = 'table-filter.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

$supplies = get_supplies();

// Split into the two panels shown at the top of the page
$lowStock = $outOfStock = [];
foreach ($supplies as $s) {
    $status = supply_status($s['stock'], $s['min']);
    if ($status === 'out') {
        $outOfStock[] = $s;
    } elseif ($status === 'low') {
        $lowStock[] = $s;
    }
}
?>

  <main class="content">
    <h1>Supplies</h1>
    <p class="subtitle">Clinic consumables and stock levels &mdash; placeholder data</p>

    <section class="panels">
      <article class="panel">
        <div class="panel__head">
          <h2>Low Stock</h2>
          <span class="panel__count"><?= count($lowStock) ?></span>
        </div>
        <ul class="panel__list">
          <?php foreach ($lowStock as $s): ?>
            <li><span><?= htmlspecialchars($s['item']) ?></span><strong><?= (int) $s['stock'] ?> <?= htmlspecialchars($s['unit']) ?></strong></li>
          <?php endforeach; ?>
          <?php if (!$lowStock): ?><li class="panel__empty">Nothing running low.</li><?php endif; ?>
        </ul>
      </article>

      <article class="panel">
        <div class="panel__head">
          <h2>Out of Stock</h2>
          <span class="panel__count"><?= count($outOfStock) ?></span>
        </div>
        <ul class="panel__list">
          <?php foreach ($outOfStock as $s): ?>
            <li><span><?= htmlspecialchars($s['item']) ?></span><strong>0 <?= htmlspecialchars($s['unit']) ?></strong></li>
          <?php endforeach; ?>
          <?php if (!$outOfStock): ?><li class="panel__empty">Nothing out of stock.</li><?php endif; ?>
        </ul>
      </article>
    </section>

    <div class="toolbar">
      <input type="search" id="tableSearch" placeholder="Search supplies or type...">
    </div>

    <div class="table-wrap">
      <table class="table table--fit js-filter-table" id="suppliesTable">
        <thead>
          <tr>
            <th>Supplies Name</th>
            <th>Supply Type</th>
            <th class="num">Stock Quantity Left</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($supplies as $s): ?>
            <?php
              $st = supply_status($s['stock'], $s['min']);
              $stLabel = ['out' => 'Out of Stock', 'low' => 'Low Stock', 'ok' => 'In Stock'][$st];
            ?>
            <tr data-status="<?= $st ?>">
              <td><?= htmlspecialchars($s['item']) ?></td>
              <td><?= htmlspecialchars($s['type'] ?: '—') ?></td>
              <td class="num"><?= (int) $s['stock'] ?> <?= htmlspecialchars($s['unit']) ?></td>
              <td><span class="badge badge--<?= $st ?>"><?= $stLabel ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No supplies found.</p>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
