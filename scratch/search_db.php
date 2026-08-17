<?php
require 'db.php';

echo "--- TABLES IN DATABASE ---\n";
$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_row()) {
    echo $t[0] . "\n";
}

echo "\n--- SEARCHING FOR 'Parks' IN ALL TABLES ---\n";
$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_row()) {
    $tbl = $t[0];
    $cols = $conn->query("SHOW COLUMNS FROM `$tbl`");
    $textCols = [];
    while ($c = $cols->fetch_assoc()) {
        if (strpos($c['Type'], 'varchar') !== false || strpos($c['Type'], 'text') !== false) {
            $textCols[] = "`" . $c['Field'] . "`";
        }
    }
    if (!empty($textCols)) {
        $where = implode(" LIKE '%Parks%' OR ", $textCols) . " LIKE '%Parks%'";
        $check = $conn->query("SELECT * FROM `$tbl` WHERE $where");
        if ($check && $check->num_rows > 0) {
            echo "Found in $tbl ({$check->num_rows} rows):\n";
            while ($r = $check->fetch_assoc()) {
                print_r($r);
            }
        }
    }
}
