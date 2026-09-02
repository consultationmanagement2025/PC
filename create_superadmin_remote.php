<?php
/**
 * Remote Superadmin Account Seeder
 * Run this file in your browser at: https://consultation.spvalenzuela.com/create_superadmin_remote.php
 */
require_once __DIR__ . '/db.php';

$conn = dbEnsureConnection();

echo "<h2>PCMS Superadmin Account Seeder</h2>";

$accounts = [
    [
        'fullname' => 'Super Administrator',
        'name' => 'Super Administrator',
        'username' => 'consultationmanagement2026',
        'email' => 'consultationmanagement2026@gmail.com',
        'password' => 'consultation2026',
        'role' => 'super admin',
        'status' => 'active'
    ],
    [
        'fullname' => 'Super Administrator',
        'name' => 'Super Administrator',
        'username' => 'cons2026',
        'email' => 'cons2026@gmail.com',
        'password' => 'consultation2026',
        'role' => 'super admin',
        'status' => 'active'
    ]
];

foreach ($accounts as $acc) {
    $email = $acc['email'];
    $username = $acc['username'];
    $fullname = $acc['fullname'];
    $password = $acc['password'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = $acc['role'];
    $status = $acc['status'];

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $uStmt = $conn->prepare("UPDATE users SET password = ?, role = ?, status = 'active', fullname = ?, name = ? WHERE id = ?");
        $uStmt->bind_param("ssssi", $hashedPassword, $role, $fullname, $fullname, $row['id']);
        if ($uStmt->execute()) {
            echo "<p style='color:green;'>Updated existing user <strong>{$email}</strong> (ID: {$row['id']}) to super admin role and password '{$password}'!</p>";
        } else {
            echo "<p style='color:red;'>Error updating {$email}: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        $iStmt = $conn->prepare("INSERT INTO users (fullname, name, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$iStmt) {
            $iStmt = $conn->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $iStmt->bind_param("ssssss", $fullname, $username, $email, $hashedPassword, $role, $status);
        } else {
            $iStmt->bind_param("sssssss", $fullname, $fullname, $username, $email, $hashedPassword, $role, $status);
        }

        if ($iStmt && $iStmt->execute()) {
            echo "<p style='color:green;'>Successfully created new super admin user <strong>{$email}</strong> (ID: {$conn->insert_id}) with password '{$password}'!</p>";
        } else {
            echo "<p style='color:red;'>Error inserting {$email}: " . htmlspecialchars($conn->error) . "</p>";
        }
    }
}

echo "<br><p><strong>All done! You can now log in at your website.</strong></p>";
