<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$pageScript = 'dashboard.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

// Live numbers from the directory (archived items are not counted).
$stats = dashboard_stats();
$dbOk  = db() !== null;
?>

  <main class="content">
    <h1>Dashboard</h1>
    <p class="subtitle">Inventory overview &mdash; live counts from the directory (archived items not counted)</p>

    <?php if (!$dbOk): ?>
      <div class="alert">
        <strong>Not connected to the database.</strong> These numbers come from built-in sample data.
      </div>
    <?php endif; ?>

    <h2 class="section-title">Inventory</h2>
    <section class="cards">
      <article class="card card--type">
        <div class="card__label">Products</div>
        <div class="card__value" id="totalProducts"><?= (int)$stats['products'] ?></div>
        <div class="card__note">Different products on the list</div>
      </article>

      <article class="card card--type">
        <div class="card__label">Machines</div>
        <div class="card__value" id="totalMachines"><?= (int)$stats['machines'] ?></div>
        <div class="card__note">Different machines on the list</div>
      </article>

      <article class="card card--type">
        <div class="card__label">Supplies</div>
        <div class="card__value" id="totalSupplies"><?= (int)$stats['supplies'] ?></div>
        <div class="card__note">Different supplies on the list</div>
      </article>
    </section>

    <h2 class="section-title">Needs attention</h2>
    <section class="cards">
      <article class="card">
        <div class="card__label">Low Stock</div>
        <div class="card__value" id="lowStock"><?= (int)$stats['low_stock'] ?></div>
        <div class="card__note">Products and supplies at or below the minimum level</div>
        <div class="card__note"><strong id="outOfStock"><?= (int)$stats['out_of_stock'] ?></strong> more are out of stock</div>
      </article>

      <article class="card">
        <div class="card__label">Expiring Soon</div>
        <div class="card__value" id="expiringSoon"><?= (int)$stats['expiring_soon'] ?></div>
        <div class="breakdown">
          <span><strong><?= (int)$stats['expiring_30'] ?></strong>0&ndash;30 days</span>
          <span><strong><?= (int)$stats['expiring_60'] ?></strong>31&ndash;60 days</span>
          <span><strong><?= (int)$stats['expiring_90'] ?></strong>61&ndash;90 days</span>
        </div>
      </article>

      <article class="card">
        <div class="card__label">Expired</div>
        <div class="card__value" id="expired"><?= (int)$stats['expired'] ?></div>
        <div class="card__note">Items past their expiry date</div>
      </article>

      <article class="card">
        <div class="card__label">Maintenance Due</div>
        <div class="card__value" id="maintenanceDue"><?= (int)$stats['maintenance_due'] ?></div>
        <div class="card__note">Machines overdue or due within 30 days</div>
        <div class="card__note"><strong id="maintenanceOverdue"><?= (int)$stats['maintenance_overdue'] ?></strong> already overdue</div>
      </article>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>