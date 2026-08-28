<?php
require_once __DIR__ . '/../db.php';

echo "=== DB NAME: " . (defined('DB_NAME') ? DB_NAME : 'unknown') . " ===\n";

$res = $conn->query("SHOW TABLES");
$tables = [];
if ($res) {
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }
}
echo "Tables found: " . implode(', ', $tables) . "\n\n";

foreach (['posts', 'consultations', 'documents', 'survey_responses', 'survey_templates', 'resolution_reports', 'feedback'] as $t) {
    if (in_array($t, $tables)) {
        $r = $conn->query("SELECT COUNT(*) as cnt FROM $t");
        $row = $r ? $r->fetch_assoc() : ['cnt' => 0];
        echo "Table '$t': " . $row['cnt'] . " rows\n";
        if ($row['cnt'] > 0) {
            $sample = $conn->query("SELECT * FROM $t LIMIT 2");
            while ($s = $sample->fetch_assoc()) {
                echo "   Sample: " . json_encode($s) . "\n";
            }
        }
    } else {
        echo "Table '$t': DOES NOT EXIST\n";
    }
}
?>
