<?php
require_once 'db.php';

$conn = dbConnect();
$result = $conn->query("SELECT id, email, reset_token, reset_expires FROM users WHERE email = 'consultationmanagement2026@gmail.com'");

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode($user, JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'User not found'], JSON_PRETTY_PRINT);
}
?>
