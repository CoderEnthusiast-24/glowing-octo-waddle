<?php
/**
 * DermAve IMS - Users (administrators only): view accounts and reset passwords.
 * Accounts themselves are still created in phpMyAdmin.
 *
 * Click "Reset password" on a row -> a form appears above the table.
 * Submitting the form saves the new password and reloads this page.
 */
require_once __DIR__ . '/includes/auth.php';
require_admin();                       // staff get an "Access denied" page
require_once __DIR__ . '/includes/users.php';

$pageTitle  = 'Users';
$activePage = 'users';

$pdo     = db();
$message = '';
$error   = '';

// Which user is being reset? (from the link, or from the submitted form)
$resetId = (int) ($_GET['reset'] ?? 0);

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $resetId  = (int) ($_POST['id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$resetId]);
    $target = $stmt->fetch();

    if (!$target) {
        $error   = 'That user no longer exists.';
        $resetId = 0;
    } else {
        $problems = password_problems($password, $confirm);
        if ($problems) {
            $error = implode(' ', $problems);      // keep the form open
        } else {
            user_set_password($pdo, $target['id'], $password);
            $message = 'Password updated for ' . $target['username'] . '.';
            $resetId = 0;                          // close the form
        }
    }
}

// All accounts for the table
$users = [];
if ($pdo) {
    $users = $pdo->query('SELECT id, username, full_name, role, status, last_login FROM users ORDER BY role, username')->fetchAll();
}

// The account whose form should be showing (if any)
$resetUser = null;
foreach ($users as $u) {
    if ((int) $u['id'] === $resetId) {
        $resetUser = $u;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

  <main class="content">
    <h1>Users</h1>
    <p class="subtitle">Login accounts &mdash; administrators can reset passwords here</p>

    <?php if ($message): ?>
      <div class="alert alert--success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($resetUser): ?>
      <article class="panel" style="max-width:420px; margin-bottom:24px;">
        <div class="panel__head">
          <h2>Reset password for <?= htmlspecialchars($resetUser['username']) ?></h2>
        </div>

        <form method="POST" action="users.php">
          <input type="hidden" name="id" value="<?= (int) $resetUser['id'] ?>">

          <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required>
          </div>

          <div class="form-group">
            <label for="confirm">Confirm new password</label>
            <input type="password" id="confirm" name="confirm" required>
          </div>

          <p class="muted small">Use at least <?= (int) MIN_PASSWORD_LENGTH ?> characters.</p>

          <div class="form-actions">
            <a href="users.php" class="btn btn--ghost">Cancel</a>
            <button type="submit" class="btn btn--primary">Save password</button>
          </div>
        </form>
      </article>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="table table--fit">
        <thead>
          <tr>
            <th>Full name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last login</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
              <td><span class="badge badge--<?= $u['status'] === 'active' ? 'ok' : 'expired' ?>"><?= htmlspecialchars(ucfirst($u['status'])) ?></span></td>
              <td><?= $u['last_login'] ? htmlspecialchars(date('M d, Y g:i A', strtotime($u['last_login']))) : '—' ?></td>
              <td>
                <a href="users.php?reset=<?= (int) $u['id'] ?>" class="btn btn--ghost">Reset password</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <tr><td colspan="6">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>