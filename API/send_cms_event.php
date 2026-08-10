<?php
/**
 * CMS Outbound Integration API for PCMS
 * Sends consultation & feedback events to the CMS Live API Endpoint
 * Target Endpoint: https://cms.spvalenzuela.com/api/v1/events.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Read incoming request data (if any) to allow dynamic parameters
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?: $_REQUEST;

// Dynamic payload with exact default fallback values provided for classmate's CMS integration
$payload = [
    "source_system"   => trim($inputData['source_system'] ?? 'PCMS'),
    "event"           => trim($inputData['event'] ?? 'consultation_feedback'),
    "consultation_id" => (int)($inputData['consultation_id'] ?? 12),
    "committee_id"    => isset($inputData['committee_id']) ? (int)$inputData['committee_id'] : 3,
    "committee_name"  => trim($inputData['committee_name'] ?? 'Committee on Finance'),
    "title"           => trim($inputData['title'] ?? 'Public Consultation on Ordinance No. 001'),
    "description"     => trim($inputData['description'] ?? 'Consultation feedback requiring committee review.'),
    "referral_date"   => trim($inputData['referral_date'] ?? '2026-08-07'),
    "notes"           => trim($inputData['notes'] ?? 'Referred for committee hearing and action.')
];

// Exact CMS Live API Endpoint
$cmsApiUrl = "https://cms.spvalenzuela.com/api/v1/events.php";

$ch = curl_init($cmsApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer cms_live_9c1e5a7b3f8042d6b8e2a4c7f1d90638'
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
        'message'    => 'cURL error when communicating with CMS endpoint: ' . $curlError,
        'payload'    => $payload,
        'endpoint'   => $cmsApiUrl
    ]);
    exit;
}

// Return structured JSON response containing CMS response or raw output
$decodedResponse = json_decode($response, true);

echo json_encode([
    'success'      => ($httpCode >= 200 && $httpCode < 300),
    'http_code'    => $httpCode,
    'cms_response' => $decodedResponse !== null ? $decodedResponse : $response,
    'payload_sent' => $payload,
    'endpoint'     => $cmsApiUrl
], JSON_PRETTY_PRINT);

