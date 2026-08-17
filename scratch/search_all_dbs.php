<?php
require_once 'db.php';
$conn = dbConnect();
if (!$conn) {
    die("Database connection failed.");
}

echo "=== SHOW DATABASES ===\n";
$dbRes = $conn->query("SHOW DATABASES");
$databases = [];
if ($dbRes) {
    while ($d = $dbRes->fetch_row()) {
        $dbName = $d[0];
        if (in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'])) continue;
        $databases[] = $dbName;
        echo "Found Database: $dbName\n";
    }
}

echo "\n=== SEARCHING ALL DATABASES FOR 'Parks' or 'Improvement' or 'General' ===\n";
foreach ($databases as $db) {
    $conn->select_db($db);
    $tables = $conn->query("SHOW TABLES");
    if (!$tables) continue;
    while ($t = $tables->fetch_row()) {
        $tbl = $t[0];
        $cols = $conn->query("SHOW COLUMNS FROM `$tbl`");
        if (!$cols) continue;
        $textCols = [];
        while ($c = $cols->fetch_assoc()) {
            if (preg_match('/(varchar|text|char|blob)/i', $c['Type'])) {
                $textCols[] = "`" . $c['Field'] . "`";
            }
        }
        if (!empty($textCols)) {
            $where = implode(" LIKE '%Parks%' OR ", $textCols) . " LIKE '%Parks%' OR " . implode(" LIKE '%Improvement%' OR ", $textCols) . " LIKE '%Improvement%'";
            $check = $conn->query("SELECT * FROM `$tbl` WHERE $where");
            if ($check && $check->num_rows > 0) {
                echo "--> FOUND in DB '$db' -> Table '$tbl' ({$check->num_rows} rows):\n";
                while ($r = $check->fetch_assoc()) {
                    echo "  ID: " . ($r['id'] ?? 'N/A') . " | Title: " . ($r['title'] ?? $r['name'] ?? $r['subject'] ?? 'N/A') . "\n";
                    print_r($r);
                    echo "-------------------------\n";
                }
            }
        }
    }
}
