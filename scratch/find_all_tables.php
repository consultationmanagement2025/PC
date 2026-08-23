<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SHOW TABLES");
$tables = [];
while ($r = $res->fetch_array()) {
    $tables[] = $r[0];
}

echo "ALL TABLES IN DB (" . count($tables) . "):\n";
print_r($tables);

echo "\nSEARCHING ALL TABLES FOR 'Aljan' OR ID 8:\n";

foreach ($tables as $tbl) {
    // Describe columns
    $colsRes = $conn->query("DESCRIBE `$tbl`");
    $cols = [];
    while ($c = $colsRes->fetch_assoc()) {
        $cols[] = "`" . $c['Field'] . "`";
    }
    
    if (empty($cols)) continue;

    $concatCols = "CONCAT_WS(' ', " . implode(', ', $cols) . ")";
    $sql = "SELECT * FROM `$tbl` WHERE $concatCols LIKE '%Aljan%' OR $concatCols LIKE '%CONSULT-000008%' OR $concatCols LIKE '%000008%'";
    $searchRes = $conn->query($sql);
    if ($searchRes && $searchRes->num_rows > 0) {
        echo "\n>>> MATCH IN TABLE `$tbl` (" . $searchRes->num_rows . " rows):\n";
        while ($row = $searchRes->fetch_assoc()) {
            print_r($row);
        }
    }
}
