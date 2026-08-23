<?php
require_once __DIR__ . '/../db.php';
$ok = $conn->query("INSERT INTO consultations (id, title, category, type, user_name, user_email, status, created_at, updated_at) VALUES (8, 'test', 'General Governance', 'user', 'Aljan', 'aljan@example.com', 'pending', '2026-08-14 04:21:45', NOW())");
if (!$ok) {
    echo "INSERT ERROR: " . $conn->error . "\n";
} else {
    echo "INSERT SUCCESS!\n";
}
