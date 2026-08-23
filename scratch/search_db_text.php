<?php
require_once __DIR__ . '/../db.php';

echo "=== SEARCHING ALL DB TABLES FOR 'Legal' OR 'Assistance' ===\n";
$tables = $conn->query("SHOW TABLES");
while ($tRow = $tables->fetch_row()) {
    $tbl = $tRow[0];
    $colsRes = $conn->query("SHOW COLUMNS FROM `$tbl`");
    $textCols = [];
    while ($c = $colsRes->fetch_assoc()) {
        if (strpos($c['Type'], 'varchar') !== false || strpos($c['Type'], 'text') !== false) {
            $textCols[] = "`{$c['Field']}`";
        }
    }
    if (!empty($textCols)) {
        $whereClauses = [];
        foreach ($textCols as $col) {
            $whereClauses[] = "$col LIKE '%Legal%' OR $col LIKE '%Assistance%'";
        }
        $where = implode(" OR ", $whereClauses);
        $q = "SELECT * FROM `$tbl` WHERE $where LIMIT 5";
        $r = $conn->query($q);
        if ($r && $r->num_rows > 0) {
            echo "Found in Table: $tbl\n";
            while ($row = $r->fetch_assoc()) {
                echo "   " . json_encode($row) . "\n";
            }
        }
    }
}
