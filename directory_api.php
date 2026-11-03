<?php
/**
 * DermAve IMS - Directory API (used by assets/js/directory.js)
 *
 * POST actions:
 *   save     - add (no id) or edit (with id) an item
 *   archive  - ids[] -> status = archived
 *   restore  - ids[] -> status = active
 * Always answers JSON: { ok: true, ... } or { ok: false, error|errors }.
 */

session_start();
require_once __DIR__ . '/includes/data.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $body, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (empty($_SESSION['user'])) {
    respond(['ok' => false, 'error' => 'Your session has expired. Please log in again.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'POST only.'], 405);
}
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    respond(['ok' => false, 'error' => 'Security check failed. Please refresh the page and try again.'], 403);
}

$pdo = db();
if (!$pdo) {
    respond(['ok' => false, 'error' => 'Cannot connect to the database: ' . db_error()], 503);
}

try {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $errors = [];
        $data   = directory_validate($_POST, $errors);
        if ($errors) {
            respond(['ok' => false, 'errors' => $errors], 422);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $saved = directory_save($pdo, $data, $id > 0 ? $id : null);
        if ($saved === null) {
            respond(['ok' => false, 'error' => 'That item no longer exists.'], 404);
        }
        respond(['ok' => true, 'id' => $saved, 'message' => ($id > 0 ? 'Updated ' : 'Added ') . $data['name'] . '.']);
    }

    if ($action === 'archive' || $action === 'restore') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || !$ids) {
            respond(['ok' => false, 'error' => 'No items selected.'], 422);
        }
        $n = directory_set_status($pdo, $ids, $action === 'archive' ? 'archived' : 'active');
        $word = $action === 'archive' ? 'Archived' : 'Restored';
        respond(['ok' => true, 'count' => $n, 'message' => "$word $n item" . ($n === 1 ? '' : 's') . '.']);
    }

    respond(['ok' => false, 'error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
