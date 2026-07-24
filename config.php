<?php
// Environment Configuration for public release
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($file = '.env') {
        global $APP_ENV_DATA;
        if (!is_array($APP_ENV_DATA)) {
            $APP_ENV_DATA = [];
        }
        $paths = [
            __DIR__ . '/' . $file,
            dirname(__DIR__) . '/' . $file
        ];
        $filePath = null;
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $filePath = $p;
                break;
            }
        }
        if (!$filePath) {
            return false;
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Strip surrounding single or double quotes
                if (strlen($value) >= 2) {
                    $first = substr($value, 0, 1);
                    $last = substr($value, -1);
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }
                
                $APP_ENV_DATA[$name] = $value;
                @putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        return true;
    }
}

// Load .env automatically
loadEnvFile();

$sharedEnvFile = __DIR__ . '/../shared/app_env.php';
if (file_exists($sharedEnvFile)) {
    require_once $sharedEnvFile;
    if (function_exists('app_env_bootstrap')) {
        app_env_bootstrap(__DIR__);
    }
}

// Fallback app_env function if shared file doesn't exist
if (!function_exists('app_env')) {
    function app_env($key, $default = null) {
        global $APP_ENV_DATA;
        if (isset($APP_ENV_DATA[$key]) && $APP_ENV_DATA[$key] !== '') {
            return $APP_ENV_DATA[$key];
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        return $default;
    }
}

$serverHost = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
$serverHost = $serverHost === '' ? 'localhost' : $serverHost;

define('APP_HOST', $serverHost);
define('IS_LOCALHOST', in_array($serverHost, ['localhost', '127.0.0.1', '::1'], true) || strpos($serverHost, '.local') !== false);

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) ? 'https://' : 'http://';
$defaultHost = $serverHost !== '' ? $serverHost : 'idevfinite';
$envBaseUrl = (string) app_env('APP_URL', app_env('SITE_URL', ''));
define('APP_BASE_URL', $envBaseUrl !== '' ? rtrim($envBaseUrl, '/') : $scheme . $defaultHost);

function siteUrl($path = '') {
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';
    $path = ltrim((string)$path, '/');
    return $base === '' ? $path : $base . ($path === '' ? '' : '/' . $path);
}

// Environment settings
define('APP_ENV', strtolower((string) app_env('APP_ENV', app_env('ENVIRONMENT', IS_LOCALHOST ? 'development' : 'production'))));
$debugFlag = app_env('DEBUG');
if ($debugFlag === false || $debugFlag === '') {
    define('DEBUG_MODE', IS_LOCALHOST);
} else {
    define('DEBUG_MODE', filter_var($debugFlag, FILTER_VALIDATE_BOOLEAN));
}
define('LOG_ERRORS', !DEBUG_MODE);

// Email Configuration
define('EMAIL_USERNAME', (string) app_env('EMAIL_USERNAME', 'consultationmanagement2025@gmail.com'));
define('EMAIL_PASSWORD', (string) app_env('EMAIL_PASSWORD', ''));
define('EMAIL_FROM', (string) app_env('EMAIL_FROM', 'Valenzuela City Government'));

// Database Configuration - Defaults match production credentials when not on localhost
define('DB_HOST', (string) app_env('DB_HOST', 'localhost'));
define('DB_USER', (string) app_env('DB_USER', IS_LOCALHOST ? 'root' : 'cons_pc_db'));
define('DB_PASS', (string) app_env('DB_PASS', IS_LOCALHOST ? '' : 'e3sEe1sf!g6+uoak'));
define('DB_NAME', (string) app_env('DB_NAME', IS_LOCALHOST ? 'pc_db' : 'cons_pc_db'));
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
