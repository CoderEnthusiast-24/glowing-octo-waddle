<?php
/**
 * DermAve IMS - session helpers (hardened).
 *
 *   app_session_start()   starts the session with a safe cookie
 *   session_login($id)    call right after a successful password check
 *   app_session_end()     logs out: clears data, destroys the session, deletes the cookie
 *   auth_user()           the logged-in user (array) or null; enforces the timeouts
 *   no_cache_headers()    stops the browser keeping a copy of the page (Back button)
 *   csrf_token()          the session's CSRF token
 *   csrf_field()          a ready-made hidden <input> for forms
 *   csrf_verify()         true if the submitted token matches
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Manila');

/* ------------------------------------------------------------------
 * Session lifecycle
 * ----------------------------------------------------------------*/

function app_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    ini_set('session.use_strict_mode', '1');   // refuse session ids the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept a session id from the URL

    session_name(SESSION_COOKIE_NAME);
    session_set_cookie_params([
        'lifetime' => 0,          // gone when the browser closes
        'path'     => '/',
        'secure'   => $https,     // only sent over HTTPS when the site uses HTTPS
        'httponly' => true,       // JavaScript cannot read the cookie
        'samesite' => 'Lax',      // not sent with cross-site POSTs
    ]);
    session_start();
}

/** Remember that the last session ended because of a timeout (so login can say so). */
function session_expired_flag($set = null)
{
    static $flag = false;
    if ($set !== null) {
        $flag = (bool) $set;
    }
    return $flag;
}

/**
 * Log a user in. Call this once the password has been verified.
 * Gives the user a brand-new session id and a fresh CSRF token.
 */
function session_login(int $userId)
{
    app_session_start();
    session_regenerate_id(true);

    $_SESSION = [];
    $_SESSION['user_id']    = $userId;
    $_SESSION['started_at'] = time();
    $_SESSION['last_seen']  = time();
    $_SESSION['csrf']       = bin2hex(random_bytes(32));
}

/** Log out completely: data, server-side session and the browser cookie. */
function app_session_end()
{
    app_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * The logged-in user, read from the database (once per request), so a disabled
 * account stops working right away. Also enforces:
 *   - idle timeout     (SESSION_IDLE_MINUTES without a request)
 *   - absolute timeout (SESSION_ABSOLUTE_HOURS since sign-in)
 *
 * Returns an array (id, username, full_name, role, status) or null.
 */
function auth_user()
{
    static $cache = null;

    app_session_start();

    if (empty($_SESSION['user_id'])) {
        $cache = null;
        return null;
    }

    // Already checked during this request
    if ($cache !== null && (int) $cache['id'] === (int) $_SESSION['user_id']) {
        return $cache;
    }

    // Timeouts. A session with no timestamps (made before this update) is treated as expired.
    $now     = time();
    $last    = (int) ($_SESSION['last_seen'] ?? 0);
    $started = (int) ($_SESSION['started_at'] ?? 0);
    if ($last === 0 || $started === 0
        || $now - $last > SESSION_IDLE_MINUTES * 60
        || $now - $started > SESSION_ABSOLUTE_HOURS * 3600) {
        session_expired_flag(true);
        app_session_end();
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
        app_session_end();
        return null;
    }

    $_SESSION['last_seen'] = $now;   // activity: restart the idle clock
    $cache = $user;
    return $user;
}

/* ------------------------------------------------------------------
 * Browser caching
 * ----------------------------------------------------------------*/

/** Logged-in pages must never be cached, or Back can show them after logout. */
function no_cache_headers()
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* ------------------------------------------------------------------
 * CSRF
 * ----------------------------------------------------------------*/

/** The session's CSRF token (created on first use). Stored under $_SESSION['csrf']. */
function csrf_token(): string
{
    app_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden input to drop inside any <form method="POST">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** True if the submitted token (default: $_POST['csrf']) matches the session's. */
function csrf_verify($submitted = null): bool
{
    app_session_start();
    if ($submitted === null) {
        $submitted = $_POST['csrf'] ?? '';
    }
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $submitted);
}