<?php
require_once __DIR__ . '/../db.php';

$tables = $conn->query("SHOW TABLES");
echo "=== DATABASE TABLE COUNTS ===\n";
while ($row = $tables->fetch_array()) {
    $tableName = $row[0];
    $countRes = $conn->query("SELECT COUNT(*) as total FROM `$tableName`");
    $total = $countRes ? $countRes->fetch_assoc()['total'] : 0;
    echo sprintf("%-35s : %d rows\n", $tableName, $total);
}
