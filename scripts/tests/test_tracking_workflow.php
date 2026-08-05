<?php
// Test 3-step workflow for LRM API integration

$lrmBase = 'https://llrm.spvalenzuela.com';
$apiKey = 'pcm_f9e0185dca4546c83a1c5afa187ff10f';

echo "=== STEP 1: Initiate Tracking ===\n";
$ch = curl_init($lrmBase . '/modules/document-tracking/api/initiate.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'document_type' => 'consultation',
        'source_system' => 'pcms'
    ]),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 15
]);
$res1 = curl_exec($ch);
$http1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Step 1 HTTP Code: $http1\n";
echo "Step 1 Response: $res1\n\n";

$data1 = json_decode($res1, true);
$trackingId = $data1['tracking_id'] ?? ($data1['data']['tracking_id'] ?? ($data1['tracking_number'] ?? ($data1['id'] ?? null)));
echo "Extracted Tracking ID: " . var_export($trackingId, true) . "\n\n";

// Step 2: Upload Document
echo "=== STEP 2: Upload Document ===\n";
$pdfFile = __DIR__ . '/../../uploads/documents/CONSULT-000001_Proposed_Waste_Segregation_Enforcement_Program_2026-07-31_05-08-35.pdf';
if (!file_exists($pdfFile)) {
    $files = glob(__DIR__ . '/../../uploads/documents/*.pdf');
    if (!empty($files)) $pdfFile = $files[0];
}

if (file_exists($pdfFile)) {
    $cFile = new CURLFile($pdfFile, 'application/pdf', 'document.pdf');
    $postFields = [
        'title' => 'Sample Consultation Summary',
        'document_type' => 'consultation',
        'source_system' => 'pcms',
        'external_id' => 'PCM-EXAMPLE-001',
        'document_date' => date('Y-m-d'),
        'description' => 'Sample consultation summary from PCMS',
        'file' => $cFile
    ];

    $ch = curl_init($lrmBase . '/modules/integration/api/receive_document.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $res2 = curl_exec($ch);
    $http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Step 2 HTTP Code: $http2\n";
    echo "Step 2 Response: $res2\n\n";
} else {
    echo "No PDF file found for Step 2 testing\n\n";
}

// Step 3: Send Tracking Events
echo "=== STEP 3: Send Tracking Events ===\n";
if ($trackingId) {
    $ch = curl_init($lrmBase . '/modules/document-tracking/api/document-events.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'tracking_id' => $trackingId,
            'source_system' => 'pcms',
            'local_document_id' => 'PCM-EXAMPLE-001',
            'activity' => 'Transferred',
            'status' => 'Transferred',
            'performed_by' => 'Ana Reyes',
            'department' => 'Consultation Office',
            'remarks' => 'Transferred to LRM',
            'timestamp' => date('c'),
            'metadata' => ['destination' => 'lrm']
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $res3 = curl_exec($ch);
    $http3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Step 3 HTTP Code: $http3\n";
    echo "Step 3 Response: $res3\n";
} else {
    echo "Skipping Step 3 because trackingId was not returned in Step 1\n";
}
