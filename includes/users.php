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

/** Save a new password for a user (stored as plain text in the `password` column). */
function user_set_password($pdo, $userId, $password)
{
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$password, $userId]);
}