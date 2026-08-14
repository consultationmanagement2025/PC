<?php
/**
 * ACK Receiver Endpoint for PCMS Integration (LGU-2 standard)
 * Processes delivery confirmations/rejections from peer systems.
 */
require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['integration_conn'];
$requestId = $GLOBALS['integration_request_id'];

// Requires minimum sync:write scope
$client = lgu2_require_auth($conn, $requestId, ['sync:write']);

$input = lgu2_json_input();
if (empty($input) && isset($_POST['payload_json'])) { 
    $input = json_decode($_POST['payload_json'], true) ?: [];
}

$ackRequestId = (string) ($input['request_id'] ?? '');
$status = (string) ($input['status'] ?? 'accepted');
$sourceSystem = !empty($client['source_system']) ? $client['source_system'] : (string) ($input['source_system'] ?? 'UNKNOWN');
$rejectionCodes = isset($input['rejection_codes']) ? (is_array($input['rejection_codes']) ? json_encode($input['rejection_codes']) : (string) $input['rejection_codes']) : null;
$responseJson = json_encode($input);

if (!empty($ackRequestId)) {
    $stmt = $conn->prepare("UPDATE integration_outbox SET status = ?, response_json = ?, rejection_codes = ?, acked_at = CURRENT_TIMESTAMP WHERE request_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssss", $status, $responseJson, $rejectionCodes, $ackRequestId);
        $stmt->execute();
        $stmt->close();
    }
}

lgu2_log_request($conn, (int) $client['client_id'], $_SERVER['SCRIPT_NAME'] ?? '/API/v1/ack.php', 'POST', $requestId, 200);

echo json_encode([
    'success' => true,
    'message' => 'ACK recorded successfully',
    'request_id' => $requestId,
    'acked_request_id' => $ackRequestId,
    'status' => $status
]);
?>
