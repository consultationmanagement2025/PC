<?php

if (!function_exists('dbConnect')) {
    function dbConnect() {
        $primaryHost = getenv('DB_HOST') ?: 'localhost';
        $primaryUser = getenv('DB_USER') ?: 'cons_pc_db';
        $primaryPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '%wE!*-vMg4GCbB#3';
        $primaryName = getenv('DB_NAME') ?: 'cons_pc_db';
        $db_port_raw = getenv('DB_PORT');
        $db_port = 3306;
        if ($db_port_raw !== false && $db_port_raw !== '') {
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
