<?php
require_once 'db.php';

$conn = dbConnect();

// First, just get the user
$sql = "SELECT id, email, reset_token, reset_expires FROM users WHERE email = 'consultationmanagement2026@gmail.com'";
$result = $conn->query($sql);

if ($result) {
    echo json_encode([
        'rows' => $result->num_rows,
        'data' => $result->fetch_assoc()
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'Query failed: ' . $conn->error], JSON_PRETTY_PRINT);
}

$conn->close();
?>
