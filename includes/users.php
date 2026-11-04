<?php
/**
 * DermAve IMS - password helpers shared by the Users page and the
 * "Forgot?" popup on the login page.
 */

require_once __DIR__ . '/session.php';

/**
 * Check a new password and its confirmation.
 *
 * @return string[] human-readable problems (empty = the password is fine)
 */
function password_problems(string $password, string $confirm): array
{
    $min = defined('MIN_PASSWORD_LENGTH') ? (int) MIN_PASSWORD_LENGTH : 8;
    $errors = [];

    if ($password === '') {
        return ['Please enter a new password.'];
    }
    if (mb_strlen($password) < $min) {
        $errors[] = "Password must be at least $min characters long.";
    }
    if (strlen($password) > 72) {   // bcrypt ignores everything after 72 bytes
        $errors[] = 'Password is too long (maximum 72 characters).';
    }
    if ($password !== $confirm) {
        $errors[] = 'The passwords do not match.';
    }
    return $errors;
}

/** Store a new hashed password for a user. */
function user_set_password(PDO $pdo, int $userId, string $password): bool
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    return $stmt->rowCount() > 0;
}
