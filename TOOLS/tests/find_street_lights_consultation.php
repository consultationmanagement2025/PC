<?php
require_once 'db.php';

// Search for consultations with street lights in title or description
$sql = "SELECT id, title, description, tracking_number 
        FROM consultations 
        WHERE (title LIKE '%street%' OR description LIKE '%street%' OR title LIKE '%light%' OR description LIKE '%light%')
        ORDER BY id DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Consultations with street/light references:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Tracking: " . $row['tracking_number'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Description: " . substr($row['description'], 0, 100) . "...\n";
        echo "---\n";
    }
} else {
    echo "No consultations found with street/light references\n";
}
