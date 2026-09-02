<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== HEARING_QUEUE TABLE CONTENT ===\n";
$res = $conn->query("SELECT * FROM hearing_queue");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No rows found in hearing_queue or error: " . $conn->error . "\n";
}
?>