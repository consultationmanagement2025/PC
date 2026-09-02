<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../login.php'; // or test the exact login logic

echo "Testing login logic for consultationmanagement2026@gmail.com...\n";

$email = 'consultationmanagement2026@gmail.com';
$password = 'consultation2026';

$conn = dbEnsureConnection();

$stmt = $conn->prepare("SELECT id, fullname, password, role, email FROM users WHERE email=? OR username=?");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo "User found: ID={$user['id']}, Email={$user['email']}, Role={$user['role']}\n";
    if (password_verify($password, $user['password'])) {
        echo "Password verified successfully!\n";
        $normalized_db_role = normalizeUserRole($user['role'] ?? '');
        echo "Normalized DB role: '$normalized_db_role'\n";
        
        $allowed_login_roles = ['admin', 'super admin', 'superadmin', 'super_admin', 'administrator', 'staff', 'resource person', 'resource_person', 'expert'];
        if (!in_array($normalized_db_role, $allowed_login_roles, true) && !in_array($user['role'] ?? '', $allowed_login_roles, true)) {
            echo "FAILED: Invalid role for portal access.\n";
        } else {
            echo "SUCCESS: Access granted! Role is allowed.\n";
            $roleNorm = strtolower(str_replace([' ', '_'], '', $normalized_db_role));
            if (in_array($roleNorm, ['admin', 'administrator', 'superadmin', 'staff', 'barangaystaff', 'barangay'], true)) {
                echo "Redirect target: system-template-full.php (Admin / Superadmin dashboard)\n";
            } else {
                echo "Redirect target: index.php\n";
            }
        }
    } else {
        echo "FAILED: Password verify failed!\n";
    }
} else {
    echo "FAILED: User not found!\n";
}
