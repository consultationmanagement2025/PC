<?php
require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['integration_conn'];
$requestId = $GLOBALS['integration_request_id'];
$client = lgu2_require_auth($conn, $requestId, ['events:write', 'hearings:write', 'registrations:write']);

$input = lgu2_json_input();
$event = $input['event'] ?? 'unknown';

lgu2_inbox_record($conn, $event, $client['source_system'], $input['external_ref'] ?? null, $input);

if ($event === 'hearing_notice' || $event === 'hearing_queue_item' || !empty($input['phms_hearing_id'])) {
    $phmsId = (int) ($input['phms_hearing_id'] ?? $input['hearing_id'] ?? 0);
    $fullName = trim((string) ($input['full_name'] ?? $input['registrant_name'] ?? 'Citizen Participant'));
    $email = trim((string) ($input['email'] ?? ''));

    if ($phmsId > 0 && $fullName !== '') {
        $stmt = $conn->prepare(
            "INSERT INTO hearing_queue (phms_hearing_id, full_name, email, external_ref, source_system, payload_json)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $jsonStr = json_encode($input);
        $extRef = (string) ($input['external_ref'] ?? '');
        $srcSys = $client['source_system'];
        $stmt->bind_param("isssss", $phmsId, $fullName, $email, $extRef, $srcSys, $jsonStr);
        $stmt->execute();
        $stmt->close();
    }
}

lgu2_log_request($conn, (int) $client['client_id'], $_SERVER['SCRIPT_NAME'], 'POST', $requestId, 200);
lgu2_json_success(['event' => $event, 'status' => 'processed']);
