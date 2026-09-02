<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== DATABASE TABLE COUNTS & DATA ===\n";

$tables = ['feedback', 'consultation_votes', 'consultation_guest_votes', 'consultations'];

foreach ($tables as $t) {
    $res = @$conn->query("SELECT COUNT(*) as cnt FROM `$t`");
    if ($res) {
        $row = $res->fetch_assoc();
        echo "Table '$t' count: " . $row['cnt'] . "\n";
        
        // Show sample rows
        if ($row['cnt'] > 0) {
            $sample = $conn->query("SELECT * FROM `$t` LIMIT 5");
            while ($sRow = $sample->fetch_assoc()) {
                print_r($sRow);
            }
        }
    } else {
        echo "Table '$t': " . $conn->error . "\n";
    }
    echo "----------------------------------------\n";
}
?>