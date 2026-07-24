<?php
require_once __DIR__ . '/../../db.php';

echo "Checking latest consultations...\n";

$result = $conn->query("SELECT id, title, image_path, created_at FROM consultations ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Image Path: " . ($row['image_path'] ?? 'NULL') . "\n";
        echo "Created: " . $row['created_at'] . "\n";
        echo "---\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
