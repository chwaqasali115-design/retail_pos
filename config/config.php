<?php
// config/config.php

// Support for DATABASE_URL or MYSQL_URL format (used by Railway, PlanetScale, etc.)
$database_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($database_url) {
    $db_parts = parse_url($database_url);
    define('DB_HOST', $db_parts['host'] ?? 'localhost');
    define('DB_NAME', ltrim($db_parts['path'] ?? '/retail_pos_db', '/'));
    define('DB_USER', $db_parts['user'] ?? 'root');
    define('DB_PASS', $db_parts['pass'] ?? '');
    define('DB_PORT', $db_parts['port'] ?? 3306);
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'retail_pos_db');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: ''); // Default XAMPP password is empty
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}

define('APP_NAME', 'Waqas Retail POS');
define('BASE_URL', getenv('BASE_URL') ?: (getenv('VERCEL_URL') ? 'https://' . getenv('VERCEL_URL') . '/' : 'http://localhost/retail_pos/'));

// Error reporting for dev (disable in production)
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

date_default_timezone_set('Asia/Karachi'); // Adjust per user location

