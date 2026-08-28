<?php
/**
 * ORTS Outbound Integration API for PCMS
 * Sends events to the ORTS Live API Endpoint
 * Target Endpoint: https://ort.spvalenzuela.com/api/v1/events.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Source-System');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include ORTS integration utility
if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
    require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
} elseif (file_exists(__DIR__ . '/UTILS/orts_integration_utils.php')) {
    require_once __DIR__ . '/UTILS/orts_integration_utils.php';
}

// Read incoming request data (if any) to allow dynamic parameters
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?: $_REQUEST;

// Dynamic payload with exact fallback values provided for ORTS event integration
$payload = [
    "event"          => trim($inputData['event'] ?? 'public_feedback_received'),
    "document_id"    => (int)($inputData['document_id'] ?? 1),
    "notes"          => trim($inputData['notes'] ?? 'Test feedback'),
    "submitter_name" => trim($inputData['submitter_name'] ?? 'Test Citizen'),
    "feedback_type"  => trim($inputData['feedback_type'] ?? 'suggestion'),
    "source_system"  => trim($inputData['source_system'] ?? 'PCMS')
];

// Include optional extended fields if provided in request
foreach (['reference_number', 'tracking_number', 'title', 'description', 'committee', 'location', 'submission_counts', 'ai_brief'] as $key) {
    if (isset($inputData[$key])) {
        $payload[$key] = $inputData[$key];
    }
}

if (function_exists('sendOrtsEvent')) {
    $res = sendOrtsEvent($payload);
    http_response_code(!empty($res['http_code']) && $res['http_code'] > 0 ? $res['http_code'] : 200);
    echo json_encode($res, JSON_PRETTY_PRINT);
    exit;
}

// Direct cURL execution fallback
$ortsApiUrl = "https://ort.spvalenzuela.com/api/v1/events.php";
$token = "pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328";

$ch = curl_init($ortsApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'X-Source-System: PCMS'
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 15
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'success'    => false,
        'message'    => 'cURL error when communicating with ORTS endpoint: ' . $curlError,
        'payload'    => $payload,
        'endpoint'   => $ortsApiUrl
    ]);
    exit;
}

$decodedResponse = json_decode($response, true);

echo json_encode([
    'success'       => ($httpCode >= 200 && $httpCode < 300),
    'http_code'     => $httpCode,
    'orts_response' => $decodedResponse !== null ? $decodedResponse : $response,
    'payload_sent'  => $payload,
    'endpoint'      => $ortsApiUrl
], JSON_PRETTY_PRINT);
