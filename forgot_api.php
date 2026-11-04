<?php
/**
 * "Forgot?" popup on the login page (JSON).
 *
 *   action=check  username                          -> is this an administrator account?
 *   action=reset  username, code, password, confirm -> set a new admin password
 *
 * Only ADMIN accounts can reset their own password here, and only with the
 * recovery code from includes/config.php. Everyone else gets the same formal
 * "contact your administrator" notice.
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

const CONTACT_ADMIN = 'Please contact your system administrator to have your password reset.';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'message' => 'Invalid request.'], 405);
}
if (!csrf_valid($_POST['csrf'] ?? null)) {
    out(['ok' => false, 'message' => 'This page has expired. Please reload and try again.'], 403);
}

$pdo = auth_db();
if (!$pdo) {
    out(['ok' => false, 'message' => 'Cannot reach the database right now. Please contact the system administrator.'], 500);
}

$action   = (string) ($_POST['action'] ?? '');
$username = trim((string) ($_POST['username'] ?? ''));
if ($username === '') {
    out(['ok' => false, 'message' => 'Please enter your username.']);
}

try {
    $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch() ?: null;
} catch (Throwable $e) {
    out(['ok' => false, 'message' => 'The users table could not be read. Please contact the system administrator.'], 500);
}

$recoveryCode = defined('ADMIN_RECOVERY_CODE') ? (string) ADMIN_RECOVERY_CODE : '';
$isAdmin = $user && $user['role'] === 'admin' && $user['status'] === 'active' && $recoveryCode !== '';

if (!$isAdmin) {
    // staff, disabled, unknown username, or popup reset turned off: same notice
    out(['ok' => false, 'notice' => true, 'message' => CONTACT_ADMIN]);
}

if ($action === 'check') {
    out(['ok' => true, 'admin' => true]);
}

if ($action === 'reset') {
    $code     = (string) ($_POST['code'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if (!hash_equals($recoveryCode, $code)) {
        out(['ok' => false, 'message' => 'The recovery code is incorrect.']);
    }
    $problems = password_problems($password, $confirm);
    if ($problems) {
        out(['ok' => false, 'message' => implode(' ', $problems)]);
    }

    try {
        user_set_password($pdo, (int) $user['id'], $password);
    } catch (Throwable $e) {
        out(['ok' => false, 'message' => 'The password could not be saved. Please try again.'], 500);
    }
    out(['ok' => true, 'message' => 'Your password has been changed. You can now sign in.']);
}

out(['ok' => false, 'message' => 'Invalid request.'], 400);
