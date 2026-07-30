<?php
/**
 * Manual Integration Trigger API for PCMS
 * Allows admins to manually trigger integration payloads to PHS and LRS
 */
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/consultations.php';
require_once __DIR__ . '/../includes/integration_outbound.php';
require_once __DIR__ . '/../UTILS/pdf_generator.php';
require_once __DIR__ . '/../DATABASE/document-management.php';

$user_id = $_SESSION['user_id'] ?? null;
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed_roles = ['admin', 'super admin', 'superadmin', 'administrator', 'staff', 'barangay staff', 'barangay_staff', 'barangay'];

if (!in_array($current_role, $allowed_roles, true) && empty($user_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$consultation_id = (int)($data['consultation_id'] ?? 0);
$target_system = strtoupper(trim((string)($data['target_system'] ?? '')));

if ($consultation_id <= 0 || !in_array($target_system, ['PHS', 'LRS', 'ORTS'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID or target system']);
    exit;
}

$consultation = getConsultationById($consultation_id);
if (!$consultation) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Consultation record not found']);
    exit;
}

$counts = pcms_consultation_submission_counts($conn, $consultation_id);
$consultation['submission_counts'] = $counts;

try {
    if ($target_system === 'PHS') {
        pcms_integration_on_consultation_updated($consultation, 'manual_phs_sync');
        echo json_encode([
            'success' => true,
            'target_system' => 'PHS',
            'message' => "Successfully synced Consultation #{$consultation_id} with PHS (Public Hearing System).",
            'payload' => [
                'consultation_id' => $consultation_id,
                'hearing_id' => $consultation['phms_hearing_id'] ?? 'PHS-LINK-001',
                'title' => $consultation['title'],
                'district' => $consultation['district'] ?? 'District 1',
                'barangay' => $consultation['barangay'] ?? 'Citywide',
                'total_submissions' => $counts['total_submissions']
            ]
        ]);
    } elseif ($target_system === 'LRS') {
        // Ensure PDF summary is generated
        $pdf_generator = new ConsultationPDFGenerator($consultation_id);
        $pdf_dir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($pdf_dir)) {
            mkdir($pdf_dir, 0755, true);
        }
        $pdf_filename = $pdf_generator->getFilename();
        $pdf_path = $pdf_dir . $pdf_filename;

        $consultation_doc_data = [
            'id' => $consultation_id,
            'name' => $_SESSION['fullname'] ?? 'Admin',
            'email' => $_SESSION['email'] ?? 'admin@valenzuela.gov.ph',
            'phone' => 'N/A',
            'topic' => $consultation['title'],
            'category' => $consultation['category'] ?? 'General',
            'department' => 'Public Consultation Office',
            'description' => $consultation['description']
        ];

        $pdf_generator->save($consultation_doc_data, $pdf_path);

        $consultation['pdf_path'] = 'uploads/documents/' . $pdf_filename;
        pcms_integration_on_consultation_closed($consultation);

        echo json_encode([
            'success' => true,
            'target_system' => 'LRS',
            'message' => "Public Input Summary PDF ({$pdf_filename}) archived to LRS (Legislative Records System).",
            'payload' => [
                'consultation_id' => $consultation_id,
                'tracking_number' => 'CONSULT-' . sprintf('%06d', $consultation_id),
                'pdf_filename' => $pdf_filename,
                'total_submissions' => $counts['total_submissions'],
                'exported_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Integration trigger error: ' . $e->getMessage()]);
}
