<?php
require 'db.php';
require 'DATABASE/document-management.php';

echo "=== Registering All Existing Consultation PDFs ===\n";

// Get all consultations that exist
$result = $conn->query("SELECT id, title FROM consultations ORDER BY id ASC");
$consultations = [];
$consultationIds = [];
while ($row = $result->fetch_assoc()) {
    $consultations[] = $row;
    $consultationIds[] = $row['id'];
}

echo "Found consultations: " . implode(', ', $consultationIds) . "\n\n";

$uploadDir = __DIR__ . '/uploads/documents/';
$registered = 0;
$skipped = 0;
$orphaned = 0;

// Get all PDF files on disk
$allPdfs = glob($uploadDir . '*.pdf');

foreach ($allPdfs as $pdfPath) {
    $filename = basename($pdfPath);
    
    // Try to extract consultation ID from filename
    $consultation_id = null;
    
    // Try pattern: CONSULT-000001_...
    if (preg_match('/CONSULT-(\d+)/', $filename, $matches)) {
        $consultation_id = (int)$matches[1];
    }
    // Try pattern: consultation_summary_1_...
    elseif (preg_match('/consultation_summary_(\d+)_/', $filename, $matches)) {
        $consultation_id = (int)$matches[1];
    }
    
    if (!$consultation_id) {
        echo "⚠ Could not extract ID from: $filename\n";
        continue;
    }
    
    // Check if consultation exists
    if (!in_array($consultation_id, $consultationIds)) {
        echo "✗ Orphaned (consultation doesn't exist): Consultation $consultation_id - $filename\n";
        $orphaned++;
        continue;
    }
    
    // Get consultation title
    $consultationTitle = '';
    foreach ($consultations as $c) {
        if ($c['id'] == $consultation_id) {
            $consultationTitle = $c['title'];
            break;
        }
    }
    
    $reference = sprintf('CONSULT-%06d', $consultation_id);
    $fileSize = filesize($pdfPath);
    
    // Check if already registered
    $checkStmt = $conn->prepare("SELECT id FROM documents WHERE consultation_id = ? AND stored_filename = ? LIMIT 1");
    $checkStmt->bind_param('is', $consultation_id, $filename);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo "  ⊘ Already registered: Consultation $consultation_id - $filename\n";
        $skipped++;
        $checkStmt->close();
        continue;
    }
    $checkStmt->close();
    
    // Register the document
    $stmt = $conn->prepare("INSERT INTO documents (consultation_id, reference_number, original_filename, stored_filename, file_type, file_size, uploaded_by, document_type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        echo "  ✗ Prepare failed for Consultation $consultation_id: " . $conn->error . "\n";
        continue;
    }
    
    $orig = $consultationTitle . ' - Summary.pdf';
    $mime = 'application/pdf';
    $user_id = null;
    $docType = 'final_document';
    $desc = 'Auto-generated PDF summary of consultation submission';
    
    $stmt->bind_param('issssisss', $consultation_id, $reference, $orig, $filename, $mime, $fileSize, $user_id, $docType, $desc);
    
    if ($stmt->execute()) {
        echo "  ✓ Registered: Consultation $consultation_id - $filename\n";
        $registered++;
    } else {
        echo "  ✗ Execute failed for Consultation $consultation_id: " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "\n=== Summary ===\n";
echo "Registered: $registered\n";
echo "Skipped (already registered): $skipped\n";
echo "Orphaned (consultation doesn't exist): $orphaned\n";

// Verify
$result = $conn->query("SELECT COUNT(*) as cnt FROM documents");
$row = $result->fetch_assoc();
echo "\nTotal documents in DB: " . $row['cnt'] . "\n";

// Show breakdown by consultation
echo "\n=== Documents by Consultation ===\n";
$result = $conn->query("SELECT consultation_id, COUNT(*) as cnt FROM documents GROUP BY consultation_id ORDER BY consultation_id");
while ($row = $result->fetch_assoc()) {
    echo "  Consultation " . $row['consultation_id'] . ": " . $row['cnt'] . " document(s)\n";
}
?>

        
        if (!$stmt) {
            echo "  ✗ Prepare failed for Consultation $id: " . $conn->error . "\n";
            $failed++;
            continue;
        }
        
        $orig = $title . ' - Summary.pdf';
        $mime = 'application/pdf';
        $user_id = null;
        $docType = 'final_document';
        $desc = 'Auto-generated PDF summary of consultation submission';
        
        $stmt->bind_param('issssisss', $id, $reference, $orig, $filename, $mime, $fileSize, $user_id, $docType, $desc);
        
        if ($stmt->execute()) {
            echo "  ✓ Registered: Consultation $id - $filename\n";
            $registered++;
        } else {
            echo "  ✗ Execute failed for Consultation $id: " . $stmt->error . "\n";
            $failed++;
        }
        $stmt->close();
    }
}

echo "\n=== Summary ===\n";
echo "Registered: $registered\n";
echo "Skipped (already registered): $skipped\n";
echo "Failed: $failed\n";

// Verify
$result = $conn->query("SELECT COUNT(*) as cnt FROM documents");
$row = $result->fetch_assoc();
echo "Total documents in DB: " . $row['cnt'] . "\n";
?>
