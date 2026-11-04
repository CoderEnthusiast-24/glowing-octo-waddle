<?php
/**
 * Users page actions (JSON, administrators only).
 *   action=reset  id, password, confirm  -> set a new password for that user
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/users.php';

no_cache_headers();
header('Content-Type: application/json; charset=utf-8');

function out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$me = auth_user();
if (!$me) {
    out(['ok' => false, 'message' => 'Your session has ended. Please sign in again.'], 401);
}
if ($me['role'] !== 'admin') {
    out(['ok' => false, 'message' => 'Only administrators can do this.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) {
    out(['ok' => false, 'message' => 'This page has expired. Please reload and try again.'], 403);
}

$pdo = auth_db();
if (!$pdo) {
    out(['ok' => false, 'message' => 'Cannot reach the database.'], 500);
}

if (($_POST['action'] ?? '') === 'reset') {
    $id       = (int) ($_POST['id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        out(['ok' => false, 'message' => 'That user no longer exists.'], 404);
    }

    $problems = password_problems($password, $confirm);
    if ($problems) {
        out(['ok' => false, 'message' => implode(' ', $problems)]);
    }

    user_set_password($pdo, (int) $target['id'], $password);
    out(['ok' => true, 'message' => 'Password updated for ' . $target['username'] . '.']);
}

out(['ok' => false, 'message' => 'Invalid request.'], 400);
