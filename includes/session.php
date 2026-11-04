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

    $stmt = $pdo->prepare('SELECT id, username, full_name, role, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Account deleted or disabled: treat as logged out
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}