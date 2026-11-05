<?php
/**
 * DermAve IMS - Barcode page
 *
 *   ?tab=search&code=XXXX   look up an item by barcode and list its batches
 *   ?tab=add                scan a barcode, then add a batch to it
 *   ?tab=add&code=XXXX      barcode known -> add a batch (or create the item)
 *
 * Scanners type the code and press Enter, which submits the scan box
 * (a normal GET form), so no JavaScript is needed.
 *
 * Quantity and expiry on directory_items are recalculated from item_batches
 * (see directory_recalc_item() in includes/data.php).
 */
require_once __DIR__ . '/includes/auth.php';   // redirects to login if needed
require_once __DIR__ . '/includes/data.php';

/* ----------------------------------------------------------
   Helpers
   ---------------------------------------------------------- */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** [css class, label] for one batch row. */
function batch_badge(array $b): array
{
    if ((int) $b['quantity'] <= 0) {
        return ['out', 'Empty'];
    }
    [$class, $label] = product_status($b['expiration_date']);
    return [$class, $label];
}

// Same token the Directory page uses
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$pdo    = db();
$dbOk   = $pdo !== null;
$tab    = (($_GET['tab'] ?? 'search') === 'add') ? 'add' : 'search';
$code   = trim((string) ($_GET['code'] ?? ''));
$errors = [];
$form   = ['type' => '', 'name' => '', 'dosage' => '', 'batch' => '', 'mfg' => '', 'exp' => '', 'qty' => ''];

/* ----------------------------------------------------------
   Add: handle the form
   ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab  = 'add';
    $code = trim((string) ($_POST['barcode'] ?? ''));
    foreach ($form as $key => $unused) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your form expired. Please try again.';
    } elseif (!$pdo) {
        $errors[] = 'Cannot connect to the database: ' . (db_error() ?? 'unknown reason');
    } else {
        $result = barcode_add_batch($pdo, $_POST, $errors);
        if ($result) {
            $_SESSION['barcode_flash'] = $result['message'];
            // Redirect so refreshing does not re-submit; ready for the next scan.
            header('Location: barcode.php?tab=add');
            exit;
        }
    }
}

$flash = $_SESSION['barcode_flash'] ?? '';
unset($_SESSION['barcode_flash']);

/* ----------------------------------------------------------
   Look up the scanned code (both tabs)
   ---------------------------------------------------------- */
$item    = null;
$batches = [];
if ($dbOk && $code !== '' && strlen($code) <= 64) {
    $item = get_item_by_barcode($code);
    if ($item) {
        $batches = get_batches((int) $item['id']);
    }
}
$recent = ($tab === 'add' && $dbOk) ? get_recent_batches(10) : [];

$pageTitle  = 'Barcode';
$activePage = 'barcode';
$extraCss   = ['barcode.css'];
require_once __DIR__ . '/includes/header.php';

$barcodeIcon = '<svg class="scan-box__icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">'
             . '<path d="M3 5v14M6 5v14M9 5v14M13 5v14M16 5v14M19 5v14M21 5v14" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>';
?>

  <main class="content">
    <h1>Barcode</h1>
    <p class="subtitle">Look up an item by barcode, or add a new batch</p>

    <?php if (!$dbOk): ?>
      <div class="alert">
        <strong>Not connected to the database.</strong> Barcode search and add are turned off.<br>
        <span class="small">Reason: <?= e(db_error() ?? 'unknown') ?></span>
      </div>
    <?php endif; ?>

    <nav class="tabs" aria-label="Barcode sections">
      <a href="barcode.php?tab=search" class="tab<?= $tab === 'search' ? ' tab--active' : '' ?>">Search</a>
      <a href="barcode.php?tab=add" class="tab<?= $tab === 'add' ? ' tab--active' : '' ?>">Add</a>
    </nav>

    <?php if ($tab === 'search'): ?>

      <!-- ===== SEARCH TAB ===== -->
      <form class="scan-box" method="GET" action="barcode.php">
        <input type="hidden" name="tab" value="search">
        <?= $barcodeIcon ?>
        <input type="text" class="scan-box__input" name="code" value="<?= e($code) ?>"
               placeholder="Scan / type barcode here" aria-label="Barcode"
               maxlength="64" autocomplete="off" autofocus onfocus="this.select()"<?= $dbOk ? '' : ' disabled' ?>>
        <button type="submit" class="btn btn--primary scan-box__go"<?= $dbOk ? '' : ' disabled' ?>>GO</button>
      </form>

      <?php if ($dbOk && $code !== ''): ?>
        <?php if ($item): ?>
          <section class="result">
            <div class="item-summary">
              <div>
                <h2 class="result__title">Item: <span><?= e($item['name']) ?></span></h2>
                <p class="note">
                  <?= e(ucfirst($item['item_type'])) ?>
                  <?= $item['dosage'] ? ' &middot; ' . e($item['dosage']) : '' ?>
                  &middot; Barcode <?= e($item['barcode']) ?>
                  <?= $item['status'] === 'archived' ? ' &middot; <strong>Archived</strong>' : '' ?>
                </p>
              </div>
              <div class="item-summary__total">
                <strong><?= (int) $item['quantity'] ?></strong>
                <span>total<?= $item['unit'] ? ' ' . e($item['unit']) : '' ?></span>
              </div>
            </div>

            <?php if ($batches): ?>
              <div class="table-wrap">
                <table class="table table--fit">
                  <thead>
                    <tr>
                      <th>Batch</th>
                      <th>Mfg</th>
                      <th>Exp</th>
                      <th class="num">Qty</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($batches as $b): [$cls, $label] = batch_badge($b); ?>
                      <tr data-status="<?= e($cls) ?>">
                        <td><?= e($b['batch_number']) ?></td>
                        <td class="nowrap"><?= e(table_date($b['manufacturing_date'])) ?></td>
                        <td class="nowrap"><?= e(table_date($b['expiration_date'])) ?></td>
                        <td class="num"><?= (int) $b['quantity'] ?></td>
                        <td><span class="badge badge--<?= e($cls) ?>"><?= e($label) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="note">No batches recorded for this item yet.</p>
            <?php endif; ?>

            <p class="manual-link">
              <a href="barcode.php?tab=add&amp;code=<?= urlencode($item['barcode']) ?>">+ Add a batch to this item</a>
            </p>
          </section>
        <?php elseif (strlen($code) > 64): ?>
          <div class="alert" style="margin-top:20px">That barcode is too long (64 characters maximum).</div>
        <?php else: ?>
          <section class="notfound">
            <h2>No item found for <span><?= e($code) ?></span></h2>
            <p>This barcode is not in the system yet.</p>
            <a class="btn btn--primary" href="barcode.php?tab=add&amp;code=<?= urlencode($code) ?>">Add it now</a>
          </section>
        <?php endif; ?>
      <?php elseif ($dbOk): ?>
        <p class="note">Scan a barcode or type it and press Enter.</p>
      <?php endif; ?>

    <?php else: ?>

      <!-- ===== ADD TAB ===== -->
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

      <!-- Step 1: scan -->
      <form class="scan-box" method="GET" action="barcode.php">
        <input type="hidden" name="tab" value="add">
        <?= $barcodeIcon ?>
        <input type="text" class="scan-box__input" name="code" value="<?= e($code) ?>"
               placeholder="Scan / type barcode here" aria-label="Barcode"
               maxlength="64" autocomplete="off" onfocus="this.select()"
               <?= ($code === '' && $dbOk) ? 'autofocus' : '' ?><?= $dbOk ? '' : ' disabled' ?>>
        <button type="submit" class="btn btn--primary scan-box__go"<?= $dbOk ? '' : ' disabled' ?>>GO</button>
      </form>

      <?php if ($dbOk && $code === ''): ?>
        <p class="note">Scan the item's barcode first. If it already exists you will add a batch to it; otherwise you can create it.</p>
      <?php endif; ?>

      <!-- Step 2: batch details -->
      <?php if ($dbOk && $code !== ''): ?>
        <?php $archived = $item && $item['status'] === 'archived'; ?>

        <?php if ($item): ?>
          <div class="item-summary item-summary--add">
            <div>
              <h2 class="result__title">Adding a batch to: <span><?= e($item['name']) ?></span></h2>
              <p class="note">
                <?= e(ucfirst($item['item_type'])) ?>
                <?= $item['dosage'] ? ' &middot; ' . e($item['dosage']) : '' ?>
                &middot; Barcode <?= e($item['barcode']) ?>
                &middot; <?= (int) $item['quantity'] ?> in stock now
              </p>
            </div>
          </div>
        <?php else: ?>
          <p class="note">New barcode <strong><?= e($code) ?></strong> &mdash; fill in the item details to create it.</p>
        <?php endif; ?>

        <form class="add-form" method="POST" action="barcode.php?tab=add&amp;code=<?= urlencode($code) ?>" novalidate>
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="barcode" value="<?= e($code) ?>">

          <div class="add-form__grid">
            <?php if (!$item): ?>
              <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" required>
                  <option value="" disabled<?= $form['type'] === '' ? ' selected' : '' ?>>Select type</option>
                  <option value="product"<?= $form['type'] === 'product' ? ' selected' : '' ?>>Product</option>
                  <option value="supply"<?= $form['type'] === 'supply' ? ' selected' : '' ?>>Supply</option>
                </select>
              </div>

              <div class="form-group">
                <label for="name">Item Name</label>
                <input type="text" id="name" name="name" placeholder="e.g. Vitamin C" value="<?= e($form['name']) ?>" maxlength="255" required>
              </div>

              <div class="form-group field--full">
                <label for="dosage">Dosage <span class="muted small">(optional)</span></label>
                <input type="text" id="dosage" name="dosage" placeholder="e.g. 5mg, 10mg" value="<?= e($form['dosage']) ?>" maxlength="120">
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label for="batch">Batch Number</label>
              <input type="text" id="batch" name="batch" placeholder="e.g. LT-123" value="<?= e($form['batch']) ?>" maxlength="50" required
                     <?= $item ? 'autofocus' : '' ?>>
            </div>

            <div class="form-group">
              <label for="qty">Quantity</label>
              <input type="number" id="qty" name="qty" min="0" max="100000" step="1" placeholder="0" value="<?= e($form['qty']) ?>" required>
            </div>

            <div class="form-group">
              <label for="mfg">Manufacturing Date</label>
              <input type="date" id="mfg" name="mfg" value="<?= e($form['mfg']) ?>" required>
            </div>

            <div class="form-group">
              <label for="exp">Expiry Date</label>
              <input type="date" id="exp" name="exp" value="<?= e($form['exp']) ?>" required>
            </div>
          </div>

          <p class="note">Using a batch number that already exists on this item adds the quantity to that batch.</p>

          <div class="add-form__actions">
            <button type="submit" class="btn btn--primary"<?= $archived ? ' disabled' : '' ?>>Add</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if ($recent): ?>
        <section class="result">
          <h2 class="result__title">Recently added batches</h2>

          <div class="table-wrap">
            <table class="table table--fit">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Type</th>
                  <th>Batch</th>
                  <th>Mfg</th>
                  <th>Exp</th>
                  <th class="num">Qty</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent as $b): [$cls, $label] = batch_badge($b); ?>
                  <tr data-status="<?= e($cls) ?>">
                    <td><?= e($b['name']) ?></td>
                    <td><?= e(ucfirst($b['item_type'])) ?></td>
                    <td><?= e($b['batch_number']) ?></td>
                    <td class="nowrap"><?= e(table_date($b['manufacturing_date'])) ?></td>
                    <td class="nowrap"><?= e(table_date($b['expiration_date'])) ?></td>
                    <td class="num"><?= (int) $b['quantity'] ?></td>
                    <td><span class="badge badge--<?= e($cls) ?>"><?= e($label) ?></span></td>
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