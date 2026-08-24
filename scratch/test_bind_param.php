<?php
require_once __DIR__ . '/../db.php';
$new_status = 'declined';
$reason1 = 'Test decline reason 1';
$reason2 = 'Test decline reason 2';
$consultation_id = 1;

$stmt = $conn->prepare("UPDATE consultations SET status = ?, admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('sssi', $new_status, $reason1, $reason2, $consultation_id);
    $ok = $stmt->execute();
    echo "Separate vars bind_param result: " . var_export($ok, true) . "\n";
    if (!$ok) echo "Error: " . $stmt->error . "\n";
    $stmt->close();
}

$stmt2 = $conn->prepare("UPDATE consultations SET status = ?, admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
if ($stmt2) {
    $stmt2->bind_param('sssi', $new_status, $reason1, $reason1, $consultation_id);
    $ok2 = $stmt2->execute();
    echo "Same var twice bind_param result: " . var_export($ok2, true) . "\n";
    if (!$ok2) echo "Error: " . $stmt2->error . "\n";
    $stmt2->close();
}
