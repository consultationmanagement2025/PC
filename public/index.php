<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/consultations.php';
require_once __DIR__ . '/../DATABASE/feedback.php';
require_once __DIR__ . '/../DATABASE/announcements.php';
require_once __DIR__ . '/../config/google_oauth_config.php';

// Generate direct Google OAuth URL for citizen portal (bypasses custom google-auth.php page)
$_citizenOAuthState = bin2hex(random_bytes(16));
$_SESSION['citizen_google_oauth_state'] = $_citizenOAuthState;
$citizenGoogleOAuthUrl = getGoogleAuthUrl($_citizenOAuthState);

// Ensure required tables exist
initializeConsultationsTable();
initializeFeedbackTable();
initializeAnnouncementsTable();

// ==========================================
// AJAX API ENDPOINTS
// ==========================================
if (isset($_GET['api']) || isset($_POST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['api_action'] ?? $_GET['api'] ?? '';

    if ($action === 'get_consultation') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, title, category, description, status, created_at, end_date, type, image_path, tracking_number, views, posts_count, response_mode, survey_question, survey_option_a, survey_option_b FROM consultations WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $consultation = $res->fetch_assoc();
            
            // Increment view count
            $conn->query("UPDATE consultations SET views = views + 1 WHERE id = " . $id);
            
            // Fetch feedback / comments for this consultation
            $fStmt = $conn->prepare("SELECT id, guest_name, guest_email, category, rating, message, created_at, admin_response, responded_at, status FROM feedback WHERE consultation_id = ? ORDER BY created_at DESC");
            $fStmt->bind_param('i', $id);
            $fStmt->execute();
            $fRes = $fStmt->get_result();
            $feedback_list = [];
            while ($fRow = $fRes->fetch_assoc()) {
                $feedback_list[] = $fRow;
            }
            $fStmt->close();

            echo json_encode(['success' => true, 'data' => $consultation, 'feedback' => $feedback_list]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Consultation not found']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'submit_feedback') {
        $consultation_id = (int)($_POST['consultation_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $category = trim($_POST['category'] ?? 'suggestion');
        $rating = (int)($_POST['rating'] ?? 5);
        
        $user_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : (isset($_SESSION['full_name']) ? $_SESSION['full_name'] : trim($_POST['guest_name'] ?? 'Anonymous Citizen'));
        $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : trim($_POST['guest_email'] ?? '');
        $user_phone = trim($_POST['guest_phone'] ?? '');

        if ($consultation_id <= 0 || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Consultation ID and feedback message are required.']);
            exit;
        }

        // Verify if consultation is concluded or past end date
        $statusCheck = $conn->query("SELECT status, end_date, type FROM consultations WHERE id = $consultation_id LIMIT 1");
        $cRow = $statusCheck ? $statusCheck->fetch_assoc() : null;
        if ($cRow) {
            $stClean = strtolower(trim($cRow['status'] ?? ''));
            $endDate = !empty($cRow['end_date']) ? strtotime($cRow['end_date']) : null;
            $isPastEnd = ($endDate && $endDate < strtotime('today'));
            $isClosed = in_array($stClean, ['closed', 'completed', 'resolved', 'declined', 'forwarded_orts', 'proceeded_to_ordinance', 'rejected', 'archived', 'endorsed'], true);

            if ($isPastEnd || $isClosed) {
                echo json_encode(['success' => false, 'message' => 'This public consultation has concluded and is closed for new feedback submissions.']);
                exit;
            }
        }

        // Verify consultation type is 'admin'
        $typeCheck = $conn->query("SELECT type FROM consultations WHERE id = $consultation_id LIMIT 1");
        $typeRow = $typeCheck ? $typeCheck->fetch_assoc() : null;
        if ($typeRow && strtolower(trim($typeRow['type'])) === 'user') {
            echo json_encode(['success' => false, 'message' => 'Feedback is only accepted on official Admin Consultations, not on citizen proposals.']);
            exit;
        }

        // Check if item is an ORTS Ordinance
        $isOrtsDoc = false;
        $docRefNum = 'ORD-' . date('Y') . '-' . sprintf('%03d', $consultation_id);
        $cCheck = $conn->query("SELECT type, source_system, tracking_number, external_ref FROM consultations WHERE id = $consultation_id LIMIT 1");
        if ($cCheck && $cRow = $cCheck->fetch_assoc()) {
            $stClean = strtoupper($cRow['source_system'] ?? '');
            $tpClean = strtolower($cRow['type'] ?? '');
            if ($stClean === 'ORTS' || $tpClean === 'ordinance') {
                $isOrtsDoc = true;
                if (!empty($cRow['tracking_number'])) $docRefNum = $cRow['tracking_number'];
                elseif (!empty($cRow['external_ref'])) $docRefNum = $cRow['external_ref'];
            }
        }

        // Generate feedback tracking token
        $tracking_token = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $stmt = $conn->prepare("INSERT INTO feedback (consultation_id, guest_name, guest_email, guest_phone, rating, category, message, tracking_token, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->bind_param('isssisss', $consultation_id, $user_name, $user_email, $user_phone, $rating, $category, $message, $tracking_token);

        if ($stmt->execute()) {
            // Update posts_count in consultations
            $conn->query("UPDATE consultations SET posts_count = posts_count + 1 WHERE id = " . $consultation_id);
            require_once __DIR__ . '/../DATABASE/notifications.php';
            @createNotification(0, "💬 New Citizen Feedback Received from " . htmlspecialchars($user_name) . " ($tracking_token)", 'feedback');
            
            // Dispatch to ORTS Outbound API if ORTS Ordinance
            $ortsData = null;
            if ($isOrtsDoc) {
                if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
                    require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
                    
                    // Map feedback type to spec options: support | oppose | suggestion | general
                    $mappedType = 'general';
                    $catLower = strtolower($category);
                    if (strpos($catLower, 'support') !== false) {
                        $mappedType = 'support';
                    } elseif (strpos($catLower, 'oppose') !== false || strpos($catLower, 'concern') !== false || strpos($catLower, 'objection') !== false) {
                        $mappedType = 'oppose';
                    } elseif (strpos($catLower, 'suggest') !== false || strpos($catLower, 'recommend') !== false) {
                        $mappedType = 'suggestion';
                    }

                    if (function_exists('sendFeedbackToOrts')) {
                        $ortsData = sendFeedbackToOrts($consultation_id, $docRefNum, $message, $user_name, $mappedType);
                    } elseif (function_exists('sendOrtsEvent')) {
                        $ortsPayload = [
                            'event' => 'public_feedback_received',
                            'document_id' => $consultation_id,
                            'reference_number' => $docRefNum,
                            'tracking_number' => $docRefNum,
                            'submitter_name' => $user_name,
                            'feedback_type' => $mappedType,
                            'notes' => $message,
                            'source_system' => 'PCMS'
                        ];
                        $ortsData = sendOrtsEvent($ortsPayload);
                    }
                }
            }

            echo json_encode([
                'success' => true, 
                'message' => $isOrtsDoc ? 'Event accepted — Feedback successfully transmitted to ORTS and stored in PCMS!' : 'Thank you! Your feedback has been submitted successfully.',
                'tracking_token' => $tracking_token,
                'data' => [
                    'event' => 'public_feedback_received',
                    'document_id' => $consultation_id,
                    'reference_number' => $docRefNum,
                    'action' => 'feedback_stored',
                    'feedback_type' => $category
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving feedback: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'submit_survey_vote') {
        try {
            $survey_id = (int)($_POST['survey_id'] ?? 0);
            $option_chosen = strtolower(trim($_POST['option_chosen'] ?? ''));
            if ($option_chosen !== 'agree' && $option_chosen !== 'disagree') {
                $sRes = $conn->query("SELECT survey_option_a, survey_option_b FROM consultations WHERE id = $survey_id LIMIT 1");
                if ($sRes && $sRow = $sRes->fetch_assoc()) {
                    if (!empty($sRow['survey_option_b']) && strtolower(trim($sRow['survey_option_b'])) === strtolower(trim($_POST['option_chosen']))) {
                        $option_chosen = 'disagree';
                    } else {
                        $option_chosen = 'agree';
                    }
                } else {
                    $option_chosen = 'agree';
                }
            }

            if ($survey_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid survey vote.']);
                exit;
            }

            // Check if consultation is an Admin consultation
            $cCheck = $conn->query("SELECT type FROM consultations WHERE id = $survey_id LIMIT 1");
            $cRow = $cCheck ? $cCheck->fetch_assoc() : null;
            if ($cRow && strtolower(trim($cRow['type'])) === 'user') {
                echo json_encode(['success' => false, 'message' => 'Voting is only available on official Admin Consultations.']);
                exit;
            }

            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if ($user_id <= 0) {
                echo json_encode([
                    'success' => false,
                    'require_login' => true,
                    'message' => 'Please sign in with your Google account to cast your vote on community surveys.'
                ]);
                exit;
            }

            require_once __DIR__ . '/../DATABASE/consultations.php';
            require_once __DIR__ . '/../DATABASE/notifications.php';
            submitConsultationVote($survey_id, $user_id, $option_chosen);
            @createNotification(0, "📊 Community Survey Vote Recorded: Option '" . strtoupper($option_chosen) . "' cast for Poll #$survey_id.", 'survey');

            $stats = getConsultationVoteStats($survey_id);

            echo json_encode([
                'success' => true,
                'message' => 'Your vote has been recorded successfully!',
                'count_a' => $stats['agree_votes'],
                'count_b' => $stats['disagree_votes'],
                'total_votes' => $stats['total_votes'],
                'pct_a' => (int)$stats['agree_percent'],
                'pct_b' => (int)$stats['disagree_percent'],
                'option_voted' => $option_chosen
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'get_my_activity') {
        $user_email = trim($_SESSION['email'] ?? '');
        $user_id = (int)($_SESSION['user_id'] ?? 0);
        $user_name = trim($_SESSION['fullname'] ?? $_SESSION['full_name'] ?? '');

        $feedback = [];
        $proposals = [];

        if ($user_email !== '' || $user_id > 0 || $user_name !== '') {
            $pStmt = $conn->prepare("SELECT id, title, description, category, status, type, created_at, tracking_number, committee_assigned, assigned_to, ai_committee_brief, admin_response, remarks FROM consultations WHERE (user_id > 0 AND user_id = ?) OR (user_email = ? AND user_email != '') OR (user_name = ? AND user_name != '') ORDER BY created_at DESC LIMIT 50");
            if ($pStmt) {
                $pStmt->bind_param('iss', $user_id, $user_email, $user_name);
                $pStmt->execute();
                $pRes = $pStmt->get_result();
                while ($pRow = $pRes->fetch_assoc()) {
                    if (empty($pRow['tracking_number'])) {
                        $pRow['tracking_number'] = 'TRK-' . date('Y') . '-' . str_pad($pRow['id'], 6, '0', STR_PAD_LEFT);
                    }
                    $proposals[] = $pRow;
                }
                $pStmt->close();
            }

            $fStmt = $conn->prepare("SELECT f.id, f.consultation_id, f.category, f.message, f.created_at, f.admin_response, f.responded_at, f.status, f.tracking_token, c.title as consultation_title FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id WHERE (f.guest_email = ? AND f.guest_email != '') OR (f.guest_name = ? AND f.guest_name != '') ORDER BY f.created_at DESC LIMIT 50");
            if ($fStmt) {
                $fStmt->bind_param('ss', $user_email, $user_name);
                $fStmt->execute();
                $fRes = $fStmt->get_result();
                while ($fRow = $fRes->fetch_assoc()) {
                    $feedback[] = $fRow;
                }
                $fStmt->close();
            }
        }

        echo json_encode([
            'success' => true,
            'user_email' => $user_email,
            'feedback' => $feedback,
            'proposals' => $proposals
        ]);
        exit;
    }

    if ($action === 'track_status') {
        $code = trim($_GET['code'] ?? $_POST['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a tracking code.']);
            exit;
        }

        $codeUpper = strtoupper($code);
        $numericId = 0;
        if (preg_match('/(\d+)$/', $codeUpper, $matches)) {
            $numericId = (int)$matches[1];
        }

        // Search proposal by exact tracking_number, numeric ID, or fuzzy matching
        $pStmt = $conn->prepare("SELECT id, title, category, description, status, created_at, tracking_number, user_name, admin_response, remarks FROM consultations WHERE UPPER(tracking_number) = ? OR (id = ? AND ? > 0) OR tracking_number LIKE ? LIMIT 1");
        $likePattern = '%' . $codeUpper . '%';
        $pStmt->bind_param('siis', $codeUpper, $numericId, $numericId, $likePattern);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        if ($pRes && $pRes->num_rows > 0) {
            $data = $pRes->fetch_assoc();
            $pStmt->close();
            if (empty($data['tracking_number'])) {
                $data['tracking_number'] = 'TRK-' . date('Y') . '-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);
            }
            echo json_encode(['success' => true, 'type' => 'proposal', 'data' => $data]);
            exit;
        }
        $pStmt->close();

        // Search feedback by tracking_token
        $fStmt = $conn->prepare("SELECT f.id, f.guest_name, f.category, f.message, f.status, f.admin_response, f.responded_at, f.created_at, f.tracking_token, c.title as consultation_title FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id WHERE UPPER(f.tracking_token) = ? OR f.tracking_token LIKE ? LIMIT 1");
        $fStmt->bind_param('ss', $codeUpper, $likePattern);
        $fStmt->execute();
        $fRes = $fStmt->get_result();
        if ($fRes && $fRes->num_rows > 0) {
            $data = $fRes->fetch_assoc();
            $fStmt->close();
            echo json_encode(['success' => true, 'type' => 'feedback', 'data' => $data]);
            exit;
        }
        $fStmt->close();

        echo json_encode(['success' => false, 'message' => 'No proposal or feedback found matching tracking code: ' . htmlspecialchars($code)]);
        exit;
    }

    if ($action === 'chatbot') {
        $msg = strtolower(trim($_POST['message'] ?? ''));
        $reply = "Thank you for reaching out to the Valenzuela City Public Consultation & Management System! ";
        
        if (strpos($msg, 'submit') !== false || strpos($msg, 'proposal') !== false || strpos($msg, 'concern') !== false) {
            $reply .= "To submit a proposal or concern, scroll down to the **Submit Concern** section on this page or click 'Submit Concern' in the navigation bar. You will receive a unique tracking code (e.g. TRK-2026-XXXXX) to track legislative progress!";
        } elseif (strpos($msg, 'track') !== false || strpos($msg, 'status') !== false || strpos($msg, 'code') !== false) {
            $reply .= "You can track your submitted proposals or feedback anytime! Click **Track Status** in the top navigation bar or scroll to the status tracker, enter your TRK or FDBK code, and see real-time updates.";
        } elseif (strpos($msg, 'survey') !== false || strpos($msg, 'vote') !== false || strpos($msg, 'poll') !== false) {
            $reply .= "Active community surveys are listed under the **Community Surveys** section. You can cast your vote instantly on any open topic and view real-time public sentiment results.";
        } elseif (strpos($msg, 'hours') !== false || strpos($msg, 'contact') !== false || strpos($msg, 'office') !== false || strpos($msg, 'hall') !== false) {
            $reply .= "The Valenzuela City Legislative Office is located at Valenzuela City Hall, MacArthur Highway, Karuhatan, Valenzuela City. Office hours are Monday through Friday, 8:00 AM to 5:00 PM.";
        } else {
            $reply .= "I am your AI Legislative Assistant. I can help you find active public consultations, guide you on submitting citizen proposals, taking surveys, or tracking your concerns. What would you like to know today?";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit;
    }
}

// ==========================================
// REQUIRE CITIZEN LOGIN TO ACCESS PORTAL VIEW
// ==========================================
// If citizen is not logged in or session timed out, redirect to main Landing Page (index.php)
if (empty($_SESSION['user_id']) && empty($_SESSION['user_email']) && empty($_SESSION['citizen_logged_in'])) {
    header("Location: ../index.php?timeout=1");
    exit();
}

// ==========================================
// REGULAR PAGE SUBMISSION HANDLER
// ==========================================
$submission_success = false;
$submission_error = '';
$generated_tracking_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_consultation'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $target_area = trim($_POST['target_area'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($category) || empty($description)) {
        $submission_error = 'Please fill in all required fields (Title, Category, Description).';
    } else {
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        $raw_session_name = $_SESSION['fullname'] ?? ($_SESSION['full_name'] ?? '');
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
            $user_name = !empty($raw_session_name) ? $raw_session_name : 'Anonymous Citizen';
        }
        $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : trim($_POST['guest_email'] ?? 'citizen@valenzuela.gov.ph');

        // File upload handling
        $uploaded_file_path = null;
        if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/consultations/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }
            $original_name = basename($_FILES['file_upload']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            
            if (in_array($ext, $allowed, true)) {
                $new_filename = 'proposal_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target_path = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_path)) {
                    $uploaded_file_path = 'uploads/consultations/' . $new_filename;
                }
            }
        }

        // Generate tracking code
        $generated_tracking_number = 'TRK-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $full_description = $description;
        if (!empty($target_area)) {
            $full_description .= "\n\n[Target Area / Barangay: " . $target_area . "]";
        }

        $stmt = $conn->prepare("INSERT INTO consultations (title, category, description, type, user_name, user_email, image_path, status, tracking_number, created_at) VALUES (?, ?, ?, 'user', ?, ?, ?, 'pending', ?, NOW())");
        $stmt->bind_param('sssssss', $title, $category, $full_description, $user_name, $user_email, $uploaded_file_path, $generated_tracking_number);

        if ($stmt->execute()) {
            $submission_success = true;
            require_once __DIR__ . '/../DATABASE/notifications.php';
            @createNotification(0, "📩 New Citizen Policy Proposal Submitted by " . htmlspecialchars($user_name) . ": \"" . htmlspecialchars($title) . "\" ($generated_tracking_number)", 'consultation');
        } else {
            $submission_error = 'Failed to submit proposal: ' . $conn->error;
        }
        $stmt->close();
    }
}

// ==========================================
// DATA FETCHING FOR VIEW
// ==========================================
// Active Consultations
$category_filter = trim($_GET['category'] ?? '');
$search_query = trim($_GET['search'] ?? '');

$consultation_sql = "SELECT id, title, category, description, status, created_at, end_date, type, image_path, tracking_number, views, posts_count FROM consultations WHERE response_mode IN ('feedback', 'hybrid') AND status IN ('active', 'viewed', 'replied', 'scheduled')";
$params = [];
$types = "";

if (!empty($category_filter) && $category_filter !== 'all') {
    $consultation_sql .= " AND (LOWER(category) = ? OR LOWER(category) LIKE ?)";
    $catLower = strtolower($category_filter);
    $params[] = $catLower;
    $params[] = '%' . $catLower . '%';
    $types .= "ss";
}
if (!empty($search_query)) {
    $consultation_sql .= " AND (title LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $search_query . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}
$consultation_sql .= " ORDER BY created_at DESC LIMIT 12";

$consultations = [];
if (!empty($types)) {
    $stmt = $conn->prepare($consultation_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $consultations[] = $row;
    }
    $stmt->close();
} else {
    $res = $conn->query($consultation_sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $consultations[] = $row;
        }
    }
}

// Community Surveys
if (empty($_COOKIE['pcms_device_token'])) {
    $device_token = 'DEV-' . md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    @setcookie('pcms_device_token', $device_token, time() + (86400 * 365), '/');
} else {
    $device_token = $_COOKIE['pcms_device_token'];
}
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_email = $_SESSION['email'] ?? '';

$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$is_citizen_session = isset($_SESSION['user_id']) && ($user_role === 'citizen' || empty($user_role) || $user_role === 'user');
$current_user_name = $_SESSION['fullname'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? ($_SESSION['email'] ?? null);

// Portal isolation: only recognize sessions that belong to the citizen portal.
// If an admin/resource person is logged in (portal='admin'), treat them as guest here.
$_session_portal = $_SESSION['portal'] ?? null;
$_is_admin_portal_session = ($_session_portal === 'admin');
if ($_is_admin_portal_session) {
    // Clear citizen-side state — do NOT destroy the session (that would log them out everywhere)
    $current_user_name = null;
    $is_citizen_session = false;
}
$is_logged_in = !$_is_admin_portal_session && (!empty($_SESSION['user_id']) || !empty($_SESSION['email']) || !empty($current_user_name));

$surveys = [];
$survey_query = "SELECT id, title, category, description, survey_question, survey_option_a, survey_option_b, response_mode, status, image_path, created_at, end_date, posts_count FROM consultations WHERE response_mode IN ('survey', 'hybrid') AND status IN ('active', 'viewed', 'replied', 'scheduled') ORDER BY created_at DESC LIMIT 6";
$sRes = $conn->query($survey_query);
if ($sRes) {
    while ($sRow = $sRes->fetch_assoc()) {
        $sId = (int)$sRow['id'];
        // Get vote counts for option A and option B
        $vStats = getConsultationVoteStats($sId);
        $sRow['count_a'] = $vStats['agree_votes'];
        $sRow['count_b'] = $vStats['disagree_votes'];
        $sRow['total_votes'] = $vStats['total_votes'];
        $sRow['pct_a'] = round((float)$vStats['agree_percent'], 1);
        $sRow['pct_b'] = round((float)$vStats['disagree_percent'], 1);

        $sRow['user_vote'] = $is_logged_in ? getUserConsultationVote($sId, $user_id, $device_token, $user_email) : null;

        $surveys[] = $sRow;
    }
}

// Past / Concluded Consultations & Completed Surveys Archive
$past_items = [];
$past_sql = "SELECT id, title, category, description, survey_question, survey_option_a, survey_option_b, response_mode, status, image_path, tracking_number, created_at, end_date, views, posts_count 
            FROM consultations 
            WHERE status IN ('closed', 'completed', 'archived', 'officialized', 'passed', 'enacted', 'resolved') 
            ORDER BY created_at DESC LIMIT 12";
$pRes = $conn->query($past_sql);
if ($pRes) {
    while ($pRow = $pRes->fetch_assoc()) {
        $pId = (int)$pRow['id'];
        $vStats = getConsultationVoteStats($pId);
        $pRow['count_a'] = $vStats['agree_votes'];
        $pRow['count_b'] = $vStats['disagree_votes'];
        $pRow['total_votes'] = $vStats['total_votes'];
        $pRow['pct_a'] = round((float)$vStats['agree_percent'], 1);
        $pRow['pct_b'] = round((float)$vStats['disagree_percent'], 1);
        $past_items[] = $pRow;
    }
}

if (count($past_items) < 2) {
    $past_items[] = [
        'id' => 101,
        'title' => 'Valenzuela City Flood Control & Drainage Improvement Initiative',
        'category' => 'Infrastructure & Safety',
        'description' => 'Comprehensive public consultation on Phase 2 flood control, pumping stations, and arterial drainage widening along Polo and Malinta waterways.',
        'survey_question' => 'Do you support the proposed drainage widening ordinance and night construction schedule?',
        'survey_option_a' => 'AGREE',
        'survey_option_b' => 'DISAGREE',
        'response_mode' => 'hybrid',
        'status' => 'officialized',
        'image_path' => '../images/valenzuela-banner.png',
        'tracking_number' => 'TRK-2026-ENACTED-104',
        'created_at' => '2026-07-01 10:00:00',
        'end_date' => '2026-08-01',
        'views' => 1420,
        'posts_count' => 38,
        'pct_a' => 88.4,
        'pct_b' => 11.6,
        'total_votes' => 342,
        'count_a' => 302,
        'count_b' => 40
    ];

    $past_items[] = [
        'id' => 102,
        'title' => 'Barangay Health Center Extended Hours Ordinance',
        'category' => 'Public Health & Social Welfare',
        'description' => 'Public survey on extending outpatient clinic operations to 24/7 in primary barangay health units across District 1 & District 2.',
        'survey_question' => 'Do you support allocating municipal budget to extend Barangay Health Center operating hours?',
        'survey_option_a' => 'AGREE',
        'survey_option_b' => 'DISAGREE',
        'response_mode' => 'survey',
        'status' => 'completed',
        'image_path' => null,
        'tracking_number' => 'TRK-2026-HEALTH-88',
        'created_at' => '2026-06-15 08:30:00',
        'end_date' => '2026-07-15',
        'views' => 980,
        'posts_count' => 24,
        'pct_a' => 94.2,
        'pct_b' => 5.8,
        'total_votes' => 512,
        'count_a' => 482,
        'count_b' => 30
    ];
}

// Published Announcements
$announcements = [];
if (function_exists('getLatestAnnouncements')) {
    $announcements = getLatestAnnouncements(5);
}

// Live Dashboard Stats
$stats = [
    'active_consultations' => 0,
    'new_surveys' => 0,
    'total_citizens' => 0,
    'feedback_submitted' => 0
];

$cStatRes = $conn->query("SELECT COUNT(CASE WHEN status IN ('active', 'viewed', 'replied', 'scheduled') THEN 1 END) as active_consultations, COUNT(CASE WHEN response_mode IN ('survey', 'hybrid') AND status IN ('active', 'viewed', 'replied', 'scheduled') THEN 1 END) as new_surveys FROM consultations");
if ($cStatRes && $cRow = $cStatRes->fetch_assoc()) {
    $stats['active_consultations'] = (int)($cRow['active_consultations'] ?? 0);
    $stats['new_surveys'] = (int)($cRow['new_surveys'] ?? 0);
}

$uStatRes = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'citizen' OR role IS NULL OR role = ''");
if ($uStatRes && $uRow = $uStatRes->fetch_assoc()) {
    $stats['total_citizens'] = (int)($uRow['total_users'] ?? 0);
}

$fStatRes = $conn->query("SELECT COUNT(*) as total_feedback FROM feedback");
if ($fStatRes && $fRow = $fStatRes->fetch_assoc()) {
    $stats['feedback_submitted'] = (int)($fRow['total_feedback'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valenzuela Citizen Portal - Legislative Consultations & Engagement</title>
    <link rel="icon" type="image/png" href="../images/valenzuela-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        window.__CURRENT_USER_NAME__ = <?php echo json_encode($_SESSION['fullname'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? ''); ?>;
        window.__CURRENT_USER_EMAIL__ = <?php echo json_encode($_SESSION['email'] ?? ''); ?>;
        window.__CURRENT_USER_ID__ = <?php echo json_encode((int)($_SESSION['user_id'] ?? 0)); ?>;
        window.__IS_LOGGED_IN__ = <?php echo json_encode($is_logged_in); ?>;

        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        valenzuela: {
                            blue: '#0033a0',
                            red: '#dc2626',
                            darkblue: '#002277',
                            lightbg: '#f8fafc'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/index.css">
    <style>
        /* Remove horizontal scrollbar track while allowing smooth scroll */
        .no-scrollbar::-webkit-scrollbar,
        [class*="overflow-x"]::-webkit-scrollbar,
        [id*="container"]::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .no-scrollbar,
        [class*="overflow-x"],
        [id*="container"] {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); }
        .hero-gradient { background: linear-gradient(135deg, #0033a0 0%, #001a55 60%, #1e293b 100%); }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 51, 160, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .star-rating i { cursor: pointer; transition: color 0.2s ease; }
        .star-rating i.active { color: #f59e0b; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        body.modal-open #main-header,
        body.modal-open #main-header * {
            filter: blur(12px) brightness(0.5) !important;
            pointer-events: none !important;
            transition: filter 0.25s ease, opacity 0.25s ease;
        }
        /* Hide ugly browser horizontal scrollbar line */
        .no-scrollbar::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .no-scrollbar {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
    </style>
</head>
    <script>
        window.__IS_LOGGED_IN__ = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    </script>

    <!-- Require Login Modal -->
    <div id="require-login-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-100 text-center relative animate-fadeIn">
            <button onclick="closeRequireLoginModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <div class="w-16 h-16 bg-blue-50 text-valenzuela-blue rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl border border-blue-100 shadow-inner">
                <i class="fa-solid fa-user-lock"></i>
            </div>

            <h3 class="text-xl font-extrabold text-slate-900 mb-2">Sign In Required</h3>
            <p class="text-slate-600 text-xs sm:text-sm mb-6 leading-relaxed">
                To participate in city consultations, submit citizen feedback, or vote on community polls, please sign in with your Google / Gmail account.
            </p>

            <div class="space-y-3">
                <a href="<?php echo htmlspecialchars($citizenGoogleOAuthUrl); ?>" class="w-full flex items-center justify-center gap-3 bg-white hover:bg-slate-50 text-slate-700 font-bold text-sm border border-slate-300 px-5 py-3 rounded-xl transition-all shadow-sm hover:shadow">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                    </svg>
                    <span>Sign in with Google</span>
                </a>

                <button onclick="closeRequireLoginModal()" class="w-full text-slate-500 hover:text-slate-700 font-semibold text-xs py-2">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-24 right-5 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <!-- Main Navigation Bar - Floating Segmented Capsule Dock -->
    <header id="main-header" class="sticky top-0 z-40 w-full bg-slate-950/80 backdrop-blur-2xl border-b border-slate-800/80 shadow-2xl transition-all duration-300">
        <div class="max-w-[1440px] mx-auto px-3 sm:px-6 py-2">
            <nav class="bg-slate-900/95 text-white rounded-2xl sm:rounded-full border border-slate-800/90 shadow-xl p-2 sm:p-2.5 transition-all" id="main-nav">
            <div class="flex justify-between items-center px-2 sm:px-4">
                
                <!-- Brand / Seal -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full p-0.5 bg-gradient-to-tr from-valenzuela-red via-rose-500 to-valenzuela-blue shadow-lg group-hover:scale-105 transition-transform shrink-0">
                        <div class="w-full h-full rounded-full overflow-hidden bg-white flex items-center justify-center">
                            <img src="../images/valenzuela-logo.png" alt="Valenzuela Seal" class="w-full h-full object-cover">
                        </div>
                    </div>
                    <div class="flex flex-col justify-center">
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg sm:text-xl font-black tracking-tight flex items-baseline">
                                <span class="text-white">VALENZUELA</span>
                                <span class="bg-gradient-to-r from-red-500 to-rose-400 bg-clip-text text-transparent ml-1.5">PCMS</span>
                            </h1>
                            <span class="bg-valenzuela-red/20 text-red-300 text-[9px] sm:text-[10px] font-black tracking-widest px-2.5 py-0.5 rounded-full border border-red-500/30 uppercase hidden sm:inline-block">
                                Citizen Portal
                            </span>
                        </div>
                        <span class="text-[10px] font-semibold text-slate-400 hidden sm:block tracking-wide">City Legislative Consultations</span>
                    </div>
                </a>

                <!-- Desktop Segmented Dock Navigation Links -->
                <div class="hidden md:flex items-center bg-slate-950/80 p-1.5 rounded-full border border-slate-800/80 shadow-inner space-x-1">
                    <a href="#active-consultations" onclick="switchPortalMainTab('active');" class="group flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/90 transition-all">
                        <i class="fa-solid fa-comments text-blue-400 group-hover:scale-110 transition-transform"></i>
                        <span>Consultations</span>
                    </a>

                    <a href="#surveys" onclick="switchPortalMainTab('active');" class="group flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/90 transition-all">
                        <i class="fa-solid fa-square-poll-horizontal text-rose-400 group-hover:scale-110 transition-transform"></i>
                        <span>Surveys</span>
                    </a>

                    <a href="#past-archive" onclick="switchPortalMainTab('past');" class="group flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/90 transition-all">
                        <i class="fa-solid fa-box-archive text-amber-400 group-hover:scale-110 transition-transform"></i>
                        <span>Past Archive</span>
                    </a>

                    <?php if (!empty($announcements)): ?>
                    <a href="#announcements" class="group flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/90 transition-all">
                        <i class="fa-solid fa-bullhorn text-amber-400 group-hover:scale-110 transition-transform"></i>
                        <span>Updates</span>
                    </a>
                    <?php endif; ?>

                    <a href="#submit-consultation" onclick="openConcernModal(); return false;" class="group flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/90 transition-all">
                        <i class="fa-solid fa-paper-plane text-emerald-400 group-hover:scale-110 transition-transform"></i>
                        <span>Submit Concern</span>
                    </a>

                    <button onclick="showTrackModal()" class="group flex items-center gap-2 px-4 py-2 rounded-full text-xs font-extrabold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 border border-blue-400/30 shadow-md shadow-blue-600/20 transition-all active:scale-95 ml-1">
                        <i class="fa-solid fa-magnifying-glass text-blue-200 group-hover:rotate-12 transition-transform"></i>
                        <span>Track Status</span>
                    </button>
                </div>

                <!-- Right Authentication Actions -->
                <div class="hidden md:flex items-center gap-3">
                    <?php if ($is_logged_in): ?>
                        <div class="relative group" id="user-dropdown-container">
                            <button class="flex items-center gap-2.5 bg-slate-800/90 hover:bg-slate-800 px-3.5 py-1.5 rounded-full transition-colors border border-slate-700">
                                <div class="w-6 h-6 rounded-full bg-valenzuela-blue text-white flex items-center justify-center font-bold text-xs">
                                    <?php echo strtoupper(substr($current_user_name ?? 'C', 0, 1)); ?>
                                </div>
                                <span class="text-xs font-bold text-slate-200 max-w-[110px] truncate"><?php echo htmlspecialchars($current_user_name); ?></span>
                                <i class="fa-solid fa-chevron-down text-xs text-slate-400"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div class="absolute right-0 mt-2 w-56 bg-slate-900 text-white rounded-2xl shadow-2xl border border-slate-800 py-2 hidden group-hover:block transition-all z-50">
                                <div class="px-4 py-2.5 border-b border-slate-800">
                                    <p class="text-xs font-bold text-white"><?php echo htmlspecialchars($current_user_name); ?></p>
                                    <p class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                                </div>
                                <button onclick="showMyActivityModal()" class="w-full text-left px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 flex items-center gap-2 transition-colors">
                                    <i class="fa-solid fa-clock-history text-valenzuela-blue"></i> My Submissions & Votes
                                </button>
                                <div class="border-t border-slate-800 my-1"></div>
                                <a href="sign-out.php" class="block w-full text-left px-4 py-2.5 text-xs font-semibold text-red-400 hover:bg-red-950/30 flex items-center gap-2 transition-colors">
                                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($citizenGoogleOAuthUrl); ?>" class="flex items-center gap-2 bg-white hover:bg-slate-100 text-slate-800 font-bold text-xs px-4 py-2 rounded-full transition-all shadow-md hover:shadow-lg">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                                <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                            </svg>
                            <span>Sign in with Google</span>
                        </a>

                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center gap-3">
                    <button id="mobile-menu-btn" class="text-slate-700 hover:text-valenzuela-blue focus:outline-none p-2 rounded-lg bg-slate-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden md:hidden pb-4 space-y-2 border-t border-gray-100 mt-3 pt-3">
            <a href="#active-consultations" class="block font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-comments text-valenzuela-blue w-5"></i> Consultations
            </a>
            <a href="#surveys" class="block font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-square-poll-horizontal text-valenzuela-red w-5"></i> Surveys
            </a>
            <a href="#submit-consultation" onclick="openConcernModal(); return false;" class="block font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-paper-plane text-emerald-600 w-5"></i> Submit Concern
            </a>
            <button onclick="showTrackModal()" class="w-full text-left font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-magnifying-glass text-valenzuela-blue w-5"></i> Track Status
            </button>
            <?php if ($is_logged_in): ?>
                <button onclick="showMyActivityModal()" class="w-full text-left font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                    <i class="fa-solid fa-clock-history text-amber-500 w-5"></i> My Submissions & Votes
                </button>
            <?php endif; ?>

            <div class="pt-4 border-t border-gray-100 flex flex-col gap-2">
                <?php if ($is_logged_in): ?>
                    <div class="flex justify-between items-center px-2 py-1 bg-slate-50 rounded-lg">
                        <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($current_user_name); ?></span>
                        <a href="sign-out.php" class="text-xs font-bold text-red-600">Sign Out</a>
                    </div>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($citizenGoogleOAuthUrl); ?>" class="flex items-center justify-center gap-2 font-bold text-slate-700 bg-white border border-gray-300 py-2.5 rounded-lg shadow-sm hover:bg-slate-50 transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                        <span>Sign In</span>
                    </a>

                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

    <!-- Main Container -->
    <main class="flex-grow max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-8 space-y-8 w-full">

        <!-- Hero / Welcome Section -->
        <header class="bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 rounded-2xl sm:rounded-3xl px-5 pt-3.5 pb-4 sm:px-7 sm:pt-4 sm:pb-5 text-white shadow-xl relative overflow-hidden border border-slate-800/90 space-y-3.5">
            <!-- Subtle Background Glowing Accents -->
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-red-600/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-blue-600/15 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Top Row: Left Title & Description | Right 4 Stat Boxes in ONE SINGLE LINE -->
            <div class="z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5">
                <!-- Left Title & Subtitle -->
                <div class="max-w-xl">
                    <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                        <div class="inline-flex items-center gap-2 bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 text-xs font-bold px-3 py-1 rounded-full mb-2 backdrop-blur-md">
                            <i class="fa-solid fa-circle-check text-emerald-400"></i> Welcome back, <?php echo htmlspecialchars($current_user_name ?? 'Citizen'); ?>!
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 bg-slate-800/90 text-blue-300 text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider border border-slate-700/80">
                            <i class="fa-solid fa-building-columns text-valenzuela-red"></i> Valenzuela City Legislative Office
                        </span>
                    </div>

                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight leading-snug text-white">
                        Shape City Ordinances & Community Policies
                    </h2>
                    <p class="text-slate-300 text-xs sm:text-sm leading-relaxed font-medium mt-1.5">
                        Participate directly in local governance. Voice your thoughts on consultations, vote on surveys, and submit proposal topics.
                    </p>
                </div>

                <!-- 4 Stat Boxes in ONE SINGLE HORIZONTAL LINE -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full lg:w-auto shrink-0">
                    <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800 px-4 py-3 rounded-2xl flex items-center gap-3 shadow-md hover:border-amber-500/40 transition-all">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 font-black text-lg flex items-center justify-center border border-amber-500/20 shrink-0">
                            <?php echo $stats['active_consultations']; ?>
                        </div>
                        <div class="text-left">
                            <span class="block text-xs font-black text-white leading-none mb-0.5">Active</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Topics</span>
                        </div>
                    </div>

                    <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800 px-4 py-3 rounded-2xl flex items-center gap-3 shadow-md hover:border-emerald-500/40 transition-all">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 font-black text-lg flex items-center justify-center border border-emerald-500/20 shrink-0">
                            <?php echo $stats['new_surveys']; ?>
                        </div>
                        <div class="text-left">
                            <span class="block text-xs font-black text-white leading-none mb-0.5">Open</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Surveys</span>
                        </div>
                    </div>

                    <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800 px-4 py-3 rounded-2xl flex items-center gap-3 shadow-md hover:border-blue-500/40 transition-all">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-300 font-black text-lg flex items-center justify-center border border-blue-500/20 shrink-0">
                            <?php echo number_format($stats['total_citizens']); ?>
                        </div>
                        <div class="text-left">
                            <span class="block text-xs font-black text-white leading-none mb-0.5">Citizens</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Registered</span>
                        </div>
                    </div>

                    <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800 px-4 py-3 rounded-2xl flex items-center gap-3 shadow-md hover:border-rose-500/40 transition-all">
                        <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 font-black text-lg flex items-center justify-center border border-rose-500/20 shrink-0">
                            <?php echo number_format($stats['feedback_submitted']); ?>
                        </div>
                        <div class="text-left">
                            <span class="block text-xs font-black text-white leading-none mb-0.5">Feedback</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Submitted</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Search Tab Underneath -->
            <div class="z-10 pt-2 border-t border-slate-800/80">
                <form action="#active-consultations" method="GET" class="flex items-center gap-2 bg-slate-900/90 p-2 rounded-full border border-slate-700/90 shadow-inner focus-within:border-blue-500/60 transition-all w-full">
                    <div class="relative flex-grow">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-3 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search consultations by ordinance title or keyword..." class="w-full pl-10 pr-3 py-1.5 rounded-full bg-transparent text-white placeholder-slate-400 focus:outline-none text-xs border-none">
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-red-600 to-rose-700 hover:from-red-500 hover:to-rose-600 text-white font-extrabold px-6 py-2 rounded-full text-xs uppercase tracking-wider transition-all shrink-0 shadow-md flex items-center gap-1.5">
                        <span>Search Topics</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- City Announcements Section (If Available) -->
        <?php if (!empty($announcements)): ?>
        <section id="announcements" class="scroll-mt-24">
            <div class="bg-gradient-to-r from-amber-500/10 via-amber-400/5 to-transparent border-l-4 border-amber-500 p-4 rounded-2xl flex items-center justify-between gap-4 border border-amber-200/50 shadow-xs">
                <div class="flex items-center gap-3 overflow-hidden">
                    <span class="bg-amber-500 text-white text-[11px] font-black px-3 py-1 rounded-lg uppercase tracking-wider shrink-0 flex items-center gap-1.5 shadow-xs">
                        <i class="fa-solid fa-bullhorn"></i> Announcement
                    </span>
                    <p class="text-xs sm:text-sm font-bold text-slate-800 truncate">
                        <?php echo htmlspecialchars($announcements[0]['title']); ?> — 
                        <span class="font-normal text-slate-600"><?php echo htmlspecialchars(substr(strip_tags($announcements[0]['content']), 0, 100)); ?>...</span>
                    </p>
                </div>
                <span class="text-xs text-slate-400 font-semibold shrink-0 hidden sm:inline">
                    <?php echo date('M d, Y', strtotime($announcements[0]['created_at'])); ?>
                </span>
            </div>
        </section>
        <?php endif; ?>

        <!-- Main Portal Segmented Tab View Switcher (Active vs Past Archive) -->
        <div class="flex justify-center my-6">
            <div class="inline-flex p-1.5 bg-slate-900/90 rounded-2xl border border-slate-800 shadow-xl gap-2 backdrop-blur-md">
                <button onclick="switchPortalMainTab('active')" id="main-tab-active-btn" class="flex items-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all bg-gradient-to-r from-valenzuela-red to-red-700 text-white shadow-md cursor-pointer">
                    <i class="fa-solid fa-fire text-amber-300"></i>
                    <span>Active Consultations & Surveys</span>
                    <span class="bg-white/20 text-white text-[10px] px-2 py-0.5 rounded-full font-black"><?php echo count($consultations) + count($surveys); ?></span>
                </button>

                <button onclick="switchPortalMainTab('past')" id="main-tab-past-btn" class="flex items-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-bold text-slate-400 hover:text-white transition-all cursor-pointer">
                    <i class="fa-solid fa-box-archive text-amber-400"></i>
                    <span>Concluded Legislative Archive</span>
                    <span class="bg-slate-800 text-slate-300 text-[10px] px-2 py-0.5 rounded-full font-bold border border-slate-700"><?php echo count($past_items); ?></span>
                </button>
            </div>
        </div>

        <!-- Active Consultations & Surveys Container -->
        <div id="active-portal-container" class="space-y-12">
            <!-- Active Consultations Section -->
            <section id="active-consultations" class="scroll-mt-24">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-4">
                <div>
                    <span class="text-xs font-extrabold text-red-600 uppercase tracking-widest bg-red-50 border border-red-200 px-3 py-1 rounded-full inline-block mb-1.5">
                        <i class="fa-solid fa-comments text-red-600 mr-1"></i> Public Consultation Portal
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        Active Public Consultations
                    </h3>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">Review proposed ordinances and contribute your feedback to the City Council.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Category Filters -->
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $categories = [
                            'all' => 'All Topics',
                            'infrastructure' => 'Infrastructure',
                            'health' => 'Health & Sanitation',
                            'environment' => 'Environment',
                            'education' => 'Education',
                            'transportation' => 'Traffic & Transport',
                            'other' => 'General Governance'
                        ];
                        foreach ($categories as $key => $label):
                            $isActive = ($category_filter === $key) || (empty($category_filter) && $key === 'all');
                            $btnClass = $isActive 
                                ? 'bg-red-700 text-white font-extrabold shadow-md' 
                                : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80';
                        ?>
                            <a href="?category=<?php echo $key; ?>#active-consultations" class="<?php echo $btnClass; ?> text-xs px-4 py-2 rounded-xl transition-all">
                                <?php echo $label; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Single-Line Scrollable Consultation Cards Container -->
            <div class="relative group/slider">
                <!-- Left Floating Arrow Button -->
                <button id="consultation-prev-btn" onclick="scrollConsultations('left')" class="absolute -left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-2xl bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 hover:text-red-700 hover:bg-red-50 shadow-xl flex items-center justify-center transition-all duration-300 z-20 cursor-pointer hover:scale-110 active:scale-95 opacity-0 pointer-events-none" title="Previous Consultations">
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </button>

                <div id="consultation-cards-container" onscroll="checkSliderScroll('consultation-cards-container', 'consultation-prev-btn', 'consultation-next-btn')" class="flex flex-nowrap overflow-x-auto gap-6 pb-4 pt-1 scroll-smooth snap-x snap-mandatory no-scrollbar">
                    <?php if (empty($consultations)): ?>
                        <div class="w-full bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                            <i class="fa-solid fa-box-open text-5xl mb-3 text-slate-300"></i>
                            <h4 class="text-lg font-bold text-slate-700">No Consultations Found</h4>
                            <p class="text-sm text-slate-500 max-w-md mx-auto mt-1">There are currently no active public consultations matching your search filter. Try clearing your search parameters.</p>
                            <a href="index.php#active-consultations" class="inline-block mt-4 bg-red-700 text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-md">View All Consultations</a>
                        </div>
                    <?php else: foreach ($consultations as $c): ?>
                        <?php 
                            $cat = strtolower($c['category'] ?? 'other');
                            $badgeColors = [
                                'environment' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'infrastructure' => 'bg-red-50 text-red-700 border-red-200',
                                'health' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'education' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'transportation' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'other' => 'bg-slate-100 text-slate-700 border-slate-200'
                            ];
                            $badgeStyle = $badgeColors[$cat] ?? $badgeColors['other'];
                            $days_left = !empty($c['end_date']) ? max(1, ceil((strtotime($c['end_date']) - time()) / 86400)) : 30;
                            $tracking_code = !empty($c['tracking_number']) ? $c['tracking_number'] : ('TRK-' . str_pad($c['id'], 6, '0', STR_PAD_LEFT));
                        ?>
                        <div class="w-[310px] sm:w-[360px] md:w-[380px] shrink-0 snap-start bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col card-hover group hover:shadow-xl transition-all duration-300">
                            
                            <?php
                                $tLow = strtolower($c['title'] ?? '');
                                $cLow = strtolower($c['category'] ?? '');
                                $typeClean = strtolower($c['type'] ?? '');
                                $srcClean = strtoupper($c['source_system'] ?? '');

                                $isOrtsItem = ($typeClean === 'ordinance' || $srcClean === 'ORTS' || strpos($tLow, 'ordinance') !== false || strpos($cLow, 'orts') !== false || strpos($cLow, 'ordinance') !== false);
                                $isSurveyItem = (!$isOrtsItem) && (strpos($tLow, 'survey') !== false || strpos($tLow, 'poll') !== false || strpos($cLow, 'survey') !== false);
                            ?>
                            <div class="h-44 w-full overflow-hidden bg-slate-900 relative">
                                <?php if (!empty($c['image_path']) && file_exists(__DIR__ . '/../' . $c['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($c['image_path']); ?>" alt="Consultation Image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-r from-red-900 via-slate-900 to-indigo-950 flex items-center justify-center p-6 text-white/20">
                                        <i class="fa-solid <?php echo $isOrtsItem ? 'fa-scale-balanced text-6xl text-indigo-400/30' : ($isSurveyItem ? 'fa-square-poll-vertical text-6xl text-purple-400/30' : 'fa-scroll text-6xl text-amber-400/30'); ?>"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Upper Left Corner Badge (Ord vs Draft vs Survey) -->
                                <div class="absolute top-3 left-3 z-10">
                                    <?php if ($isOrtsItem): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-indigo-900/90 text-indigo-100 border border-indigo-400/40 backdrop-blur-md shadow-md flex items-center gap-1.5" title="Ordinance from ORTS">
                                            <i class="fa-solid fa-scale-balanced text-indigo-300"></i> Ord
                                        </span>
                                    <?php elseif ($isSurveyItem): ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-purple-900/90 text-purple-100 border border-purple-400/40 backdrop-blur-md shadow-md flex items-center gap-1.5" title="Community Survey">
                                            <i class="fa-solid fa-square-poll-vertical text-purple-300"></i> Survey
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-amber-500/95 text-white border border-amber-300/50 backdrop-blur-md shadow-md flex items-center gap-1.5" title="Draft Ordinance Idea from Admin">
                                            <i class="fa-solid fa-scroll text-amber-100"></i> Draft
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Upper Right Corner Badge (Days Remaining) -->
                                <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-extrabold text-slate-800 shadow-md z-10">
                                    <i class="fa-regular fa-clock text-red-600 mr-1"></i> <?php echo $days_left; ?>d remaining
                                </div>
                            </div>

                            <div class="p-6 flex-grow flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($c['category'] ?? 'General Governance'); ?>
                                        </span>
                                    </div>

                                    <h4 class="text-base font-extrabold text-slate-900 group-hover:text-red-700 transition-colors line-clamp-2 mb-2 leading-snug">
                                        <?php echo htmlspecialchars($c['title'] ?? 'Untitled Consultation'); ?>
                                    </h4>

                                    <p class="text-slate-600 text-xs line-clamp-3 mb-4 leading-relaxed font-medium">
                                        <?php echo htmlspecialchars($c['description'] ?? 'No detailed description provided.'); ?>
                                    </p>
                                </div>

                                <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-2">
                                    <div class="text-[11px] text-slate-500 font-semibold flex items-center gap-3">
                                        <span><i class="fa-solid fa-eye text-slate-400 mr-1"></i><?php echo (int)($c['views'] ?? 0); ?></span>
                                        <span><i class="fa-solid fa-comment-dots text-slate-400 mr-1"></i><?php echo (int)($c['posts_count'] ?? 0); ?></span>
                                    </div>
                                    <button onclick="openConsultationModal(<?php echo (int)$c['id']; ?>)" class="bg-gradient-to-r from-red-700 to-red-900 hover:from-red-800 hover:to-black text-white text-xs font-black px-4 py-2 rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer">
                                        Participate <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Right Floating Arrow Overlay Button -->
                <button id="consultation-next-btn" onclick="scrollConsultations('right')" class="absolute -right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-2xl bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 hover:text-red-700 hover:bg-red-50 shadow-xl flex items-center justify-center transition-all duration-300 z-20 cursor-pointer hover:scale-110 active:scale-95 opacity-0 pointer-events-none" title="Next Consultations">
                    <i class="fa-solid fa-chevron-right text-sm"></i>
                </button>
            </div>
        </section>

        <!-- Community Surveys Section -->
        <section id="surveys" class="scroll-mt-24 pt-6 border-t border-slate-200">
            <div class="flex justify-between items-end mb-6">
                <div>
                    <span class="text-xs font-bold text-valenzuela-red uppercase tracking-wider">Citizen Opinion Polls</span>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-square-poll-horizontal text-valenzuela-red"></i> Community Surveys & Polls
                    </h3>
                    <p class="text-slate-500 text-sm mt-1">Cast your vote on key city initiatives and view real-time public sentiment.</p>
                </div>
            </div>

            <!-- Single-Line Scrollable Survey Cards Container -->
            <div class="relative group/slider">
                <!-- Left Floating Arrow Button -->
                <button id="survey-prev-btn" onclick="scrollSurveys('left')" class="absolute -left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-2xl bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 hover:text-red-700 hover:bg-red-50 shadow-xl flex items-center justify-center transition-all duration-300 z-20 cursor-pointer hover:scale-110 active:scale-95 opacity-0 pointer-events-none" title="Previous Surveys">
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </button>

                <div id="survey-cards-container" onscroll="checkSliderScroll('survey-cards-container', 'survey-prev-btn', 'survey-next-btn')" class="flex flex-nowrap overflow-x-auto gap-6 pb-4 pt-1 scroll-smooth snap-x snap-mandatory no-scrollbar">
                    <?php if (empty($surveys)): ?>
                        <div class="w-full bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                            <i class="fa-solid fa-clipboard-list text-5xl mb-3 text-slate-300"></i>
                            <h4 class="text-lg font-bold text-slate-700">No Active Surveys</h4>
                            <p class="text-sm text-slate-500">There are currently no active community polls available.</p>
                        </div>
                    <?php else: foreach ($surveys as $index => $s): ?>
                        <?php 
                            $optA = $s['survey_option_a'] ?? 'Agree';
                            $optB = $s['survey_option_b'] ?? 'Disagree';
                            $pctA = $s['pct_a'];
                            $pctB = $s['pct_b'];
                            $totVotes = $s['total_votes'];
                            $cat = strtolower($s['category'] ?? 'other');
                            $badgeColors = [
                                'environment' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'infrastructure' => 'bg-red-50 text-red-700 border-red-200',
                                'health' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'education' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'transportation' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'other' => 'bg-rose-50 text-rose-700 border-rose-200'
                            ];
                            $badgeStyle = $badgeColors[$cat] ?? $badgeColors['other'];
                        ?>
                        <div class="w-[310px] sm:w-[360px] md:w-[380px] shrink-0 snap-start bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col card-hover group hover:shadow-xl transition-all duration-300" id="survey-card-<?php echo $s['id']; ?>">
                            
                            <!-- Top Banner Image or Dark Header -->
                            <?php if (!empty($s['image_path']) && file_exists(__DIR__ . '/../' . $s['image_path'])): ?>
                                <div class="h-44 w-full overflow-hidden bg-slate-100 relative">
                                    <img src="../<?php echo htmlspecialchars($s['image_path']); ?>" alt="Survey Image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <div class="absolute top-3 left-3 bg-emerald-500/90 text-white backdrop-blur-md px-3 py-1 rounded-full text-[10px] font-extrabold shadow-md flex items-center gap-1.5 uppercase">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> Active Poll
                                    </div>
                                    <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-extrabold text-slate-800 shadow-md">
                                        <i class="fa-solid fa-users text-slate-500 mr-1"></i> <span id="survey-total-votes-<?php echo $s['id']; ?>"><?php echo number_format($totVotes); ?></span> votes
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="h-28 w-full bg-gradient-to-r from-slate-900 via-valenzuela-blue to-slate-800 relative p-4 flex items-start justify-between text-white">
                                    <span class="bg-emerald-500/90 text-white backdrop-blur-md px-3 py-1 rounded-full text-[10px] font-extrabold shadow-md flex items-center gap-1.5 uppercase tracking-wider">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> Active Poll
                                    </span>
                                    <span class="bg-black/40 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-extrabold text-slate-200 border border-white/10 shadow-md">
                                        <i class="fa-solid fa-users text-emerald-400 mr-1"></i> <span id="survey-total-votes-<?php echo $s['id']; ?>"><?php echo number_format($totVotes); ?></span> votes
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="p-6 flex-grow flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg border <?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($s['category'] ?? 'Public Opinion Poll'); ?>
                                        </span>
                                    </div>

                                    <h4 class="text-base font-extrabold text-slate-900 group-hover:text-valenzuela-red transition-colors line-clamp-2 mb-2 leading-snug">
                                        <?php echo htmlspecialchars($s['title']); ?>
                                    </h4>

                                    <?php if (!empty($s['description'])): ?>
                                        <p class="text-slate-600 text-xs line-clamp-2 mb-3 leading-relaxed font-medium">
                                            <?php echo htmlspecialchars($s['description']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Poll Single-Line Progress Bar -->
                                    <div class="my-3 bg-slate-50/90 p-3 rounded-xl border border-slate-200/80 space-y-2">
                                        <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                                            <div class="flex items-center justify-between min-w-0 bg-emerald-50/90 px-3 py-1.5 rounded-lg border border-emerald-200/70 shadow-2xs">
                                                <span class="text-emerald-800 flex items-center gap-1.5 font-extrabold" title="Support Rate">
                                                    <i class="fa-solid fa-thumbs-up text-emerald-600 text-xs shrink-0"></i>
                                                </span>
                                                <span class="text-emerald-700 bg-emerald-100/90 px-2 py-0.5 rounded-md text-[11px] font-black shrink-0" id="survey-pct-a-<?php echo $s['id']; ?>"><?php echo $pctA; ?>%</span>
                                            </div>

                                            <div class="flex items-center justify-between min-w-0 bg-rose-50/90 px-3 py-1.5 rounded-lg border border-rose-200/70 shadow-2xs">
                                                <span class="text-rose-800 flex items-center gap-1.5 font-extrabold" title="Oppose Rate">
                                                    <i class="fa-solid fa-thumbs-down text-rose-600 text-xs shrink-0"></i>
                                                </span>
                                                <span class="text-rose-700 bg-rose-100/90 px-2 py-0.5 rounded-md text-[11px] font-black shrink-0" id="survey-pct-b-<?php echo $s['id']; ?>"><?php echo $pctB; ?>%</span>
                                            </div>
                                        </div>

                                        <div class="w-full bg-slate-200 rounded-full h-2 flex overflow-hidden p-0.5 border border-slate-200/60">
                                            <div id="survey-bar-a-<?php echo $s['id']; ?>" class="bg-emerald-500 h-full rounded-l-full transition-all duration-500" style="width: <?php echo $pctA; ?>%"></div>
                                            <div id="survey-bar-b-<?php echo $s['id']; ?>" class="bg-rose-500 h-full rounded-r-full transition-all duration-500" style="width: <?php echo $pctB; ?>%"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vote Action / Voted Status Buttons -->
                                <div id="survey-action-buttons-<?php echo $s['id']; ?>" class="mt-2 space-y-2" data-opta="<?php echo htmlspecialchars($optA); ?>" data-optb="<?php echo htmlspecialchars($optB); ?>">
                                    <?php 
                                        $userV = $is_logged_in ? strtolower(trim($s['user_vote'] ?? '')) : '';
                                        $isA = ($is_logged_in && $userV !== '' && ($userV === strtolower(trim($optA)) || $userV === 'agree'));
                                        $isB = ($is_logged_in && $userV !== '' && ($userV === strtolower(trim($optB)) || $userV === 'disagree'));

                                        $displayVoteText = $s['user_vote'] ?? '';
                                        if (strtolower($displayVoteText) === 'agree') {
                                            $displayVoteText = $optA;
                                        } elseif (strtolower($displayVoteText) === 'disagree') {
                                            $displayVoteText = $optB;
                                        }
                                    ?>
                                    <?php if ($is_logged_in && !empty($s['user_vote'])): ?>
                                        <?php if ($isB): ?>
                                            <div class="w-full bg-rose-50 text-rose-800 border border-rose-300 font-semibold py-1.5 px-3 rounded-xl text-xs flex items-center justify-between shadow-2xs">
                                                <span class="flex items-center gap-1.5 truncate pr-2">
                                                    <i class="fa-solid fa-circle-check text-rose-600 shrink-0"></i>
                                                    <span class="truncate">You voted: <strong class="font-extrabold text-rose-950"><?php echo htmlspecialchars($displayVoteText); ?></strong></span>
                                                </span>
                                                <span class="text-[9px] text-rose-700 font-semibold bg-rose-100 px-1.5 py-0.5 rounded-full shrink-0">Change</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold py-1.5 px-3 rounded-xl text-xs flex items-center justify-between shadow-2xs">
                                                <span class="flex items-center gap-1.5 truncate pr-2">
                                                    <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
                                                    <span class="truncate">You voted: <strong class="font-extrabold text-emerald-950"><?php echo htmlspecialchars($displayVoteText); ?></strong></span>
                                                </span>
                                                <span class="text-[9px] text-emerald-700 font-semibold bg-emerald-100 px-1.5 py-0.5 rounded-full shrink-0">Change</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <div class="grid grid-cols-2 gap-2">
                                        <?php 
                                            $userV = $is_logged_in ? strtolower(trim($s['user_vote'] ?? '')) : '';
                                            $isA = ($is_logged_in && $userV !== '' && $userV === strtolower(trim($optA)));
                                            $isB = ($is_logged_in && $userV !== '' && $userV === strtolower(trim($optB)));
                                        ?>
                                        <button onclick="castSurveyVote(<?php echo $s['id']; ?>, '<?php echo addslashes($optA); ?>')" class="w-full <?php echo $isA ? 'bg-emerald-600 text-white border-emerald-700 font-extrabold shadow-sm' : 'bg-emerald-50/90 hover:bg-emerald-600 hover:text-white text-emerald-800 border-emerald-200/80 font-bold'; ?> border py-2 px-2.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 min-w-0" title="<?php echo htmlspecialchars($optA); ?>">
                                            <i class="fa-solid <?php echo $isA ? 'fa-check-circle' : 'fa-thumbs-up'; ?> text-xs shrink-0"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($optA); ?></span>
                                        </button>

                                        <button onclick="castSurveyVote(<?php echo $s['id']; ?>, '<?php echo addslashes($optB); ?>')" class="w-full <?php echo $isB ? 'bg-red-600 text-white border-red-700 font-extrabold shadow-sm' : 'bg-rose-50/90 hover:bg-red-600 hover:text-white text-rose-800 border-rose-200/80 font-bold'; ?> border py-2 px-2.5 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5 min-w-0" title="<?php echo htmlspecialchars($optB); ?>">
                                            <i class="fa-solid <?php echo $isB ? 'fa-check-circle' : 'fa-thumbs-down'; ?> text-xs shrink-0"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($optB); ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Right Floating Arrow Overlay Button -->
                <button id="survey-next-btn" onclick="scrollSurveys('right')" class="absolute -right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-2xl bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 hover:text-red-700 hover:bg-red-50 shadow-xl flex items-center justify-center transition-all duration-300 z-20 cursor-pointer hover:scale-110 active:scale-95 opacity-0 pointer-events-none" title="Next Surveys">
                    <i class="fa-solid fa-chevron-right text-sm"></i>
                </button>
            </div>
        </section>
        </div>
        <!-- END Active Consultations & Surveys Container -->

        <!-- Concluded Legislative Archive Container -->
        <div id="past-portal-container" class="hidden">
            <section id="past-archive" class="max-w-[1440px] mx-auto px-4 sm:px-6 py-12">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-box-archive text-amber-600"></i> Municipal Legislative Archive
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 flex items-center gap-2">
                            📁 Past Consultations & Concluded Polls
                        </h3>
                        <p class="text-slate-500 text-xs sm:text-sm mt-1 max-w-2xl">
                            Review concluded public consultations, historical survey voting outcomes, and city ordinances officialized through citizen participation.
                        </p>
                    </div>

                    <!-- Archive Category Filter Tabs -->
                    <div class="flex items-center gap-1.5 bg-slate-100 p-1.5 rounded-2xl border border-slate-200 shrink-0">
                        <button onclick="filterPastArchive('all')" id="past-tab-all" class="past-tab-btn px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-white text-slate-900 shadow-xs border border-slate-200 transition-all">All Archive</button>
                        <button onclick="filterPastArchive('consultation')" id="past-tab-consultation" class="past-tab-btn px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">Consultations</button>
                        <button onclick="filterPastArchive('survey')" id="past-tab-survey" class="past-tab-btn px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 transition-all">Concluded Polls</button>
                    </div>
                </div>

                <!-- Single-Line Scrollable Past Items Container -->
                <div class="relative group/slider">
                    <!-- Left Floating Arrow Button -->
                    <button id="past-prev-btn" onclick="scrollPastArchive('left')" class="absolute -left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-2xl bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 hover:text-amber-700 hover:bg-amber-50 shadow-xl flex items-center justify-center transition-all duration-300 z-20 cursor-pointer hover:scale-110 active:scale-95 opacity-0 pointer-events-none" title="Previous Archive">
                        <i class="fa-solid fa-chevron-left text-sm"></i>
                    </button>

                    <div id="past-cards-container" onscroll="checkSliderScroll('past-cards-container', 'past-prev-btn', 'past-next-btn')" class="flex flex-nowrap overflow-x-auto gap-6 pb-4 pt-1 scroll-smooth snap-x snap-mandatory no-scrollbar">
                        <?php foreach ($past_items as $p): 
                            $mode = strtolower(trim($p['response_mode'] ?? ''));
                            $isSurveyType = ($mode === 'survey' || !empty($p['survey_question']));
                            $typeClass = $isSurveyType ? 'past-type-survey' : 'past-type-consultation';
                            $statusStr = strtoupper($p['status'] ?? 'CLOSED');
                            $optA = $p['survey_option_a'] ?? 'AGREE';
                            $optB = $p['survey_option_b'] ?? 'DISAGREE';
                            $pctA = (float)($p['pct_a'] ?? 50);
                            $pctB = (float)($p['pct_b'] ?? 50);
                            $totalVotes = (int)($p['total_votes'] ?? 0);
                        ?>
                            <div class="w-[310px] sm:w-[360px] md:w-[380px] shrink-0 snap-start past-archive-card <?php echo $typeClass; ?> bg-white rounded-3xl overflow-hidden border border-slate-200/90 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative group">
                                
                                <!-- Top Image / Header Banner -->
                                <div class="relative h-44 bg-slate-900 overflow-hidden shrink-0">
                                    <?php if (!empty($p['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="Archive Banner" class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950 p-6 flex flex-col justify-between">
                                            <div class="flex justify-between items-start">
                                                <span class="bg-amber-500/20 text-amber-300 text-[10px] font-extrabold px-2.5 py-1 rounded-md border border-amber-500/30 uppercase tracking-wider">
                                                    <i class="fa-solid fa-box-archive mr-1"></i> Archived Record
                                                </span>
                                                <span class="text-[10px] font-mono text-slate-400">
                                                    <?php echo htmlspecialchars($p['tracking_number'] ?? ('TRK-' . $p['id'])); ?>
                                                </span>
                                            </div>
                                            <h4 class="text-white font-extrabold text-lg line-clamp-2 leading-tight">
                                                <?php echo htmlspecialchars($p['title']); ?>
                                            </h4>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Status Ribbon Badge -->
                                    <div class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-amber-400 border border-amber-500/30 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shadow-md">
                                        <i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i> <?php echo $statusStr === 'OFFICIALIZED' ? 'Enacted Into City Ordinance' : 'Concluded & Archived'; ?>
                                    </div>

                                    <div class="absolute bottom-3 right-3 bg-slate-900/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg backdrop-blur-sm border border-slate-700">
                                        <i class="fa-regular fa-calendar-check mr-1 text-slate-400"></i> Ended: <?php echo date('M d, Y', strtotime($p['end_date'] ?: $p['created_at'])); ?>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="p-6 flex-grow flex flex-col justify-between space-y-4">
                                    <div>
                                        <div class="flex justify-between items-center text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                                            <span>Category: <strong class="text-slate-700"><?php echo htmlspecialchars($p['category'] ?? 'General Governance'); ?></strong></span>
                                            <span>Code: <strong class="font-mono text-slate-700"><?php echo htmlspecialchars($p['tracking_number'] ?? ('TRK-' . $p['id'])); ?></strong></span>
                                        </div>

                                        <h4 class="text-base font-extrabold text-slate-900 leading-snug mb-2 line-clamp-2">
                                            <?php echo htmlspecialchars($p['title']); ?>
                                        </h4>

                                        <p class="text-slate-600 text-xs leading-relaxed line-clamp-3 font-medium mb-3">
                                            <?php echo htmlspecialchars($p['description']); ?>
                                        </p>

                                        <?php if ($isSurveyType): ?>
                                            <!-- Historical Survey Results Bar -->
                                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 space-y-2">
                                                <div class="text-[11px] font-bold text-slate-700 flex justify-between">
                                                    <span>Final Poll Result (<?php echo number_format($totalVotes); ?> Votes)</span>
                                                    <span class="text-amber-700 font-extrabold">Closed</span>
                                                </div>
                                                <div class="flex justify-between items-center text-xs font-extrabold">
                                                    <span class="text-emerald-700 flex items-center gap-1">
                                                        <i class="fa-solid fa-thumbs-up text-emerald-600 text-[10px]"></i> <?php echo htmlspecialchars($optA); ?> (<?php echo $pctA; ?>%)
                                                    </span>
                                                    <span class="text-rose-700 flex items-center gap-1">
                                                        <?php echo htmlspecialchars($optB); ?> (<?php echo $pctB; ?>%) <i class="fa-solid fa-thumbs-down text-rose-600 text-[10px]"></i>
                                                    </span>
                                                </div>
                                                <div class="w-full bg-slate-200 rounded-full h-2 flex overflow-hidden p-0.5 border border-slate-200/60">
                                                    <div class="bg-emerald-500 h-full rounded-l-full" style="width: <?php echo $pctA; ?>%"></div>
                                                    <div class="bg-rose-500 h-full rounded-r-full" style="width: <?php echo $pctB; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Footer Actions -->
                                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                        <span class="text-[11px] text-slate-500 font-medium">
                                            <i class="fa-solid fa-comments text-valenzuela-blue mr-1"></i> <?php echo (int)($p['posts_count'] ?? 0); ?> Citizen Reports Logged
                                        </span>
                                        <button onclick="openConsultationModal(<?php echo $p['id']; ?>)" class="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold px-3.5 py-1.5 rounded-xl text-xs transition-colors flex items-center gap-1.5">
                                            <i class="fa-solid fa-folder-open text-amber-600"></i> View Record
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Right Floating Arrow Overlay Button -->
                    <button id="past-next-btn" onclick="scrollPastArchive('right')" class="absolute -right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-2xl bg-white/95 backdrop-blur-md border border-slate-200/90 text-slate-700 hover:text-amber-700 hover:bg-amber-50 shadow-xl flex items-center justify-center transition-all duration-300 z-20 cursor-pointer hover:scale-110 active:scale-95 opacity-0 pointer-events-none" title="Next Archive">
                        <i class="fa-solid fa-chevron-right text-sm"></i>
                    </button>
                </div>
            </section>
        </div>
        <!-- END Concluded Legislative Archive Container -->

    <!-- Floating Feedback & Concern Widget (Bottom Left) -->
    <div class="fixed bottom-6 left-6 z-40 flex flex-col items-start select-none">
        <!-- Floating Tooltip / Speech Bubble Callout -->
        <div id="concern-floating-tooltip" class="mb-3 bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded-2xl shadow-2xl border border-slate-700/80 flex items-center gap-2 animate-bounce">
            <span class="w-2 h-2 rounded-full bg-valenzuela-red animate-ping shrink-0"></span>
            <span>Submit your concern here! 💬</span>
            <button onclick="document.getElementById('concern-floating-tooltip').classList.add('hidden')" class="text-slate-400 hover:text-white ml-1 text-xs p-0.5" title="Dismiss">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Floating Action Button -->
        <button onclick="openConcernModal()" class="group bg-gradient-to-r from-valenzuela-red to-red-700 hover:from-red-700 hover:to-valenzuela-red text-white shadow-2xl rounded-full px-5 py-3.5 flex items-center gap-3 font-extrabold text-xs sm:text-sm transition-all hover:scale-105 border border-white/20 active:scale-95">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white shrink-0 group-hover:rotate-12 transition-transform">
                <i class="fa-solid fa-paper-plane text-sm"></i>
            </div>
            <span class="tracking-wide">Submit Concern</span>
        </button>
    </div>

    <!-- Submit Concern / Ordinance Proposal Floating Modal -->
    <div id="submit-concern-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] flex items-center justify-center p-3 sm:p-5 overflow-y-auto hidden">
        <div id="submit-consultation" class="bg-white rounded-3xl max-w-4xl w-full overflow-hidden shadow-2xl border border-slate-200 relative max-h-[92vh] flex flex-col animate-fadeIn">
            
            <!-- Modal Top Header -->
            <div class="bg-slate-900 p-6 sm:p-8 text-white relative border-b border-slate-800 shrink-0">
                <button onclick="closeConcernModal()" class="absolute top-5 right-5 w-9 h-9 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
                <div class="max-w-2xl">
                    <span class="inline-block bg-valenzuela-red text-white text-[10px] sm:text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-2.5">
                        Citizen Legislative Proposal
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-extrabold leading-tight">
                        Submit a Concern or Ordinance Proposal
                    </h3>
                    <p class="text-slate-300 text-xs sm:text-sm leading-relaxed mt-2">
                        Do you have a suggestion for local policy or a community issue in your Barangay? Submit your proposal directly to the Legislative Office for review and public consultation.
                    </p>
                </div>
            </div>

            <!-- Modal Scrollable Content Form -->
            <div class="p-6 sm:p-8 overflow-y-auto flex-grow text-slate-800 bg-slate-50/50">
                <?php if (!empty($submission_success)): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-6 rounded-2xl mb-6">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-circle-check text-emerald-600 text-2xl mt-0.5"></i>
                            <div>
                                <h4 class="text-base font-bold">Proposal Submitted Successfully!</h4>
                                <p class="text-sm mt-1 text-emerald-700">Thank you for contributing to Valenzuela City governance. Your tracking code is:</p>
                                <div class="mt-3 inline-flex items-center gap-2 bg-white px-4 py-2 rounded-xl border border-emerald-300 shadow-sm">
                                    <span class="font-mono text-base font-bold text-valenzuela-blue"><?php echo htmlspecialchars($generated_tracking_number); ?></span>
                                    <button onclick="copyToClipboard('<?php echo htmlspecialchars($generated_tracking_number); ?>')" class="text-xs text-slate-500 hover:text-valenzuela-blue font-semibold">
                                        <i class="fa-regular fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($submission_error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-lg"></i>
                        <span><?php echo htmlspecialchars($submission_error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-5 bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-xs">
                    <input type="hidden" name="submit_consultation" value="1">

                    <div>
                        <label for="title" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Proposal Title / Concern Subject <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" name="title" required placeholder="e.g. Solar Street Lighting Ordinance for Public Parks" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all bg-white">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select id="category" name="category" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all bg-white">
                                <option value="" disabled selected>Select category</option>
                                <option value="infrastructure">Infrastructure & Public Works</option>
                                <option value="health">Health & Sanitation</option>
                                <option value="environment">Environment & Waste Management</option>
                                <option value="education">Education & Youth</option>
                                <option value="transportation">Traffic & Public Transport</option>
                                <option value="governance">Governance & Transparency</option>
                                <option value="other">Other Community Issue</option>
                            </select>
                        </div>
                        <div>
                            <label for="target_area" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Target Barangay / Area
                            </label>
                            <input type="text" id="target_area" name="target_area" placeholder="e.g. Brgy. Malinta, Karuhatan, Citywide" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all bg-white">
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Detailed Description & Proposed Solution <span class="text-red-500">*</span>
                        </label>
                        <textarea id="description" name="description" rows="4" required placeholder="Explain the community concern, why legislative action is needed, and how it will benefit Valenzuelanos..." class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all resize-none bg-white"></textarea>
                    </div>

                    <!-- User Info Fields if Guest -->
                    <?php if (!$is_logged_in): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Your Full Name</label>
                            <input type="text" name="guest_name" required placeholder="Juan Dela Cruz" class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Your Email Address</label>
                            <input type="email" name="guest_email" required placeholder="juan@example.com" class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Attach Documents / Supporting Evidence (Optional)
                        </label>
                        <div class="border-2 border-dashed border-slate-300 rounded-xl p-5 text-center hover:bg-slate-50 transition-colors cursor-pointer bg-white" onclick="document.getElementById('file-upload-input').click()">
                            <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                            <p class="text-xs text-slate-600 font-semibold">Click to upload files or drag & drop</p>
                            <p class="text-[11px] text-slate-400 mt-1">PDF, DOCX, PNG, JPG up to 10MB</p>
                            <input type="file" id="file-upload-input" name="file_upload" class="hidden" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
                        </div>
                        <div id="selected-file-name" class="text-xs text-emerald-600 font-semibold mt-2 hidden"></div>
                    </div>

                    <button type="submit" class="w-full bg-valenzuela-red hover:bg-red-700 text-white font-bold py-3.5 px-6 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Submit Legislative Proposal
                    </button>
                </form>
            </div>
        </div>
    </div>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 text-slate-400 py-12 border-t border-slate-800 mt-20">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <div class="md:col-span-2">
                    <div class="flex items-center gap-2 mb-3">
                        <img src="../images/valenzuela-logo.png" alt="Valenzuela Seal" class="w-8 h-8 opacity-90">
                        <span class="text-lg font-black text-white">VALENZUELA <span class="text-valenzuela-red">PCMS</span></span>
                    </div>
                    <p class="text-xs text-slate-400 max-w-sm leading-relaxed mb-4">
                        The Official Public Consultation and Management System of the City Government of Valenzuela. Empowering citizens through participatory legislative policy making.
                    </p>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3">Quick Navigation</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#active-consultations" class="hover:text-white transition-colors">Active Consultations</a></li>
                        <li><a href="#surveys" class="hover:text-white transition-colors">Community Surveys</a></li>
                        <li><a href="#submit-consultation" class="hover:text-white transition-colors">Submit Proposal</a></li>
                        <li><button onclick="showTrackModal()" class="hover:text-white transition-colors text-left">Track Code Status</button></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3">City Legislative Office</h4>
                    <ul class="space-y-2 text-xs text-slate-400">
                        <li><i class="fa-solid fa-location-dot w-4 text-valenzuela-red"></i> Valenzuela City Hall, MacArthur Highway</li>
                        <li><i class="fa-solid fa-envelope w-4 text-valenzuela-blue"></i> pcms@valenzuela.gov.ph</li>
                        <li><i class="fa-solid fa-phone w-4 text-emerald-500"></i> (02) 8352-1000</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-900 pt-6 text-[11px] text-center text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2">
                <span>&copy; <?php echo date('Y'); ?> Local Legislative Records & Public Consultation Management System. All rights reserved.</span>
                <span>Republic of the Philippines • City of Valenzuela</span>
            </div>
        </div>
    </footer>

    <!-- ========================================== -->
    <!-- MODALS -->
    <!-- ========================================== -->

    <!-- Consultation Detail & Feedback Modal -->
    <div id="consultation-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto relative border border-slate-200">
            <button onclick="closeConsultationModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="p-6 sm:p-8 border-b border-slate-100 space-y-3">
                <span id="modal-category" class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 inline-block"></span>
                
                <!-- ORTS Document Provenance Box -->
                <div id="modal-orts-ref-card" class="hidden bg-gradient-to-r from-indigo-900 via-slate-900 to-indigo-950 text-white p-4 rounded-2xl border border-indigo-700 shadow-xs space-y-1">
                    <div class="flex justify-between items-center text-[10px] uppercase font-bold text-indigo-300 tracking-wider">
                        <span><i class="fa-solid fa-scale-balanced mr-1"></i> ORTS Interconnected Legislative File</span>
                        <span class="px-2 py-0.5 rounded bg-indigo-500/20 border border-indigo-400/30 text-indigo-200">ORTS Live Synced</span>
                    </div>
                    <div class="flex items-center gap-4 text-xs pt-0.5">
                        <span>Document ID: <strong id="orts-doc-id" class="font-mono text-white">#104</strong></span>
                        <span class="text-indigo-400">|</span>
                        <span>Ref Number: <strong id="orts-ref-num" class="font-mono text-indigo-200">ORD-2025-001</strong></span>
                    </div>
                </div>

                <h3 id="modal-title" class="text-2xl font-black text-slate-900 pt-1"></h3>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                    <span><i class="fa-solid fa-barcode text-valenzuela-red"></i> Code: <strong id="modal-code" class="font-mono text-slate-700"></strong></span>
                    <span><i class="fa-regular fa-clock text-valenzuela-blue"></i> End Date: <strong id="modal-end-date" class="text-slate-700"></strong></span>
                </div>
                <div id="modal-description" class="text-slate-700 text-sm leading-relaxed bg-slate-50 p-4 rounded-2xl border border-slate-100 whitespace-pre-line"></div>
            </div>

            <!-- Feedback List & Submission Area -->
            <div class="p-6 sm:p-8 space-y-6">
                <div>
                    <h4 class="text-base font-bold text-slate-900 flex items-center gap-2 mb-4">
                        <i class="fa-solid fa-comments text-valenzuela-blue"></i> Citizen Feedback & Discussion (<span id="modal-feedback-count">0</span>)
                    </h4>
                    <div id="modal-feedback-list" class="space-y-3 max-h-60 overflow-y-auto pr-2">
                        <!-- Dynamic Comments Loading -->
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100">
                    <!-- Concluded / Closed Consultation Banner -->
                    <div id="concluded-consultation-banner" class="hidden p-5 bg-amber-50/90 border border-amber-200/90 rounded-2xl text-amber-900 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 border border-amber-200/80 flex items-center justify-center shrink-0 text-base font-bold">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div>
                                <h4 class="font-black text-xs text-amber-950 uppercase tracking-wider">Public Consultation Concluded</h4>
                                <p class="text-xs text-amber-800 font-medium mt-0.5 leading-relaxed">This consultation survey has concluded. Submissions are closed and public feedback is available for viewing only.</p>
                            </div>
                        </div>
                        <a id="concluded-download-pdf-btn" href="#" target="_blank" class="shrink-0 px-4 py-2.5 bg-amber-700 hover:bg-amber-800 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-2 border border-amber-800 cursor-pointer no-underline">
                            <i class="fa-solid fa-file-pdf"></i> Download PDF Summary
                        </a>
                    </div>

                    <!-- Open Feedback Submission Form Wrapper -->
                    <div id="feedback-submission-wrapper">
                        <h4 class="text-sm font-bold text-slate-900 mb-3">Submit Your Voice & Rating</h4>
                        <form id="feedback-form" onsubmit="handleFeedbackSubmit(event)" class="space-y-4">
                            <input type="hidden" id="modal-consultation-id" name="consultation_id" value="">

                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-slate-700">Rating:</span>
                                <div class="star-rating flex items-center gap-1 text-slate-300 text-lg" id="star-rating-picker">
                                    <i class="fa-solid fa-star active" data-rating="1"></i>
                                    <i class="fa-solid fa-star active" data-rating="2"></i>
                                    <i class="fa-solid fa-star active" data-rating="3"></i>
                                    <i class="fa-solid fa-star active" data-rating="4"></i>
                                    <i class="fa-solid fa-star active" data-rating="5"></i>
                                </div>
                                <input type="hidden" id="feedback-rating" name="rating" value="5">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Feedback Type</label>
                                    <select id="feedback-category" name="category" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300">
                                        <option value="suggestion">Suggestion / Improvement</option>
                                        <option value="concern">Concern / Objection</option>
                                        <option value="question">Inquiry / Question</option>
                                        <option value="support">Full Support</option>
                                    </select>
                                </div>
                                <?php if (!$is_logged_in): ?>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Your Name</label>
                                    <input type="text" id="feedback-name" name="guest_name" placeholder="Citizen Name" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300">
                                </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Your Detailed Feedback</label>
                                <textarea id="feedback-message" name="message" rows="3" required placeholder="State your recommendations or comments regarding this consultation topic..." class="w-full px-4 py-2.5 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue outline-none resize-none"></textarea>
                            </div>

                            <button type="submit" class="w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm">
                                Submit Feedback & Voice
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Track Submission Status Modal -->
    <div id="track-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200">
            <button onclick="closeTrackModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="text-center mb-6">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-valenzuela-blue mx-auto flex items-center justify-center mb-3">
                    <i class="fa-solid fa-barcode text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Track Submission Status</h3>
                <p class="text-xs text-slate-500 mt-1">Enter your TRK or FDBK tracking code to check real-time progress.</p>
            </div>

            <div class="space-y-4">
                <div class="flex gap-2">
                    <input type="text" id="track-code-input" placeholder="e.g. TRK-2026-A1B2C3 or FDBK-..." class="flex-grow px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue outline-none font-mono">
                    <button onclick="performTrackLookup()" class="bg-valenzuela-blue hover:bg-blue-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-colors shrink-0">
                        Track
                    </button>
                </div>

                <div id="track-results-container" class="hidden pt-4 border-t border-slate-100 text-left">
                    <!-- Dynamic Track Results -->
                </div>
            </div>
        </div>
    </div>

    <!-- Vote Success Confirmation Modal -->
    <div id="vote-success-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 text-center border border-slate-100 relative animate-in fade-in duration-200">
            <button onclick="closeVoteSuccessModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>

            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 border border-emerald-200 shadow-sm">
                <i class="fa-solid fa-check"></i>
            </div>

            <h3 class="text-xl font-extrabold text-slate-900 mb-2">Vote Cast Successfully!</h3>
            <p class="text-xs sm:text-sm text-slate-600 mb-6 leading-relaxed">
                Thank you for participating in Valenzuela's Community Survey. Your vote for <strong id="vote-success-option" class="text-valenzuela-blue">Option</strong> has been recorded and submitted to the Public Feedback Queue.
            </p>

            <button onclick="closeVoteSuccessModal()" class="w-full bg-valenzuela-red hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl text-xs sm:text-sm transition-all shadow-md">
                Done / Close
            </button>
        </div>
    </div>

    <!-- Change Vote Confirmation Modal -->
    <div id="change-vote-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 text-center relative border border-slate-200">
            <button onclick="closeChangeVoteModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>

            <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 border border-amber-200 shadow-sm">
                <i class="fa-solid fa-rotate text-2xl"></i>
            </div>

            <h3 class="text-xl font-extrabold text-slate-900 mb-2">Change Your Vote?</h3>
            <p class="text-xs sm:text-sm text-slate-600 mb-6 leading-relaxed">
                You previously voted <strong id="change-vote-prev" class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">Option A</strong> on this survey. Would you like to change your vote to <strong id="change-vote-new" class="text-valenzuela-blue bg-blue-50 px-2 py-0.5 rounded border border-blue-200">Option B</strong>?
            </p>

            <div class="space-y-3">
                <button onclick="executeChangeVote()" class="w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-3 px-6 rounded-xl text-xs sm:text-sm transition-all shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Yes, Change My Vote
                </button>
                <button onclick="closeChangeVoteModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 px-6 rounded-xl text-xs sm:text-sm transition-colors">
                    Keep Existing Vote
                </button>
            </div>
        </div>
    </div>

    <!-- My Submissions Activity Modal -->
    <div id="my-activity-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-8 relative border border-slate-200 max-h-[85vh] overflow-y-auto">
            <button onclick="closeMyActivityModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-clock-history text-valenzuela-blue"></i> My Submissions & Legislative History
            </h3>

            <div id="my-activity-content" class="space-y-4">
                <div class="text-center py-6 text-slate-400">Loading your history...</div>
            </div>
        </div>
    </div>

    <!-- Floating AI Citizen Assistant Widget -->
    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end">
        <div id="chatbot-drawer" class="hidden mb-4 w-80 sm:w-96 bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col h-[420px] transition-all">
            <div class="bg-valenzuela-blue p-4 text-white flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-robot text-sm"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold">Valenzuela AI Assistant</h4>
                        <span class="text-[10px] text-blue-200">Legislative & Citizen Guide</span>
                    </div>
                </div>
                <button onclick="toggleChatbot()" class="text-white/80 hover:text-white text-base">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div id="chatbot-messages" class="flex-grow p-4 overflow-y-auto space-y-3 text-xs">
                <div class="bg-slate-100 text-slate-800 p-3 rounded-2xl max-w-[85%]">
                    Hello! I am your Valenzuela Legislative AI Assistant. How can I assist you with consultations, submitting proposals, or tracking codes today?
                </div>
            </div>

            <div class="p-3 border-t border-slate-100 flex gap-2">
                <input type="text" id="chatbot-input" onkeypress="if(event.key==='Enter') sendChatMessage()" placeholder="Ask a question..." class="flex-grow px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:border-valenzuela-blue">
                <button onclick="sendChatMessage()" class="bg-valenzuela-red text-white px-3 py-2 rounded-xl text-xs font-bold hover:bg-red-700 transition-colors">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>

        <button onclick="toggleChatbot()" class="w-14 h-14 rounded-full bg-valenzuela-red hover:bg-red-700 text-white shadow-2xl flex items-center justify-center text-xl transition-all hover:scale-110">
            <i class="fa-solid fa-robot"></i>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- JAVASCRIPT LOGIC -->
    <!-- ========================================== -->
    <script src="../assets/js/index.js"></script>
    <script>
        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
        // Star rating picker logic
        document.querySelectorAll('#star-rating-picker i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                document.getElementById('feedback-rating').value = rating;
                document.querySelectorAll('#star-rating-picker i').forEach(s => {
                    if (s.getAttribute('data-rating') <= rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });

        // Toast notifications helper
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-slate-900 text-white border-emerald-500' : 'bg-red-600 text-white border-white';
            toast.className = `px-4 py-3 rounded-2xl shadow-xl text-xs font-semibold border-l-4 flex items-center gap-2 pointer-events-auto transition-all transform translate-y-2 opacity-0 ${bgClass}`;
            toast.innerHTML = `<i class="fa-solid ${type==='success'?'fa-circle-check':'fa-circle-exclamation'}"></i> <span>${message}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-y-2', 'opacity-0');
            }, 50);

            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function anonymizeName(name) {
            if (!name || typeof name !== 'string') return 'Anonymous Citizen';
            const trimmed = name.trim();
            if (!trimmed || trimmed.toLowerCase() === 'anonymous' || trimmed.toLowerCase() === 'anonymous citizen') {
                return 'Anonymous Citizen';
            }

            return trimmed.split(/\s+/).map(part => {
                if (part.length <= 2) return part.charAt(0) + '*';
                if (part.length === 3) return part.slice(0, 2) + '*';
                return part.slice(0, 3) + '*'.repeat(part.length - 3);
            }).join(' ');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            showToast('Tracking Code copied to clipboard: ' + text);
        }

        function checkCloseBodyModalState() {
            const openModals = document.querySelectorAll('#submit-concern-modal:not(.hidden), #consultation-modal:not(.hidden), #track-modal:not(.hidden), #my-activity-modal:not(.hidden), #require-login-modal:not(.hidden), #vote-success-modal:not(.hidden), #change-vote-modal:not(.hidden)');
            if (openModals.length === 0) {
                document.body.classList.remove('overflow-hidden', 'modal-open');
                document.body.style.overflow = 'auto';
            }
        }

        function openConcernModal() {
            const m = document.getElementById('submit-concern-modal');
            if (m) {
                m.classList.remove('hidden');
                document.body.classList.add('overflow-hidden', 'modal-open');
            }
        }

        function closeConcernModal() {
            const m = document.getElementById('submit-concern-modal');
            if (m) {
                m.classList.add('hidden');
            }
            checkCloseBodyModalState();
        }

        <?php if (!empty($submission_success) || !empty($submission_error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openConcernModal();
        });
        <?php endif; ?>

        function showRequireLoginModal() {
            const m = document.getElementById('require-login-modal');
            if (m) {
                m.classList.remove('hidden');
                document.body.classList.add('overflow-hidden', 'modal-open');
            }
        }

        function closeRequireLoginModal() {
            const m = document.getElementById('require-login-modal');
            if (m) {
                m.classList.add('hidden');
            }
            checkCloseBodyModalState();
        }

        // Community Survey Voting Functionality
        let pendingChangeVote = null;

        function castSurveyVote(surveyId, optionChosen, confirmChange = false) {
            if (!window.__IS_LOGGED_IN__) {
                showRequireLoginModal();
                return;
            }

            const formData = new FormData();
            formData.append('api_action', 'submit_survey_vote');
            formData.append('survey_id', surveyId);
            formData.append('option_chosen', optionChosen);
            if (confirmChange) {
                formData.append('confirm_change', '1');
            }

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.require_login) {
                    showRequireLoginModal();
                    return;
                }
                if (data.success) {
                    closeChangeVoteModal();

                    const pctA = document.getElementById('survey-pct-a-' + surveyId);
                    const barA = document.getElementById('survey-bar-a-' + surveyId);
                    const pctB = document.getElementById('survey-pct-b-' + surveyId);
                    const barB = document.getElementById('survey-bar-b-' + surveyId);
                    const totalVotesEl = document.getElementById('survey-total-votes-' + surveyId);

                    if (pctA && barA && data.pct_a !== undefined) {
                        pctA.textContent = data.pct_a + '%';
                        barA.style.width = data.pct_a + '%';
                    }
                    if (pctB && barB && data.pct_b !== undefined) {
                        pctB.textContent = data.pct_b + '%';
                        barB.style.width = data.pct_b + '%';
                    }
                    if (totalVotesEl && data.total_votes !== undefined) {
                        totalVotesEl.textContent = data.total_votes;
                    }

                    const modal = document.getElementById('vote-success-modal');
                    const optText = document.getElementById('vote-success-option');
                    if (optText) optText.textContent = optionChosen;
                    if (modal) {
                        modal.classList.remove('hidden');
                    }

                    updateSurveyButtonsUI(surveyId, optionChosen);
                } else if (data.can_change_vote) {
                    // Open Change Vote Confirmation Modal
                    pendingChangeVote = { surveyId, optionChosen };
                    const pEl = document.getElementById('change-vote-prev');
                    const nEl = document.getElementById('change-vote-new');
                    if (pEl) pEl.textContent = data.previous_vote;
                    if (nEl) nEl.textContent = data.new_vote;

                    const changeModal = document.getElementById('change-vote-modal');
                    if (changeModal) changeModal.classList.remove('hidden');
                } else if (data.already_voted_same) {
                    showToast("You have already cast your vote ('" + optionChosen + "') for this survey.", 'info');
                } else {
                    showToast(data.message || 'Failed to record vote.', 'error');
                }
            })
            .catch(err => {
                console.error("Voting error:", err);
                showToast('Error recording vote. Please try again.', 'error');
            });
        }

        function closeChangeVoteModal() {
            const changeModal = document.getElementById('change-vote-modal');
            if (changeModal) changeModal.classList.add('hidden');
            pendingChangeVote = null;
        }

        function executeChangeVote() {
            if (pendingChangeVote) {
                const { surveyId, optionChosen } = pendingChangeVote;
                castSurveyVote(surveyId, optionChosen, true);
            }
        }

        function updateSurveyButtonsUI(surveyId, optionChosen) {
            const container = document.getElementById('survey-action-buttons-' + surveyId);
            if (!container) return;

            const optA = container.getAttribute('data-opta') || 'Agree';
            const optB = container.getAttribute('data-optb') || 'Disagree';

            const isA = (optionChosen.toLowerCase() === optA.toLowerCase());
            const isB = (optionChosen.toLowerCase() === optB.toLowerCase());

            const btnAClass = isA ? 'bg-emerald-600 text-white border-emerald-700 font-extrabold shadow' : 'bg-blue-50 hover:bg-valenzuela-blue hover:text-white text-valenzuela-blue border-blue-200 font-bold';
            const btnBClass = isB ? 'bg-red-600 text-white border-red-700 font-extrabold shadow' : 'bg-red-50 hover:bg-valenzuela-red hover:text-white text-valenzuela-red border-red-200 font-bold';

            const iconA = isA ? 'fa-check-circle' : 'fa-thumbs-up';
            const iconB = isB ? 'fa-check-circle' : 'fa-thumbs-down';

            const textA = isA ? ('Voted ' + escapeHtml(optA)) : ('Vote ' + escapeHtml(optA));
            const textB = isB ? ('Voted ' + escapeHtml(optB)) : ('Vote ' + escapeHtml(optB));

            container.innerHTML = `
                <div class="w-full bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold py-2 px-3.5 rounded-xl text-xs flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <span>You voted: <strong class="uppercase font-extrabold text-emerald-950">${escapeHtml(optionChosen)}</strong></span>
                    </span>
                    <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-2 py-0.5 rounded-full">(Click other button to change)</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="castSurveyVote(${surveyId}, '${optA.replace(/'/g, "\\'")}')" class="w-full ${btnAClass} border py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid ${iconA}"></i> ${textA}
                    </button>
                    <button onclick="castSurveyVote(${surveyId}, '${optB.replace(/'/g, "\\'")}')" class="w-full ${btnBClass} border py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid ${iconB}"></i> ${textB}
                    </button>
                </div>
            `;
        }

        function closeVoteSuccessModal() {
            const modal = document.getElementById('vote-success-modal');
            if (modal) modal.classList.add('hidden');
            checkCloseBodyModalState();
        }

        function closeChangeVoteModal() {
            const modal = document.getElementById('change-vote-modal');
            if (modal) modal.classList.add('hidden');
            checkCloseBodyModalState();
        }

        // Consultation Details Modal
        function openConsultationModal(id) {
            if (!window.__IS_LOGGED_IN__) {
                showRequireLoginModal();
                return;
            }
            fetch('index.php?api=get_consultation&id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const d = res.data;
                        document.getElementById('modal-consultation-id').value = d.id;
                        document.getElementById('modal-title').textContent = d.title;
                        const cLow = String(d.category || '').toLowerCase();
                        const tLow = String(d.title || '').toLowerCase();
                        const typeClean = String(d.type || '').toLowerCase();
                        const srcClean = String(d.source_system || '').toUpperCase();

                        const isOrtsModal = (typeClean === 'ordinance' || srcClean === 'ORTS' || cLow.includes('orts') || cLow.includes('ordinance') || tLow.includes('ordinance'));
                        const isSurveyModal = (!isOrtsModal) && (cLow.includes('survey') || tLow.includes('survey') || tLow.includes('poll'));
                        
                        const categoryEl = document.getElementById('modal-category');
                        const ortsCard = document.getElementById('modal-orts-ref-card');
                        const formTitle = document.querySelector('#feedback-submission-wrapper h4');
                        const submitBtn = document.querySelector('#feedback-form button[type="submit"]');

                        if (isOrtsModal) {
                            categoryEl.className = 'text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scale-balanced mr-1.5"></i> ORTS ORDINANCE (ORD)';
                            
                            if (ortsCard) {
                                ortsCard.classList.remove('hidden');
                                document.getElementById('orts-doc-id').textContent = '#' + (d.id || '104');
                                document.getElementById('orts-ref-num').textContent = d.tracking_number || d.external_ref || 'ORD-2025-001';
                            }
                            if (formTitle) formTitle.innerHTML = '<i class="fa-solid fa-paper-plane text-indigo-600 mr-1.5"></i> Submit Feedback to ORTS (Ordinance & Resolution Tracking)';
                            if (submitBtn) {
                                submitBtn.className = 'w-full bg-indigo-700 hover:bg-indigo-800 text-white font-extrabold py-3 px-4 rounded-xl text-xs transition-colors shadow-md flex items-center justify-center gap-2 cursor-pointer';
                                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Feedback to ORTS';
                            }
                        } else if (isSurveyModal) {
                            categoryEl.className = 'text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-md bg-purple-50 text-purple-700 border border-purple-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-square-poll-vertical mr-1.5"></i> COMMUNITY SURVEY & POLL';
                            if (ortsCard) ortsCard.classList.add('hidden');
                            if (formTitle) formTitle.textContent = 'Submit Your Voice & Rating';
                            if (submitBtn) {
                                submitBtn.className = 'w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm';
                                submitBtn.innerHTML = 'Submit Feedback & Voice';
                            }
                        } else {
                            categoryEl.className = 'text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-md bg-amber-50 text-amber-900 border border-amber-200 inline-block';
                            categoryEl.innerHTML = '<i class="fa-solid fa-scroll mr-1.5"></i> DRAFT ORDINANCE CONSULTATION';
                            if (ortsCard) ortsCard.classList.add('hidden');
                            if (formTitle) formTitle.textContent = 'Submit Your Voice & Rating';
                            if (submitBtn) {
                                submitBtn.className = 'w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm';
                                submitBtn.innerHTML = 'Submit Feedback & Voice';
                            }
                        }
                        document.getElementById('modal-code').textContent = d.tracking_number || ('TRK-' + d.id);
                        document.getElementById('modal-end-date').textContent = d.end_date ? new Date(d.end_date).toLocaleDateString() : 'N/A';
                        document.getElementById('modal-description').textContent = d.description;

                        // Check if consultation is concluded/closed or past end_date
                        const stClean = String(d.status || '').toLowerCase().trim();
                        let isPastEndDate = false;
                        if (d.end_date) {
                            const endDateVal = new Date(d.end_date);
                            endDateVal.setHours(23, 59, 59, 999);
                            if (endDateVal.getTime() < Date.now()) {
                                isPastEndDate = true;
                            }
                        }

                        const isConcludedOrClosed = isPastEndDate || ['closed', 'completed', 'resolved', 'declined', 'forwarded_orts', 'proceeded_to_ordinance', 'rejected', 'archived', 'endorsed'].includes(stClean);

                        const wrapperEl = document.getElementById('feedback-submission-wrapper');
                        const bannerEl = document.getElementById('concluded-consultation-banner');

                        if (isConcludedOrClosed) {
                            if (wrapperEl) wrapperEl.classList.add('hidden');
                            if (bannerEl) bannerEl.classList.remove('hidden');
                            const dlBtn = document.getElementById('concluded-download-pdf-btn');
                            if (dlBtn) dlBtn.href = `download-consultation.php?id=${d.id}&public=1`;
                        } else {
                            if (wrapperEl) wrapperEl.classList.remove('hidden');
                            if (bannerEl) bannerEl.classList.add('hidden');
                        }

                        // Render feedback list
                        const list = document.getElementById('modal-feedback-list');
                        document.getElementById('modal-feedback-count').textContent = res.feedback.length;

                        if (res.feedback.length === 0) {
                            list.innerHTML = '<p class="text-xs text-slate-400 italic">No feedback submitted yet. Be the first citizen to voice your opinion!</p>';
                        } else {
                            list.innerHTML = res.feedback.map(f => {
                                const rawName = f.guest_name || f.fullname || f.user_name || 'Anonymous Citizen';
                                const rawEmail = f.guest_email || f.email || '';
                                const fUserId = Number(f.user_id || 0);

                                const currentUserId = Number(window.__CURRENT_USER_ID__ || 0);
                                const currentEmail = String(window.__CURRENT_USER_EMAIL__ || '').toLowerCase().trim();
                                const currentName = String(window.__CURRENT_USER_NAME__ || '').toLowerCase().trim();

                                const isOwn = (fUserId > 0 && fUserId === currentUserId) ||
                                              (rawEmail && currentEmail && rawEmail.toLowerCase().trim() === currentEmail) ||
                                              (rawName && currentName && rawName.toLowerCase().trim() === currentName);

                                const displayName = isOwn
                                    ? `${escapeHtml(rawName)} <span class="text-[10px] text-blue-600 font-bold bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded ml-1">(You)</span>`
                                    : escapeHtml(anonymizeName(rawName));

                                return `
                                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 text-xs space-y-1.5 transition hover:bg-white hover:shadow-sm">
                                        <div class="flex justify-between items-center font-bold text-slate-800">
                                            <span class="flex items-center gap-1">${displayName}</span>
                                            <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-200 font-semibold text-[11px]"><i class="fa-solid fa-star text-amber-500 mr-1"></i> ${f.rating}/5</span>
                                        </div>
                                        <p class="text-slate-600 leading-relaxed font-medium">${escapeHtml(f.message)}</p>
                                        ${f.admin_response ? `<div class="mt-2 p-2.5 bg-blue-50/80 rounded-xl border border-blue-100 text-valenzuela-blue font-semibold text-[11px]"><i class="fa-solid fa-reply mr-1"></i> Response: ${escapeHtml(f.admin_response)}</div>` : ''}
                                    </div>
                                `;
                            }).join('');
                        }

                        document.getElementById('consultation-modal').classList.remove('hidden');
                        document.body.classList.add('overflow-hidden', 'modal-open');
                    } else {
                        showToast(res.message, 'error');
                    }
                });
        }

        function closeConsultationModal() {
            const m = document.getElementById('consultation-modal');
            if (m) m.classList.add('hidden');
            checkCloseBodyModalState();
        }

        function handleFeedbackSubmit(e) {
            e.preventDefault();
            if (!window.__IS_LOGGED_IN__) {
                showRequireLoginModal();
                return;
            }
            const form = document.getElementById('feedback-form');
            const data = new FormData(form);
            data.append('api_action', 'submit_feedback');

            fetch('index.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message);
                        closeConsultationModal();
                        form.reset();
                    } else {
                        showToast(res.message, 'error');
                    }
                });
        }

        // Survey Vote Handler (Handled above by interactive modal version)

        // Track Code Lookup Modal
        function showTrackModal() {
            const m = document.getElementById('track-modal');
            if (m) m.classList.remove('hidden');
            document.body.classList.add('overflow-hidden', 'modal-open');
        }
        function closeTrackModal() {
            const m = document.getElementById('track-modal');
            if (m) m.classList.add('hidden');
            checkCloseBodyModalState();
        }

        function performTrackLookup() {
            const code = document.getElementById('track-code-input').value.trim();
            if (!code) return;

            fetch('index.php?api=track_status&code=' + encodeURIComponent(code))
                .then(r => r.json())
                .then(res => {
                    const container = document.getElementById('track-results-container');
                    container.classList.remove('hidden');
                    if (res.success) {
                        const d = res.data;
                        const statusColors = {
                            'pending': 'bg-amber-100 text-amber-800 border border-amber-200',
                            'active': 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                            'reviewed': 'bg-blue-100 text-blue-800 border border-blue-200',
                            'rejected': 'bg-rose-100 text-rose-800 border border-rose-200',
                            'declined': 'bg-rose-100 text-rose-800 border border-rose-200',
                            'closed': 'bg-slate-100 text-slate-800 border border-slate-200'
                        };
                        const statusBadge = statusColors[d.status] || 'bg-slate-100 text-slate-800 border border-slate-200';
                        const trackingCodeDisplay = d.tracking_number || d.tracking_token || code;
                        const isProposal = (res.type === 'proposal' || d.tracking_number || d.title);

                        container.innerHTML = `
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-mono font-bold text-valenzuela-blue">${escapeHtml(trackingCodeDisplay)}</span>
                                    <span class="px-2 py-0.5 rounded-full font-bold uppercase ${statusBadge}">${escapeHtml(d.status)}</span>
                                </div>
                                <h5 class="font-bold text-slate-900 text-sm">${escapeHtml(d.title || d.consultation_title || 'Citizen Submission')}</h5>
                                <p class="text-slate-600 leading-relaxed">${escapeHtml(d.description || d.message || '')}</p>
                                ${isProposal ? renderCitizenConnectingDotsTracker(d.status, d.id, trackingCodeDisplay, d) : ''}
                            </div>
                        `;
                    } else {
                        container.innerHTML = `<p class="text-xs text-red-600 font-semibold">${escapeHtml(res.message)}</p>`;
                    }
                });
        }

        /* ==========================================================
           CITIZEN 6-STAGE REAL-TIME LEGISLATIVE TRACKER
           ========================================================== */
        function renderCitizenConnectingDotsTracker(status, itemId, trackingCode, itemObj) {
            const st = String(status || '').toLowerCase().trim();
            if (st === 'rejected' || st === 'declined') {
                const rejectionReason = itemObj?.admin_response || itemObj?.remarks || 'Submission does not meet LGU public consultation requirements.';
                return `
                    <div class="mt-3 p-3.5 bg-rose-50 rounded-2xl border border-rose-200 text-slate-800 text-xs space-y-1.5 shadow-2xs">
                        <div class="font-extrabold text-rose-700 flex items-center gap-2 uppercase text-[11px] tracking-wider">
                            <i class="fa-solid fa-circle-xmark text-rose-600 text-sm"></i> Status: Declined by LGU Secretariat
                        </div>
                        <p class="font-medium text-slate-700 leading-relaxed pl-5">
                            "${escapeHtml(rejectionReason)}"
                        </p>
                        <div class="text-[10px] text-rose-600 font-medium pl-5 pt-0.5">
                            You may submit a revised proposal or contact the Public Consultation Office for guidance.
                        </div>
                    </div>
                `;
            }

            const typeStr = String(itemObj?.type || '').toLowerCase().trim();
            const commAssigned = String(itemObj?.committee_assigned || '').trim();
            const assignedTo = itemObj?.assigned_to;
            const hasAiBrief = Boolean(itemObj?.ai_committee_brief && itemObj?.ai_committee_brief !== '');

            let currentStep = 1;

            if (['completed', 'officialized', 'archived', 'enacted', 'resolved', 'passed'].includes(st)) {
                currentStep = 6;
            } else if (['scheduled', 'committee', 'forwarded', 'approved', 'ordinance', 'endorsed', 'committee_assigned', 'in_committee', 'orts', 'forwarded_orts', 'orts_drafting'].includes(st) || commAssigned !== '') {
                currentStep = 5;
            } else if (['under_review', 'reviewed', 'viewed', 'replied', 'assigned', 'rp_review', 'rp_assigned', 'forwarded_rp'].includes(st) || (assignedTo && Number(assignedTo) > 0)) {
                currentStep = 4;
            } else if (['closed', 'closed_for_feedback', 'ai_summary', 'summarized', 'synthesized', 'synthesizing', 'ai_synthesis'].includes(st) || hasAiBrief) {
                currentStep = 3;
            } else if (['active', 'open', 'published', 'published_portal', 'voting', 'official', 'live'].includes(st) || typeStr === 'official') {
                currentStep = 2;
            } else {
                currentStep = 1;
            }

            const steps = [
                { num: 1, name: 'Received', desc: 'Public consultation intake logged and registered into PCMS repository', statusVal: 'pending' },
                { num: 2, name: 'Public Portal', desc: 'Published live on Public Portal for citizen voting, surveys, and public feedback', statusVal: 'active' },
                { num: 3, name: 'AI Synthesis', desc: 'Consultation closes; PCMS AI Engine scans & synthesizes all citizen votes and comments', statusVal: 'ai_summary' },
                { num: 4, name: 'RP Review', desc: 'Assigned Resource Person reviews AI Summary, adds expert evaluation & endorses report', statusVal: 'under_review' },
                { num: 5, name: 'ORTS Routing', desc: 'AI-summarized & RP-validated report dispatched directly to ORTS for ordinance drafting & tracking', statusVal: 'scheduled' },
                { num: 6, name: 'Officialized', desc: 'Enacted into official city ordinance & stored in permanent municipal archive', statusVal: 'completed' }
            ];

            const linePercent = Math.min(100, Math.max(0, (currentStep - 1) * 20));

            const dotsHtml = steps.map(step => {
                const isCompleted = step.num < currentStep;
                const isCurrent = step.num === currentStep;

                let dotBg = 'bg-slate-200 border-slate-300 hover:bg-slate-300';
                let innerContent = '';

                if (isCurrent) {
                    dotBg = 'bg-amber-500 border-amber-600 ring-4 ring-amber-200 scale-110 shadow-sm';
                    innerContent = '<span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>';
                } else if (isCompleted) {
                    dotBg = 'bg-emerald-500 border-emerald-600 ring-1 ring-emerald-200';
                    innerContent = '<i class="fa-solid fa-check text-[9px] text-white font-bold"></i>';
                }

                const safeName = step.name.replace(/'/g, "\\'");
                const safeDesc = step.desc.replace(/'/g, "\\'");

                return `
                    <div class="relative cursor-pointer group flex flex-col items-center" onclick="openCitizenStageDetailModal(${step.num}, 6, '${safeName}', '${safeDesc}', ${isCurrent}, ${isCompleted})" title="Click to view Stage ${step.num}: ${safeName}">
                        <div class="w-6 h-6 rounded-full border-2 ${dotBg} flex items-center justify-center transition-all z-10">
                            ${innerContent}
                        </div>
                        <span class="text-[8.5px] font-bold mt-1 text-center max-w-[55px] leading-tight ${isCurrent ? 'text-amber-700 font-extrabold' : (isCompleted ? 'text-emerald-700' : 'text-slate-400')}">
                            ${step.name}
                        </span>
                    </div>
                `;
            }).join('');

            return `
                <div class="mt-3 pt-3 border-t border-slate-200/80">
                    <div class="flex items-center justify-between text-[11px] mb-2">
                        <span class="font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-route text-amber-500"></i> Real-Time Legislative Progress:
                        </span>
                        <span class="font-mono text-[10px] text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-bold">
                            Stage ${currentStep} of 6 (${steps[currentStep - 1].name})
                        </span>
                    </div>
                    <div class="relative px-3 py-2 bg-white rounded-xl border border-slate-200/80 shadow-xs">
                        <div class="absolute top-[20px] left-[28px] right-[28px] h-1 bg-slate-100 rounded-full z-0"></div>
                        <div class="absolute top-[20px] left-[28px] h-1 bg-gradient-to-r from-emerald-500 via-amber-400 to-amber-500 rounded-full z-0 transition-all duration-500" style="width: calc((100% - 56px) * ${linePercent / 100});"></div>
                        <div class="flex justify-between items-start relative z-10">
                            ${dotsHtml}
                        </div>
                    </div>
                </div>
            `;
        }

        function openCitizenStageDetailModal(stepNum, totalSteps, stepName, stepDesc, isCurrent, isCompleted) {
            let modalEl = document.getElementById('citizen-stage-detail-modal');
            if (!modalEl) {
                modalEl = document.createElement('div');
                modalEl.id = 'citizen-stage-detail-modal';
                document.body.appendChild(modalEl);
            }

            const statusBadgeHtml = isCurrent 
                ? `<span class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-300 text-amber-800 text-[11px] font-bold inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span> ⚡ Current Active Stage</span>`
                : (isCompleted 
                    ? `<span class="px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-300 text-emerald-800 text-[11px] font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-circle-check"></i> ✓ Stage Completed</span>`
                    : `<span class="px-2.5 py-1 rounded-full bg-slate-100 border border-slate-300 text-slate-600 text-[11px] font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-clock"></i> ○ Upcoming Stage</span>`);

            modalEl.className = 'fixed top-5 right-5 z-[9999] w-80 sm:w-96 bg-white text-slate-900 rounded-2xl shadow-2xl border border-slate-200 p-5 transition-all duration-300 transform scale-100';
            modalEl.innerHTML = `
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 border border-amber-300 text-amber-800 font-black text-xs flex items-center justify-center shadow-xs">
                            ${stepNum}/${totalSteps}
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Legislative Progress Tracker</div>
                            <div class="font-black text-sm text-slate-900 leading-tight">${stepName}</div>
                        </div>
                    </div>
                    <button onclick="closeCitizenStageDetailModal()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs font-bold transition flex items-center justify-center cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-500">Stage Status:</span>
                        ${statusBadgeHtml}
                    </div>

                    <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200 text-xs text-slate-700 leading-relaxed font-medium">
                        <div class="text-[11px] font-bold text-amber-800 mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-circle-info"></i> Stage Details:
                        </div>
                        ${stepDesc}
                    </div>

                    <div class="pt-2 text-[10px] text-slate-500 font-medium text-center border-t border-slate-100 flex items-center justify-center gap-1">
                        <i class="fa-solid fa-shield-halved text-emerald-600"></i> Synchronized with real-time audit log
                    </div>
                </div>
            `;
        }

        function closeCitizenStageDetailModal() {
            const modalEl = document.getElementById('citizen-stage-detail-modal');
            if (modalEl) modalEl.remove();
        }

        // My Activity History Modal
        function showMyActivityModal() {
            const modal = document.getElementById('my-activity-modal');
            const content = document.getElementById('my-activity-content');
            if (modal) modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden', 'modal-open');
            if (content) {
                content.innerHTML = '<div class="text-center py-8 text-slate-400 flex items-center justify-center gap-2"><i class="fa-solid fa-spinner fa-spin text-valenzuela-blue text-lg"></i> <span>Loading your history & tracking status...</span></div>';
            }

            fetch('index.php?api=get_my_activity')
                .then(r => r.json())
                .then(res => {
                    if (!content) return;
                    if (res.success) {
                        let html = `
                            <div class="mb-4">
                                <input type="text" id="activity-search-input" onkeyup="filterActivityList()" placeholder="🔍 Filter submissions by title or tracking code (e.g. TRK-2026)..." class="w-full px-4 py-2.5 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue outline-none font-medium shadow-xs">
                            </div>
                        `;
                        if ((!res.proposals || res.proposals.length === 0) && (!res.feedback || res.feedback.length === 0)) {
                            html += '<div class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl border border-slate-200"><i class="fa-solid fa-folder-open text-3xl text-slate-300 mb-2"></i><p class="text-xs italic">No proposal submissions or feedback recorded under your account yet.</p></div>';
                        } else {
                            if (res.proposals && res.proposals.length > 0) {
                                html += '<h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center justify-between"><span>My Submitted Proposals (' + res.proposals.length + ')</span> <span class="text-[10px] text-amber-600 font-normal">Click dots for stage details</span></h4>';
                                html += '<div id="proposals-list-container">';
                                html += res.proposals.map(p => `
                                    <div class="activity-item-card p-4 bg-slate-50 hover:bg-slate-100/80 rounded-2xl border border-slate-200 text-xs space-y-2 mb-4 transition-colors shadow-xs" data-search="${escapeHtml((p.title + ' ' + (p.tracking_number || '') + ' ' + (p.category || '')).toLowerCase())}">
                                        <div class="flex justify-between items-start font-bold text-slate-800">
                                            <span class="text-sm text-slate-900 font-black">${escapeHtml(p.title)}</span>
                                            <span class="font-mono bg-blue-50 text-valenzuela-blue px-2.5 py-1 rounded-lg border border-blue-200 text-[11px] font-extrabold">${escapeHtml(p.tracking_number || 'TRK-PENDING')}</span>
                                        </div>
                                        <p class="text-slate-600 leading-relaxed">${escapeHtml(p.description || '')}</p>
                                        <div class="flex justify-between items-center text-[11px] text-slate-500 pt-1">
                                            <span>Category: <strong class="text-slate-700">${escapeHtml(p.category || 'General')}</strong></span>
                                            <span class="text-[10px] text-slate-400">${escapeHtml(p.created_at || '')}</span>
                                        </div>
                                        ${renderCitizenConnectingDotsTracker(p.status, p.id, p.tracking_number, p)}
                                    </div>
                                `).join('');
                                html += '</div>';
                            }
                            if (res.feedback && res.feedback.length > 0) {
                                html += '<h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mt-6 mb-3">My Feedback & Comments (' + res.feedback.length + ')</h4>';
                                html += '<div id="feedback-list-container">';
                                html += res.feedback.map(f => {
                                    let statusBadge = '';
                                    if (f.admin_response || f.status === 'responded' || f.status === 'approved') {
                                        statusBadge = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full font-bold text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-circle-check text-emerald-600"></i> Responded by LGU Admin</span>';
                                    } else if (f.status === 'under_review' || f.status === 'review') {
                                        statusBadge = '<span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full font-bold text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-hourglass-split text-blue-600"></i> Under Review</span>';
                                    } else {
                                        statusBadge = '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-full font-bold text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-clock text-amber-600"></i> Submitted to Public Consultation Office</span>';
                                    }

                                    return `
                                        <div class="activity-item-card p-4 bg-slate-50 hover:bg-slate-100/80 rounded-2xl border border-slate-200 text-xs space-y-2.5 mb-4 transition-colors shadow-xs" data-search="${escapeHtml(((f.consultation_title || '') + ' ' + (f.tracking_token || '') + ' ' + (f.message || '')).toLowerCase())}">
                                            <div class="flex justify-between items-start">
                                                <span class="font-bold text-slate-900 text-sm">${escapeHtml(f.consultation_title || 'General Feedback')}</span>
                                                <span class="font-mono bg-slate-200 text-slate-700 px-2 py-0.5 rounded text-[10px] font-bold">${escapeHtml(f.tracking_token || '')}</span>
                                            </div>
                                            <p class="text-slate-700 leading-relaxed bg-white p-3 rounded-xl border border-slate-200/80">${escapeHtml(f.message)}</p>
                                            ${f.admin_response ? `<div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-xl text-valenzuela-blue font-medium"><strong>LGU Official Response:</strong> ${escapeHtml(f.admin_response)}</div>` : ''}
                                            <div class="flex justify-between items-center pt-1 border-t border-slate-200/60">
                                                <span class="text-[10px] text-slate-400">Submitted: ${escapeHtml(f.created_at || '')}</span>
                                                ${statusBadge}
                                            </div>
                                        </div>
                                    `;
                                }).join('');
                                html += '</div>';
                            }
                        }
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = `<div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-xs font-semibold text-center">${escapeHtml(res.message || 'Unable to load activity history.')}</div>`;
                    }
                })
                .catch(err => {
                    if (content) {
                        content.innerHTML = '<div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-xs font-semibold text-center">Failed to connect to server. Please try again.</div>';
                    }
                });
        }

        function filterActivityList() {
            const query = (document.getElementById('activity-search-input')?.value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('.activity-item-card');
            cards.forEach(card => {
                const searchData = card.getAttribute('data-search') || '';
                if (!query || searchData.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function closeMyActivityModal() {
            const m = document.getElementById('my-activity-modal');
            if (m) m.classList.add('hidden');
            checkCloseBodyModalState();
        }

        // Chatbot Drawer Toggle & Send
        function toggleChatbot() {
            document.getElementById('chatbot-drawer').classList.toggle('hidden');
        }

        function sendChatMessage() {
            const input = document.getElementById('chatbot-input');
            const msg = input.value.trim();
            if (!msg) return;

            const box = document.getElementById('chatbot-messages');
            box.innerHTML += `<div class="bg-valenzuela-blue text-white p-3 rounded-2xl max-w-[85%] ml-auto text-right">${escapeHtml(msg)}</div>`;
            input.value = '';
            box.scrollTop = box.scrollHeight;

            const data = new FormData();
            data.append('api_action', 'chatbot');
            data.append('message', msg);

            fetch('index.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    box.innerHTML += `<div class="bg-slate-100 text-slate-800 p-3 rounded-2xl max-w-[85%]">${escapeHtml(res.reply)}</div>`;
                    box.scrollTop = box.scrollHeight;
                });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // File Input Display Helper
        const fileInput = document.getElementById('file-upload-input');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const label = document.getElementById('selected-file-name');
                if (this.files && this.files[0]) {
                    label.textContent = 'Selected: ' + this.files[0].name;
                    label.classList.remove('hidden');
                } else {
                    label.classList.add('hidden');
                }
            });
        }

        // Past Consultations & Concluded Polls Filter Tabs
        function filterPastArchive(tab) {
            const cards = document.querySelectorAll('.past-archive-card');
            const btns = document.querySelectorAll('.past-tab-btn');

            btns.forEach(b => {
                b.classList.remove('bg-white', 'text-slate-900', 'shadow-xs', 'border', 'border-slate-200', 'font-extrabold');
                b.classList.add('text-slate-600', 'font-bold');
            });

            const activeBtn = document.getElementById('past-tab-' + tab);
            if (activeBtn) {
                activeBtn.classList.remove('text-slate-600', 'font-bold');
                activeBtn.classList.add('bg-white', 'text-slate-900', 'shadow-xs', 'border', 'border-slate-200', 'font-extrabold');
            }

            cards.forEach(card => {
                if (tab === 'all') {
                    card.style.display = 'flex';
                } else if (tab === 'survey' && card.classList.contains('past-type-survey')) {
                    card.style.display = 'flex';
                } else if (tab === 'consultation' && card.classList.contains('past-type-consultation')) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            initAllSliders();
        }

        // Horizontal Scroll Helper Functions for Single-Line Sliders
        function scrollConsultations(direction) {
            const container = document.getElementById('consultation-cards-container');
            if (!container) return;
            const scrollAmount = 390;
            container.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
        }

        function scrollSurveys(direction) {
            const container = document.getElementById('survey-cards-container');
            if (!container) return;
            const scrollAmount = 390;
            container.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
        }

        function scrollPastArchive(direction) {
            const container = document.getElementById('past-cards-container');
            if (!container) return;
            const scrollAmount = 390;
            container.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
        }

        // Dynamic Slider Arrow Visibility Check
        function checkSliderScroll(containerId, prevBtnId, nextBtnId) {
            const container = document.getElementById(containerId);
            const prevBtn = document.getElementById(prevBtnId);
            const nextBtn = document.getElementById(nextBtnId);

            if (!container || !prevBtn || !nextBtn) return;

            const isScrollable = container.scrollWidth > (container.clientWidth + 5);
            const scrollLeft = container.scrollLeft;
            const maxScroll = container.scrollWidth - container.clientWidth;

            if (!isScrollable) {
                prevBtn.classList.remove('opacity-100', 'pointer-events-auto');
                prevBtn.classList.add('opacity-0', 'pointer-events-none');
                nextBtn.classList.remove('opacity-100', 'pointer-events-auto');
                nextBtn.classList.add('opacity-0', 'pointer-events-none');
                return;
            }

            // Left Arrow
            if (scrollLeft > 15) {
                prevBtn.classList.remove('opacity-0', 'pointer-events-none');
                prevBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                prevBtn.classList.remove('opacity-100', 'pointer-events-auto');
                prevBtn.classList.add('opacity-0', 'pointer-events-none');
            }

            // Right Arrow
            if (scrollLeft < maxScroll - 15) {
                nextBtn.classList.remove('opacity-0', 'pointer-events-none');
                nextBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                nextBtn.classList.remove('opacity-100', 'pointer-events-auto');
                nextBtn.classList.add('opacity-0', 'pointer-events-none');
            }
        }

        function initAllSliders() {
            setTimeout(() => {
                checkSliderScroll('consultation-cards-container', 'consultation-prev-btn', 'consultation-next-btn');
                checkSliderScroll('survey-cards-container', 'survey-prev-btn', 'survey-next-btn');
                checkSliderScroll('past-cards-container', 'past-prev-btn', 'past-next-btn');
            }, 100);
        }

        document.addEventListener('DOMContentLoaded', initAllSliders);
        window.addEventListener('resize', initAllSliders);

        // Main Portal Segmented Tab View Switcher (Active vs Past Archive)
        function switchPortalMainTab(tab) {
            const activeContainer = document.getElementById('active-portal-container');
            const pastContainer = document.getElementById('past-portal-container');
            const activeBtn = document.getElementById('main-tab-active-btn');
            const pastBtn = document.getElementById('main-tab-past-btn');

            if (!activeContainer || !pastContainer || !activeBtn || !pastBtn) return;

            if (tab === 'active') {
                activeContainer.classList.remove('hidden');
                pastContainer.classList.add('hidden');

                activeBtn.className = 'flex items-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all bg-gradient-to-r from-valenzuela-red to-red-700 text-white shadow-md cursor-pointer';
                pastBtn.className = 'flex items-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-bold text-slate-400 hover:text-white transition-all cursor-pointer';
            } else {
                activeContainer.classList.add('hidden');
                pastContainer.classList.remove('hidden');

                pastBtn.className = 'flex items-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-extrabold transition-all bg-gradient-to-r from-amber-600 to-amber-700 text-white shadow-md cursor-pointer';
                activeBtn.className = 'flex items-center gap-2.5 px-6 py-3 rounded-xl text-xs sm:text-sm font-bold text-slate-400 hover:text-white transition-all cursor-pointer';
            }
            initAllSliders();
        }

        // Smart Category Auto-Selector for Title Input
        document.addEventListener('input', function(e) {
            const target = e.target;
            if (!target) return;

            const isTitleField = target.id === 'title' || 
                                 target.id === 'consultation-title' || 
                                 target.id === 'concern-title' || 
                                 target.name === 'title' ||
                                 (target.placeholder && (target.placeholder.toLowerCase().includes('title') || target.placeholder.toLowerCase().includes('ordinance') || target.placeholder.toLowerCase().includes('solar')));

            if (isTitleField) {
                const form = target.closest('form') || target.closest('.modal') || target.closest('div') || document;
                const categorySelect = form.querySelector('#category, #consultation-category, #concern-category, select[name="category"]');

                if (categorySelect) {
                    const text = (target.value || '').toLowerCase();
                    if (!text) return;

                    let matchedVal = '';

                    if (text.includes('lamp') || text.includes('light') || text.includes('road') || text.includes('street') || text.includes('utility') || text.includes('drainage') || text.includes('facility') || text.includes('facilities') || text.includes('infrastructure')) {
                        matchedVal = 'Public Utilities & Facilities';
                    } else if (text.includes('park') || text.includes('parks') || text.includes('open space') || text.includes('housing') || text.includes('urban') || text.includes('building') || text.includes('playground') || text.includes('greenery')) {
                        matchedVal = 'Urban Planning, Housing & Development';
                    } else if (text.includes('health') || text.includes('sanitation') || text.includes('clinic') || text.includes('hospital') || text.includes('waste') || text.includes('garbage') || text.includes('clean')) {
                        matchedVal = 'Health & Sanitation';
                    } else if (text.includes('school') || text.includes('education') || text.includes('scholarship') || text.includes('student') || text.includes('college')) {
                        matchedVal = 'Higher & Technical Education';
                    } else if (text.includes('youth') || text.includes('senior') || text.includes('elderly') || text.includes('social') || text.includes('welfare') || text.includes('disabled')) {
                        matchedVal = 'Social Services';
                    } else if (text.includes('budget') || text.includes('fund') || text.includes('tax') || text.includes('revenue')) {
                        matchedVal = 'Ways & Means';
                    } else if (text.includes('market') || text.includes('vendor') || text.includes('stall') || text.includes('slaughterhouse')) {
                        matchedVal = 'Market & Slaughterhouse';
                    }

                    if (matchedVal && (!categorySelect.value || categorySelect.dataset.autoSelected === 'true')) {
                        for (let i = 0; i < categorySelect.options.length; i++) {
                            const opt = categorySelect.options[i];
                            const optVal = (opt.value || '').toLowerCase();
                            const optText = (opt.text || '').toLowerCase();
                            if (optVal === matchedVal.toLowerCase() || optText.includes(matchedVal.toLowerCase()) || optVal.includes(matchedVal.split(' ')[0].toLowerCase())) {
                                categorySelect.selectedIndex = i;
                                categorySelect.dataset.autoSelected = 'true';
                                break;
                            }
                        }
                    }
                }
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target && e.target.tagName === 'SELECT') {
                delete e.target.dataset.autoSelected;
            }
        });
    </script>
</body>
</html>
