<?php
/**
 * DermAve IMS - Directory API (used by assets/js/directory.js)
 *
 * POST actions:
 *   save     - add (no id) or edit (with id) an item; item_type decides the table
 *              (product / supply -> items, machine -> machines)
 *   archive  - keys[] -> status = archived
 *   restore  - keys[] -> status = active
 *
 * A "key" is what get_directory_items() gives every row: "product:11",
 * "supply:35" or "machine:3". Items and machines can share an id, so the key
 * says which table the id belongs to.
 * (For older callers, ids[] + item_type is also accepted and turned into keys.)
 *
 * Always answers JSON: { ok: true, ... } or { ok: false, error|errors }.
 */

require_once __DIR__ . '/includes/session.php';
app_session_start();
require_once __DIR__ . '/includes/data.php';   // also loads includes/stock.php

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

// Same token the Stock / Barcode pages use
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    respond(['ok' => false, 'error' => 'Your form expired. Please reload the page.'], 403);
}

$pdo = db();
if (!$pdo) {
    respond(['ok' => false, 'error' => 'Cannot connect to the database: ' . db_error()], 503);
}

try {
    $action = $_POST['action'] ?? '';

    /* ---------- add / edit ---------- */
    if ($action === 'save') {
        $errors = [];
        $data   = directory_validate($_POST, $errors);
        if ($errors) {
            respond(['ok' => false, 'errors' => $errors], 422);
        }

        $id = (int) ($_POST['id'] ?? 0);

        // The user id is saved with maintenance logs and opening-stock movements
        $saved = directory_save($pdo, $data, $id > 0 ? $id : null, (int) $user['id']);
        if ($saved === null) {
            respond(['ok' => false, 'error' => 'That item no longer exists.'], 404);
        }
        respond(['ok' => true, 'id' => $saved, 'message' => ($id > 0 ? 'Updated ' : 'Added ') . $data['name'] . '.']);
    }

    /* ---------- archive / restore ---------- */
    if ($action === 'archive' || $action === 'restore') {
        $keys = $_POST['keys'] ?? [];

        // Older callers send ids[] + item_type: turn them into keys ("product:11")
        if (!$keys && isset($_POST['ids'], $_POST['item_type']) && is_array($_POST['ids'])) {
            $type = (string) $_POST['item_type'];
            if (in_array($type, ['product', 'supply', 'machine'], true)) {
                $keys = array_map(fn($i) => $type . ':' . (int) $i, $_POST['ids']);
            }
        }

        if (!is_array($keys) || !$keys) {
            respond(['ok' => false, 'error' => 'No items selected.'], 422);
        }

        $n    = directory_set_status($pdo, $keys, $action === 'archive' ? 'archived' : 'active');
        $word = $action === 'archive' ? 'Archived' : 'Restored';
        respond(['ok' => true, 'count' => $n, 'message' => "$word $n item" . ($n === 1 ? '' : 's') . '.']);
    }

    respond(['ok' => false, 'error' => 'Unknown action.'], 400);

} catch (InvalidArgumentException $e) {
    // A rule broken on purpose in data.php, e.g. "That barcode is already used by another item."
    respond(['ok' => false, 'errors' => [$e->getMessage()]], 422);

} catch (PDOException $e) {
    // MySQL error numbers: 1062 = duplicate value, 1452 = points to a row that does not exist
    $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
    if ($mysqlCode === 1062) {
        respond(['ok' => false, 'errors' => ['That name / unit label, serial number or barcode is already used by another record.']], 422);
    }
    if ($mysqlCode === 1452) {
        respond(['ok' => false, 'errors' => ['The chosen brand, category or unit no longer exists. Reload the page and try again.']], 422);
    }
    respond(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);

} catch (Exception $e) {
    respond(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}