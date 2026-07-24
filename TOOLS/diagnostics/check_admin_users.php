<?php
require_once __DIR__ . '/../../db.php';

echo "=== CHECK ADMIN USERS ===\n\n";

// Check all admin users
$stmt = $conn->prepare("SELECT id, fullname, email, role FROM users WHERE role IN ('admin', 'administrator')");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Found " . $result->num_rows . " admin users:\n\n";
        while ($user = $result->fetch_assoc()) {
            echo "ID: " . $user['id'] . "\n";
            echo "Name: " . $user['fullname'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Role: " . $user['role'] . "\n";
                        echo "-------------------\n";
        }
    } else {
        echo "No admin users found in the database!\n";
        
        // Check if there are any users at all
        $all_users = $conn->query("SELECT COUNT(*) as count FROM users");
        $count = $all_users->fetch_assoc()['count'];
        echo "Total users in database: " . $count . "\n";
    }
    $stmt->close();
} else {
    echo "Error preparing query: " . $conn->error . "\n";
}

$conn->close();
?>
