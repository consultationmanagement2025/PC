<?php
require 'db.php';
echo "--- RECENT CONSULTATIONS (ALL) ---\n";
$res = $conn->query("SELECT id, title, category, description, created_at, image_path FROM consultations ORDER BY id DESC");
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . " | Title: " . $r['title'] . " | Created: " . $r['created_at'] . " | Image: " . $r['image_path'] . "\n";
}

echo "\n--- SEARCHING FOR 'Improvement' OR 'Parks' ---\n";
$r2 = $conn->query("SELECT * FROM consultations WHERE title LIKE '%Improvement%' OR title LIKE '%Parks%' OR description LIKE '%Parks%'");
while ($row = $r2->fetch_assoc()) {
    print_r($row);
}
