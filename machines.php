<?php
$pageTitle  = 'Machines';
$activePage = 'machines';
$pageScript = 'table-filter.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

$machines = get_machines();
?>

  <main class="content">
    <h1>Machines</h1>
    <p class="subtitle">Equipment and maintenance dates &mdash; placeholder data</p>

    <div class="toolbar">
      <input type="search" id="tableSearch" placeholder="Search machine...">
      <select id="statusFilter" aria-label="Filter by status">
        <option value="all">All statuses</option>
        <option value="overdue">Overdue</option>
        <option value="soon">Due soon</option>
        <option value="ok">OK</option>
      </select>
    </div>

    <div class="table-wrap">
      <table class="table table--fit js-filter-table" id="machinesTable">
        <thead>
          <tr>
            <th>Machine Name</th>
            <th>Last Maintenance</th>
            <th>Next Maintenance Due Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($machines as $m): ?>
            <?php [$statusClass, $statusLabel] = machine_status($m['date']); ?>
            <tr data-status="<?= $statusClass ?>">
              <td><?= htmlspecialchars($m['name']) ?></td>
              <td class="nowrap"><?= table_date($m['last']) ?></td>
              <td class="nowrap"><?= table_date($m['date']) ?></td>
              <td><span class="badge badge--<?= $statusClass ?>"><?= $statusLabel ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No machines found.</p>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
