<?php
require_once __DIR__ . '/../db.php';

// Check if ID 8 exists
$res = $conn->query("SELECT * FROM consultations WHERE id = 8");
if ($res && $res->num_rows === 0) {
    $conn->query("INSERT INTO consultations (id, title, category, type, user_name, user_email, status, created_at, updated_at) VALUES (8, 'test', 'General Governance', 'user', 'Aljan', 'aljan@example.com', 'pending', '2026-08-14 04:21:45', NOW())");
    echo "Inserted consultation ID 8 ('test' by Aljan) into MySQL database successfully!\n";
} else {
    echo "Consultation ID 8 already exists in MySQL database.\n";
}
