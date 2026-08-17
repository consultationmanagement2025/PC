<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'superadmin';

$_GET['action'] = 'decline_submission';

// Mock php://input
class BufferStream {
    public function stream_open() { return true; }
    public function stream_read() { return '{"id": 1, "status": "rejected", "reason": "Test reason"}'; }
    public function stream_eof() { return true; }
    public function stream_stat() { return []; }
}

require_once __DIR__ . '/../db.php';
$stmt = $conn->prepare("UPDATE consultations SET status = 'rejected', admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = 1");
$reason = "Test reason";
$stmt->bind_param('ss', $reason, $reason);
$ok = $stmt->execute();
$stmt->close();

$json = json_encode([
    'success' => $ok,
    'message' => 'Consultation submission declined and submitter notified.',
    'email_sent' => false
]);

echo "JSON String Output:\n{$json}\n";
$decoded = json_decode($json, true);
echo "Decoded Array:\n";
print_r($decoded);
echo "success property value: " . var_export($decoded['success'], true) . "\n";
echo "truthy check: " . ($decoded['success'] ? "TRUE" : "FALSE") . "\n";
