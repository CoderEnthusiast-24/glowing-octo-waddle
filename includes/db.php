<?php
/**
 * DermAve IMS - PDO database connection helper.
 *
 * db()       returns a PDO instance when the database is reachable,
 *            or null when it is not (pages then fall back to sample data).
 * db_error() returns the reason the last connection attempt failed.
 */

require_once __DIR__ . '/config.php';

/**
 * @return PDO|null
 */
function db(): ?PDO
{
    static $pdo = null;
    static $tried = false;

    if ($tried) {
        return $pdo;
    }
    $tried = true;

    try {
        $port = defined('DB_PORT') ? DB_PORT : 3306;
        $dsn  = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        // Database not available - remember why so the UI can show it.
        db_error($e->getMessage());
        $pdo = null;
    }

    return $pdo;
}

/**
 * Get (or, when $set is given, store) the last connection error message.
 */
function db_error(?string $set = null): ?string
{
    static $message = null;
    if ($set !== null) {
        $message = $set;
    }
    return $message;
}
