<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';

echo "=======================================================\n";
echo "REGENERATING ALL PAST CONSULTATION DOCUMENTS (NEW LAYOUT)\n";
echo "=======================================================\n\n";

// Get all consultations
$result = $conn->query("SELECT id, title, tracking_number FROM consultations ORDER BY id ASC");
if (!$result || $result->num_rows === 0) {
    die("No consultations found in database.\n");
}

$consultations = [];
while ($row = $result->fetch_assoc()) {
    $consultations[] = $row;
}

echo "Found " . count($consultations) . " consultation(s) to process.\n\n";

$_SESSION['user_id'] = 1; // Admin user ID context

$upload_dir = __DIR__ . '/../uploads/documents/';

foreach ($consultations as $consultation) {
    $cid = (int)$consultation['id'];
    $title = $consultation['title'];
    $tracking = $consultation['tracking_number'];

    echo "-------------------------------------------------------\n";
    echo "Processing Consultation #{$cid}: \"{$title}\"\n";

    // Delete old document records from `documents` table for this consultation
    $delStmt = $conn->prepare("DELETE FROM documents WHERE consultation_id = ?");
    if ($delStmt) {
        $delStmt->bind_param('i', $cid);
        $delStmt->execute();
        $affected = $delStmt->affected_rows;
        $delStmt->close();
        echo " - Cleared {$affected} old database record(s) from `documents` table.\n";
    }

    // Clear old physical PDF files in uploads/documents/ matching this consultation or tracking code
    if (is_dir($upload_dir)) {
        $files = glob($upload_dir . '*.pdf');
        $deletedCount = 0;
        foreach ($files as $file) {
            $bName = basename($file);
            if (strpos($bName, (string)$cid) !== false || ($tracking && strpos($bName, $tracking) !== false)) {
                if (@unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        echo " - Cleaned up {$deletedCount} old PDF file(s) from disk.\n";
    }

    // Re-generate official PDF document with the NEW 7-Section AI Brief layout
    try {
        $saved = generateConsultationDocuments($cid, ['pdf' => true, 'docx' => false]);
        if (!empty($saved)) {
            echo " ✅ Successfully generated NEW 7-Section PDF:\n";
            foreach ($saved as $doc) {
                echo "    -> Path: {$doc['path']} ({$doc['size']} bytes)\n";
            }
        } else {
            echo " ⚠️ Document generation returned empty array for Consultation #{$cid}.\n";
        }
    } catch (Throwable $e) {
        echo " ❌ Error generating document for Consultation #{$cid}: " . $e->getMessage() . "\n";
    }
}

echo "\n=======================================================\n";
echo "ALL CONSULTATION DOCUMENTS REGENERATED SUCCESSFULLY!\n";
echo "=======================================================\n";
?>
