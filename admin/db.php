<?php

$configFile = dirname(__DIR__) . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

if (!function_exists('dbConnect')) {
    function dbConnect() {
        $isLocal = defined('IS_LOCALHOST') ? IS_LOCALHOST : true;
        $db_host = function_exists('app_env') ? app_env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost') : (getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost'));
        $db_user = function_exists('app_env') ? app_env('DB_USER', defined('DB_USER') ? DB_USER : ($isLocal ? 'root' : 'cons_pc_db')) : (getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : ($isLocal ? 'root' : 'cons_pc_db')));
        $db_pass = function_exists('app_env') ? app_env('DB_PASS', defined('DB_PASS') ? DB_PASS : ($isLocal ? '' : 'e3sEe1sf!g6+uoak')) : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : ($isLocal ? '' : 'e3sEe1sf!g6+uoak')));
        $db_name = function_exists('app_env') ? app_env('DB_NAME', defined('DB_NAME') ? DB_NAME : ($isLocal ? 'pc_db' : 'cons_pc_db')) : (getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : ($isLocal ? 'pc_db' : 'cons_pc_db')));
        $db_port_raw = function_exists('app_env') ? app_env('DB_PORT', getenv('DB_PORT')) : getenv('DB_PORT');
        $db_port = 3306;
        if ($db_port_raw !== null && $db_port_raw !== false && $db_port_raw !== '') {
            $db_port = (int)$db_port_raw;
            if ($db_port <= 0) {
                $db_port = 3306;
            }
        }

        $err = null;
        try {
            mysqli_report(MYSQLI_REPORT_OFF);
            $c = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
            if ($c->connect_error) {
                $err = $c->connect_error;
            }
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }

        if ($err !== null) {
            error_log('Database connection failed: ' . $err);
            die('Database connection failed: ' . htmlspecialchars($err) . ' [Host: ' . htmlspecialchars($db_host) . ', User: ' . htmlspecialchars($db_user) . ', DB: ' . htmlspecialchars($db_name) . ']');
        }
        $c->set_charset('utf8mb4');
        return $c;
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
