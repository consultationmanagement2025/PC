<?php
// Simple test for image upload
require_once __DIR__ . '/../../db.php';

echo "<h2>Image Upload Test</h2>";

// Test citizen image upload function
if (file_exists(__DIR__ . '/../../public-portal.php')) {
    include_once __DIR__ . '/../../public-portal.php';
    
    echo "<p>Citizen image upload function exists: " . (function_exists('handleCitizenImageUpload') ? 'YES' : 'NO') . "</p>";
}

// Check directories
$admin_dir = __DIR__ . '/../../ASSETS/images/consultations/';
$citizen_dir = __DIR__ . '/../../ASSETS/images/citizen-consultations/';

echo "<h3>Directory Check:</h3>";
echo "<p>Admin directory ($admin_dir): " . (file_exists($admin_dir) ? 'EXISTS' : 'MISSING') . "</p>";
echo "<p>Citizen directory ($citizen_dir): " . (file_exists($citizen_dir) ? 'EXISTS' : 'MISSING') . "</p>";

// Check recent consultations
echo "<h3>Recent Consultations:</h3>";
$result = $conn->query("SELECT id, title, status, image_path, created_at FROM consultations ORDER BY created_at DESC LIMIT 5");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Image Path</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td style='color: " . ($row['status'] == 'active' ? 'green' : 'orange') . ";'>" . $row['status'] . "</td>";
        echo "<td>" . ($row['image_path'] ?: 'NULL') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No consultations found</p>";
}

// Check if image files exist for consultations with image_path
echo "<h3>Image File Check:</h3>";
$result = $conn->query("SELECT id, title, image_path FROM consultations WHERE image_path IS NOT NULL AND image_path != '' ORDER BY created_at DESC LIMIT 5");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $image_path = $row['image_path'];
        $full_path = __DIR__ . '/../../' . $image_path;
        echo "<p>Consultation ID " . $row['id'] . " (" . htmlspecialchars($row['title']) . "): ";
        echo "Path: $image_path - ";
        echo "File exists: " . (file_exists($full_path) ? 'YES' : 'NO') . "</p>";
    }
} else {
    echo "<p>No consultations with images found</p>";
}
?>
