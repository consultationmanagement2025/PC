<?php
require_once __DIR__ . '/../db.php';

echo "=== MIGRATING FEEDBACK COLUMNS ===\n";
$colsRes = $conn->query("SHOW COLUMNS FROM feedback");
$existing = [];
while ($c = $colsRes->fetch_assoc()) {
    $existing[] = $c['Field'];
}

$columnsToAdd = [
    'sentiment_tag' => "VARCHAR(20) DEFAULT 'neutral'",
    'sentiment_score' => "DECIMAL(6,2) DEFAULT 0.0",
    'topic_tags' => "JSON DEFAULT NULL",
    'tracking_token' => "VARCHAR(64) DEFAULT NULL",
    'feedback_hash' => "VARCHAR(64) DEFAULT NULL",
    'submission_type' => "VARCHAR(50) DEFAULT 'comment'",
    'barangay' => "VARCHAR(150) DEFAULT NULL"
];

foreach ($columnsToAdd as $col => $def) {
    if (!in_array($col, $existing, true)) {
        $ok = $conn->query("ALTER TABLE feedback ADD COLUMN `$col` $def");
        echo "Adding column `$col`: " . ($ok ? "SUCCESS" : "FAILED: " . $conn->error) . "\n";
    } else {
        echo "Column `$col` already exists.\n";
    }
}
