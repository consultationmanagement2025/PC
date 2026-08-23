<?php
require_once __DIR__ . '/../db.php';

// 1. Remove duplicates from hearing_queue keeping the one with max queue_id per phms_hearing_id
$conn->query("
    DELETE hq1 FROM hearing_queue hq1
    INNER JOIN hearing_queue hq2 
    ON hq1.phms_hearing_id = hq2.phms_hearing_id AND hq1.queue_id < hq2.queue_id
    WHERE hq1.phms_hearing_id IS NOT NULL AND hq1.phms_hearing_id > 0
");

// 2. Drop existing non-unique idx_hearing if present
$conn->query("ALTER TABLE hearing_queue DROP INDEX idx_hearing");

// 3. Add UNIQUE index on phms_hearing_id
$res = $conn->query("ALTER TABLE hearing_queue ADD UNIQUE KEY uq_phms_hearing_id (phms_hearing_id)");
if ($res) {
    echo "UNIQUE KEY uq_phms_hearing_id ADDED SUCCESSFULLY!\n";
} else {
    echo "FAILED TO ADD UNIQUE KEY: " . $conn->error . "\n";
}
