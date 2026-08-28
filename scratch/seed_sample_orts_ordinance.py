import os
import subprocess

seed_script = """<?php
require_once 'db.php';
global $conn;

$ref = 'ORD-2025-104';
$title = 'Citywide Anti-Single-Use Plastics & Eco-Packaging Ordinance';
$desc = 'Proposed ordinance regulating single-use non-biodegradable plastic packaging and promoting eco-friendly alternatives across commercial establishments and barangay markets in Valenzuela City.';
$cat = 'Environment & Waste Management';

$chk = $conn->query("SELECT id FROM consultations WHERE tracking_number = '$ref' OR external_ref = '$ref' LIMIT 1");
if ($chk && $row = $chk->fetch_assoc()) {
    $cid = (int)$row['id'];
    $conn->query("UPDATE consultations SET type = 'ordinance', source_system = 'ORTS', title = '$title', category = '$cat' WHERE id = $cid");
    echo "Updated existing ORTS Ordinance ID #$cid ($ref)\\n";
} else {
    $conn->query("INSERT INTO consultations (title, description, category, status, type, tracking_number, external_ref, source_system, posts_count, created_at) VALUES ('$title', '$desc', '$cat', 'active', 'ordinance', '$ref', '$ref', 'ORTS', 3, NOW())");
    $cid = $conn->insert_id;
    echo "Inserted sample ORTS Ordinance ID #$cid ($ref)\\n";
}
"""

with open(r'c:\xampp\htdocs\CAP101\PC\scratch\seed_orts.php', 'w', encoding='utf-8') as f:
    f.write(seed_script)

print("Created seed_orts.php script.")
