<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$pageScript = 'dashboard.js';

require_once __DIR__ . '/includes/header.php';

// TODO: replace these placeholder numbers with real counts from the database.
$stats = [
    'low_stock'       => 3,
    'expiring_soon'   => 5,
    'expiring_30'     => 3,
    'expiring_60'     => 2,
    'expiring_90'     => 0,
    'expired'         => 1,
    'maintenance_due' => 2,
];
?>

  <main class="content">
    <h1>Dashboard</h1>
    <p class="subtitle">Inventory overview &mdash; placeholder data</p>

    <section class="cards">
      <article class="card">
        <div class="card__label">Low Stock</div>
        <div class="card__value" id="lowStock"><?= (int)$stats['low_stock'] ?></div>
        <div class="card__note">Items below the minimum level</div>
      </article>

      <article class="card">
        <div class="card__label">Expiring Soon</div>
        <div class="card__value" id="expiringSoon"><?= (int)$stats['expiring_soon'] ?></div>
        <div class="breakdown">
          <span><strong><?= (int)$stats['expiring_30'] ?></strong>30 days</span>
          <span><strong><?= (int)$stats['expiring_60'] ?></strong>60 days</span>
          <span><strong><?= (int)$stats['expiring_90'] ?></strong>90 days</span>
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
        <div class="card__note">Machines needing servicing</div>
      </article>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
