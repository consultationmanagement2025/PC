<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== AUDIT_LOGS TABLE COUNT ===
";
$res = $conn->query("SELECT COUNT(*) as total FROM audit_logs");
if ($res) {
    print_r($res->fetch_assoc());
} else {
    echo "Error: " . $conn->error . "
";
}

echo "
=== RECENT AUDIT_LOGS ENTRIES ===
";
$res2 = $conn->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 15");
if ($res2 && $res2->num_rows > 0) {
    while ($row = $res2->fetch_assoc()) {
        echo "[{$row['timestamp']}] User #{$row['admin_id']} ({$row['admin_user']}) -> Action: {$row['action']} | Entity: {$row['entity_type']}#{$row['entity_id']} | Details: {$row['details']}
";
    }
} else {
    echo "No audit_logs rows found.
";
}
?>