<?php

if (!function_exists('dbConnect')) {
    function dbConnect() {
        $db_host = getenv('DB_HOST') ?: 'localhost';
        $db_user = getenv('DB_USER') ?: 'root';
        $db_pass = getenv('DB_PASS');
        if ($db_pass === false) {
            $db_pass = '';
        }
        $db_name = getenv('DB_NAME') ?: 'pc_db';
        $db_port_raw = getenv('DB_PORT');
        $db_port = 3306;
        if ($db_port_raw !== false && $db_port_raw !== '') {
            $db_port = (int)$db_port_raw;
            if ($db_port <= 0) {
                $db_port = 3306;
            }
        }

        try {
            mysqli_report(MYSQLI_REPORT_OFF);
            $c = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
        } catch (Throwable $e) {
            error_log('Database connection exception: ' . $e->getMessage());
            die('Database connection failed');
        }

        if ($c->connect_error) {
            error_log('Database connection failed: ' . $c->connect_error);
            die('Database connection failed');
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
