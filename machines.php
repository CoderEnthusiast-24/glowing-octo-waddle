<?php
/**
 * DermAve IMS - Machines page (read-only list)
 *
 * v2 database: last / next maintenance come from the machine's latest row in
 * maintenance_logs, so either date can be empty:
 *   - no log at all             -> "Never serviced" (counts as overdue in the filter)
 *   - a log but no next-due date -> "No date set"
 * Each physical machine is its own row (name + unit label, e.g. "Unit 2").
 * A machine that is under repair or retired also gets a second badge.
 */
$pageTitle  = 'Machines';
$activePage = 'machines';
$pageScript = 'table-filter.js';

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

$machines = get_machines();
?>

  <main class="content">
    <h1>Machines</h1>
    <p class="subtitle">Equipment and maintenance dates</p>

    <div class="toolbar">
      <input type="search" id="tableSearch" placeholder="Search machine...">
      <select id="statusFilter" aria-label="Filter by status">
        <option value="all">All statuses</option>
        <option value="overdue">Overdue</option>
        <option value="never">Never serviced</option>
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
            <?php
              $hasDate = !empty($m['date']);
              $hasLast = !empty($m['last']);

              if ($hasDate) {
                  [$statusClass, $statusLabel] = machine_status($m['date']);
                  $statusAttr = $statusClass;
              } elseif (!$hasLast) {
                  // no maintenance log at all
                  $statusClass = 'overdue';
                  $statusLabel = 'Never serviced';
                  $statusAttr  = 'overdue never';
              } else {
                  // serviced before, but no next due date was recorded
                  $statusClass = 'ok';
                  $statusLabel = 'No date set';
                  $statusAttr  = 'ok';
              }

              // Machines that are not in normal use get an extra badge
              $condition = $m['status'] ?? 'active';
              $conditionBadge = [
                  'under_repair' => ['soon', 'Under repair'],
                  'retired'      => ['expired', 'Retired'],
              ][$condition] ?? null;
            ?>
            <tr data-status="<?= $statusAttr ?>">
              <td>
                <?= htmlspecialchars($m['name']) ?>
                <?php if (!empty($m['unit_label'])): ?>
                  <span class="muted small">&middot; <?= htmlspecialchars($m['unit_label']) ?></span>
                <?php endif; ?>
              </td>
              <td class="nowrap"><?= table_date($m['last'] ?? null) ?></td>
              <td class="nowrap"><?= table_date($m['date'] ?? null) ?></td>
              <td>
                <span class="badge badge--<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                <?php if ($conditionBadge): ?>
                  <span class="badge badge--<?= $conditionBadge[0] ?>"><?= $conditionBadge[1] ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="empty" id="noResults" hidden>No machines found.</p>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>