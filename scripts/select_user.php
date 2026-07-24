<?php
require __DIR__ . '/../db.php';

$email = 'samplestaff01@gmail.com';
$stmt = $conn->prepare("SELECT id, fullname, email, role, password FROM users WHERE email LIKE ?");
$like = "%" . $email . "%";
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Fullname: " . $row['fullname'] . "\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Role: " . var_export($row['role'], true) . "\n";
    echo "Password hash: " . substr($row['password'],0,40) . "...\n";
    echo "----\n";
}
$stmt->close();

?>