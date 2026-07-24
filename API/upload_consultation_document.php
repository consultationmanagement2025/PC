<?php
/**
 * API endpoint for uploading consultation documents
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/document-management.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Check if consultation ID is provided
$consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
if ($consultation_id <= 0) {
    echo json_encode(['error' => 'Invalid consultation ID']);
    exit();
}

// Check if file is uploaded
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit();
}

try {
    $document_type = $_POST['document_type'] ?? 'consultation_form';
    $description = $_POST['description'] ?? '';
    
    $result = uploadConsultationDocument(
        $consultation_id, 
        $_FILES['document'], 
        $document_type, 
        $description
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'document' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
