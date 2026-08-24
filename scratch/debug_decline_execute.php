<?php
require_once __DIR__ . '/../db.php';
$new_status = 'declined';
$reason = 'Test decline reason from script';
$consultation_id = 1;

$stmt = $conn->prepare("UPDATE consultations SET status = ?, admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('sssi', $new_status, $reason, $reason, $consultation_id);
    $ok = $stmt->execute();
    echo "EXECUTE RESULT: " . var_export($ok, true) . "\n";
    if (!$ok) {
        echo "STMT ERROR: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    echo "PREPARE ERROR: " . $conn->error . "\n";
}
