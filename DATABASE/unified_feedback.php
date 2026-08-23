<?php
if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
}

function ensureUnifiedFeedbackTables($conn) {
    if (!$conn) return false;

    // 1. Create unified_feedback_compilations table
    $sql1 = "CREATE TABLE IF NOT EXISTS unified_feedback_compilations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        merge_id VARCHAR(64) NOT NULL UNIQUE,
        total_feedback_count INT NOT NULL DEFAULT 0,
        categories_summary_json LONGTEXT,
        pdf_filename VARCHAR(255) DEFAULT NULL,
        pdf_path VARCHAR(255) DEFAULT NULL,
        compiled_by INT DEFAULT NULL,
        compiled_by_name VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_merge_id (merge_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($sql1)) {
        error_log("Failed to create unified_feedback_compilations table: " . $conn->error);
    }

    // 2. Add columns to feedback table
    $res = $conn->query("SHOW COLUMNS FROM feedback");
    if ($res) {
        $cols = [];
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
        if (!in_array('is_processed', $cols, true)) {
            $conn->query("ALTER TABLE feedback ADD COLUMN is_processed TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('merge_id', $cols, true)) {
            $conn->query("ALTER TABLE feedback ADD COLUMN merge_id VARCHAR(64) DEFAULT NULL");
        }
        if (!in_array('processed_at', $cols, true)) {
            $conn->query("ALTER TABLE feedback ADD COLUMN processed_at DATETIME DEFAULT NULL");
        }
    }

    // 3. Add columns to hearing_queue table if hearing_queue exists
    $hqCheck = $conn->query("SHOW TABLES LIKE 'hearing_queue'");
    if ($hqCheck && $hqCheck->num_rows > 0) {
        $resHq = $conn->query("SHOW COLUMNS FROM hearing_queue");
        if ($resHq) {
            $colsHq = [];
            while ($r = $resHq->fetch_assoc()) {
                $colsHq[] = $r['Field'];
            }
            if (!in_array('is_processed', $colsHq, true)) {
                $conn->query("ALTER TABLE hearing_queue ADD COLUMN is_processed TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!in_array('merge_id', $colsHq, true)) {
                $conn->query("ALTER TABLE hearing_queue ADD COLUMN merge_id VARCHAR(64) DEFAULT NULL");
            }
            if (!in_array('processed_at', $colsHq, true)) {
                $conn->query("ALTER TABLE hearing_queue ADD COLUMN processed_at DATETIME DEFAULT NULL");
            }
        }
    }

    return true;
}

if (isset($conn) && $conn) {
    ensureUnifiedFeedbackTables($conn);
}
