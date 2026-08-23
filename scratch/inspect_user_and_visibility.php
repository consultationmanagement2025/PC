<?php
require_once __DIR__ . '/../db.php';

echo "=== USER MAINE DA ===\n";
$res = $conn->query("SELECT id, fullname, email, role, expertise_areas, department FROM users WHERE email LIKE '%staff01%' OR fullname LIKE '%Maine%' OR role LIKE '%resource%'");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "=== ALL CONSULTATIONS WITH DETAILED VISIBILITY evaluation ===\n";
$raw_consultations = [];
$cRes = $conn->query("SELECT * FROM consultations");
while ($row = $cRes->fetch_assoc()) {
    $raw_consultations[] = $row;
}

$user_id = 5; // Maine Da ID or whatever
$expertise_areas = "Women, Family & Gender Equality, Health & Sanitation, Social Services, Public Utilities & Facilities";

foreach ($raw_consultations as $cRow) {
    echo "Consultation #{$cRow['id']} - Title: '{$cRow['title']}' | Status: {$cRow['status']} | Type: {$cRow['type']} | AI: {$cRow['ai_analyzed']} | Fwd: {$cRow['forwarded_to_expert']} | Assigned: {$cRow['assigned_to']} | DocStatus: {$cRow['document_status']}\n";
}
