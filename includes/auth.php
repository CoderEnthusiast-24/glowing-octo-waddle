<?php
/**
 * Include at the top of any page that requires a logged-in user.
 *
 * - sends "do not cache" headers (Back button cannot show the page after logout)
 * - redirects to login.php when nobody is logged in
 * - provides $authUser (id, username, full_name, role, status)
 * - require_admin() blocks staff accounts from admin-only pages
 */
require_once __DIR__ . '/session.php';

no_cache_headers();

/** Call near the top of an admin-only page. */
function require_admin(): void
{
    $user = auth_user();
    if ($user && $user['role'] === 'admin') {
        return;
    }
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access denied | DermAve IMS</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
  <main class="login-card">
    <h1 class="login-card__title">Access denied</h1>
    <p class="muted" style="text-align:center">Only administrators can open this page.</p>
    <p style="text-align:center;margin-top:18px"><a href="dashboard.php">Back to dashboard</a></p>
  </main>
</body>
</html>
    <?php
    exit;
}

$authUser = auth_user();

if (!$authUser) {
    // Remember the page they wanted so login can bring them back to it
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        app_session_start();
        $path  = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        $_SESSION['return_to'] = $path . ($query !== '' ? '?' . $query : '');
    }
    header('Location: login.php');
    exit;
}
