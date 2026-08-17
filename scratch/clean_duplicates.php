<?php
require 'db.php';

echo "--- DELETING DUPLICATE CONSULTATIONS ---\n";
// Find duplicate titles and keep only the earliest (MIN id)
$res = $conn->query("SELECT title, COUNT(*) as cnt, MIN(id) as keep_id FROM consultations GROUP BY title HAVING cnt > 1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $title = $row['title'];
        $keep_id = (int)$row['keep_id'];
        echo "Found title '{$title}' repeated {$row['cnt']} times. Keeping ID {$keep_id} and deleting others.\n";
        
        $stmt = $conn->prepare("DELETE FROM consultations WHERE title = ? AND id != ?");
        $stmt->bind_param("si", $title, $keep_id);
        $stmt->execute();
        echo "Deleted " . $stmt->affected_rows . " duplicate row(s).\n";
        $stmt->close();
    }
}

echo "\n--- REMAINING CONSULTATIONS ---\n";
$res2 = $conn->query("SELECT id, title, category, status, created_at FROM consultations ORDER BY id ASC");
while ($r = $res2->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Title: " . $r['title'] . " | Status: " . $r['status'] . "\n";
}
