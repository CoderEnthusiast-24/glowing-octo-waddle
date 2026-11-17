<?php
/**
 * DermAve IMS - session helpers.
 *
 *   app_session_start()  starts the PHP session (remembers who is logged in)
 *   auth_user()          returns the logged-in user (array) or null
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Manila');

function app_session_start()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * The logged-in user, read from the database every time, so a disabled
 * account stops working right away.
 * Returns an array (id, username, full_name, role, status) or null.
 *
 * v2: the role now lives in the `roles` table, so it is joined in and
 * returned under the same key ('role') the rest of the app already uses.
 */
function auth_user()
{
    app_session_start();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = db();
    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.full_name, r.name AS role, u.status
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Account deleted or disabled: treat as logged out
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}