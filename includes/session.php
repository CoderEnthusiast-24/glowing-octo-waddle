<?php
/**
 * DermAve IMS - session + security helpers.
 *
 * Only defines functions: it sends no output and never redirects, so both
 * normal pages and the JSON APIs can load it.
 *
 *   app_session_start()  start the session with hardened cookie settings
 *   auth_user()          the logged-in user (array) or null
 *   csrf_token()         token to put in forms
 *   csrf_valid($t)       check a submitted token
 *   no_cache_headers()   stop the browser from keeping a copy of the page
 *   safe_return_target() only allow redirects to pages inside this app
 */

require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Manila');

/**
 * Database connection used by the login system (self-contained, so it does not
 * depend on how the rest of the project connects). Uses the DB_* settings in
 * config.php. Returns null when MySQL cannot be reached.
 */
function auth_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST
             . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306)
             . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // "Secure" only works over HTTPS, so it turns on by itself once you deploy
    // with HTTPS and stays off on plain http://localhost.
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');   // reject session IDs the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept the session ID from a URL

    session_name('DERMAVE_IMS');
    session_set_cookie_params([
        'lifetime' => 0,          // ends when the browser is closed
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,       // JavaScript cannot read the cookie
        'samesite' => 'Lax',      // not sent with requests started by other sites
    ]);
    session_start();
}

function no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    app_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid($token): bool
{
    app_session_start();
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------------- current user ---------------- */

/** Wipe the session and expire its cookie. */
function auth_clear_session(): void
{
    app_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * The logged-in user, read fresh from the database on every request so a
 * disabled account or a changed role takes effect immediately.
 *
 * @return array|null  id, username, full_name, role, status
 */
function auth_user(): ?array
{
    static $loaded = false;
    static $user   = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    app_session_start();
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $pdo = auth_db();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, username, full_name, role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }

    if (!$row || $row['status'] !== 'active') {
        auth_clear_session();
        return null;
    }

    return $user = $row;
}

/* ---------------- redirects ---------------- */

/** Only plain "page.php" or "page.php?x=y" inside this app is allowed. */
function safe_return_target(?string $target): string
{
    $target = (string) $target;
    $ok = preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%\-]*)?$/', $target) === 1;
    $page = strtolower(strtok($target, '?') ?: '');
    if (!$ok || in_array($page, ['login.php', 'logout.php', 'index.php'], true)) {
        return 'dashboard.php';
    }
    return $target;
}
