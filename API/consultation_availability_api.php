<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db.php';

// CORS (local use)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function isAdmin(): bool {
    $role = isset($_SESSION['role']) ? strtolower(trim((string)$_SESSION['role'])) : '';
    return $role === 'admin' || $role === 'administrator';
}

function ensureAvailabilityTables(mysqli $conn): void {
        $conn->query("CREATE TABLE IF NOT EXISTS consultation_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL DEFAULT '09:00:00',
        end_time TIME NOT NULL DEFAULT '17:00:00',
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        max_bookings INT NOT NULL DEFAULT 5,
        current_bookings INT NOT NULL DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_admin_date (admin_id, date),
        INDEX idx_date (date),
        INDEX idx_admin_id (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS consultation_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consultation_id INT NOT NULL,
        availability_id INT NOT NULL,
        user_id INT DEFAULT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('requested','confirmed','rescheduled','cancelled') NOT NULL DEFAULT 'requested',
        meeting_platform VARCHAR(50) DEFAULT NULL,
        meeting_link VARCHAR(500) DEFAULT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_consultation_id (consultation_id),
        INDEX idx_availability_id (availability_id),
        INDEX idx_booking_date (booking_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function getPublicHolidays(int $year): array {
    // Fixed-date PH holidays (core set)
    $fixed = [
        "$year-01-01",
        "$year-04-09",
        "$year-05-01",
        "$year-06-12",
        "$year-08-21",
        "$year-11-01",
        "$year-11-30",
        "$year-12-08",
        "$year-12-25",
        "$year-12-30"
    ];
    return $fixed;
}

try {
    $action = $_GET['action'] ?? '';
    $conn = dbEnsureConnection();
    ensureAvailabilityTables($conn);

    if ($action === 'list_month') {
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        if ($month < 1 || $month > 12) $month = (int)date('m');
        $adminId = (int)($_GET['admin_id'] ?? 0);
        if ($adminId <= 0) $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) $adminId = 1;

        $stmt = $conn->prepare("SELECT id, admin_id, date, start_time, end_time, is_available, max_bookings, current_bookings, notes
            FROM consultation_availability
            WHERE admin_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
            ORDER BY date ASC");
        $stmt->bind_param('iii', $adminId, $year, $month);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        echo json_encode([
            'success' => true,
            'availability' => $rows,
            'holidays' => getPublicHolidays($year)
        ]);
        exit;
    }

    if ($action === 'save_availability') {
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $date = trim((string)($_POST['date'] ?? ''));
        $start = trim((string)($_POST['start_time'] ?? '09:00'));
        $end = trim((string)($_POST['end_time'] ?? '17:00'));
        $isAvailable = (int)($_POST['is_available'] ?? 1) ? 1 : 0;
        $max = (int)($_POST['max_consultations'] ?? 5);
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($date === '') {
            echo json_encode(['success' => false, 'error' => 'Date is required']);
            exit;
        }
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) $adminId = 1;

        $sql = "INSERT INTO consultation_availability (admin_id, date, start_time, end_time, is_available, max_bookings, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time),
                is_available = VALUES(is_available), max_bookings = VALUES(max_bookings), notes = VALUES(notes)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            // Log detailed DB error for debugging
            $logDir = __DIR__ . '/../LOGS';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logMsg = "[".date('Y-m-d H:i:s')."] prepare_failed: " . $conn->error . " SQL: " . str_replace("\n"," ", $sql) . "\n";
            @file_put_contents($logDir . '/availability_errors.log', $logMsg, FILE_APPEND | LOCK_EX);

            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'prepare_failed', 'db_error' => $conn->error, 'sql' => $sql]);
            exit;
        }

        // types: adminId (i), date (s), start (s), end (s), isAvailable (i), max (i), notes (s)
        $stmt->bind_param('isssiis', $adminId, $date, $start, $end, $isAvailable, $max, $notes);
        $ok = $stmt->execute();
        if ($stmt->errno) {
            // return error details for debugging
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'execute_failed', 'stmt_error' => $stmt->error]);
            $stmt->close();
            exit;
        }
        $stmt->close();
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
