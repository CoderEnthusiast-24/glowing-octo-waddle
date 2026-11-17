<?php
/**
 * DermAve IMS - Stock API (used by assets/js/stock.js)
 *
 * POST actions:
 *   move - item_id, type (in|out), qty, reason (a movement_reasons name)
 *          optional: batch_id            (a specific batch, in or out)
 *                    batch_number, mfg_date, expiry_date   (stock IN to a batch by number;
 *                                          an existing number gets the quantity added,
 *                                          a new number creates the batch)
 *   -> stock_move()
 * Stock OUT without batch_id takes from the batches that expire first.
 * Always answers JSON: { ok: true, ... } or { ok: false, error }.
 */

require_once __DIR__ . '/includes/session.php';
app_session_start();
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/stock.php';

header('Content-Type: application/json; charset=utf-8');

function respond($body, $code = 200)
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

// Must be logged in
$user = auth_user();
if (!$user) {
    respond(['ok' => false, 'error' => 'Your session has expired. Please log in again.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'POST only.'], 405);
}

// Same token the Directory / Barcode pages use
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    respond(['ok' => false, 'error' => 'Your form expired. Please reload the page.'], 403);
}

$pdo = db();
if (!$pdo) {
    respond(['ok' => false, 'error' => 'Cannot connect to the database: ' . db_error()], 503);
}

try {
    if (($_POST['action'] ?? '') !== 'move') {
        respond(['ok' => false, 'error' => 'Unknown action.'], 400);
    }

    $itemId = (int) ($_POST['item_id'] ?? 0);
    $type   = (string) ($_POST['type'] ?? '');
    $qtyRaw = trim((string) ($_POST['qty'] ?? ''));
    $reason = (string) ($_POST['reason'] ?? '');

    if ($itemId < 1) {
        respond(['ok' => false, 'error' => 'No item selected.'], 422);
    }
    if (filter_var($qtyRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) === false) {
        respond(['ok' => false, 'error' => 'Quantity must be a whole number from 1 to 1,000,000.'], 422);
    }

    // Optional batch details (stock_move() checks them)
    $opts    = [];
    $batchId = (int) ($_POST['batch_id'] ?? 0);
    if ($batchId > 0) {
        $opts['batch_id'] = $batchId;
    }
    $batchNo = trim((string) ($_POST['batch_number'] ?? ''));
    if ($type === 'in' && $batchNo !== '') {
        $opts['batch'] = [
            'batch_number' => $batchNo,
            'mfg_date'     => trim((string) ($_POST['mfg_date'] ?? '')),
            'expiry_date'  => trim((string) ($_POST['expiry_date'] ?? '')),
        ];
    }

    $res = stock_move($pdo, $itemId, $type, (int) $qtyRaw, $reason, (int) $user['id'], null, $opts);
    if (!$res['ok']) {
        respond($res, 422);
    }

    $verb = $type === 'in' ? 'Stock in' : 'Stock out';
    respond([
        'ok'      => true,
        'before'  => $res['before'],
        'after'   => $res['after'],
        'message' => "$verb: {$res['item']} ({$res['before']} → {$res['after']}).",
    ]);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}