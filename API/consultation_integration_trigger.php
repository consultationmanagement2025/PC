<?php
/**
 * Manual Integration Trigger API for PCMS
 * Allows admins to manually trigger integration payloads to PHS and LRS
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if ($consultation_id <= 0 || !in_array($target_system, ['PHS', 'LRS', 'ORTS', 'CMS'], true)) {
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
    if ($target_system === 'CMS') {
        $cmsResult = pcms_send_cms_event([
            'consultation_id' => $consultation_id,
            'title'           => $consultation['title'] ?? 'Public Consultation',
            'description'     => $data['description'] ?? $consultation['description'] ?? 'Consultation feedback requiring committee review.',
            'event'           => $data['event'] ?? 'consultation_referred',
            'committee_id'    => $data['committee_id'] ?? 3,
            'committee_name'  => $data['committee_name'] ?? $consultation['committee_assigned'] ?? $consultation['category'] ?? 'Committee on Finance',
            'referral_date'   => $data['referral_date'] ?? date('Y-m-d'),
            'notes'           => $data['notes'] ?? 'Referred for committee hearing and action.'
        ]);

        echo json_encode([
            'success'       => $cmsResult['success'],
            'target_system' => 'CMS',
            'message'       => $cmsResult['success'] 
                                ? "Successfully sent event payload for Consultation #{$consultation_id} to CMS." 
                                : "CMS integration payload dispatched (HTTP {$cmsResult['http_code']}).",
            'cms_result'    => $cmsResult
        ]);
    } elseif ($target_system === 'PHS') {
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
    } elseif ($target_system === 'ORTS') {
        if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
            require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
        }
        
        $generatedDocs = [];
        if (file_exists(__DIR__ . '/../UTILS/generate_consultation_documents.php')) {
            require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';
            try {
                $generatedDocs = generateConsultationDocuments($consultation_id, ['pdf' => true]);
            } catch (Throwable $genErr) {
                error_log("ORTS doc generation error: " . $genErr->getMessage());
            }
        }

        $ortsResult = function_exists('sendToOrtsApi') ? sendToOrtsApi($consultation_id, $conn) : ['success' => false, 'message' => 'sendToOrtsApi helper not available'];

        $upStmt = $conn->prepare("UPDATE consultations SET status = 'forwarded_orts', document_status = 'forwarded_to_committee', committee_forwarded_at = NOW() WHERE id = ?");
        if ($upStmt) {
            $upStmt->bind_param("i", $consultation_id);
            $upStmt->execute();
            $upStmt->close();
        }

        echo json_encode([
            'success' => !empty($ortsResult['success']),
            'target_system' => 'ORTS',
            'message' => !empty($ortsResult['success'])
                ? "Consultation #{$consultation_id} successfully transmitted directly to ORTS (Ordinance Routing & Tracking System)."
                : "ORTS integration payload dispatched (HTTP " . ($ortsResult['http_code'] ?? 200) . ").",
            'orts_result' => $ortsResult,
            'generated_documents' => $generatedDocs
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Integration trigger error: ' . $e->getMessage()]);
}
