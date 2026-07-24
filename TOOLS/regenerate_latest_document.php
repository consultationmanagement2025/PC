<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';

// Get the latest consultation
$result = $conn->query("SELECT id, title FROM consultations ORDER BY created_at DESC LIMIT 1");
if (!$result || $result->num_rows === 0) {
    die("No consultations found.\n");
}

$consultation = $result->fetch_assoc();
$consultation_id = $consultation['id'];
$title = $consultation['title'];

echo "Latest consultation: ID $consultation_id - $title\n";

// Delete old documents for this consultation
$delete_stmt = $conn->prepare("DELETE FROM documents WHERE consultation_id = ?");
if ($delete_stmt) {
    $delete_stmt->bind_param('i', $consultation_id);
    $delete_stmt->execute();
    $deleted = $delete_stmt->affected_rows;
    $delete_stmt->close();
    echo "Deleted $deleted old document(s) from database.\n";
}

// Delete actual PDF files
$upload_dir = __DIR__ . '/../uploads/documents/';
if (is_dir($upload_dir)) {
    $files = glob($upload_dir . '*.pdf');
    $deleted_files = 0;
    foreach ($files as $file) {
        // Check if file name contains the consultation ID or reference
        if (strpos($file, (string)$consultation_id) !== false) {
            if (unlink($file)) {
                $deleted_files++;
                echo "Deleted file: " . basename($file) . "\n";
            }
        }
    }
    if ($deleted_files === 0) {
        echo "No matching PDF files found to delete.\n";
    }
}

// Regenerate the document
echo "\nRegenerating document...\n";
$_SESSION['user_id'] = 1; // Set admin user ID for document generation
$saved = generateConsultationDocuments($consultation_id, ['pdf' => true, 'docx' => false]);

if (!empty($saved)) {
    echo "Document regenerated successfully!\n";
    foreach ($saved as $doc) {
        echo " - Type: {$doc['type']}, Path: {$doc['path']}, Size: {$doc['size']} bytes\n";
    }
} else {
    echo "Failed to regenerate document.\n";
}
?>
