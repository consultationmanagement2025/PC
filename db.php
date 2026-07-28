<?php

// Load config if available for fallback constants
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

if (!function_exists('dbConnect')) {
    function dbConnect() {
        $isLocal = defined('IS_LOCALHOST') ? IS_LOCALHOST : true;
        $primaryHost = function_exists('app_env') ? app_env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost') : (getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost'));
        $primaryUser = function_exists('app_env') ? app_env('DB_USER', defined('DB_USER') ? DB_USER : ($isLocal ? 'root' : 'cons_pc_db')) : (getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : ($isLocal ? 'root' : 'cons_pc_db')));
        $primaryPass = function_exists('app_env') ? app_env('DB_PASS', defined('DB_PASS') ? DB_PASS : ($isLocal ? '' : '%wE!*-vMg4GCbB#3')) : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : ($isLocal ? '' : '%wE!*-vMg4GCbB#3')));
        $primaryName = function_exists('app_env') ? app_env('DB_NAME', defined('DB_NAME') ? DB_NAME : ($isLocal ? 'pc_db' : 'cons_pc_db')) : (getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : ($isLocal ? 'pc_db' : 'cons_pc_db')));
        
        $db_port_raw = function_exists('app_env') ? app_env('DB_PORT', getenv('DB_PORT')) : getenv('DB_PORT');
        $db_port = 3306;
        if ($db_port_raw !== null && $db_port_raw !== false && $db_port_raw !== '') {
            $db_port = (int)$db_port_raw;
            if ($db_port <= 0) {
                $db_port = 3306;
            }
        }

        $hosts = array_unique(array_filter([$primaryHost, 'localhost', '127.0.0.1']));
        $users = array_unique(array_filter([$primaryUser, 'cons_pc_db', 'consu2396_cons_pc_db', 'consu2396_pc_db', 'root']));
        $passes = array_unique([$primaryPass, '%wE!*-vMg4GCbB#3', 'e3sEe1sf!g6+uoak', 'consultation2025', '']);
        $names = array_unique(array_filter([$primaryName, 'cons_pc_db', 'consu2396_cons_pc_db', 'consu2396_pc_db', 'pc_db']));

        $lastErr = null;
        mysqli_report(MYSQLI_REPORT_OFF);

        foreach ($hosts as $h) {
            foreach ($users as $u) {
                foreach ($passes as $p) {
                    foreach ($names as $n) {
                        try {
                            $c = @new mysqli($h, $u, $p, $n, $db_port);
                            if (!$c->connect_error) {
                                $c->set_charset('utf8mb4');
                                return $c;
                            }
                            $lastErr = $c->connect_error;
                        } catch (Throwable $e) {
                            $lastErr = $e->getMessage();
                        }
                    }
                }
            }
        }

        error_log('Database connection failed: ' . $lastErr);
        die('Database connection failed: ' . htmlspecialchars($lastErr) . ' [Host: ' . htmlspecialchars($primaryHost) . ', User: ' . htmlspecialchars($primaryUser) . ', DB: ' . htmlspecialchars($primaryName) . ']');
    }
}

if (!function_exists('dbEnsureConnection')) {
    function dbEnsureConnection() {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            $conn = dbConnect();
            return $conn;
        }

        try {
            $ok = @$conn->ping();
        } catch (Throwable $e) {
            $ok = false;
        }

        if (!$ok) {
            try {
                @$conn->close();
            } catch (Throwable $e) {
            }
            $conn = dbConnect();
        }

        return $conn;
    }
}

$conn = dbEnsureConnection();
