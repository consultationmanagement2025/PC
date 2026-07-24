<?php
$conn = new mysqli("localhost", "root", "", "pc_db");
echo "=== Recent Consultations ===\n";
$result = $conn->query("SELECT id, email, created_at, type FROM consultations ORDER BY created_at DESC LIMIT 10");
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Email: " . $row['email'] . " | Date: " . $row['created_at'] . " | Type: " . $row['type'] . "\n";
}
$conn->close();
?>
