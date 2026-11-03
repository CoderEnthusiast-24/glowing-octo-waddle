<?php
/**
 * Shared header for every logged-in page.
 *
 * Set these before including it:
 *   $pageTitle  - text for the browser tab, e.g. 'Products'
 *   $activePage - nav key to highlight, e.g. 'products'
 */
require_once __DIR__ . '/auth.php';    // redirects to login if not signed in
require_once __DIR__ . '/config.php';

$pageTitle  = $pageTitle  ?? 'DermAve IMS';
$activePage = $activePage ?? '';
$extraCss   = $extraCss   ?? [];   // optional extra stylesheets, e.g. ['barcode.css']

// nav key => [label, file]. Use null for pages that are not built yet.
$navItems = [
    'dashboard' => ['Dashboard', 'dashboard.php'],
    'directory' => ['Directory', 'directory.php'],
    'products'  => ['Products',  'products.php'],
    'machines'  => ['Machines',  'machines.php'],
    'supplies'  => ['Supplies',  'supplies.php'],
    'barcode'   => ['Barcode',   'barcode.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | DermAve IMS</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<?php foreach ($extraCss as $css): ?>
  <link rel="stylesheet" href="assets/css/<?= htmlspecialchars($css) ?>?v=<?= filemtime(__DIR__ . '/../assets/css/' . $css) ?>">
<?php endforeach; ?>
</head>
<body>

  <header class="topbar">
    <div class="brand brand--small">
      <div class="brand__name">DERM<span>AVE</span> IMS</div>
    </div>

    <nav class="nav" id="mainNav">
      <?php foreach ($navItems as $key => [$label, $file]): ?>
        <?php if ($file): ?>
          <a href="<?= $file ?>"<?= $key === $activePage ? ' class="active"' : '' ?>><?= $label ?></a>
        <?php else: ?>
          <a href="#" data-soon="1"><?= $label ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="topbar__user">
      <?= htmlspecialchars($_SESSION['user']) ?>
      <a href="logout.php">Logout</a>
    </div>
  </header>
