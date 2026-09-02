<?php
require_once __DIR__ . '/../db.php';
$conn = dbEnsureConnection();
$res = $conn->query("SELECT id, fullname, username, email, role, status, password FROM users");
while ($user = $res->fetch_assoc()) {
    echo "ID: " . $user['id'] . "\n";
    echo "Fullname: " . $user['fullname'] . "\n";
    echo "Username: '" . $user['username'] . "'\n";
    echo "Email: '" . $user['email'] . "'\n";
    echo "Role: '" . $user['role'] . "'\n";
    echo "Status: '" . $user['status'] . "'\n";
    echo "Password Hash: " . $user['password'] . "\n";
    echo "Verify 'consultation2026': " . (password_verify('consultation2026', $user['password']) ? "YES" : "NO") . "\n";
    echo "----------------------------------------\n";
}
