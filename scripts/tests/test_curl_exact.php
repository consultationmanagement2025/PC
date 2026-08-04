<?php
// Test exact cURL payload matching user prompt

$fullPath = __DIR__ . '/../../uploads/documents/CONSULT-000001_Proposed_Waste_Segregation_Enforcement_Program_2026-07-31_05-08-35.pdf';
if (!file_exists($fullPath)) {
    $files = glob(__DIR__ . '/../../uploads/documents/*.pdf');
    if (!empty($files)) $fullPath = $files[0];
}

echo "Testing file: " . $fullPath . "\n";

$lrsUrl = 'https://llrm.spvalenzuela.com/modules/integration/api/receive_document.php';
$apiKey = 'pcm_f9e0185dca4546c83a1c5afa187ff10f';

$cFile = new CURLFile($fullPath, 'application/pdf', 'document.pdf');

$postFields = [
    'title' => 'Sample Consultation Summary',
    'document_type' => 'consultation_summary',
    'source_system' => 'pcms',
    'external_id' => 'PCM-EXAMPLE-001',
    'document_date' => '2026-07-23',
    'description' => 'Sample consultation summary from PCMS',
    'file' => $cFile
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $lrsUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$responseBody}\n";
