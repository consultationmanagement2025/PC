<?php
require_once __DIR__ . '/../db.php';
$id = 1;
$reason = "Test decline reason";

$stmt = $conn->prepare("UPDATE consultations SET status = 'rejected', admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('ssi', $reason, $reason, $id);
    $ok = $stmt->execute();
    echo "execute() return value: " . var_export($ok, true) . "\n";
    if (!$ok) {
        echo "stmt error: " . $stmt->error . "\n";
        echo "conn error: " . $conn->error . "\n";
    }
    $stmt->close();
} else {
    echo "prepare failed: " . $conn->error . "\n";
}
