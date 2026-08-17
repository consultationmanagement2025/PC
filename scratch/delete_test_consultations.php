<?php
require_once 'db.php';
$conn = dbConnect();

echo "--- DELETING TEST/DUPLICATE CONSULTATIONS ---\n";
// Delete IDs 6, 10, 14 or any test consultations created during testing
$stmt = $conn->query("DELETE FROM consultations WHERE id IN (6, 10, 14) OR title LIKE '%Test Policy%' OR title LIKE '%Health Ordinance%' OR title LIKE '%Barangay Health Center Hours%'");
echo "Deleted " . $conn->affected_rows . " test consultation row(s) from database.\n";

echo "\n--- REMAINING CONSULTATIONS IN DATABASE ---\n";
$res = $conn->query("SELECT id, title, category, status, created_at FROM consultations ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Title: " . $r['title'] . " | Category: " . $r['category'] . " | Status: " . $r['status'] . "\n";
}
