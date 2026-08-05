<?php
/**
 * Test full 3-step LRM cURL workflow and forwardDocumentToLRS database integration
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../DATABASE/document-management.php';

echo "=== LRM INTEGRATION TEST RUNNER ===\n\n";

// 1. Test Step 1: Initiate Tracking
echo "--- Test 1: initiateLRMTracking() ---\n";
$step1 = initiateLRMTracking('consultation', 'pcms');
echo "Status: " . ($step1['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "HTTP Code: " . $step1['http_code'] . "\n";
echo "Tracking ID: " . var_export($step1['tracking_id'], true) . "\n";
echo "Raw Response: " . $step1['raw_response'] . "\n\n";

$trackingId = $step1['tracking_id'];

// 2. Test Step 2: Upload Document
echo "--- Test 2: uploadDocumentToLRM() ---\n";
$pdfFile = __DIR__ . '/../../uploads/documents/CONSULT-000001_Proposed_Waste_Segregation_Enforcement_Program_2026-07-31_05-08-35.pdf';
if (!file_exists($pdfFile)) {
    $files = glob(__DIR__ . '/../../uploads/documents/*.pdf');
    if (!empty($files)) $pdfFile = $files[0];
}

if (!file_exists($pdfFile)) {
    echo "Creating dummy PDF for upload test...\n";
    $dir = __DIR__ . '/../../uploads/documents';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $pdfFile = $dir . '/test_doc.pdf';
    file_put_contents($pdfFile, "%PDF-1.4 Test Document Content");
}

$cFile = new CURLFile($pdfFile, 'application/pdf', 'Sample_Consultation.pdf');
$uploadFields = [
    'title' => 'Sample Consultation Summary',
    'document_type' => 'consultation',
    'source_system' => 'pcms',
    'external_id' => 'PCM-EXAMPLE-001',
    'document_date' => '2026-08-03',
    'description' => 'Sample consultation summary from PCMS',
    'file' => $cFile
];

$step2 = uploadDocumentToLRM($uploadFields);
echo "Status: " . ($step2['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "HTTP Code: " . $step2['http_code'] . "\n";
echo "Raw Response: " . $step2['raw_response'] . "\n\n";

if (empty($trackingId) && is_array($step2['decoded'])) {
    $trackingId = $step2['decoded']['tracking_id'] ?? ($step2['decoded']['reference_number'] ?? null);
}

// 3. Test Step 3: Send Tracking Events
echo "--- Test 3: sendLRMTrackingEvent() ---\n";
if ($trackingId) {
    $step3 = sendLRMTrackingEvent(
        $trackingId,
        'PCM-EXAMPLE-001',
        'Transferred',
        'Transferred',
        'Ana Reyes',
        'Consultation Office',
        'Transferred to ORTS',
        ['destination' => 'orts']
    );
    echo "Status: " . ($step3['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "HTTP Code: " . $step3['http_code'] . "\n";
    echo "Raw Response: " . $step3['raw_response'] . "\n\n";
} else {
    echo "Skipped step 3 (No tracking ID)\n\n";
}

// 4. Test forwardDocumentToLRS helper with DB record
echo "--- Test 4: forwardDocumentToLRS() with DB record ---\n";
$firstDoc = $conn->query("SELECT id FROM documents LIMIT 1");
if ($firstDoc && $row = $firstDoc->fetch_assoc()) {
    $docId = (int)$row['id'];
    echo "Forwarding admin document ID #$docId...\n";
    $fwdRes = forwardDocumentToLRS($docId, 'admin', 'Test automated forward with 3-step tracking', 'Ana Reyes');
    echo "Result: " . json_encode($fwdRes, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Creating mock admin document record to test forwardDocumentToLRS()...\n";
    $conn->query("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(100),
        title VARCHAR(255),
        type VARCHAR(100),
        status VARCHAR(50),
        document_date DATE,
        description TEXT,
        file_path VARCHAR(500),
        tags VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $relFile = 'uploads/documents/sample_test.pdf';
    $fullFile = __DIR__ . '/../../' . $relFile;
    if (!file_exists($fullFile)) {
        @mkdir(dirname($fullFile), 0755, true);
        file_put_contents($fullFile, "%PDF-1.4 Mock Document Content");
    }
    
    $stmt = $conn->prepare("INSERT INTO documents (reference, title, type, status, document_date, description, file_path) VALUES ('PCM-TEST-001', 'Sample Consultation Summary', 'consultation', 'pending', CURDATE(), 'Sample consultation summary from PCMS', ?)");
    $stmt->bind_param('s', $relFile);
    $stmt->execute();
    $mockId = $conn->insert_id;
    $stmt->close();
    
    echo "Created mock document ID #$mockId. Forwarding to LRM...\n";
    $fwdRes = forwardDocumentToLRS($mockId, 'admin', 'Sample consultation summary from PCMS', 'Ana Reyes');
    echo "Result: " . json_encode($fwdRes, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== LRM INTEGRATION TEST COMPLETE ===\n";
