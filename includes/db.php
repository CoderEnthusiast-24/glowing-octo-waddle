<?php
/**
 * DermAve IMS - database connection (the ONE connection used by every page).
 *
 * db()       gives you the database connection, or null if MySQL is not reachable.
 * db_error() gives you the reason the connection failed.
 */

require_once __DIR__ . '/config.php';

function db()
{
    static $pdo = null;
    static $tried = false;

    // Only try to connect once per page
    if ($tried) {
        return $pdo;
    }
    $tried = true;

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Exception $e) {
        // Remember why it failed so the page can show it
        db_error($e->getMessage());
        $pdo = null;
    }

    return $pdo;
}

/**
 * Get the last connection error message
 * (or save one, when a message is passed in).
 */
function db_error($set = null)
{
    static $message = null;
    if ($set !== null) {
        $message = $set;
    }
    return $message;
}