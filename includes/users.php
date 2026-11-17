<?php
/**
 * DermAve IMS - password helpers shared by the Users page and the Forgot page.
 */

require_once __DIR__ . '/config.php';

/**
 * Check a new password and its confirmation.
 * Returns a list of problems (an empty list means the password is fine).
 */
function password_problems($password, $confirm)
{
    if ($password === '') {
        return ['Please enter a new password.'];
    }

    $errors = [];
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The passwords do not match.';
    }
    return $errors;
}

/**
 * Save a new password for a user.
 *
 * v2: only a bcrypt hash is stored (users.password_hash), never the password.
 * A reset also clears any login lockout so the person can sign in right away.
 */
function user_set_password($pdo, $userId, $password)
{
    $stmt = $pdo->prepare(
        'UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?'
    );
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
}