<?php
/**
 * DermAve IMS - Users (administrators only): view accounts and reset passwords.
 * Accounts themselves are still created in phpMyAdmin.
 */
require_once __DIR__ . '/includes/auth.php';
require_admin();                       // staff get an "Access denied" page

$pageTitle  = 'Users';
$activePage = 'users';
$pageScript = 'users.js';

$users = [];
$pdo = auth_db();
if ($pdo) {
    $users = $pdo->query('SELECT id, username, full_name, role, status, last_login FROM users ORDER BY role, username')->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

  <main class="content">
    <h1>Users</h1>
    <p class="subtitle">Login accounts &mdash; administrators can reset passwords here</p>

    <div class="alert alert--success" id="flash" hidden></div>

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
                <button type="button" class="btn btn--ghost js-reset"
                        data-id="<?= (int) $u['id'] ?>"
                        data-username="<?= htmlspecialchars($u['username']) ?>">Reset password</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <tr><td colspan="6">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Reset password modal -->
    <div class="modal" id="resetModal" hidden>
      <div class="modal__backdrop" data-close></div>
      <div class="modal__card">
        <h2 id="resetTitle">Reset password</h2>
        <div class="alert" id="resetError" hidden></div>
        <form id="resetForm" novalidate>
          <input type="hidden" id="r_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" id="r_id">
          <label>New password
            <input type="password" id="r_password" autocomplete="new-password">
          </label>
          <label>Confirm new password
            <input type="password" id="r_confirm" autocomplete="new-password">
          </label>
          <p class="hint muted small">Use at least <?= (int) MIN_PASSWORD_LENGTH ?> characters.</p>
          <div class="form-actions">
            <button type="button" class="btn btn--ghost" data-close>Cancel</button>
            <button type="submit" class="btn btn--primary" id="r_save">Save password</button>
          </div>
        </form>
      </div>
    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
