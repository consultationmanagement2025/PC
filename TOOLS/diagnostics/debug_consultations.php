<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../DATABASE/consultations.php';

echo "<h2>Debug Consultations</h2>";

// Test database connection
global $conn;
if (!$conn) {
    echo "<p style='color: red;'>Database connection failed!</p>";
    exit;
}

echo "<p style='color: green;'>Database connected!</p>";

// Get all consultations
$result = $conn->query("SELECT id, title, status, start_date, end_date, image_path, created_at FROM consultations ORDER BY created_at DESC LIMIT 10");

if (!$result) {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
    exit;
}

echo "<h3>All Consultations (Last 10):</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Image Path</th><th>Created</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td style='color: " . ($row['status'] == 'active' ? 'green' : 'red') . ";'>" . $row['status'] . "</td>";
    echo "<td>" . $row['start_date'] . "</td>";
    echo "<td>" . $row['end_date'] . "</td>";
    echo "<td>" . ($row['image_path'] ?: 'NULL') . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test active consultation query
$where_active = "status = 'active'";
$active_result = $conn->query("SELECT id, title, status, start_date, end_date FROM consultations WHERE $where_active ORDER BY start_date DESC LIMIT 50");

echo "<h3>Active Consultations Query Result:</h3>";
echo "<p>Query: SELECT id, title, status, start_date, end_date FROM consultations WHERE status = 'active' ORDER BY start_date DESC LIMIT 50</p>";

if ($active_result && $active_result->num_rows > 0) {
    echo "<p style='color: green;'>Found " . $active_result->num_rows . " active consultations</p>";
} else {
    echo "<p style='color: red;'>No active consultations found!</p>";
}

// Check if images directory exists
$image_dir = __DIR__ . '/../../ASSETS/images/consultations/';
if (file_exists($image_dir)) {
    echo "<p style='color: green;'>Images directory exists: $image_dir</p>";
    $files = glob($image_dir . '*');
    echo "<p>Files in directory: " . count($files) . "</p>";
    if (!empty($files)) {
        echo "<ul>";
        foreach ($files as $file) {
            echo "<li>" . basename($file) . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>Images directory missing: $image_dir</p>";
}
?>
