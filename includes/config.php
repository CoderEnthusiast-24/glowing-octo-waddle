<?php
/**
 * DermAve IMS - Configuration
 *
 * Login accounts now live in the `users` table (see sql/users_schema.sql) and
 * passwords are checked with password_verify(). The old demo login
 * (DEMO_USER / DEMO_PASS) has been removed.
 */

define('APP_NAME', 'DermAve IMS');

// Shortest password allowed when a password is set or reset
define('MIN_PASSWORD_LENGTH', 8);

// Secret code an ADMIN must type in the "Forgot?" popup on the login page
// before a new password is accepted. Share it only with administrators and
// CHANGE IT to your own value. Leave it empty ('') to turn that popup reset off.
define('ADMIN_RECOVERY_CODE', 'DAVE-B485-DC7F');

// Database settings (XAMPP defaults - change if yours differ)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);   // XAMPP/MariaDB default. Change if MySQL runs on another port (e.g. 3307).
define('DB_NAME', 'dermave_ims');
define('DB_USER', 'root');
define('DB_PASS', '');
