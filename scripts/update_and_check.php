<?php
require __DIR__ . '/../db.php';

$id = 6;
$sql = "UPDATE users SET role = 'staff' WHERE id = $id";
$res = $conn->query($sql);
if ($res === false) {
    echo "Query error: " . $conn->error . PHP_EOL;
} else {
    echo "Query OK, affected_rows: " . $conn->affected_rows . PHP_EOL;
}

// Fetch row
$r = $conn->query("SELECT id, fullname, email, HEX(role) as role_hex, role, LENGTH(role) as role_len FROM users WHERE id = $id");
if ($r) {
    $row = $r->fetch_assoc();
    print_r($row);
} else {
    echo "Select failed: " . $conn->error . PHP_EOL;
}

?>