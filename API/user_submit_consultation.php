<?php
// user_submit_consultation.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';
require_once __DIR__ . '/../UTILS/ai_routing.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_email = isset($_POST['email']) ? trim($_POST['email']) : null;
$topic = trim($_POST['topic'] ?? '');
$description = trim($_POST['description'] ?? '');
$allow_email_notifications = isset($_POST['allow_email_notifications']) ? 1 : 0;

// Support both preferred_datetime or separate date and time fields
if (isset($_POST['preferred_date']) && isset($_POST['preferred_time'])) {
    $preferred_datetime = $_POST['preferred_date'] . ' ' . $_POST['preferred_time'];
} else {
    $preferred_datetime = $_POST['preferred_datetime'] ?? null;
}

if (!$topic || !$preferred_datetime) {
    echo json_encode(['error' => 'Topic and preferred date/time required']);
    exit();
}

// Store user-submitted consultation requests into the existing consultations table
// (admin publishes official consultations by setting status to 'active')
$raw_session_name = trim($_SESSION['fullname'] ?? ($_SESSION['full_name'] ?? ''));
$is_admin_identity = (
    (isset($_SESSION['role']) && in_array(strtolower(trim($_SESSION['role'])), ['admin', 'super_admin', 'superadmin', 'staff'], true)) ||
    (strpos(strtolower($raw_session_name), 'system administrator') !== false) ||
    (strpos(strtolower($raw_session_name), 'admin') !== false)
);

$guest_name_input = trim($_POST['guest_name'] ?? ($_POST['user_name'] ?? ''));

if (!empty($guest_name_input)) {
    $user_name = $guest_name_input;
} elseif ($is_admin_identity) {
    $user_name = 'Citizen (Admin Test)';
} else {
    $user_name = !empty($raw_session_name) ? $raw_session_name : 'Citizen';
}

$full_description = "Preferred Date/Time: " . $preferred_datetime . "\n\n" . $description;

$aiResult = classifyConsultationRequest($topic, $description, '', $user_email);
$aiClassification = $aiResult['classification'] ?? 'consultation';
$aiDepartment = $aiResult['department'] ?? 'General';
$aiReason = $aiResult['reason'] ?? '';
$aiIsConsultation = ($aiClassification === 'consultation');

$stmt = $conn->prepare("INSERT INTO consultations (title, description, user_name, user_email, allow_email_notifications, status, created_at, type) VALUES (?, ?, ?, ?, ?, 'draft', NOW(), 'user')");
if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit();
}
$stmt->bind_param('ssssi', $topic, $full_description, $user_name, $user_email, $allow_email_notifications);

if ($stmt->execute()) {
    // Log user action
    $username = $_SESSION['fullname'] ?? '';
    $action = 'submit_consultation';
    $action_type = 'create';
    $entity_type = 'consultation';
    $entity_id = $stmt->insert_id;
    $desc = 'User submitted a new consultation: ' . $topic;
    $aiRemarks = "AI Routing: classification={$aiClassification}; department={$aiDepartment}; confidence={$aiResult['confidence']}; reason={$aiReason}";
    $metaStmt = $conn->prepare('UPDATE consultations SET remarks = ? WHERE id = ?');
    if ($metaStmt) {
        $metaStmt->bind_param('si', $aiRemarks, $entity_id);
        $metaStmt->execute();
        $metaStmt->close();
    }
    logUserAction($user_id, $username, $action, $action_type, $entity_type, $entity_id, $desc, 'success', json_encode([
        'topic' => $topic,
        'description' => $description,
        'preferred_datetime' => $preferred_datetime,
        'allow_email_notifications' => $allow_email_notifications,
        'ai_classification' => $aiClassification,
        'ai_department' => $aiDepartment,
        'ai_reason' => $aiReason
    ]));
    
    // Send confirmation email
    if ($user_email) {
        $subject = "Consultation Request Received - City of Valenzuela";
        $body = "Thank you for submitting your consultation request.\n\n";
        $body .= "Topic: " . $topic . "\n";
        $body .= "Preferred Date/Time: " . $preferred_datetime . "\n";
        $body .= "Submitted: " . date('F j, Y \a\t g:i A') . "\n\n";
        if ($allow_email_notifications) {
            $body .= "You have opted in to receive email updates about your consultation.\n";
            $body .= "We will notify you about your request status via email.\n\n";
        } else {
            $body .= "You will not receive email notifications about this consultation.\n\n";
        }

        if (!$aiIsConsultation) {
            $body .= "Note: Our system review did not classify this submission as a public consultation request.\n";
            $body .= "It may be routed to the appropriate office for further handling.\n\n";
        }

        $body .= "Thank you,\nCity Government of Valenzuela";
        
        $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($user_email, $subject, $body, $headers);

        if (!$aiIsConsultation) {
            sendAiRoutingNotification($user_email, $topic, $description, $aiDepartment, $aiReason);
        }
    }
    // Generate consultation documents (best-effort)
    try {
        require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';
        // create PDF; DOCX optional (disabled by default)
                        // generateConsultationDocuments((int)$entity_id, ['pdf' => true, 'docx' => false, 'created_by' => $user_id]); // Document generated only upon ORTS forwarding
    } catch (Throwable $e) {
        error_log('Document generation error: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Consultation submitted successfully.']);
} else {
    echo json_encode(['error' => 'Failed to submit consultation']);
}

$stmt->close();
