<?php
require __DIR__ . '/../db.php';

// 1) Alter the users.role enum to include 'staff' and 'barangay staff'
$alter = "ALTER TABLE users MODIFY role ENUM('admin','super admin','citizen','staff','barangay staff') NOT NULL DEFAULT 'citizen'";
if ($conn->query($alter) === TRUE) {
    echo "Altered role enum successfully\n";
} else {
    echo "Alter enum failed: " . $conn->error . "\n";
}

// 2) Update the specific user to 'staff'
$email = 'samplestaff01@gmail.com';
$update = $conn->prepare("UPDATE users SET role = 'staff' WHERE email = ?");
if (!$update) {
    echo "Prepare failed: " . $conn->error . "\n";
    exit(1);
}
$update->bind_param('s', $email);
if ($update->execute()) {
    echo "Updated rows: " . $update->affected_rows . "\n";
} else {
    echo "Update failed: " . $update->error . "\n";
}
$update->close();

// 3) Show current row
$r = $conn->prepare("SELECT id, fullname, email, role FROM users WHERE email = ?");
$r->bind_param('s', $email);
$r->execute();
$res = $r->get_result();
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Fullname: " . $row['fullname'] . "\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Role: " . $row['role'] . "\n";
}
$r->close();

?>