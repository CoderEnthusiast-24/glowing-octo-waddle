<?php
/**
 * Logout. Only a POST with a valid CSRF token signs the user out, so another
 * website cannot log people out just by linking to this page.
 */
require_once __DIR__ . '/includes/session.php';

no_cache_headers();
app_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    auth_clear_session();
    header('Location: login.php?loggedout=1');
    exit;
}

// Opened as a plain link (or bad token): nothing is logged out
header('Location: ' . (auth_user() ? 'dashboard.php' : 'login.php'));
exit;
