<?php
require_once 'db.php';

$conn = dbConnect();
$result = $conn->query("SELECT id, email, fullname FROM users LIMIT 10");

echo json_encode([
    'users' => $result->fetch_all(MYSQLI_ASSOC)
], JSON_PRETTY_PRINT);
?>
