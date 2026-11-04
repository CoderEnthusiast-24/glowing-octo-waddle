<?php
/**
 * Forgot password page.
 *
 * Step 1: type your username. If it is an active administrator account,
 *         the page asks for a new password.
 * Step 2: type the new password twice. The password is saved in the
 *         `users` table and you are sent back to the login page.
 *
 * Staff accounts are told to contact the system administrator.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/users.php';

$username = trim($_POST['username'] ?? '');
$error    = '';
$step     = 1;   // 1 = ask for username, 2 = ask for new password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();

    if ($username === '') {
        $error = 'Please enter your username.';
    } elseif (!$pdo) {
        $error = 'Cannot reach the database right now. Please contact the system administrator.';
    } else {
        $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] !== 'admin' || $user['status'] !== 'active') {
            // staff, disabled, or unknown username: same message for all
            $error = 'Please contact your system administrator to have your password reset.';
        } elseif (($_POST['action'] ?? '') === 'reset') {
            // The username is an admin and a new password was sent
            $step = 2;
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm'] ?? '';

            $problems = password_problems($password, $confirm);
            if ($problems) {
                $error = implode(' ', $problems);
            } else {
                user_set_password($pdo, $user['id'], $password);
                header('Location: login.php?pwchanged=1');
                exit;
            }
        } else {
            // The username is an admin: show the new password boxes
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot password | DermAve IMS</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

  <main class="login-card">
    <h1 class="login-card__title">Forgot password</h1>

    <?php if ($error): ?>
      <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <p class="muted small" style="margin-bottom:18px">Enter your username to continue.</p>
    <?php else: ?>
      <p class="muted small" style="margin-bottom:18px">Choose a new password. Use at least <?= (int) MIN_PASSWORD_LENGTH ?> characters.</p>
    <?php endif; ?>

    <form method="POST" action="forgot.php">
      <input type="hidden" name="action" value="<?= $step === 2 ? 'reset' : 'check' ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="50"
               value="<?= htmlspecialchars($username) ?>"<?= $step === 2 ? ' readonly' : '' ?> required>
      </div>

      <?php if ($step === 2): ?>
        <div class="form-group">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
          <label for="confirm">Confirm new password</label>
          <input type="password" id="confirm" name="confirm" required>
        </div>
      <?php endif; ?>

      <div class="form-actions">
        <a href="login.php" class="btn btn--ghost">Back</a>
        <button type="submit" class="btn btn--primary"><?= $step === 2 ? 'Change password' : 'Continue' ?></button>
      </div>
    </form>
  </main>

</body>
</html>