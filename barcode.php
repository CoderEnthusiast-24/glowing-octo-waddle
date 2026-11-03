<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

date_default_timezone_set('Asia/Manila');

/* ----------------------------------------------------------
   Helpers
   ---------------------------------------------------------- */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// A batch is "expired" once its expiry date has passed, otherwise "ok".
function batch_status($exp)
{
    return $exp < date('Y-m-d') ? 'expired' : 'ok';
}

function show_date($date)
{
    return $date ? date('m/d/Y', strtotime($date)) : '-';
}

// True if $value is a real calendar date written as YYYY-MM-DD.
function valid_date($value)
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

/* ----------------------------------------------------------
   Sample data (static) - used by the Search tab preview.
   TODO: replace with a database lookup by barcode.
   ---------------------------------------------------------- */
$sample_item = [
    'name'    => 'Vitamin C',
    'batches' => [
        ['batch' => 'LT-123', 'exp' => '2026-10-08', 'qty' => 2],
        ['batch' => 'LT-303', 'exp' => '2026-12-01', 'qty' => 20],
    ],
];

/* ----------------------------------------------------------
   Which tab / view is showing?
     ?tab=search            -> Search tab
     ?tab=add               -> Add tab (scan box)
     ?tab=add&manual=1      -> Add tab, manual entry form
   ---------------------------------------------------------- */
$tab    = (($_GET['tab'] ?? 'search') === 'add') ? 'add' : 'search';
$manual = ($tab === 'add' && ($_GET['manual'] ?? '') === '1');

/* ----------------------------------------------------------
   Manual add: handle the form.
   TODO: save to the database instead of the session.
   For now, added items live in $_SESSION until logout.
   ---------------------------------------------------------- */
$types  = ['Product', 'Supply'];
$errors = [];
$form   = ['type' => '', 'name' => '', 'dosage' => '', 'batch' => '', 'mfg' => '', 'exp' => '', 'qty' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab    = 'add';
    $manual = true;

    foreach ($form as $key => $unused) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    if (!in_array($form['type'], $types, true)) {
        $errors[] = 'Please choose Product or Supply.';
    }

    if ($form['name'] === '') {
        $errors[] = 'Item name is required.';
    } elseif (mb_strlen($form['name']) > 100) {
        $errors[] = 'Item name must be 100 characters or less.';
    }

    if (mb_strlen($form['dosage']) > 50) {
        $errors[] = 'Dosage must be 50 characters or less.';
    }

    if ($form['batch'] === '') {
        $errors[] = 'Batch number is required.';
    } elseif (mb_strlen($form['batch']) > 50) {
        $errors[] = 'Batch number must be 50 characters or less.';
    }

    $mfg_ok = false;
    if ($form['mfg'] === '') {
        $errors[] = 'Manufacturing date is required.';
    } elseif (!valid_date($form['mfg'])) {
        $errors[] = 'Manufacturing date is not a valid date.';
    } elseif ($form['mfg'] > date('Y-m-d')) {
        $errors[] = 'Manufacturing date cannot be in the future.';
    } else {
        $mfg_ok = true;
    }

    $exp_ok = false;
    if ($form['exp'] === '') {
        $errors[] = 'Expiry date is required.';
    } elseif (!valid_date($form['exp'])) {
        $errors[] = 'Expiry date is not a valid date.';
    } else {
        $exp_ok = true;
    }

    if ($mfg_ok && $exp_ok && $form['exp'] <= $form['mfg']) {
        $errors[] = 'Expiry date must be after the manufacturing date.';
    }

    if ($form['qty'] === '') {
        $errors[] = 'Quantity is required.';
    } elseif (filter_var($form['qty'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) === false) {
        $errors[] = 'Quantity must be a whole number of at least 1.';
    }

    if (!$errors) {
        $_SESSION['barcode_added'][] = [
            'type'  => $form['type'],
            'name'  => $form['name'],
            'dosage' => $form['dosage'],
            'batch' => $form['batch'],
            'mfg'   => $form['mfg'],
            'exp'   => $form['exp'],
            'qty'   => (int)$form['qty'],
        ];
        $_SESSION['barcode_flash'] = 'Added ' . $form['name'] . ' (batch ' . $form['batch'] . ').';

        // Redirect so refreshing the page does not re-submit the form.
        header('Location: barcode.php?tab=add&manual=1');
        exit;
    }
}

$flash = $_SESSION['barcode_flash'] ?? '';
unset($_SESSION['barcode_flash']);

$added = array_reverse($_SESSION['barcode_added'] ?? []); // newest first

// Shared header (top bar + working nav links) and this page's extra stylesheet.
$pageTitle  = 'Barcode';
$activePage = 'barcode';
$extraCss   = ['barcode.css'];
require_once __DIR__ . '/includes/header.php';
?>

  <main class="content">
    <h1>Barcode</h1>
    <p class="subtitle">Look up an item by barcode, or add a new batch</p>

    <nav class="tabs" aria-label="Barcode sections">
      <a href="barcode.php?tab=search" class="tab<?= $tab === 'search' ? ' tab--active' : '' ?>">Search</a>
      <a href="barcode.php?tab=add" class="tab<?= $tab === 'add' ? ' tab--active' : '' ?>">Add</a>
    </nav>

    <?php if ($tab === 'search'): ?>

      <!-- ===== SEARCH TAB (static preview - not connected yet) ===== -->
      <section class="scan-box">
        <svg class="scan-box__icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
          <path d="M3 5v14M6 5v14M9 5v14M13 5v14M16 5v14M19 5v14M21 5v14" stroke="currentColor" stroke-width="1.6" fill="none"/>
        </svg>
        <input type="text" class="scan-box__input" placeholder="Scan / type barcode here" aria-label="Barcode">
        <button type="button" class="btn btn--primary scan-box__go">GO</button>
      </section>
      <p class="note">Preview only &mdash; scanning and search are not connected yet.</p>

      <section class="result">
        <h2 class="result__title">Item: <span><?= e($sample_item['name']) ?></span></h2>

        <div class="table-wrap">
          <table class="table table--fit">
            <thead>
              <tr>
                <th>Batch</th>
                <th>Exp</th>
                <th>Qty</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sample_item['batches'] as $b): $status = batch_status($b['exp']); ?>
                <tr>
                  <td><?= e($b['batch']) ?></td>
                  <td><?= e(show_date($b['exp'])) ?></td>
                  <td><?= (int)$b['qty'] ?></td>
                  <td><span class="status status--<?= e($status) ?>"><?= e($status === 'ok' ? 'OK' : 'Expired') ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

    <?php elseif (!$manual): ?>

      <!-- ===== ADD TAB: scan box + link to manual entry ===== -->
      <section class="scan-box">
        <svg class="scan-box__icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
          <path d="M3 5v14M6 5v14M9 5v14M13 5v14M16 5v14M19 5v14M21 5v14" stroke="currentColor" stroke-width="1.6" fill="none"/>
        </svg>
        <input type="text" class="scan-box__input" placeholder="Scan / type barcode here" aria-label="Barcode">
      </section>
      <p class="note">Barcode scanning is not connected yet.</p>

      <p class="manual-link">
        Manually add the item, <a href="barcode.php?tab=add&amp;manual=1">press here</a>.
      </p>

    <?php else: ?>

      <!-- ===== ADD TAB: manual entry form ===== -->
      <a class="back-link" href="barcode.php?tab=add">&larr; Back to scan</a>

      <?php if ($flash): ?>
        <div class="alert alert--success"><?= e($flash) ?></div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="add-form" method="POST" action="barcode.php?tab=add&amp;manual=1" novalidate>
        <div class="add-form__grid">
          <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type" required>
              <option value="" disabled<?= $form['type'] === '' ? ' selected' : '' ?>>Select type</option>
              <?php foreach ($types as $t): ?>
                <option value="<?= e($t) ?>"<?= $form['type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div></div>

          <div class="form-group">
            <label for="name">Item Name</label>
            <input type="text" id="name" name="name" placeholder="e.g. Vitamin C" value="<?= e($form['name']) ?>" required>
          </div>

          <div class="form-group">
            <label for="dosage">Dosage <span class="muted small">(optional)</span></label>
            <input type="text" id="dosage" name="dosage" placeholder="e.g. 5mg, 10mg" value="<?= e($form['dosage']) ?>">
          </div>

          <div class="form-group">
            <label for="batch">Batch Number</label>
            <input type="text" id="batch" name="batch" placeholder="e.g. LT-123" value="<?= e($form['batch']) ?>" required>
          </div>

          <div class="form-group">
            <label for="mfg">Manufacturing Date</label>
            <input type="date" id="mfg" name="mfg" value="<?= e($form['mfg']) ?>" required>
          </div>

          <div class="form-group">
            <label for="exp">Expiry Date</label>
            <input type="date" id="exp" name="exp" value="<?= e($form['exp']) ?>" required>
          </div>

          <div class="form-group">
            <label for="qty">Quantity</label>
            <input type="number" id="qty" name="qty" min="1" step="1" placeholder="0" value="<?= e($form['qty']) ?>" required>
          </div>
        </div>

        <div class="add-form__actions">
          <button type="submit" class="btn btn--primary">Add</button>
        </div>
      </form>

      <?php if ($added): ?>
        <section class="result">
          <h2 class="result__title">Added this session</h2>
          <p class="note">Stored in your session only for now &mdash; cleared when you log out.</p>

          <div class="table-wrap">
            <table class="table table--fit">
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Item</th>
                  <th>Dosage</th>
                  <th>Batch</th>
                  <th>Mfg</th>
                  <th>Exp</th>
                  <th>Qty</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($added as $row): $status = batch_status($row['exp']); ?>
                  <tr>
                    <td><?= e($row['type'] ?? '-') ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e(($row['dosage'] ?? '') !== '' ? $row['dosage'] : '-') ?></td>
                    <td><?= e($row['batch']) ?></td>
                    <td><?= e(show_date($row['mfg'] ?? '')) ?></td>
                    <td><?= e(show_date($row['exp'])) ?></td>
                    <td><?= (int)$row['qty'] ?></td>
                    <td><span class="status status--<?= e($status) ?>"><?= e($status === 'ok' ? 'OK' : 'Expired') ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>