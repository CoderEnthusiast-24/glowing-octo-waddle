<?php
require_once __DIR__ . '/includes/session.php';

app_session_start();

// Already logged in? Go to the dashboard.
if (auth_user()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$notice   = '';
$username = '';

// Messages shown after coming from another page
if (!empty($_GET['loggedout'])) {
    $notice = 'You have been signed out.';
} elseif (!empty($_GET['expired'])) {
    $notice = 'Your session has ended. Please sign in again.';
} elseif (!empty($_GET['pwchanged'])) {
    $notice = 'Your password has been changed. You can now sign in.';
}

// The form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';      // not trimmed: spaces can be part of a password

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $pdo = db();

        if (!$pdo) {
            $error = 'Cannot reach the database right now. Please contact the system administrator.';
        } else {
            $row = null;
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, password_hash, status, failed_attempts, locked_until
                     FROM users WHERE username = ?'
                );
                $stmt->execute([$username]);
                $row = $stmt->fetch();
            } catch (Exception $e) {
                $error = 'The users table could not be read. Please contact the system administrator.';
            }

            if (!$error) {
                // 1. Temporarily locked after too many wrong passwords?
                $lockedUntil = ($row && $row['locked_until']) ? strtotime($row['locked_until']) : 0;
                if ($lockedUntil > time()) {
                    $minutes = (int) ceil(($lockedUntil - time()) / 60);
                    $error = 'Too many failed attempts. Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
                }
                // 2. Correct username + correct password + active account = logged in
                elseif ($row && $row['status'] === 'active' && password_verify($password, $row['password_hash'])) {
                    session_regenerate_id(true);               // new session id after login
                    $_SESSION['user_id'] = (int) $row['id'];

                    // Keep the hash up to date if PHP's default algorithm / cost has changed
                    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
                    }

                    // Clear the failed-attempt counter and record the login time
                    $pdo->prepare('UPDATE users SET last_login = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?')
                        ->execute([date('Y-m-d H:i:s'), $row['id']]);

                    header('Location: dashboard.php');
                    exit;
                }
                // 3. Wrong password for a real, active account: count it, lock after the limit
                else {
                    if ($row && $row['status'] === 'active') {
                        $attempts = (int) $row['failed_attempts'] + 1;
                        if ($attempts >= MAX_FAILED_ATTEMPTS) {
                            $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = ? WHERE id = ?')
                                ->execute([date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60), $row['id']]);
                        } else {
                            $pdo->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')
                                ->execute([$attempts, $row['id']]);
                        }
                    }

                    // Same message for every kind of failure
                    $error = 'Invalid username or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | DermAve IMS</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

  <main class="login-card">
    <div class="brand">
      <div class="brand__name">DERM<span>AVE</span></div>
      <div class="brand__tag">Medical Aesthetic &amp; Wellness Clinic</div>
    </div>

    <h1 class="login-card__title">Inventory Management System</h1>

    <?php if ($error): ?>
      <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="alert alert--success"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Username" maxlength="50"
               value="<?= htmlspecialchars($username) ?>" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" placeholder="Password" required>
          <button type="button" id="togglePassword">Show</button>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Sign In</button>
        <a href="forgot.php" class="btn btn--ghost">Forgot?</a>
      </div>
    </form>
  </main>

  <script src="assets/js/login.js"></script>
</body>
</html>