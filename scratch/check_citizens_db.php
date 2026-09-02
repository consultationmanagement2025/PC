<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== CITIZEN USERS IN DATABASE (users table) ===
";
$res1 = $conn->query("SELECT id, fullname, email, role, created_at FROM users WHERE LOWER(role) IN ('citizen', 'user') OR role IS NULL OR role = ''");
if ($res1 && $res1->num_rows > 0) {
    while ($r = $res1->fetch_assoc()) {
        echo "ID: {$r['id']} | Name: {$r['fullname']} | Email: {$r['email']} | Role: {$r['role']} | Created: {$r['created_at']}
";
    }
} else {
    echo "No citizen rows found in users table.
";
}

echo "
=== CITIZENS FROM FEEDBACK TABLE ===
";
$res2 = $conn->query("SELECT DISTINCT guest_name, guest_email FROM feedback");
if ($res2 && $res2->num_rows > 0) {
    while ($r = $res2->fetch_assoc()) {
        echo "Name: {$r['guest_name']} | Email: {$r['guest_email']}
";
    }
}
?>