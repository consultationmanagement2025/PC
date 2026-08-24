<?php
require_once __DIR__ . '/../db.php';

echo "=== CONSULTATIONS TABLE COLUMNS ===\n";
$res = $conn->query('DESCRIBE consultations');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\n=== CONSULTATION ID 1 DATA ===\n";
$res1 = $conn->query('SELECT * FROM consultations WHERE id = 1');
if ($res1 && $row1 = $res1->fetch_assoc()) {
    print_r($row1);
} else {
    echo "ID 1 not found\n";
}

echo "\n=== USERS TABLE COLUMNS & DATA SAMPLE ===\n";
$resU = $conn->query('SELECT id, name, fullname, email, role FROM users LIMIT 10');
if ($resU) {
    while ($u = $resU->fetch_assoc()) {
        print_r($u);
    }
}
