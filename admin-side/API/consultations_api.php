<?php

header('Content-Type: application/json');

session_start();

require_once '../db.php';

require_once '../UTILS/security.php';

require_once '../DATABASE/consultations.php';
require_once __DIR__ . '/../DATABASE/document-management.php';
require_once __DIR__ . '/../UTILS/pdf_generator.php';
require_once __DIR__ . '/../config/email_config.php';

/**
 * Resize image to specified dimensions
 * @param string $source Path to source image
 * @param string $destination Path to save resized image
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @param string $extension Image extension
 * @return bool Success status
 */
function resizeImage($source, $destination, $maxWidth, $maxHeight, $extension) {
    try {
        // Get image info
        $imageInfo = getimagesize($source);
        if (!$imageInfo) {
            error_log('Failed to get image info');
            return false;
        }

        list($width, $height) = $imageInfo;
        $mime = $imageInfo['mime'];

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Create image resource based on mime type
        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($source);
                break;
            default:
                error_log('Unsupported image type: ' . $mime);
                return false;
        }

        if (!$sourceImage) {
            error_log('Failed to create image resource');
            return false;
        }

        // Create new image with true color support
        $destinationImage = imagecreatetruecolor($newWidth, $newHeight);

        // Handle transparency for PNG and GIF
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
            $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
            imagefilledrectangle($destinationImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize image
        imagecopyresampled($destinationImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save resized image
        $success = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($destinationImage, $destination, 90);
                break;
            case 'png':
                $success = imagepng($destinationImage, $destination, 9);
                break;
            case 'gif':
                $success = imagegif($destinationImage, $destination);
                break;
            case 'webp':
                $success = imagewebp($destinationImage, $destination, 90);
                break;
        }

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($destinationImage);

        if (!$success) {
            error_log('Failed to save resized image');
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log('Image resize error: ' . $e->getMessage());
        return false;
    }
}

// Allow admin or staff roles to access create/update consultation endpoints

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

$allowed_roles = ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'resource person', 'resource_person'];

if (!in_array($current_role, $allowed_roles, true)) {

    http_response_code(403);

    echo json_encode(['success' => false, 'message' => 'Unauthorized']);

    exit;

}



$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['staff', 'resource person', 'resource_person'], true);

$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');



try {

    switch ($action) {

        case 'debug':

            $dbRow = $conn->query("SELECT DATABASE() AS db") ? $conn->query("SELECT DATABASE() AS db")->fetch_assoc() : null;

            $dbName = $dbRow['db'] ?? null;

            $countRow = $conn->query("SELECT COUNT(*) AS cnt FROM consultations") ? $conn->query("SELECT COUNT(*) AS cnt FROM consultations")->fetch_assoc() : null;

            $cnt = isset($countRow['cnt']) ? (int)$countRow['cnt'] : null;



            echo json_encode([

                'success' => true,

                'data' => [

                    'session' => [

                        'user_id' => $_SESSION['user_id'] ?? null,

                        'fullname' => $_SESSION['fullname'] ?? null,

                        'role' => $_SESSION['role'] ?? null,

                        'role_normalized' => $current_role,

                    ],

                    'db' => [
                        'database' => $dbName,
                        'consultations_count' => $cnt,
                    ],
                ],

            ]);

            break;



        case 'list':

            $status = $_GET['status'] ?? null;

            $limit = (int)($_GET['limit'] ?? 50);

            $offset = (int)($_GET['offset'] ?? 0);

            

            $consultations = getConsultations($status, $limit, $offset);

            echo json_encode(['success' => true, 'data' => $consultations]);

            break;

            

        case 'get':

            $id = (int)($_GET['id'] ?? 0);

            if (!$id) {

                http_response_code(400);

                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);

                exit;

            }

            

            $consultation = getConsultationById($id);

            if ($consultation) {

                echo json_encode(['success' => true, 'data' => $consultation]);

            } else {

                http_response_code(404);

                echo json_encode(['success' => false, 'message' => 'Consultation not found']);

            }

            break;

            

        case 'create':

            // Verify CSRF token

            $csrf = $_POST['csrf_token'] ?? null;

            if (!$csrf || !verifyCSRFToken($csrf)) {

                http_response_code(403);

                echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);

                exit;

            }

            

            // Support both JSON and multipart/form-data

            if (!empty($_POST)) {

                $data = $_POST;

            } else {

                $data = json_decode(file_get_contents('php://input'), true) ?? [];

            }

            

            $required = ['title', 'description', 'category', 'start_date', 'end_date'];

            foreach ($required as $field) {

                if (empty($data[$field])) {

                    http_response_code(400);

                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);

                    exit;

                }

            }

            

            // Handle image upload

            $image_path = null;

            if (isset($_FILES['consultation_image']) && $_FILES['consultation_image']['error'] === UPLOAD_ERR_OK) {

                $file = $_FILES['consultation_image'];

                $maxSize = 10 * 1024 * 1024; // 10MB

                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                

                if ($file['size'] > $maxSize) {

                    http_response_code(400);

                    echo json_encode(['success' => false, 'message' => 'Image file size must be less than 10MB']);

                    exit;

                }

                

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExt)) {

                    http_response_code(400);

                    echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WebP']);

                    exit;

                }

                

                $uploadDir = __DIR__ . '/../ASSETS/images/consultations/';

                if (!is_dir($uploadDir)) {

                    mkdir($uploadDir, 0755, true);

                }

                

                $filename = 'consultation_' . time() . '_' . uniqid() . '.' . $ext;

                $filepath = $uploadDir . $filename;

                // Resize image to 600x600px before saving
                $resized = resizeImage($file['tmp_name'], $filepath, 600, 600, $ext);

                if ($resized) {
                    $image_path = 'ASSETS/images/consultations/' . $filename;
                }

            }

            

            $id = createConsultation(

                $data['title'],

                $data['description'],

                $data['category'],

                $data['start_date'],

                $data['end_date'],

                $_SESSION['user_id'],

                $data['expected_posts'] ?? 0,

                $image_path,

                null,

                null,

                1,

                'admin',

                null,

                $data['source_url'] ?? null,
                $data['response_mode'] ?? 'hybrid',
                $data['survey_question'] ?? null,
                $data['survey_option_a'] ?? 'Agree',
                $data['survey_option_b'] ?? 'Disagree',
                !empty($data['allow_guest_quick_vote']) ? 1 : 0,
                !empty($data['allow_guest_verified_vote']) ? 1 : 0

            );

            

            if ($id) {
                initializeDocumentsTable();

                try {
                    $consultation_data = [
                        'id' => $id,
                        'name' => $_SESSION['fullname'] ?? 'Admin',
                        'email' => $_SESSION['email'] ?? '',
                        'phone' => $_SESSION['phone'] ?? 'N/A',
                        'topic' => $data['title'],
                        'category' => $data['category'],
                        'department' => 'Public Consultation Office',
                        'description' => $data['description']
                    ];

                    $pdf_generator = new ConsultationPDFGenerator($id);
                    $pdf_dir = __DIR__ . '/../uploads/documents/';
                    if (!is_dir($pdf_dir)) {
                        mkdir($pdf_dir, 0755, true);
                    }
                    $pdf_path = $pdf_dir . $pdf_generator->getFilename();

                    if ($pdf_generator->save($consultation_data, $pdf_path)) {
                        $reference_number = generateDocumentReference($id);
                        $original_filename = $pdf_generator->getFilename();
                        $stored_filename = $original_filename;
                        $file_size = filesize($pdf_path);

                        $stmtDoc = $conn->prepare("INSERT INTO documents (
                            consultation_id, reference_number, original_filename,
                            stored_filename, file_type, file_size, uploaded_by,
                            document_type, description
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        if ($stmtDoc) {
                            $uploaded_by = (int)($_SESSION['user_id'] ?? 0);
                            if ($uploaded_by <= 0) $uploaded_by = null;
                            $file_type = 'application/pdf';
                            $document_type = 'final_document';
                            $document_description = 'Auto-generated PDF summary for admin-created consultation';
                            $file_size = (int)$file_size;
                            $stmtDoc->bind_param('isssissss',
                                $id, $reference_number, $original_filename,
                                $stored_filename, $file_type, $file_size, $uploaded_by,
                                $document_type, $document_description
                            );
                            $stmtDoc->execute();
                            $stmtDoc->close();
                        }
                    }
                } catch (Throwable $e) {
                    error_log('Admin consultation PDF/document save failed: ' . $e->getMessage());
                }

                $consultation = getConsultationById($id);
                echo json_encode(['success' => true, 'data' => $consultation]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create consultation']);
            }

            break;

            

        case 'update':

            if (!empty($_POST)) {
                $data = $_POST;
            } else {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
            }

            $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));

            

            if (!$id) {

                http_response_code(400);

                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);

                exit;

            }

            

            $success = updateConsultation(

                $id,

                $data['title'],

                $data['description'],

                $data['category'],

                null,

                $data['start_date'],

                $data['end_date'],
                $data['response_mode'] ?? 'hybrid',
                $data['survey_question'] ?? null,
                $data['survey_option_a'] ?? 'Agree',
                $data['survey_option_b'] ?? 'Disagree',
                !empty($data['allow_guest_quick_vote']) ? 1 : 0,
                !empty($data['allow_guest_verified_vote']) ? 1 : 0

            );

            

            echo json_encode(['success' => $success]);

            break;

            

        case 'close':

            $data = json_decode(file_get_contents('php://input'), true);

            $id = (int)($data['id'] ?? 0);

            

            if (!$id) {

                http_response_code(400);

                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);

                exit;

            }

            

            $success = closeConsultation($id);

            echo json_encode(['success' => $success]);

            break;

            

        case 'delete':

            $id = (int)($_GET['id'] ?? 0);

            if (!$id) {

                http_response_code(400);

                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);

                exit;

            }

            

            $success = deleteConsultation($id);

            echo json_encode(['success' => $success]);

            break;

            

        case 'stats':

            $id = (int)($_GET['id'] ?? 0);

            if (!$id) {

                http_response_code(400);

                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);

                exit;

            }

            

            $stats = getConsultationStats($id);

            echo json_encode(['success' => true, 'data' => $stats]);

            break;

            

        case 'save_outcome':

            $consultation_id = (int)($_POST['consultation_id'] ?? 0);

            $outcome = trim((string)($_POST['outcome'] ?? ''));

            $remarks = trim((string)($_POST['remarks'] ?? ''));

            $user_email = trim((string)($_POST['user_email'] ?? ''));
            $manual_email_body = trim((string)($_POST['manual_email_body'] ?? ''));

            

            if (!$consultation_id || !$outcome) {

                http_response_code(400);

                echo json_encode(['success' => false, 'error' => 'Missing required fields']);

                exit;

            }

            

            if ($remarks === '') {
                $remarks = 'No additional remarks were provided.';
            }

            $statusMap = [
                'solved' => 'completed',
                'needs-follow-up' => 'replied',
                'escalated' => 'viewed',
            ];
            $newStatus = $statusMap[$outcome] ?? 'replied';

            // Save outcome, remarks, and status to database

            $stmt = $conn->prepare("UPDATE consultations SET outcome = ?, remarks = ?, status = ? WHERE id = ?");

            if (!$stmt) {

                throw new Exception("Prepare failed: " . $conn->error);

            }

            

            $stmt->bind_param("sssi", $outcome, $remarks, $newStatus, $consultation_id);

            if (!$stmt->execute()) {

                throw new Exception("Execute failed: " . $stmt->error);

            }
            $stmt->close();

            $savedStatus = $newStatus;
            $statusResult = $conn->query("SELECT status FROM consultations WHERE id = " . intval($consultation_id));
            if ($statusResult && $statusRow = $statusResult->fetch_assoc()) {
                $savedStatus = strtolower(trim((string)($statusRow['status'] ?? $newStatus)));
            }

            $responseMessage = 'Outcome saved successfully.';
            echo json_encode([
                'success' => true,
                'message' => $responseMessage,
                'email_sent' => false,
                'email_error' => null,
                'status' => $savedStatus
            ]);

            break;

            

        default:

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Invalid action']);

    }

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

}


// ========== AI EMAIL GENERATION FUNCTION ==========

function generateAndSendOutcomeEmail($consultationId, $outcome, $remarks, $userEmail, ?string &$error = null) {

    $error = null;
    if (empty($userEmail)) {

        $error = 'User email is missing.';
        return false;

    }

    

    $outcome = strtolower($outcome);

    

    // Generate email based on outcome type

    switch ($outcome) {

        case 'solved':

            $subject = 'Your Consultation Has Been Successfully Resolved';

            $body = generateSolvedEmailBody($consultationId, $remarks);

            break;

            

        case 'needs-follow-up':

            $subject = 'Update on Your Consultation - Follow-up Required';

            $body = generateFollowUpEmailBody($consultationId, $remarks);

            break;

            

        case 'escalated':

            $subject = 'Your Consultation Has Been Escalated for Further Review';

            $body = generateEscalatedEmailBody($consultationId, $remarks);

            break;

            

        default:

            return false;

    }

    

    // Send email using built-in PHP mail or PHPMailer if available

    return sendOutcomeEmail($userEmail, $subject, $body, $error);

}


function generateSolvedEmailBody($consultationId, $remarks) {

    $refId = 'CONSULT-' . str_pad($consultationId, 6, '0', STR_PAD_LEFT);

    

    $body = "<!DOCTYPE html>

<html>

<head>

    <style>

        body { font-family: Arial, sans-serif; color: #333; }

        .container { max-width: 600px; margin: 0 auto; padding: 20px; }

        .header { background-color: #22c55e; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }

        .content { background-color: #f9fafb; padding: 20px; border-bottom: 1px solid #e5e7eb; }

        .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #666; }

        .remarks { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #22c55e; }

    </style>

</head>

<body>

    <div class='container'>

        <div class='header'>

            <h2>✓ Your Consultation Has Been Resolved</h2>

        </div>

        <div class='content'>

            <p>Dear Valued Citizen,</p>

            <p>Thank you for submitting your consultation to our office. We are pleased to inform you that your submission (Reference ID: <strong>$refId</strong>) has been successfully resolved.</p>

            <div class='remarks'>

                <p><strong>Administrator's Remarks:</strong></p>

                <p>" . nl2br(htmlspecialchars($remarks)) . "</p>

            </div>

            <p>We greatly appreciate your engagement with our office and your trust in our services. Your feedback has been valuable in helping us improve our processes and better serve our community.</p>

            <p><strong>We would like to encourage you to:</strong></p>

            <ul>

                <li>Provide feedback on your experience through our feedback form</li>

                <li>Share your experience with other citizens who may benefit from similar services</li>

                <li>Contact us again if you have any additional concerns or consultations</li>

            </ul>

            <p>Thank you for being part of our community and for helping us provide better service.</p>

            <p>Sincerely,<br>

            <strong>Barangay Office</strong></p>

        </div>

        <div class='footer'>

            <p>This is an automated email. Please do not reply directly to this message.</p>

        </div>

    </div>

</body>

</html>";

    

    return $body;

}


function generateFollowUpEmailBody($consultationId, $remarks) {

    $refId = 'CONSULT-' . str_pad($consultationId, 6, '0', STR_PAD_LEFT);

    

    $body = "<!DOCTYPE html>

<html>

<head>

    <style>

        body { font-family: Arial, sans-serif; color: #333; }

        .container { max-width: 600px; margin: 0 auto; padding: 20px; }

        .header { background-color: #f59e0b; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }

        .content { background-color: #f9fafb; padding: 20px; border-bottom: 1px solid #e5e7eb; }

        .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #666; }

        .remarks { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f59e0b; }

    </style>

</head>

<body>

    <div class='container'>

        <div class='header'>

            <h2>⏳ Your Consultation Update</h2>

        </div>

        <div class='content'>

            <p>Dear Valued Citizen,</p>

            <p>Thank you for your patience. We are writing to provide you with an update on your consultation (Reference ID: <strong>$refId</strong>).</p>

            <div class='remarks'>

                <p><strong>Update from Administrator:</strong></p>

                <p>" . nl2br(htmlspecialchars($remarks)) . "</p>

            </div>

            <p>We are actively working on your consultation and expect to provide a complete resolution soon. We will contact you with further updates as progress is made.</p>

            <p>If you have any urgent concerns or additional information to provide, please do not hesitate to reach out to our office.</p>

            <p>Thank you for your cooperation and patience.</p>

            <p>Sincerely,<br>

            <strong>Barangay Office</strong></p>

        </div>

        <div class='footer'>

            <p>This is an automated email. Please do not reply directly to this message.</p>

        </div>

    </div>

</body>

</html>";

    

    return $body;

}


function generateEscalatedEmailBody($consultationId, $remarks) {

    $refId = 'CONSULT-' . str_pad($consultationId, 6, '0', STR_PAD_LEFT);

    

    $body = "<!DOCTYPE html>

<html>

<head>

    <style>

        body { font-family: Arial, sans-serif; color: #333; }

        .container { max-width: 600px; margin: 0 auto; padding: 20px; }

        .header { background-color: #ef4444; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }

        .content { background-color: #f9fafb; padding: 20px; border-bottom: 1px solid #e5e7eb; }

        .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #666; }

        .remarks { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #ef4444; }

    </style>

</head>

<body>

    <div class='container'>

        <div class='header'>

            <h2>🚀 Your Consultation Has Been Escalated</h2>

        </div>

        <div class='content'>

            <p>Dear Valued Citizen,</p>

            <p>Your consultation (Reference ID: <strong>$refId</strong>) has been reviewed and escalated to a higher level of authority for further investigation and resolution.</p>

            <div class='remarks'>

                <p><strong>Escalation Details:</strong></p>

                <p>" . nl2br(htmlspecialchars($remarks)) . "</p>

            </div>

            <p>This escalation ensures that your concern receives the appropriate attention and expertise it requires. You can expect to hear from our office with a detailed response within the next 5-7 business days.</p>

            <p>We appreciate your patience as we work to resolve this matter thoroughly and satisfactorily.</p>

            <p>Sincerely,<br>

            <strong>Barangay Office</strong></p>

        </div>

        <div class='footer'>

            <p>This is an automated email. Please do not reply directly to this message.</p>

        </div>

    </div>

</body>

</html>";

    

    return $body;

}


function sendOutcomeEmail($to, $subject, $body, ?string &$error = null) {
    $error = null;
    if (function_exists('sendGmailEmail')) {
        return sendGmailEmail($to, $subject, $body, true, $error);
    }

    // Fallback to native PHP mail function
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@barangay.gov.ph\r\n";

    $success = mail($to, $subject, $body, $headers);
    if (!$success) {
        $error = 'PHP mail() failed to send message';
        error_log('Fallback mail failed for ' . $to . ' subject: ' . $subject);
    }
    return $success;

}


?>

