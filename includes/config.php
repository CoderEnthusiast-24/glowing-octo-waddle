<?php
/**
 * DermAve IMS - Configuration
 */

define('APP_NAME', 'DermAve IMS');

// Shortest password allowed when a password is set or reset
define('MIN_PASSWORD_LENGTH', 8);

// Login lockout: after this many wrong passwords in a row the account is
// locked for LOCKOUT_MINUTES (uses users.failed_attempts / users.locked_until).
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// Database settings (XAMPP defaults - change if yours differ)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);   // XAMPP/MariaDB default. Change if MySQL runs on another port (e.g. 3307).
define('DB_NAME', 'dermave_ims_v2');   // was 'dermave_ims' (v1)
define('DB_USER', 'root');
define('DB_PASS', '');