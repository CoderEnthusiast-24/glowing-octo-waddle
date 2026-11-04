<?php
/**
 * Shared footer. Optionally set $pageScript (e.g. 'products.js') before including.
 *
 * Scripts get ?v=<last-modified time> so the browser always loads the newest
 * version after a file changes (no more stale cached JavaScript).
 */
$jsVersion = function (string $file): int {
    $path = __DIR__ . '/../assets/js/' . $file;
    return is_file($path) ? filemtime($path) : 0;
};
?>
  <script src="assets/js/main.js?v=<?= $jsVersion('main.js') ?>"></script>
<?php if (!empty($pageScript)): ?>
  <script src="assets/js/<?= htmlspecialchars($pageScript) ?>?v=<?= $jsVersion($pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
