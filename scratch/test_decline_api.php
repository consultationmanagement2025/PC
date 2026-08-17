<?php
require_once __DIR__ . '/../db.php';
$stmt = $conn->query("SELECT id, title, status, admin_response FROM consultations WHERE type='user' LIMIT 1");
$row = $stmt ? $stmt->fetch_assoc() : null;
echo "Sample User Submission:\n";
print_r($row);
