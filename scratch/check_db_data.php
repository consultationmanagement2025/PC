<?php
require_once __DIR__ . '/../db.php';

echo "=== DESCRIBE consultations ===\n";
$res = $conn->query("DESCRIBE consultations");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

echo "\n=== DESCRIBE feedback ===\n";
$res = $conn->query("DESCRIBE feedback");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

echo "\n=== SAMPLE FEEDBACK ROWS ===\n";
$res = $conn->query("SELECT * FROM feedback LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
