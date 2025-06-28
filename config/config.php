<?php
// Base configuration for XOBO MART

// Session settings - MUST be set before any session is started
if (session_status() === PHP_SESSION_NONE) {
    define('SESSION_LIFETIME', 3600); // 1 hour
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
}

// Detect if running locally or on server
$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;

// Set base URL dynamically
if ($isLocal) {
    $baseUrl = '/xobo-c'; // Local dev folder
} else {
    $baseUrl = ''; // Subdomain root, so no subdirectory
}
define('BASE_URL', $baseUrl);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'xobo-c');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'XOBO MART');
define('APP_VERSION', '1.0.0');

// File upload settings
define('UPLOAD_DIR', 'assets/images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Error reporting (disable in production)
if ($isLocal) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?> 