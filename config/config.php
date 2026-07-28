<?php
// Environment Configuration for public release
$serverHost = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
$serverHost = $serverHost === '' ? 'localhost' : $serverHost;

define('APP_HOST', $serverHost);
define('IS_LOCALHOST', in_array($serverHost, ['localhost', '127.0.0.1', '::1'], true) || strpos($serverHost, '.local') !== false);

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) ? 'https://' : 'http://';
$defaultHost = $serverHost !== '' ? $serverHost : 'idevfinite';
$envBaseUrl = getenv('APP_URL') ?: getenv('SITE_URL') ?: '';
define('APP_BASE_URL', $envBaseUrl !== '' ? rtrim($envBaseUrl, '/') : $scheme . $defaultHost);

function siteUrl($path = '') {
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';
    $path = ltrim((string)$path, '/');
    return $base === '' ? $path : $base . ($path === '' ? '' : '/' . $path);
}

// Environment settings
define('APP_ENV', strtolower((string)(getenv('APP_ENV') ?: getenv('ENVIRONMENT') ?: (IS_LOCALHOST ? 'development' : 'production'))));
$debugFlag = getenv('DEBUG');
if ($debugFlag === false || $debugFlag === '') {
    define('DEBUG_MODE', IS_LOCALHOST);
} else {
    define('DEBUG_MODE', filter_var($debugFlag, FILTER_VALIDATE_BOOLEAN));
}
define('LOG_ERRORS', !DEBUG_MODE);

// Email Configuration
if (!defined('EMAIL_USERNAME')) define('EMAIL_USERNAME', getenv('EMAIL_USERNAME') ?: 'consultationmanagement2025@gmail.com');
if (!defined('EMAIL_PASSWORD')) define('EMAIL_PASSWORD', getenv('EMAIL_PASSWORD') ?: '');
if (!defined('EMAIL_FROM')) define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'Valenzuela City Government');

// Database Configuration - Matches .env and live server credentials
if (!defined('DB_HOST')) define('DB_HOST', (string)(getenv('DB_HOST') ?: 'localhost'));
if (!defined('DB_USER')) define('DB_USER', (string)(getenv('DB_USER') ?: (IS_LOCALHOST ? 'root' : 'cons_pc_db')));
if (!defined('DB_PASS')) define('DB_PASS', (string)(getenv('DB_PASS') !== false ? getenv('DB_PASS') : (IS_LOCALHOST ? '' : '%wE!*-vMg4GCbB#3')));
if (!defined('DB_NAME')) define('DB_NAME', (string)(getenv('DB_NAME') ?: (IS_LOCALHOST ? 'pc_db' : 'cons_pc_db')));
// Security settings
define('SESSION_LIFETIME', 120); // 2 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
