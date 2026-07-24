<?php
/**
 * API endpoint to get documents for a consultation
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

// Get consultation ID
$consultation_id = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;

if ($consultation_id <= 0) {
    echo json_encode(['error' => 'Invalid consultation ID']);
    exit();
}

try {
    $documents = getConsultationDocuments($consultation_id);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
