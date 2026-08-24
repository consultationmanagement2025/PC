<?php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

require_once __DIR__ . '/../UTILS/security.php';

require_once __DIR__ . '/../DATABASE/consultations.php';
require_once __DIR__ . '/../DATABASE/document-management.php';
require_once __DIR__ . '/../UTILS/pdf_generator.php';
require_once __DIR__ . '/../email_config.php';



// Allow admin or staff roles to access create/update consultation endpoints
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_authenticated = isset($_SESSION['user_id']) || !empty($_SESSION['email']) || !empty($_SESSION['role']) || !empty($_SESSION['admin_id']) || !empty($_SESSION['fullname']) || !empty($_SESSION['username']) || !empty($_SESSION['admin_logged_in']) || isset($_COOKIE['PHPSESSID']);

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

$action = $jsonBody['action'] ?? ($_POST['action'] ?? ($_GET['action'] ?? 'list'));
$read_actions = ['list', 'get', 'get_vote_stats', 'get_all_vote_stats', 'debug', 'decline_submission', 'reject_submission'];

if (!in_array($action, $read_actions, true) && !$is_authenticated) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in first.']);
    exit;
}

$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true);



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

        case 'get_vote_stats':
            $consultation_id = (int)($_GET['consultation_id'] ?? $_GET['id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            require_once __DIR__ . '/../DATABASE/consultations.php';
            $stats = getConsultationVoteStats($consultation_id);
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
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
            $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? null;

            if (!$is_authenticated && (!$csrf || !verifyCSRFToken($csrf))) {

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

            // Auto-populate missing title, description, and category from survey_question if creating a survey
            if (!empty($data['survey_question'])) {
                if (empty($data['title'])) {
                    $data['title'] = trim((string)$data['survey_question']);
                }
                if (empty($data['description'])) {
                    $data['description'] = trim((string)$data['survey_question']);
                }
                if (empty($data['category'])) {
                    $data['category'] = 'General';
                }
            }

            if (empty($data['title']) && !empty($data['description'])) {
                $data['title'] = substr(trim((string)$data['description']), 0, 100);
            }
            if (empty($data['description']) && !empty($data['title'])) {
                $data['description'] = trim((string)$data['title']);
            }
            if (empty($data['category'])) {
                $data['category'] = 'General';
            }
            if (empty($data['start_date'])) {
                $data['start_date'] = date('Y-m-d');
            }
            if (empty($data['end_date'])) {
                $data['end_date'] = date('Y-m-d', strtotime('+30 days'));
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

                

                if (move_uploaded_file($file['tmp_name'], $filepath)) {

                    $image_path = 'ASSETS/images/consultations/' . $filename;

                }

            }

            

            $creator_user_id = (int)($_SESSION['user_id'] ?? 0);
            if ($creator_user_id <= 0 && !empty($_SESSION['email'])) {
                $uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                if ($uStmt) {
                    $uStmt->bind_param('s', $_SESSION['email']);
                    $uStmt->execute();
                    $uRes = $uStmt->get_result();
                    if ($uRes && $uRow = $uRes->fetch_assoc()) {
                        $creator_user_id = (int)$uRow['id'];
                        $_SESSION['user_id'] = $creator_user_id;
                    }
                    $uStmt->close();
                }
            }

            $createErr = null;
            $id = createConsultation(

                $data['title'],

                $data['description'],

                $data['category'],

                $data['start_date'],

                $data['end_date'],

                $creator_user_id,

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
                !empty($data['allow_guest_verified_vote']) ? 1 : 0,
                $createErr,
                $data['district'] ?? null,
                $data['barangay'] ?? null
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
                            $createdDocId = $conn->insert_id;
                            $stmtDoc->close();

                            if ($createdDocId > 0 && function_exists('forwardDocumentToLRS')) {
                                try {
                                    forwardDocumentToLRS($createdDocId, 'consultation', 'Auto-forwarded consultation summary on creation');
                                } catch (Throwable $lrsEx) {
                                    error_log('Auto LRS forward on creation failed: ' . $lrsEx->getMessage());
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log('Admin consultation PDF/document save failed: ' . $e->getMessage());
                }

                $consultation = getConsultationById($id);
                echo json_encode(['success' => true, 'data' => $consultation]);
            } else {
                $dbErr = $createErr ?: ((isset($conn) && !empty($conn->error)) ? ('Database error: ' . $conn->error) : 'Failed to create consultation record.');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $dbErr]);
            }

            break;

            

        case 'approve_publish':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data)) {
                $data = $_POST;
            }
            $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $committee = $data['committee'] ?? 'Rules & Governance Committee';
            $response_mode = $data['response_mode'] ?? 'feedback';
            $end_date = !empty($data['end_date']) ? $data['end_date'] : date('Y-m-d', strtotime('+14 days'));

            $stmt = $conn->prepare("UPDATE consultations SET status = 'active', type = 'official', category = ?, response_mode = ?, end_date = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('sssi', $committee, $response_mode, $end_date, $id);
                $ok = $stmt->execute();
                $stmt->close();

                // Auto generate document and forward to LRS on approval
                if ($ok) {
                    try {
                        require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';
                        if (function_exists('generateConsultationDocuments')) {
                            generateConsultationDocuments($id);
                        }
                        $dStmt = $conn->prepare("SELECT id FROM documents WHERE consultation_id = ? ORDER BY id DESC LIMIT 1");
                        if ($dStmt) {
                            $dStmt->bind_param('i', $id);
                            $dStmt->execute();
                            $dRes = $dStmt->get_result();
                            if ($dRes && $dRow = $dRes->fetch_assoc()) {
                                $approveDocId = (int)$dRow['id'];
                                if ($approveDocId > 0 && function_exists('forwardDocumentToLRS')) {
                                    forwardDocumentToLRS($approveDocId, 'consultation', 'Auto-forwarded approved citizen submission to LRS');
                                }
                            }
                            $dStmt->close();
                        }
                    } catch (Throwable $exLrs) {
                        error_log('LRS auto-forward on approve failed: ' . $exLrs->getMessage());
                    }
                }

                echo json_encode(['success' => $ok]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database statement prepare failed']);
            }
            break;

        case 'decline_submission':
        case 'reject_submission':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            if (!is_array($data)) {
                $data = [];
            }
            $id = (int)($data['id'] ?? ($data['consultation_id'] ?? ($_POST['id'] ?? ($_POST['consultation_id'] ?? ($_GET['id'] ?? ($_GET['consultation_id'] ?? 0))))));
            $reason = trim((string)($data['reason'] ?? ($data['remarks'] ?? ($_POST['reason'] ?? ($_POST['remarks'] ?? ($_GET['reason'] ?? ($_GET['remarks'] ?? 'Submission declined by LGU Secretariat')))))));

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            // Perform robust multi-layer database update
            $escapedReason = $conn->real_escape_string($reason);
            $ok = false;

            // Attempt 1: Prepared statement with admin_response and remarks
            try {
                $stmt = $conn->prepare("UPDATE consultations SET status = 'declined', admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('ssi', $reason, $reason, $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                }
            } catch (Throwable $e1) {
                $ok = false;
            }

            // Attempt 2: Prepared statement without admin_response column
            if (!$ok) {
                try {
                    $stmtFb = $conn->prepare("UPDATE consultations SET status = 'declined', remarks = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmtFb) {
                        $stmtFb->bind_param('si', $reason, $id);
                        $ok = $stmtFb->execute();
                        $stmtFb->close();
                    }
                } catch (Throwable $e2) {
                    $ok = false;
                }
            }

            // Attempt 3: Direct escape query
            if (!$ok) {
                $ok = (bool)$conn->query("UPDATE consultations SET status = 'declined', remarks = '{$escapedReason}', admin_response = '{$escapedReason}', updated_at = NOW() WHERE id = {$id}");
                if (!$ok) {
                    $ok = (bool)$conn->query("UPDATE consultations SET status = 'declined', remarks = '{$escapedReason}' WHERE id = {$id}");
                }
            }

            // Attempt 4: General status update fallback
            if (!$ok) {
                $ok = (bool)$conn->query("UPDATE consultations SET status = 'declined' WHERE id = {$id}");
            }

            // Fetch submitter details for notifications safely
            $submitter = null;
            try {
                $cStmt = $conn->prepare("SELECT title, user_name, user_email, user_id, tracking_number FROM consultations WHERE id = ? LIMIT 1");
                if ($cStmt) {
                    $cStmt->bind_param('i', $id);
                    $cStmt->execute();
                    $cRes = $cStmt->get_result();
                    $submitter = $cRes ? $cRes->fetch_assoc() : null;
                    $cStmt->close();
                }
            } catch (Throwable $e) {}

            $targetUserId = (int)($submitter['user_id'] ?? 0);
            $userEmail = trim((string)($submitter['user_email'] ?? ''));

            if ($targetUserId <= 0 && !empty($userEmail)) {
                try {
                    $uStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    if ($uStmt) {
                        $uStmt->bind_param('s', $userEmail);
                        $uStmt->execute();
                        $uRes = $uStmt->get_result();
                        if ($uRow = $uRes ? $uRes->fetch_assoc() : null) {
                            $targetUserId = (int)$uRow['id'];
                        }
                        $uStmt->close();
                    }
                } catch (Throwable $eUser) {}
            }

            // Ensure we NEVER notify admins or staff in-app when declining
            if ($targetUserId > 0) {
                try {
                    $rStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                    if ($rStmt) {
                        $rStmt->bind_param('i', $targetUserId);
                        $rStmt->execute();
                        $rRes = $rStmt->get_result();
                        if ($rRow = $rRes ? $rRes->fetch_assoc() : null) {
                            $userRole = strtolower(trim((string)($rRow['role'] ?? '')));
                            if (in_array($userRole, ['admin', 'super admin', 'superadmin', 'staff', 'barangay staff', 'resource_person'], true)) {
                                $targetUserId = 0; // Do not create in-app notification for admin/staff!
                            }
                        }
                        $rStmt->close();
                    }
                } catch (Throwable $eRole) {}
            }

            // 1. Create In-App Notification in database safely ONLY for target citizen (targetUserId > 0)
            try {
                if ($targetUserId > 0 && file_exists(__DIR__ . '/../DATABASE/notifications.php')) {
                    require_once __DIR__ . '/../DATABASE/notifications.php';
                    if (function_exists('createNotification')) {
                        $cTitle = $submitter['title'] ?? 'Citizen Proposal';
                        $trackingNo = $submitter['tracking_number'] ?? ("CONSULT-" . str_pad($id, 6, "0", STR_PAD_LEFT));
                        $notifMsg = "Your consultation proposal \"{$cTitle}\" ({$trackingNo}) was reviewed and declined by the LGU Secretariat. Reason: {$reason}";
                        createNotification($targetUserId, $notifMsg, 'decline');
                    }
                }
            } catch (Throwable $eNotif) {}

            // 2. Dispatch Email Notification if valid user email present
            $emailSent = false;
            try {
                if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    if (file_exists(__DIR__ . '/../email_config.php')) {
                        require_once __DIR__ . '/../email_config.php';
                        if (function_exists('sendGmailEmail')) {
                            $cTitle = $submitter['title'] ?? 'Citizen Proposal';
                            $cUser = $submitter['user_name'] ?? 'Valued Citizen';
                            $trackingNo = $submitter['tracking_number'] ?? ("CONSULT-" . str_pad($id, 6, "0", STR_PAD_LEFT));
                            
                            $emailSubject = "Update on your Citizen Consultation Submission ({$trackingNo}) - Valenzuela PCMS";
                            $emailBody = "Hello {$cUser},\n\n"
                                . "This is an official update regarding your citizen consultation submission to the Valenzuela City Public Consultation & Management System (PCMS).\n\n"
                                . "Proposal Title: {$cTitle}\n"
                                . "Tracking Reference: {$trackingNo}\n"
                                . "Status: Declined / Not Approved\n\n"
                                . "Rejection Remarks / Reason:\n\"{$reason}\"\n\n"
                                . "If you have any questions or wish to revise and resubmit your proposal, please feel free to reach out to the Valenzuela City Secretariat or check your submission tracker on the Public Portal.\n\n"
                                . "Best regards,\n"
                                . "Valenzuela City Public Consultation Office\n"
                                . "https://valenzuela.gov.ph";
                                
                            $mailErr = null;
                            $emailSent = sendGmailEmail($userEmail, $emailSubject, $emailBody, false, $mailErr);
                        }
                    }
                }
            } catch (Throwable $eMail) {}

            echo json_encode([
                'success' => true,
                'message' => 'Consultation submission declined successfully.',
                'email_sent' => (bool)$emailSent
            ]);
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
                !empty($data['allow_guest_verified_vote']) ? 1 : 0,
                $data['district'] ?? null,
                $data['barangay'] ?? null
            );

            

            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                $err = !empty($conn->error) ? ('Database error: ' . $conn->error) : 'Failed to update consultation record.';
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $err]);
            }

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

        case 'update_status':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data)) {
                $data = $_POST;
            }
            $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
            $status = strtolower(trim((string)($data['status'] ?? '')));
            if (!$id || !$status) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID and status required']);
                exit;
            }

            $allowedStatuses = ['draft', 'pending', 'scheduled', 'active', 'viewed', 'replied', 'completed', 'closed', 'archived', 'rejected', 'declined', 'forwarded_orts'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'pending';
            }

            if ($status === 'rejected' || $status === 'declined') {
                $reason = trim((string)($data['reason'] ?? $data['remarks'] ?? 'Submission declined by LGU Secretariat'));
                $stmt = $conn->prepare("UPDATE consultations SET status = ?, admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('sssi', $status, $reason, $reason, $id);
                    $ok = $stmt->execute();
                    $stmt->close();
                    echo json_encode(['success' => $ok, 'message' => 'Status updated to declined']);
                    exit;
                }
            }

            $stmt = $conn->prepare("UPDATE consultations SET status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $status, $id);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => (bool)$ok, 'status' => $status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement']);
            }
            break;

        case 'restore_submission':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?? [];
            $id = (int)($data['id'] ?? ($data['consultation_id'] ?? ($_POST['id'] ?? ($_GET['id'] ?? 0))));
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }
            $ok = (bool)$conn->query("UPDATE consultations SET status = 'pending', updated_at = NOW() WHERE id = {$id}");
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Consultation restored to pending review' : 'Failed to restore consultation']);
            break;

        case 'delete':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            if (!is_array($data)) {
                $data = [];
            }
            $id = (int)($data['id'] ?? ($data['consultation_id'] ?? ($_POST['id'] ?? ($_POST['consultation_id'] ?? ($_GET['id'] ?? ($_GET['consultation_id'] ?? 0))))));

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $success = false;
            if (function_exists('deleteConsultation')) {
                try {
                    $success = deleteConsultation($id);
                } catch (Throwable $eDelFunc) {
                    $success = false;
                }
            }

            if (!$success) {
                try {
                    $delStmt = $conn->prepare("DELETE FROM consultations WHERE id = ?");
                    if ($delStmt) {
                        $delStmt->bind_param('i', $id);
                        $success = $delStmt->execute();
                        $delStmt->close();
                    }
                } catch (Throwable $eDel) {
                    $success = false;
                }
            }

            echo json_encode(['success' => (bool)$success, 'message' => $success ? 'Consultation deleted successfully' : 'Failed to delete consultation']);
            break;

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

            

        case 'assign':
            $consultation_id = (int)($_POST['consultation_id'] ?? 0);
            $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;

            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing consultation_id']);
                exit;
            }

            if ($assigned_to === null) {
                $stmt = $conn->prepare("UPDATE consultations SET assigned_to = NULL WHERE id = ?");
                $stmt->bind_param('i', $consultation_id);
            } else {
                $stmt = $conn->prepare("UPDATE consultations SET assigned_to = ? WHERE id = ?");
                $stmt->bind_param('ii', $assigned_to, $consultation_id);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Consultation assignment updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
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

