<?php
require_once __DIR__ . '/../db.php';

$conn = dbEnsureConnection();

echo "Checking users table...\n";
$email = 'consultationmanagement2026@gmail.com';
$password = 'consultation2026';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id, fullname, username, email, role, status, password FROM users WHERE email = ? OR username = ?");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo "Found existing user:\n";
    print_r($row);
    
    // Test current password verification
    $passMatches = password_verify($password, $row['password']);
    echo "Current password matches 'consultation2026': " . ($passMatches ? "YES" : "NO") . "\n";

    // Update user to super admin with password consultation2026
    $updateStmt = $conn->prepare("UPDATE users SET password = ?, role = 'super admin', status = 'active' WHERE id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $row['id']);
    if ($updateStmt->execute()) {
        echo "Successfully updated user ID {$row['id']} to role 'super admin', status 'active', and updated password!\n";
    } else {
        echo "Error updating user: " . $conn->error . "\n";
    }
} else {
    echo "User $email not found in database. Creating new super admin user...\n";
    $fullname = "Super Admin";
    $username = "superadmin2026";
    $role = "super admin";
    $status = "active";
    
    $insertStmt = $conn->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insertStmt->bind_param("ssssss", $fullname, $username, $email, $hashedPassword, $role, $status);
    if ($insertStmt->execute()) {
        echo "Successfully created new super admin user ID: " . $conn->insert_id . "\n";
    } else {
        echo "Error inserting user: " . $conn->error . "\n";
    }
}

// Let's verify all superadmin/admin role variants in database
echo "\nChecking all admin/superadmin users in system:\n";
$resAll = $conn->query("SELECT id, fullname, username, email, role, status FROM users WHERE role LIKE '%admin%' OR email LIKE '%consultation%'");
while ($user = $resAll->fetch_assoc()) {
    echo "ID: {$user['id']} | Email: {$user['email']} | Role: {$user['role']} | Status: {$user['status']}\n";
}
