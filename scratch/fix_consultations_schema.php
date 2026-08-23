<?php
require_once __DIR__ . '/../db.php';

$sql = "ALTER TABLE consultations MODIFY COLUMN description TEXT NULL";
if ($conn->query($sql)) {
    echo "Successfully altered consultations.description to allow NULL!\n";
} else {
    echo "ALTER TABLE ERROR: " . $conn->error . "\n";
}

// Now insert ID 8 (Aljan, test)
$ins = $conn->query("INSERT INTO consultations (id, title, description, category, type, user_name, user_email, status, created_at, updated_at) VALUES (8, 'test', 'Test proposal description', 'General Governance', 'user', 'Aljan', 'aljan@example.com', 'pending', '2026-08-14 04:21:45', NOW())");
if ($ins) {
    echo "Successfully inserted consultation ID 8 ('test' by Aljan) into MySQL database!\n";
} else {
    echo "INSERT ERROR: " . $conn->error . "\n";
}
