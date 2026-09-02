<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "Resetting 2 PHMS packages to 'pending' in hearing_queue...\n";
$stmt = $conn->prepare("UPDATE hearing_queue SET approval_status = 'pending', status = 'pending' WHERE queue_id IN (3, 10)");
if ($stmt && $stmt->execute()) {
    echo "SUCCESS! Reset " . $stmt->affected_rows . " PHMS packages to PENDING APPROVAL!\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
