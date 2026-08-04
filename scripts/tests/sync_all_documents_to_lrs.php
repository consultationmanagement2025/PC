<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../DATABASE/consultations.php';
require_once __DIR__ . '/../../DATABASE/documents.php';
require_once __DIR__ . '/../../DATABASE/document-management.php';
require_once __DIR__ . '/../../UTILS/pdf_generator.php';
require_once __DIR__ . '/../../UTILS/generate_consultation_documents.php';

echo "=== GENERATING & ARCHIVING ALL PCMS CONSULTATION DOCUMENTS TO LRS ===\n";

$res = $conn->query("SELECT id, title, category, description, created_at FROM consultations");
if ($res && $res->num_rows > 0) {
    while ($consult = $res->fetch_assoc()) {
        $id = (int)$consult['id'];
        echo "Processing Consultation #{$id}: {$consult['title']}...\n";

        // Generate PDF documents for this consultation
        if (function_exists('generateConsultationDocuments')) {
            generateConsultationDocuments($id);
        }

        // Fetch generated documents
        $dRes = $conn->query("SELECT id, reference_number, original_filename FROM documents WHERE consultation_id = {$id}");
        if ($dRes && $dRes->num_rows > 0) {
            while ($doc = $dRes->fetch_assoc()) {
                $docId = (int)$doc['id'];
                echo "  -> Forwarding Doc #{$docId} ({$doc['reference_number']}) to LRS... ";
                $ret = forwardDocumentToLRS($docId, 'consultation', 'Bulk archiving PCMS consultation document');
                echo json_encode($ret) . "\n";
            }
        } else {
            echo "  -> No document record generated for Consultation #{$id}\n";
        }
    }
} else {
    echo "No consultations found in database.\n";
}

echo "=== SYNC COMPLETED ===\n";
