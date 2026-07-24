<?php
require_once __DIR__ . '/../../db.php';

// Check database table structure
echo "<h2>Database Structure:</h2>";
$sql = "DESCRIBE consultations";
$result = $conn->query($sql);
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . $row['Key'] . "</td></tr>";
}
echo "</table>";

// Check the latest consultation
echo "<h2>Latest Consultations:</h2>";
$sql = "SELECT * FROM consultations ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Topic</th><th>Status</th><th>Created</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['consultation_name'] ?? $row['name'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['consultation_email'] ?? $row['email'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['consultation_topic'] ?? $row['topic'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check documents table for PDFs
echo "<h2>Latest Documents:</h2>";
$sql = "SELECT * FROM documents ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Reference</th><th>Title</th><th>Type</th><th>Created</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['reference']) . "</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td>" . htmlspecialchars($row['type']) . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?>
