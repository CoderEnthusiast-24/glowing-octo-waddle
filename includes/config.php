<?php
/**
 * DermAve IMS - Configuration
 */

define('APP_NAME', 'DermAve IMS');

// Shortest password allowed when a password is set or reset
define('MIN_PASSWORD_LENGTH', 8);

// Database settings (XAMPP defaults - change if yours differ)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);   // XAMPP/MariaDB default. Change if MySQL runs on another port (e.g. 3307).
define('DB_NAME', 'dermave_ims');
define('DB_USER', 'root');
define('DB_PASS', '');