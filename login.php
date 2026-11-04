<?php
require_once __DIR__ . '/includes/session.php';

no_cache_headers();
app_session_start();

// Already logged in? Go to the dashboard.
if (auth_user()) {
    header('Location: dashboard.php');
    exit;
}

// Used only to keep login time similar when the username does not exist
const DUMMY_HASH = '$2y$10$UcAN.68g1PYeaTAc/offTOzFJJNlhoca.vhAqSj2dGq2giMQottY6';

$error    = '';
$notice   = '';
$username = '';

if (!empty($_GET['loggedout'])) {
    $notice = 'You have been signed out.';
} elseif (!empty($_GET['expired'])) {
    $notice = 'Your session has ended. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'This page has expired. Please try signing in again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $pdo = auth_db();
        $row = null;

        if (!$pdo) {
            $error = 'Cannot reach the database right now. Please contact the system administrator.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, username, password_hash, status FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $row = $stmt->fetch() ?: null;
            } catch (Throwable $e) {
                $error ='Sign-in is unavailable because the users table could not be read. Please contact the system administrator.';
            }
        }

        if (!$error) {
            // Always run password_verify, even for unknown users (same timing either way)
            $passwordOk = password_verify($password, $row['password_hash'] ?? DUMMY_HASH);

            if ($row && $passwordOk && $row['status'] === 'active') {
                $target = safe_return_target($_SESSION['return_to'] ?? '');

                session_regenerate_id(true);
                unset($_SESSION['return_to']);
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['user']    = $row['username'];   // kept for any older page that still reads it
                $_SESSION['csrf']    = bin2hex(random_bytes(32));

                // Housekeeping: a failure here must never block a valid login
                try {
                    $pdo->prepare('UPDATE users SET last_login = ? WHERE id = ?')
                        ->execute([date('Y-m-d H:i:s'), (int) $row['id']]);
                    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['id']]);
                    }
                } catch (Throwable $e) {
                }

                header('Location: ' . $target);
                exit;
            }

            // One message for every kind of failure, so nobody can tell which part was wrong
            $error = 'Invalid username or password.';
        }
    }
}

$jsFile = __DIR__ . '/assets/js/login.js';
$cssFile = __DIR__ . '/assets/css/style.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | DermAve IMS</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= is_file($cssFile) ? filemtime($cssFile) : 0 ?>">
</head>
<body class="login-page">

  <main class="login-card">
    <!-- TODO: swap for <img src="assets/img/logo.png"> once the logo file is added -->
    <div class="brand">
      <div class="brand__name">DERM<span>AVE</span></div>
      <div class="brand__tag">Medical Aesthetic &amp; Wellness Clinic</div>
    </div>

    <h1 class="login-card__title">Inventory Management System</h1>

    <?php if ($error): ?>
      <div class="alert" id="serverError"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="alert" id="clientError" hidden></div>
    <div class="alert alert--success" id="loginNotice"<?= $notice ? '' : ' hidden' ?>><?= htmlspecialchars($notice) ?></div>

    <form id="loginForm" method="POST" action="login.php" novalidate>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Username" autocomplete="username" maxlength="50"
               value="<?= htmlspecialchars($username) ?>"<?= $username === '' ? ' autofocus' : '' ?> required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password"<?= $username !== '' ? ' autofocus' : '' ?> required>
          <button type="button" id="togglePassword">Show</button>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Sign In</button>
        <button type="button" class="btn btn--ghost" id="forgotBtn">Forgot?</button>
      </div>
    </form>
  </main>

  <!-- Forgot password popup -->
  <div class="modal" id="forgotModal" hidden>
    <div class="modal__backdrop" data-close></div>
    <div class="modal__card">
      <h2>Forgot password</h2>
      <div class="alert" id="fgError" hidden></div>
      <p class="hint muted small" id="fgIntro">Enter your username to continue.</p>

      <form id="fgForm" novalidate>
        <input type="hidden" id="fgCsrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label>Username
          <input type="text" id="fgUsername" autocomplete="username" maxlength="50">
        </label>

        <div id="fgStep2" hidden>
          <label>Recovery code
            <input type="password" id="fgCode" autocomplete="off">
          </label>
          <label>New password
            <input type="password" id="fgNew" autocomplete="new-password">
          </label>
          <label>Confirm new password
            <input type="password" id="fgConfirm" autocomplete="new-password">
          </label>
          <p class="hint muted small">Use at least <?= (int) MIN_PASSWORD_LENGTH ?> characters.</p>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn--ghost" data-close>Close</button>
          <button type="submit" class="btn btn--primary" id="fgSubmit">Continue</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/login.js?v=<?= is_file($jsFile) ? filemtime($jsFile) : 0 ?>"></script>
</body>
</html>
