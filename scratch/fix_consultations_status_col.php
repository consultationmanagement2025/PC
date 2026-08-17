<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("ALTER TABLE consultations MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active'");
if ($res) {
    echo "SUCCESS: consultations status column updated to VARCHAR(50)\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
