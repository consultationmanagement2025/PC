<?php
require_once 'db.php';
$conn = dbConnect();

$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_row()) {
    $tbl = $t[0];
    $cols = $conn->query("SHOW COLUMNS FROM `$tbl`");
    $textCols = [];
    while ($c = $cols->fetch_assoc()) {
        if (preg_match('/(varchar|text|char|blob)/i', $c['Type'])) {
            $textCols[] = "`" . $c['Field'] . "`";
        }
    }
    if (!empty($textCols)) {
        $where = implode(" LIKE '%Parks%' OR ", $textCols) . " LIKE '%Parks%' OR " . implode(" LIKE '%playgrounds%' OR ", $textCols) . " LIKE '%playgrounds%' OR " . implode(" LIKE '%Residents%' OR ", $textCols) . " LIKE '%Residents%'";
        $check = $conn->query("SELECT * FROM `$tbl` WHERE $where");
        if ($check && $check->num_rows > 0) {
            echo "FOUND IN Table '$tbl' ({$check->num_rows} rows):\n";
            while ($r = $check->fetch_assoc()) {
                print_r($r);
            }
        }
    }
}
