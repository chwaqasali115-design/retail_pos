<?php
// config/config.php

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'retail_pos_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); // Default XAMPP password is empty

define('APP_NAME', 'Waqas Retail POS');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/retail_pos/'); // Adjust if subfolder changes

// Error reporting for dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Karachi'); // Adjust per user location
