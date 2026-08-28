<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';

$res1 = $conn->query("SELECT COUNT(*) FROM consultations");
echo "Consultations DB Count: " . ($res1 ? $res1->fetch_row()[0] : 'Error: ' . $conn->error) . "\n";

$res2 = $conn->query("SELECT COUNT(*) FROM feedback");
echo "Feedback DB Count: " . ($res2 ? $res2->fetch_row()[0] : 'Error: ' . $conn->error) . "\n";

$res3 = $conn->query("SELECT COUNT(*) FROM hearing_queue");
echo "Hearing Queue DB Count: " . ($res3 ? $res3->fetch_row()[0] : 'Error: ' . $conn->error) . "\n";

$res4 = $conn->query("SELECT status, COUNT(*) FROM consultations GROUP BY status");
if ($res4) {
    while ($row = $res4->fetch_assoc()) {
        echo "Consultation status [" . $row['status'] . "]: " . $row['COUNT(*)'] . "\n";
    }
}
