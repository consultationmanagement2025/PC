<?php
// One-click Admin Account Creator / Password Resetter for Production
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

$hash = password_hash('Admin@12345', PASSWORD_BCRYPT);

$accounts = [
    [
        'fullname' => 'System Administrator',
        'name' => 'System Administrator',
        'username' => 'admin',
        'email' => 'admin@pcms.local',
        'password' => $hash,
        'role' => 'admin',
        'status' => 'active',
        'verification_status' => 'verified'
    ],
    [
        'fullname' => 'System Administrator',
        'name' => 'System Administrator',
        'username' => 'consultationmanagement2025',
        'email' => 'consultationmanagement2025@gmail.com',
        'password' => $hash,
        'role' => 'admin',
        'status' => 'active',
        'verification_status' => 'verified'
    ]
];

$results = [];

foreach ($accounts as $acc) {
    // Check if exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
    $checkStmt->bind_param("ss", $acc['email'], $acc['username']);
    $checkStmt->execute();
    $res = $checkStmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // Update password and role to admin
        $updateStmt = $conn->prepare("UPDATE users SET password=?, role='admin', status='active', verification_status='verified' WHERE id=?");
        $updateStmt->bind_param("si", $hash, $row['id']);
        if ($updateStmt->execute()) {
            $results[] = "✅ Updated existing account for <strong>" . htmlspecialchars($acc['email']) . "</strong> (Password reset to: <code>Admin@12345</code>)";
        } else {
            $results[] = "❌ Error updating " . htmlspecialchars($acc['email']) . ": " . $conn->error;
        }
    } else {
        // Insert new user
        $insertStmt = $conn->prepare("INSERT INTO users (fullname, name, username, email, password, role, status, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("ssssssss", $acc['fullname'], $acc['name'], $acc['username'], $acc['email'], $acc['password'], $acc['role'], $acc['status'], $acc['verification_status']);
        if ($insertStmt->execute()) {
            $results[] = "✅ Created new Admin account for <strong>" . htmlspecialchars($acc['email']) . "</strong> (Password: <code>Admin@12345</code>)";
        } else {
            $results[] = "❌ Error creating " . htmlspecialchars($acc['email']) . ": " . $conn->error;
        }
    }
}

// Clear all rate limit / lockout records
$conn->query("TRUNCATE TABLE rate_limits");
$results[] = "🔓 All login attempt lockouts cleared successfully!";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Account Auto-Fixer</title>
    <style>
        body { font-family: sans-serif; padding: 30px; background: #0f172a; color: #f8fafc; }
        .card { background: #1e293b; padding: 25px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3); }
        h2 { margin-top: 0; color: #38bdf8; }
        ul { padding-left: 20px; line-height: 1.8; }
        .btn { display: inline-block; background: #0284c7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; }
        .btn:hover { background: #0369a1; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔑 Admin Accounts Synchronized</h2>
    <ul>
        <?php foreach ($results as $r): ?>
            <li><?php echo $r; ?></li>
        <?php endforeach; ?>
    </ul>
    <p>You can now log in to the admin portal using:</p>
    <ul>
        <li><strong>Email:</strong> <code>consultationmanagement2025@gmail.com</code> OR <code>admin@pcms.local</code> OR Username: <code>admin</code></li>
        <li><strong>Password:</strong> <code>Admin@12345</code></li>
    </ul>
    <a href="login.php" class="btn">Go to Login Page</a>
</div>
</body>
</html>
