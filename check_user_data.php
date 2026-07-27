<?php
require_once 'db.php';

echo "<h2>Checking User Data</h2>";

// Check admin users
$stmt = $conn->prepare("SELECT id, fullname, name, email, role FROM users WHERE role LIKE '%admin%' LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Admin Users:</h3>";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "<br>";
    echo "Fullname: " . ($row['fullname'] ?? 'NULL') . "<br>";
    echo "Name: " . ($row['name'] ?? 'NULL') . "<br>";
    echo "Email: " . $row['email'] . "<br>";
    echo "Role: " . $row['role'] . "<br>";
    echo "<hr>";
}

// Check citizen users
$stmt = $conn->prepare("SELECT id, fullname, name, email, role FROM users WHERE role = 'citizen' LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Citizen Users:</h3>";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "<br>";
    echo "Fullname: " . ($row['fullname'] ?? 'NULL') . "<br>";
    echo "Name: " . ($row['name'] ?? 'NULL') . "<br>";
    echo "Email: " . $row['email'] . "<br>";
    echo "Role: " . $row['role'] . "<br>";
    echo "<hr>";
}
?>
