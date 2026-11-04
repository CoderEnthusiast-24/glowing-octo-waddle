<?php
/**
 * Logout: clears the session and goes back to the login page.
 */
require_once __DIR__ . '/includes/session.php';

app_session_start();
$_SESSION = [];
session_destroy();

header('Location: login.php?loggedout=1');
exit;