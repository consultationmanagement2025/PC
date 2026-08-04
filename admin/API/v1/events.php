<?php
/**
 * Inbound Webhook / Event Ingestion Endpoint for PCMS Integration (LGU2 standard)
 * Handles hearing_notice, hearing_queue_item, citizen_feedback, and PHMS integration payloads.
 */
require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['integration_conn'];
$requestId = $GLOBALS['integration_request_id'];
$client = lgu2_require_auth($conn, $requestId, ['events:write', 'hearings:write', 'registrations:write']);

$input = lgu2_json_input();
if (empty($input) && isset($_POST['payload_json'])) {
    $input = json_decode($_POST['payload_json'], true) ?: [];
}

$event = $input['event'] ?? $input['event_type'] ?? 'unknown';
$srcSys = !empty($client['source_system']) ? $client['source_system'] : 'PHMS';
$extRef = (string) ($input['external_ref'] ?? $input['ref'] ?? '');

lgu2_inbox_record($conn, $event, $srcSys, $extRef !== '' ? $extRef : null, $input);

$phmsId = (int) ($input['phms_hearing_id'] ?? $input['hearing_id'] ?? $input['id'] ?? 0);
$fullName = trim((string) ($input['hearing_title'] ?? $input['full_name'] ?? $input['title'] ?? $input['registrant_name'] ?? 'Public Hearing'));
$email = trim((string) ($input['email'] ?? 'phms-integration@valenzuela.gov.ph'));

if ($phmsId > 0) {
    $chk = $conn->prepare("SELECT queue_id, payload_json FROM hearing_queue WHERE phms_hearing_id = ?");
    if ($chk) {
        $chk->bind_param("i", $phmsId);
        $chk->execute();
        $res = $chk->get_result();
        $existing = $res ? $res->fetch_assoc() : null;
        $chk->close();

        if ($existing) {
            $oldPayload = json_decode($existing['payload_json'] ?? '[]', true) ?: [];
            $mergedPayload = array_merge($oldPayload, $input);

            if (!empty($input['citizen_responses'])) {
                $mergedPayload['citizen_responses'] = $input['citizen_responses'];
            }
            if (!empty($input['citizen_feedback'])) {
                $mergedPayload['citizen_feedback'] = $input['citizen_feedback'];
            }

            $jsonStr = json_encode($mergedPayload);
            $upd = $conn->prepare("UPDATE hearing_queue SET full_name = ?, email = ?, external_ref = ?, source_system = ?, payload_json = ?, updated_at = NOW() WHERE phms_hearing_id = ?");
            if ($upd) {
                $upd->bind_param("sssssi", $fullName, $email, $extRef, $srcSys, $jsonStr, $phmsId);
                $upd->execute();
                $upd->close();
            }
        } else {
            $jsonStr = json_encode($input);
            $ins = $conn->prepare("INSERT INTO hearing_queue (phms_hearing_id, full_name, email, external_ref, source_system, payload_json) VALUES (?, ?, ?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param("isssss", $phmsId, $fullName, $email, $extRef, $srcSys, $jsonStr);
                $ins->execute();
                $ins->close();
            }
        }
    }
}

lgu2_log_request($conn, (int) $client['client_id'], $_SERVER['SCRIPT_NAME'] ?? '/API/v1/events.php', 'POST', $requestId, 200);

echo json_encode([
    'success' => true,
    'message' => 'Event ingested and synchronized with hearing queue successfully',
    'request_id' => $requestId,
    'phms_hearing_id' => $phmsId
]);
?>
