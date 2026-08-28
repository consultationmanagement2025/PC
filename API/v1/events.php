<?php
/**
 * Inbound Webhook / Event Ingestion Endpoint for PCMS Integration (LGU-2 standard)
 * Handles hearing_registrant, hearing_notice, hearing_queue_item, and PHMS integration payloads.
 */
require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['integration_conn'];
$requestId = $GLOBALS['integration_request_id'];

// Requires sync:write or event scopes
$client = lgu2_require_auth($conn, $requestId, []);

$input = lgu2_read_json_body();
if (empty($input) && isset($_POST['payload_json'])) {
    $input = json_decode($_POST['payload_json'], true) ?: [];
}

$event = (string) ($input['event'] ?? $input['event_type'] ?? 'unknown');
$srcSys = !empty($client['source_system']) ? $client['source_system'] : (string) ($input['source_system'] ?? 'PHMS');
$extRef = (string) ($input['external_ref'] ?? $input['ref'] ?? '');

$inboxId = lgu2_store_inbox($conn, $event, $srcSys, $extRef !== '' ? $extRef : null, $input);

$phmsId = (int) ($input['phms_hearing_id'] ?? $input['hearing_id'] ?? $input['public_hearing_id'] ?? 0);
$regId = (int) ($input['phms_registration_id'] ?? $input['registration_id'] ?? 0);
$fullName = trim((string) ($input['full_name'] ?? $input['hearing_title'] ?? $input['title'] ?? 'Public Registrant'));
$email = trim((string) ($input['email'] ?? ''));
$consultationId = (int) ($input['consultation_id'] ?? 0);
$status = (string) ($input['status'] ?? 'queued');

if ($phmsId > 0 || $regId > 0) {
    $jsonStr = json_encode($input);
    
    // Check by registration ID first if present, otherwise by hearing ID
    $existing = null;
    if ($regId > 0) {
        $chk = $conn->prepare("SELECT queue_id, payload_json FROM hearing_queue WHERE phms_registration_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("i", $regId);
            $chk->execute();
            $res = $chk->get_result();
            $existing = $res ? $res->fetch_assoc() : null;
            $chk->close();
        }
    }
    
    if (!$existing && $phmsId > 0 && $event !== 'hearing_registrant') {
        $chk = $conn->prepare("SELECT queue_id, payload_json FROM hearing_queue WHERE phms_hearing_id = ? AND (phms_registration_id IS NULL OR phms_registration_id = 0) LIMIT 1");
        if ($chk) {
            $chk->bind_param("i", $phmsId);
            $chk->execute();
            $res = $chk->get_result();
            $existing = $res ? $res->fetch_assoc() : null;
            $chk->close();
        }
    }

    if ($existing) {
        $queueId = (int) $existing['queue_id'];
        $upd = $conn->prepare("UPDATE hearing_queue SET phms_hearing_id = ?, phms_registration_id = ?, full_name = ?, email = ?, status = ?, external_ref = ?, source_system = ?, consultation_id = ?, payload_json = ? WHERE queue_id = ?");
        if ($upd) {
            $upd->bind_param("iisssssisi", $phmsId, $regId, $fullName, $email, $status, $extRef, $srcSys, $consultationId, $jsonStr, $queueId);
            $upd->execute();
            $upd->close();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO hearing_queue (phms_hearing_id, phms_registration_id, full_name, email, status, external_ref, source_system, consultation_id, payload_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($ins) {
            $ins->bind_param("iisssssis", $phmsId, $regId, $fullName, $email, $status, $extRef, $srcSys, $consultationId, $jsonStr);
            $ins->execute();
            $ins->close();
        }
    }
}

lgu2_log_request($conn, (int) $client['client_id'], $_SERVER['SCRIPT_NAME'] ?? '/API/v1/events.php', 'POST', $requestId, 200);


// Create system notification for ingested PHMS event
if (file_exists(__DIR__ . '/../../DATABASE/notifications.php')) {
    require_once __DIR__ . '/../../DATABASE/notifications.php';
} elseif (file_exists(__DIR__ . '/../DATABASE/notifications.php')) {
    require_once __DIR__ . '/../DATABASE/notifications.php';
}
if (function_exists('createNotification')) {
    $notifTitle = !empty($fullName) ? $fullName : ("PHMS Hearing #" . $phmsId);
    $notifMsg = "🏢 New PHMS Citizen Hearing Feedback Received: '{$notifTitle}' (Event: {$event})";
    createNotification(0, $notifMsg, 'phms_feedback');
}

echo json_encode([
    'success' => true,
    'message' => 'Event ingested successfully into PCMS',
    'event' => $event,
    'request_id' => $requestId,
    'inbox_id' => $inboxId,
    'phms_hearing_id' => $phmsId,
    'phms_registration_id' => $regId
]);
?>
