<?php
/**
 * Logout: only a POST with a valid CSRF token ends the session
 * (so another website cannot log people out by loading this URL).
 * Anything else is simply sent on to the dashboard, which sends
 * visitors who are not logged in to the login page.
 */
require_once __DIR__ . '/includes/session.php';

app_session_start();
no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    app_session_end();
    header('Location: login.php?loggedout=1');
    exit;
}

header('Location: dashboard.php');
exit;