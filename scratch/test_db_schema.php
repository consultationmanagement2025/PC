<?php
require_once __DIR__ . '/../db.php';

echo "=== USERS TABLE COLUMNS ===\n";
$r = $conn->query("DESCRIBE users");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | Null:" . $row['Null'] . " | Default:" . var_export($row['Default'], true) . "\n";
    }
} else {
    echo "DESCRIBE users error: " . $conn->error . "\n";
}

echo "\n=== USERS COUNT BY ROLE ===\n";
$r2 = $conn->query("SELECT role, status, COUNT(*) as cnt FROM users GROUP BY role, status");
if ($r2) {
    while ($row = $r2->fetch_assoc()) {
        echo "Role: " . var_export($row['role'], true) . " | Status: " . var_export($row['status'], true) . " | Count: " . $row['cnt'] . "\n";
    }
} else {
    echo "Group count error: " . $conn->error . "\n";
}

echo "\n=== SAMPLE USERS (Last 10) ===\n";
$r3 = $conn->query("SELECT id, fullname, name, username, email, role, status, created_at FROM users ORDER BY id DESC LIMIT 10");
if ($r3) {
    while ($row = $r3->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Select sample error: " . $conn->error . "\n";
}
