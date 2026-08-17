<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("ALTER TABLE consultations ADD COLUMN admin_response TEXT DEFAULT NULL");
if ($res) {
    echo "SUCCESS: Added admin_response column to consultations table.\n";
} else {
    echo "NOTICE: " . $conn->error . "\n";
}
