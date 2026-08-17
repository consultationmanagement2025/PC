<?php
require_once __DIR__ . '/../db.php';
$stmt = $conn->query("SELECT id, title, type, user_name, status, admin_response FROM consultations");
while ($row = $stmt->fetch_assoc()) {
    echo "ID #{$row['id']} | Type: {$row['type']} | User: {$row['user_name']} | Status: {$row['status']}\n";
}
