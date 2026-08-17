<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/notifications.php';

// Create dummy submission
$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, type, user_id, user_name, user_email, status, tracking_number) VALUES ('Barangay Street Lighting Request', 'Request for solar lights.', 'Infrastructure', 'user', 999, 'Maria Santos', 'maria@example.com', 'pending', 'TRK-TEST-NOTIF-01')");
$stmt->execute();
$id = $conn->insert_id;
$stmt->close();

echo "Created test citizen submission #{$id}\n";

// Execute decline logic directly
$reason = "Budget allocation for current fiscal year exceeds limits.";
$cStmt = $conn->prepare("SELECT title, user_name, user_email, user_id, tracking_number FROM consultations WHERE id = ? LIMIT 1");
$cStmt->bind_param('i', $id);
$cStmt->execute();
$submitter = $cStmt->get_result()->fetch_assoc();
$cStmt->close();

$uStmt = $conn->prepare("UPDATE consultations SET status = 'rejected', admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
$uStmt->bind_param('ssi', $reason, $reason, $id);
$ok = $uStmt->execute();
$uStmt->close();

$targetUserId = (int)($submitter['user_id'] ?? 0);
$cTitle = $submitter['title'] ?? 'Citizen Proposal';
$trackingNo = $submitter['tracking_number'] ?? ("CONSULT-" . str_pad($id, 6, "0", STR_PAD_LEFT));
$notifMsg = "Your consultation proposal \"{$cTitle}\" ({$trackingNo}) was reviewed and declined by the LGU Secretariat. Reason: {$reason}";

$notifId = createNotification($targetUserId, $notifMsg, 'decline');

echo "Decline status update: " . ($ok ? "SUCCESS" : "FAILED") . "\n";
echo "In-App Notification created ID: " . var_export($notifId, true) . "\n";

// Verify notification in DB
$nStmt = $conn->prepare("SELECT id, user_id, message, type, created_at FROM notifications WHERE id = ?");
$nStmt->bind_param('i', $notifId);
$nStmt->execute();
$notifRow = $nStmt->get_result()->fetch_assoc();
$nStmt->close();

echo "Notification DB Record:\n";
print_r($notifRow);
