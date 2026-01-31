<?php
// admin_consultation_dashboard.php
require 'db.php';

// Get counts from the database
$total = $conn->query("SELECT COUNT(*) FROM consultations")->fetch_row()[0];
$open = $conn->query("SELECT COUNT(*) FROM consultations WHERE status='pending' OR status='approved'")->fetch_row()[0];
$scheduled = $conn->query("SELECT COUNT(*) FROM consultations WHERE status='approved'")->fetch_row()[0];

header('Content-Type: application/json');
echo json_encode([
    'total' => (int)$total,
    'open' => (int)$open,
    'scheduled' => (int)$scheduled
]);
