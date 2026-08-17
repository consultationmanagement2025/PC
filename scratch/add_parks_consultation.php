<?php
require_once 'db.php';
$conn = dbConnect();

// Check if Parks consultation exists
$check = $conn->query("SELECT id FROM consultations WHERE title LIKE '%Parks%' OR title LIKE '%Playgrounds%'");
if ($check->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, type, end_date, response_mode, allow_guest_quick_vote, allow_guest_verified_vote) VALUES (?, ?, ?, 'active', 'admin', '2026-12-31 23:59:59', 'feedback', 1, 1)");
    $title = "Public Consultation on the Improvement of Parks and Open Spaces";
    $desc = "Residents are invited to share their feedback regarding proposed improvements to public parks, playgrounds, and other open community spaces. This consultation aims to identify the facility upgrades and greenery enhancements needed across Valenzuela barangays.";
    $cat = "General Governance";
    $stmt->bind_param("sss", $title, $desc, $cat);
    $stmt->execute();
    echo "Inserted 1 single clean Parks consultation record (ID: " . $stmt->insert_id . ").\n";
    $stmt->close();
} else {
    // Delete any duplicates beyond the first one
    $res = $conn->query("SELECT id FROM consultations WHERE title LIKE '%Parks%' ORDER BY id ASC");
    $ids = [];
    while ($r = $res->fetch_assoc()) $ids[] = $r['id'];
    if (count($ids) > 1) {
        $firstId = array_shift($ids);
        $idList = implode(',', $ids);
        $conn->query("DELETE FROM consultations WHERE id IN ($idList)");
        echo "Cleaned up duplicate Parks consultations. Kept ID $firstId and deleted IDs: $idList\n";
    } else {
        echo "Parks consultation already exists cleanly (ID " . $ids[0] . ").\n";
    }
}

echo "\n--- FINAL CONSULTATIONS TABLE IN DATABASE ---\n";
$res2 = $conn->query("SELECT id, title, category, status, created_at FROM consultations ORDER BY id ASC");
while ($r = $res2->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Title: " . $r['title'] . " | Category: " . $r['category'] . " | Status: " . $r['status'] . "\n";
}
