<?php
require_once __DIR__ . '/../db.php';

echo "=== Checking Recent Consultations ===\n\n";

// Check the last 5 consultations
$result = $conn->query("SELECT id, title, user_name, user_email, status, type, created_at FROM consultations ORDER BY created_at DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "Recent consultations:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}\n";
        echo "Title: {$row['title']}\n";
        echo "Name: {$row['user_name']}\n";
        echo "Email: {$row['user_email']}\n";
        echo "Status: {$row['status']}\n";
        echo "Type: {$row['type']}\n";
        echo "Created: {$row['created_at']}\n";
        echo "---\n";
    }
} else {
    echo "No consultations found.\n";
}

// Check total count
$count = $conn->query("SELECT COUNT(*) as total FROM consultations");
if ($count) {
    $row = $count->fetch_assoc();
    echo "\nTotal consultations: {$row['total']}\n";
}
?>
