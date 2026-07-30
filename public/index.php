<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/consultations.php';
require_once __DIR__ . '/../DATABASE/feedback.php';
require_once __DIR__ . '/../DATABASE/announcements.php';

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
            $fStmt = $conn->prepare("SELECT id, guest_name, category, rating, message, created_at, admin_response, responded_at, status FROM feedback WHERE consultation_id = ? ORDER BY created_at DESC");
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

        // Verify consultation type is 'admin'
        $typeCheck = $conn->query("SELECT type FROM consultations WHERE id = $consultation_id LIMIT 1");
        $typeRow = $typeCheck ? $typeCheck->fetch_assoc() : null;
        if ($typeRow && strtolower(trim($typeRow['type'])) === 'user') {
            echo json_encode(['success' => false, 'message' => 'Feedback is only accepted on official Admin Consultations, not on citizen proposals.']);
            exit;
        }

        // Generate feedback tracking token
        $tracking_token = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $stmt = $conn->prepare("INSERT INTO feedback (consultation_id, guest_name, guest_email, guest_phone, rating, category, message, tracking_token, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->bind_param('isssisss', $consultation_id, $user_name, $user_email, $user_phone, $rating, $category, $message, $tracking_token);

        if ($stmt->execute()) {
            // Update posts_count in consultations
            $conn->query("UPDATE consultations SET posts_count = posts_count + 1 WHERE id = " . $consultation_id);
            echo json_encode([
                'success' => true, 
                'message' => 'Thank you! Your feedback has been submitted successfully.',
                'tracking_token' => $tracking_token
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
            $user_email = isset($_SESSION['email']) ? strtolower(trim($_SESSION['email'])) : trim($_POST['guest_email'] ?? '');
            $device_token = trim($_POST['device_token'] ?? ('DEV-' . md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? ''))));

            require_once __DIR__ . '/../DATABASE/consultations.php';
            if ($user_id > 0) {
                submitConsultationVote($survey_id, $user_id, $option_chosen);
            } else {
                submitConsultationGuestVote($survey_id, $device_token, $option_chosen, $user_email, null, null, 1);
            }

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
        $user_email = $_SESSION['email'] ?? '';
        $user_id = (int)($_SESSION['user_id'] ?? 0);

        $feedback = [];
        $proposals = [];

        if ($user_email !== '' || $user_id > 0) {
            if ($user_email !== '') {
                $fStmt = $conn->prepare("SELECT f.id, f.consultation_id, f.category, f.message, f.created_at, f.admin_response, f.responded_at, f.status, f.tracking_token, c.title as consultation_title FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id WHERE f.guest_email = ? ORDER BY f.created_at DESC LIMIT 50");
                if ($fStmt) {
                    $fStmt->bind_param('s', $user_email);
                    $fStmt->execute();
                    $fRes = $fStmt->get_result();
                    while ($fRow = $fRes->fetch_assoc()) {
                        $feedback[] = $fRow;
                    }
                    $fStmt->close();
                }
            }

            $userName = $_SESSION['fullname'] ?? $_SESSION['full_name'] ?? '';
            $pStmt = $conn->prepare("SELECT id, title, description, category, status, created_at, tracking_number FROM consultations WHERE (created_by > 0 AND created_by = ?) OR user_name = ? ORDER BY created_at DESC LIMIT 20");
            if ($pStmt) {
                $pStmt->bind_param('is', $user_id, $userName);
                $pStmt->execute();
                $pRes = $pStmt->get_result();
                while ($pRow = $pRes->fetch_assoc()) {
                    $proposals[] = $pRow;
                }
                $pStmt->close();
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

        // Search proposal by tracking_number in consultations
        $pStmt = $conn->prepare("SELECT id, title, category, description, status, created_at, tracking_number, user_name FROM consultations WHERE tracking_number = ? LIMIT 1");
        $pStmt->bind_param('s', $code);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        if ($pRes && $pRes->num_rows > 0) {
            $data = $pRes->fetch_assoc();
            $pStmt->close();
            echo json_encode(['success' => true, 'type' => 'proposal', 'data' => $data]);
            exit;
        }
        $pStmt->close();

        // Search feedback by tracking_token in feedback
        $fStmt = $conn->prepare("SELECT f.id, f.guest_name, f.category, f.message, f.status, f.admin_response, f.responded_at, f.created_at, f.tracking_token, c.title as consultation_title FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id WHERE f.tracking_token = ? LIMIT 1");
        $fStmt->bind_param('s', $code);
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

    if ($action === 'get_my_activity') {
        $email = trim($_SESSION['email'] ?? '');
        $user_name = trim($_SESSION['fullname'] ?? $_SESSION['full_name'] ?? '');
        
        if (empty($email) && empty($user_name) && !isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please sign in to view your activity history.']);
            exit;
        }

        $proposals = [];
        if (!empty($email) || !empty($user_name)) {
            $stmt = $conn->prepare("SELECT id, title, category, description, status, created_at, tracking_number FROM consultations WHERE (user_email = ? OR (user_name = ? AND user_name != '')) AND type = 'user' ORDER BY created_at DESC");
            $stmt->bind_param('ss', $email, $user_name);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $proposals[] = $row;
            }
            $stmt->close();
        }

        $feedback_list = [];
        if (!empty($email) || !empty($user_name)) {
            $fStmt = $conn->prepare("SELECT f.id, f.message, f.category, f.status, f.created_at, f.tracking_token, f.admin_response, c.title as consultation_title FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id WHERE (f.guest_email = ? OR (f.guest_name = ? AND f.guest_name != '')) ORDER BY f.created_at DESC");
            $fStmt->bind_param('ss', $email, $user_name);
            $fStmt->execute();
            $fRes = $fStmt->get_result();
            while ($fRow = $fRes->fetch_assoc()) {
                $feedback_list[] = $fRow;
            }
            $fStmt->close();
        }

        echo json_encode(['success' => true, 'proposals' => $proposals, 'feedback' => $feedback_list]);
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
    $consultation_sql .= " AND LOWER(category) = ?";
    $params[] = strtolower($category_filter);
    $types .= "s";
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
$surveys = [];
$survey_query = "SELECT id, title, description, survey_question, survey_option_a, survey_option_b, response_mode, status, created_at, end_date, posts_count FROM consultations WHERE response_mode IN ('survey', 'hybrid') AND status IN ('active', 'viewed', 'replied', 'scheduled') ORDER BY created_at DESC LIMIT 6";
$sRes = $conn->query($survey_query);
if ($sRes) {
    while ($sRow = $sRes->fetch_assoc()) {
        // Get vote counts for option A and option B
        $vStats = getConsultationVoteStats($sId);
        $sRow['count_a'] = $vStats['agree_votes'];
        $sRow['count_b'] = $vStats['disagree_votes'];
        $sRow['total_votes'] = $vStats['total_votes'];
        $sRow['pct_a'] = (int)$vStats['agree_percent'];
        $sRow['pct_b'] = (int)$vStats['disagree_percent'];

        $user_vote = null;
        if (isset($_SESSION['user_id'])) {
            $user_vote = getUserConsultationVote($sId, (int)$_SESSION['user_id']);
        }
        $sRow['user_vote'] = $user_vote;

        $surveys[] = $sRow;
    }
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

$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$is_citizen_session = isset($_SESSION['user_id']) && ($user_role === 'citizen' || empty($user_role) || $user_role === 'user');
$current_user_name = $_SESSION['fullname'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? ($_SESSION['email'] ?? null);
$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['email']) || !empty($current_user_name);
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
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); }
        .hero-gradient { background: linear-gradient(135deg, #0033a0 0%, #001a55 60%, #1e293b 100%); }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 51, 160, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .star-rating i { cursor: pointer; transition: color 0.2s ease; }
        .star-rating i.active { color: #f59e0b; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
    <script>
        window.__IS_LOGGED_IN__ = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    </script>

    <!-- Require Login Modal -->
    <div id="require-login-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
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
                <a href="google-auth.php" class="w-full flex items-center justify-center gap-3 bg-white hover:bg-slate-50 text-slate-700 font-bold text-sm border border-slate-300 px-5 py-3 rounded-xl transition-all shadow-sm hover:shadow">
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

    <!-- Main Navigation Bar -->
    <nav class="glass border-b border-gray-200 sticky top-0 z-40 shadow-sm transition-all duration-300" id="main-nav">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                
                <!-- Brand / Seal -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-12 h-12 rounded-full border-2 border-gray-100 shadow-inner flex items-center justify-center overflow-hidden bg-white group-hover:scale-105 transition-transform">
                        <img src="../images/valenzuela-logo.png" alt="Valenzuela Seal" class="w-full h-full object-cover">
                    </div>
                    <div class="flex flex-col justify-center">
                        <div class="flex items-baseline gap-1.5">
                            <h1 class="text-xl sm:text-2xl font-black tracking-tight flex items-baseline">
                                <span class="text-valenzuela-blue">VALENZUELA</span>
                                <span class="text-valenzuela-red ml-1">PCMS</span>
                            </h1>
                            <div class="text-[10px] font-bold text-valenzuela-red tracking-wider border-l border-gray-300 pl-2 ml-1 uppercase hidden sm:block">
                                Citizen<br>Portal
                            </div>
                        </div>
                        <span class="text-[11px] font-medium text-slate-500 hidden sm:block">City Legislative Consultations</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex space-x-6 items-center font-medium text-sm text-slate-600">
                    <a href="#active-consultations" class="hover:text-valenzuela-blue transition-colors py-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-comments text-valenzuela-blue"></i> Consultations
                    </a>
                    <a href="#surveys" class="hover:text-valenzuela-blue transition-colors py-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-square-poll-horizontal text-valenzuela-red"></i> Surveys
                    </a>
                    <?php if (!empty($announcements)): ?>
                    <a href="#announcements" class="hover:text-valenzuela-blue transition-colors py-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-bullhorn text-amber-500"></i> Updates
                    </a>
                    <?php endif; ?>
                    <a href="#submit-consultation" class="hover:text-valenzuela-blue transition-colors py-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-paper-plane text-emerald-600"></i> Submit Concern
                    </a>
                    <button onclick="showTrackModal()" class="hover:text-valenzuela-blue transition-colors py-2 flex items-center gap-1.5 text-slate-700 font-semibold bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                        <i class="fa-solid fa-magnifying-glass text-valenzuela-blue"></i> Track Status
                    </button>
                </div>

                <!-- Right Authentication Actions -->
                <div class="hidden md:flex items-center gap-4">
                    <?php if ($is_logged_in): ?>
                        <div class="relative group" id="user-dropdown-container">
                            <button class="flex items-center gap-2.5 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-full transition-colors border border-slate-200">
                                <div class="w-7 h-7 rounded-full bg-valenzuela-blue text-white flex items-center justify-center font-bold text-xs">
                                    <?php echo strtoupper(substr($current_user_name ?? 'C', 0, 1)); ?>
                                </div>
                                <span class="text-xs font-bold text-slate-800 max-w-[120px] truncate"><?php echo htmlspecialchars($current_user_name); ?></span>
                                <i class="fa-solid fa-chevron-down text-xs text-slate-400"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-gray-100 py-2 hidden group-hover:block transition-all z-50">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></p>
                                    <p class="text-[11px] text-slate-500 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                                </div>
                                <button onclick="showMyActivityModal()" class="w-full text-left px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                                    <i class="fa-solid fa-clock-history text-valenzuela-blue"></i> My Submissions & Votes
                                </button>
                                <button onclick="showTrackModal()" class="w-full text-left px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                                    <i class="fa-solid fa-barcode text-valenzuela-red"></i> Track Submission
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="sign-out.php" class="block w-full text-left px-4 py-2 text-xs font-medium text-red-600 hover:bg-red-50 flex items-center gap-2">
                                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="google-auth.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-300 hover:border-slate-400 px-4 py-2 rounded-full transition-all shadow-sm hover:shadow">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                                <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                            </svg>
                            <span class="font-bold">Sign In</span>
                        </a>
                        <a href="sign-up.php" class="bg-valenzuela-red hover:bg-red-700 text-white px-5 py-2 rounded-full font-bold text-xs transition-all shadow-[0_4px_14px_0_rgba(220,38,38,0.35)] hover:shadow-lg hover:-translate-y-0.5">
                            Get Started
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
        <div id="mobile-menu" class="hidden md:hidden bg-white border-b border-gray-200 px-4 py-5 space-y-3 shadow-xl absolute w-full left-0 z-50">
            <a href="#active-consultations" class="block font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-comments text-valenzuela-blue w-5"></i> Consultations
            </a>
            <a href="#surveys" class="block font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-square-poll-horizontal text-valenzuela-red w-5"></i> Surveys
            </a>
            <a href="#submit-consultation" class="block font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-paper-plane text-emerald-600 w-5"></i> Submit Concern
            </a>
            <button onclick="showTrackModal()" class="w-full text-left font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                <i class="fa-solid fa-magnifying-glass text-valenzuela-blue w-5"></i> Track Status
            </button>
            <?php if ($is_logged_in): ?>
                <button onclick="showMyActivityModal()" class="w-full text-left font-medium text-slate-700 hover:text-valenzuela-blue py-1 flex items-center gap-2">
                    <i class="fa-solid fa-clock-history text-amber-500 w-5"></i> My Submissions
                </button>
            <?php endif; ?>

            <div class="pt-4 border-t border-gray-100 flex flex-col gap-2">
                <?php if ($is_logged_in): ?>
                    <div class="flex justify-between items-center px-2 py-1 bg-slate-50 rounded-lg">
                        <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($current_user_name); ?></span>
                        <a href="sign-out.php" class="text-xs font-bold text-red-600">Sign Out</a>
                    </div>
                <?php else: ?>
                    <a href="google-auth.php" class="flex items-center justify-center gap-2 font-bold text-slate-700 bg-white border border-gray-300 py-2.5 rounded-lg shadow-sm hover:bg-slate-50 transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.3 7.31 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.17 0 9.99 0 12s.46 3.83 1.26 5.42l4.02-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.25 2.7 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                        <span>Sign In</span>
                    </a>
                    <a href="sign-up.php" class="block text-center bg-valenzuela-red text-white py-2.5 rounded-lg font-bold">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-grow max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12 w-full">

        <!-- Hero / Welcome Section -->
        <header class="hero-gradient rounded-3xl p-6 sm:p-10 text-white shadow-xl relative overflow-hidden flex flex-col lg:flex-row items-center justify-between gap-8">
            <!-- Decorative Accent Circle -->
            <div class="absolute -right-16 -top-16 w-80 h-80 bg-red-600/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 w-80 h-80 bg-blue-400/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="z-10 max-w-2xl">
                <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
                    <div class="inline-flex items-center gap-2 bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 text-xs font-semibold px-3 py-1 rounded-full mb-3 backdrop-blur-md">
                        <i class="fa-solid fa-circle-check"></i> Welcome back, <?php echo htmlspecialchars($current_user_name ?? 'Citizen'); ?>!
                    </div>
                <?php endif; ?>

                <span class="inline-block bg-white/10 text-blue-200 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3">
                    Valenzuela City Legislative Office
                </span>

                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight mb-4">
                    Shape City Ordinances & Community Policies
                </h2>

                <p class="text-blue-100/90 text-sm sm:text-base leading-relaxed mb-6">
                    Participate directly in local governance. Voice your thoughts on active city consultations, vote on community surveys, and submit citizen proposal topics to the City Council.
                </p>

                <!-- Search Bar -->
                <form action="#active-consultations" method="GET" class="flex flex-col sm:flex-row gap-2 bg-white/10 p-2 rounded-2xl backdrop-blur-md border border-white/20">
                    <div class="relative flex-grow">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-blue-200 text-sm"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search consultations by ordinance title or keyword..." class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/10 text-white placeholder-blue-200/60 focus:bg-white/20 focus:outline-none text-sm border-none">
                    </div>
                    <button type="submit" class="bg-valenzuela-red hover:bg-red-700 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition-all shrink-0">
                        Search Topics
                    </button>
                </form>
            </div>

            <!-- Live Dashboard Stats Cards -->
            <div class="z-10 grid grid-cols-2 gap-4 w-full lg:w-auto shrink-0">
                <div class="bg-white/10 backdrop-blur-md border border-white/15 rounded-2xl p-5 text-center flex flex-col justify-center min-w-[130px]">
                    <span class="text-3xl sm:text-4xl font-black text-amber-400"><?php echo $stats['active_consultations']; ?></span>
                    <span class="text-[11px] text-blue-200 uppercase font-bold tracking-wider mt-1">Active<br>Topics</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md border border-white/15 rounded-2xl p-5 text-center flex flex-col justify-center min-w-[130px]">
                    <span class="text-3xl sm:text-4xl font-black text-emerald-400"><?php echo $stats['new_surveys']; ?></span>
                    <span class="text-[11px] text-blue-200 uppercase font-bold tracking-wider mt-1">Open<br>Surveys</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md border border-white/15 rounded-2xl p-5 text-center flex flex-col justify-center min-w-[130px]">
                    <span class="text-3xl sm:text-4xl font-black text-blue-300"><?php echo number_format($stats['total_citizens']); ?></span>
                    <span class="text-[11px] text-blue-200 uppercase font-bold tracking-wider mt-1">Registered<br>Citizens</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md border border-white/15 rounded-2xl p-5 text-center flex flex-col justify-center min-w-[130px]">
                    <span class="text-3xl sm:text-4xl font-black text-rose-400"><?php echo number_format($stats['feedback_submitted']); ?></span>
                    <span class="text-[11px] text-blue-200 uppercase font-bold tracking-wider mt-1">Feedback<br>Submitted</span>
                </div>
            </div>
        </header>

        <!-- City Announcements Section (If Available) -->
        <?php if (!empty($announcements)): ?>
        <section id="announcements" class="scroll-mt-24">
            <div class="bg-gradient-to-r from-amber-500/10 via-amber-400/5 to-transparent border-l-4 border-amber-500 p-4 rounded-xl flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 overflow-hidden">
                    <span class="bg-amber-500 text-white text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wider shrink-0 flex items-center gap-1">
                        <i class="fa-solid fa-bullhorn"></i> Announcement
                    </span>
                    <p class="text-sm font-semibold text-slate-800 truncate">
                        <?php echo htmlspecialchars($announcements[0]['title']); ?> — 
                        <span class="font-normal text-slate-600"><?php echo htmlspecialchars(substr(strip_tags($announcements[0]['content']), 0, 100)); ?>...</span>
                    </p>
                </div>
                <span class="text-xs text-slate-400 shrink-0 hidden sm:inline">
                    <?php echo date('M d, Y', strtotime($announcements[0]['created_at'])); ?>
                </span>
            </div>
        </section>
        <?php endif; ?>

        <!-- Active Consultations Section -->
        <section id="active-consultations" class="scroll-mt-24">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-4">
                <div>
                    <span class="text-xs font-bold text-valenzuela-blue uppercase tracking-wider">Public Consultation Portal</span>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-comments text-valenzuela-blue"></i> Active Public Consultations
                    </h3>
                    <p class="text-slate-500 text-sm mt-1">Review proposed ordinances and contribute your feedback to the City Council.</p>
                </div>

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
                            ? 'bg-valenzuela-blue text-white font-bold shadow-sm' 
                            : 'bg-white text-slate-600 hover:bg-slate-100 font-semibold border border-slate-200';
                    ?>
                        <a href="?category=<?php echo $key; ?>#active-consultations" class="<?php echo $btnClass; ?> text-xs px-3.5 py-1.5 rounded-full transition-all">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Consultation Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($consultations)): ?>
                    <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                        <i class="fa-solid fa-box-open text-5xl mb-3 text-slate-300"></i>
                        <h4 class="text-lg font-bold text-slate-700">No Consultations Found</h4>
                        <p class="text-sm text-slate-500 max-w-md mx-auto mt-1">There are currently no active public consultations matching your search filter. Try clearing your search parameters.</p>
                        <a href="index.php#active-consultations" class="inline-block mt-4 bg-valenzuela-blue text-white text-xs font-bold px-4 py-2 rounded-lg">View All Consultations</a>
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
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col card-hover group">
                        
                        <!-- Top Banner Image if available -->
                        <?php if (!empty($c['image_path']) && file_exists(__DIR__ . '/../' . $c['image_path'])): ?>
                            <div class="h-40 w-full overflow-hidden bg-slate-100 relative">
                                <img src="../<?php echo htmlspecialchars($c['image_path']); ?>" alt="Consultation Image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute top-3 right-3 bg-white/90 backdrop-blur-md px-2.5 py-1 rounded-full text-[11px] font-bold text-slate-700 shadow-sm">
                                    <i class="fa-regular fa-clock text-valenzuela-blue"></i> <?php echo $days_left; ?>d remaining
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="h-3 w-full bg-gradient-to-r from-valenzuela-blue to-valenzuela-red"></div>
                        <?php endif; ?>

                        <div class="p-6 flex-grow flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md border <?php echo $badgeStyle; ?>">
                                        <?php echo htmlspecialchars($c['category'] ?? 'General Governance'); ?>
                                    </span>
                                    <?php if (empty($c['image_path'])): ?>
                                        <span class="text-[11px] font-medium text-slate-400 flex items-center gap-1">
                                            <i class="fa-regular fa-clock"></i> Closes in <?php echo $days_left; ?>d
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h4 class="text-lg font-bold text-slate-900 group-hover:text-valenzuela-blue transition-colors line-clamp-2 mb-2">
                                    <?php echo htmlspecialchars($c['title'] ?? 'Untitled Consultation'); ?>
                                </h4>

                                <p class="text-slate-600 text-xs sm:text-sm line-clamp-3 mb-4 leading-relaxed">
                                    <?php echo htmlspecialchars($c['description'] ?? 'No detailed description provided.'); ?>
                                </p>
                            </div>

                            <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-2">
                                <div class="text-[11px] text-slate-500 flex items-center gap-3">
                                    <span><i class="fa-solid fa-eye text-slate-400"></i> <?php echo (int)($c['views'] ?? 0); ?> views</span>
                                    <span><i class="fa-solid fa-comment-dots text-slate-400"></i> <?php echo (int)($c['posts_count'] ?? 0); ?> responses</span>
                                </div>
                                <button onclick="openConsultationModal(<?php echo (int)$c['id']; ?>)" class="bg-valenzuela-blue hover:bg-blue-800 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                                    Participate <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (empty($surveys)): ?>
                    <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-400">
                        <i class="fa-solid fa-clipboard-list text-4xl mb-2 text-slate-300"></i>
                        <p class="text-sm font-semibold">No active community surveys available at this time.</p>
                    </div>
                <?php else: foreach ($surveys as $index => $s): ?>
                    <?php 
                        $optA = $s['survey_option_a'] ?? 'Agree';
                        $optB = $s['survey_option_b'] ?? 'Disagree';
                        $pctA = $s['pct_a'];
                        $pctB = $s['pct_b'];
                        $totVotes = $s['total_votes'];
                    ?>
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between card-hover" id="survey-card-<?php echo $s['id']; ?>">
                        <div>
                            <div class="flex justify-between items-start mb-3">
                                <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-[11px] font-bold px-2.5 py-1 rounded-md uppercase tracking-wider border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active Poll
                                </span>
                                <span class="text-[11px] font-medium text-slate-400">
                                    <i class="fa-solid fa-users text-slate-400"></i> <?php echo number_format($totVotes); ?> votes cast
                                </span>
                            </div>

                            <h4 class="text-lg font-bold text-slate-900 mb-2">
                                <?php echo htmlspecialchars($s['title']); ?>
                            </h4>

                            <p class="text-sm font-bold text-slate-800 mb-2">
                                <?php echo htmlspecialchars($s['survey_question'] ?? $s['title']); ?>
                            </p>

                            <?php if (!empty($s['description'])): ?>
                                <div class="text-xs text-slate-600 mb-4 bg-slate-50 p-3.5 rounded-xl border border-slate-200 leading-relaxed max-h-36 overflow-y-auto">
                                    <i class="fa-solid fa-circle-info text-valenzuela-blue mr-1"></i>
                                    <?php echo nl2br(htmlspecialchars($s['description'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Poll Progress Bars -->
                            <div class="space-y-3 my-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <div>
                                    <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                        <span><?php echo htmlspecialchars($optA); ?></span>
                                        <span id="survey-pct-a-<?php echo $s['id']; ?>"><?php echo $pctA; ?>%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                        <div id="survey-bar-a-<?php echo $s['id']; ?>" class="bg-valenzuela-blue h-2 rounded-full transition-all duration-500" style="width: <?php echo $pctA; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                        <span><?php echo htmlspecialchars($optB); ?></span>
                                        <span id="survey-pct-b-<?php echo $s['id']; ?>"><?php echo $pctB; ?>%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                        <div id="survey-bar-b-<?php echo $s['id']; ?>" class="bg-valenzuela-red h-2 rounded-full transition-all duration-500" style="width: <?php echo $pctB; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vote Action / Voted Status Buttons -->
                        <div id="survey-action-buttons-<?php echo $s['id']; ?>" class="mt-2 space-y-2.5" data-opta="<?php echo htmlspecialchars($optA); ?>" data-optb="<?php echo htmlspecialchars($optB); ?>">
                            <?php if (!empty($s['user_vote'])): ?>
                                <div class="w-full bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold py-2 px-3.5 rounded-xl text-xs flex items-center justify-between shadow-sm">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                        <span>You voted: <strong class="uppercase font-extrabold text-emerald-950"><?php echo htmlspecialchars($s['user_vote']); ?></strong></span>
                                    </span>
                                    <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-2 py-0.5 rounded-full">(Click other button to change)</span>
                                </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-2 gap-3">
                                <?php 
                                    $userV = strtolower(trim($s['user_vote'] ?? ''));
                                    $isA = ($userV !== '' && $userV === strtolower(trim($optA)));
                                    $isB = ($userV !== '' && $userV === strtolower(trim($optB)));
                                ?>
                                <button onclick="castSurveyVote(<?php echo $s['id']; ?>, '<?php echo addslashes($optA); ?>')" class="w-full <?php echo $isA ? 'bg-emerald-600 text-white border-emerald-700 font-extrabold shadow' : 'bg-blue-50 hover:bg-valenzuela-blue hover:text-white text-valenzuela-blue border-blue-200 font-bold'; ?> border py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5">
                                    <i class="fa-solid <?php echo $isA ? 'fa-check-circle' : 'fa-thumbs-up'; ?>"></i> <?php echo $isA ? 'Voted ' . htmlspecialchars($optA) : 'Vote ' . htmlspecialchars($optA); ?>
                                </button>

                                <button onclick="castSurveyVote(<?php echo $s['id']; ?>, '<?php echo addslashes($optB); ?>')" class="w-full <?php echo $isB ? 'bg-red-600 text-white border-red-700 font-extrabold shadow' : 'bg-red-50 hover:bg-valenzuela-red hover:text-white text-valenzuela-red border-red-200 font-bold'; ?> border py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-1.5">
                                    <i class="fa-solid <?php echo $isB ? 'fa-check-circle' : 'fa-thumbs-down'; ?>"></i> <?php echo $isB ? 'Voted ' . htmlspecialchars($optB) : 'Vote ' . htmlspecialchars($optB); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- Submit Citizen Proposal / Concern Section -->
        <section id="submit-consultation" class="scroll-mt-24 pt-6 border-t border-slate-200">
            <div class="bg-slate-900 rounded-3xl overflow-hidden shadow-2xl relative text-white">
                <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 24px 24px;"></div>

                <div class="grid grid-cols-1 lg:grid-cols-5 relative z-10">
                    
                    <!-- Left Sidebar Info -->
                    <div class="lg:col-span-2 p-8 lg:p-12 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-slate-800">
                        <div>
                            <span class="inline-block bg-valenzuela-red text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-4">
                                Citizen Legislative Proposal
                            </span>
                            <h3 class="text-3xl font-extrabold mb-4 leading-tight">
                                Submit a Concern or Ordinance Proposal
                            </h3>
                            <p class="text-slate-300 text-sm leading-relaxed mb-8">
                                Do you have a suggestion for local policy or a community issue in your Barangay? Submit your proposal directly to the Legislative Office for review and public consultation.
                            </p>

                            <ul class="space-y-4">
                                <li class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </div>
                                    <div>
                                        <strong class="block text-sm text-white">Instant Tracking Code</strong>
                                        <span class="text-xs text-slate-400">Receive a unique tracking ID (TRK-2026-XXXXX) to track legislative progress.</span>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fa-solid fa-shield-halved text-xs"></i>
                                    </div>
                                    <div>
                                        <strong class="block text-sm text-white">Direct Legislative Review</strong>
                                        <span class="text-xs text-slate-400">Proposals are evaluated by City Council committees for potential ordinance drafting.</span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-800 text-xs text-slate-400 flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-valenzuela-blue"></i> Need help? Use our AI Legislative Chatbot in the bottom right!
                        </div>
                    </div>

                    <!-- Proposal Form -->
                    <div class="lg:col-span-3 bg-white p-8 lg:p-12 text-slate-800">
                        <?php if ($submission_success): ?>
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

                        <?php if ($submission_error): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-2">
                                <i class="fa-solid fa-circle-exclamation text-lg"></i>
                                <span><?php echo htmlspecialchars($submission_error); ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="submit_consultation" value="1">

                            <div>
                                <label for="title" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Proposal Title / Concern Subject <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="title" name="title" required placeholder="e.g. Solar Street Lighting Ordinance for Public Parks" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all">
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
                                    <input type="text" id="target_area" name="target_area" placeholder="e.g. Brgy. Malinta, Karuhatan, Citywide" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all">
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Detailed Description & Proposed Solution <span class="text-red-500">*</span>
                                </label>
                                <textarea id="description" name="description" rows="4" required placeholder="Explain the community concern, why legislative action is needed, and how it will benefit Valenzuelanos..." class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue focus:border-valenzuela-blue outline-none text-sm transition-all resize-none"></textarea>
                            </div>

                            <!-- User Info Fields if Guest -->
                            <?php if (!$is_logged_in): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Your Full Name</label>
                                    <input type="text" name="guest_name" required placeholder="Juan Dela Cruz" class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Your Email Address</label>
                                    <input type="email" name="guest_email" required placeholder="juan@example.com" class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm">
                                </div>
                            </div>
                            <?php endif; ?>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Attach Documents / Supporting Evidence (Optional)
                                </label>
                                <div class="border-2 border-dashed border-slate-300 rounded-xl p-5 text-center hover:bg-slate-50 transition-colors cursor-pointer" onclick="document.getElementById('file-upload-input').click()">
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
        </section>

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
    <div id="consultation-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto relative border border-slate-200">
            <button onclick="closeConsultationModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="p-6 sm:p-8 border-b border-slate-100">
                <span id="modal-category" class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md bg-blue-50 text-valenzuela-blue border border-blue-200 mb-3 inline-block"></span>
                <h3 id="modal-title" class="text-2xl font-black text-slate-900 mb-3"></h3>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 mb-4">
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

    <!-- Track Submission Status Modal -->
    <div id="track-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
    <div id="vote-success-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
    <div id="change-vote-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
    <div id="my-activity-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            showToast('Tracking Code copied to clipboard: ' + text);
        }

        function showRequireLoginModal() {
            const m = document.getElementById('require-login-modal');
            if (m) m.classList.remove('hidden');
        }

        function closeRequireLoginModal() {
            const m = document.getElementById('require-login-modal');
            if (m) m.classList.add('hidden');
        }

        // Community Survey Voting Functionality
        let pendingChangeVote = null;

        function castSurveyVote(surveyId, optionChosen, confirmChange = false) {
            if (!window.__IS_LOGGED_IN__) {
                window.location.href = 'google-auth.php';
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
                if (data.success) {
                    closeChangeVoteModal();

                    const pctA = document.getElementById('survey-pct-a-' + surveyId);
                    const barA = document.getElementById('survey-bar-a-' + surveyId);
                    const pctB = document.getElementById('survey-pct-b-' + surveyId);
                    const barB = document.getElementById('survey-bar-b-' + surveyId);

                    if (pctA && barA && data.pct_a !== undefined) {
                        pctA.textContent = data.pct_a + '%';
                        barA.style.width = data.pct_a + '%';
                    }
                    if (pctB && barB && data.pct_b !== undefined) {
                        pctB.textContent = data.pct_b + '%';
                        barB.style.width = data.pct_b + '%';
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
                        document.getElementById('modal-category').textContent = (d.category || 'General Governance').toUpperCase();
                        document.getElementById('modal-code').textContent = d.tracking_number || ('TRK-' + d.id);
                        document.getElementById('modal-end-date').textContent = d.end_date ? new Date(d.end_date).toLocaleDateString() : 'N/A';
                        document.getElementById('modal-description').textContent = d.description;

                        // Render feedback list
                        const list = document.getElementById('modal-feedback-list');
                        document.getElementById('modal-feedback-count').textContent = res.feedback.length;

                        if (res.feedback.length === 0) {
                            list.innerHTML = '<p class="text-xs text-slate-400 italic">No feedback submitted yet. Be the first citizen to voice your opinion!</p>';
                        } else {
                            list.innerHTML = res.feedback.map(f => `
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs space-y-1">
                                    <div class="flex justify-between font-bold text-slate-800">
                                        <span>${escapeHtml(f.guest_name || 'Anonymous Citizen')}</span>
                                        <span class="text-[10px] text-amber-500"><i class="fa-solid fa-star"></i> ${f.rating}/5</span>
                                    </div>
                                    <p class="text-slate-600">${escapeHtml(f.message)}</p>
                                    ${f.admin_response ? `<div class="mt-2 pl-3 border-l-2 border-valenzuela-blue text-valenzuela-blue font-semibold text-[11px]">Response: ${escapeHtml(f.admin_response)}</div>` : ''}
                                </div>
                            `).join('');
                        }

                        document.getElementById('consultation-modal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    } else {
                        showToast(res.message, 'error');
                    }
                });
        }

        function closeConsultationModal() {
            document.getElementById('consultation-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
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
            document.getElementById('track-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeTrackModal() {
            document.getElementById('track-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
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
                            'pending': 'bg-amber-100 text-amber-800',
                            'active': 'bg-emerald-100 text-emerald-800',
                            'reviewed': 'bg-blue-100 text-blue-800',
                            'closed': 'bg-slate-100 text-slate-800'
                        };
                        const statusBadge = statusColors[d.status] || 'bg-slate-100 text-slate-800';

                        container.innerHTML = `
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-mono font-bold text-valenzuela-blue">${escapeHtml(code)}</span>
                                    <span class="px-2 py-0.5 rounded-full font-bold uppercase ${statusBadge}">${escapeHtml(d.status)}</span>
                                </div>
                                <h5 class="font-bold text-slate-900 text-sm">${escapeHtml(d.title || d.consultation_title || 'Citizen Submission')}</h5>
                                <p class="text-slate-600">${escapeHtml(d.description || d.message || '')}</p>
                                ${d.admin_response ? `<div class="mt-3 p-3 bg-white rounded-xl border border-blue-200 text-valenzuela-blue"><strong>Legislative Response:</strong> ${escapeHtml(d.admin_response)}</div>` : '<p class="text-[11px] text-slate-400 italic mt-2">Under review by City Legislative Committee.</p>'}
                            </div>
                        `;
                    } else {
                        container.innerHTML = `<p class="text-xs text-red-600 font-semibold">${escapeHtml(res.message)}</p>`;
                    }
                });
        }

        // My Activity History Modal
        function showMyActivityModal() {
            const modal = document.getElementById('my-activity-modal');
            const content = document.getElementById('my-activity-content');
            if (modal) modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (content) {
                content.innerHTML = '<div class="text-center py-8 text-slate-400 flex items-center justify-center gap-2"><i class="fa-solid fa-spinner fa-spin text-valenzuela-blue text-lg"></i> <span>Loading your history...</span></div>';
            }

            fetch('index.php?api=get_my_activity')
                .then(r => r.json())
                .then(res => {
                    if (!content) return;
                    if (res.success) {
                        let html = '';
                        if ((!res.proposals || res.proposals.length === 0) && (!res.feedback || res.feedback.length === 0)) {
                            html = '<div class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl border border-slate-200"><i class="fa-solid fa-folder-open text-3xl text-slate-300 mb-2"></i><p class="text-xs italic">No proposal submissions or feedback recorded under your account yet.</p></div>';
                        } else {
                            if (res.proposals && res.proposals.length > 0) {
                                html += '<h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">My Submitted Proposals (' + res.proposals.length + ')</h4>';
                                html += res.proposals.map(p => `
                                    <div class="p-4 bg-slate-50 hover:bg-slate-100/80 rounded-2xl border border-slate-200 text-xs space-y-2 mb-3 transition-colors">
                                        <div class="flex justify-between items-start font-bold text-slate-800">
                                            <span class="text-sm text-slate-900">${escapeHtml(p.title)}</span>
                                            <span class="font-mono bg-blue-50 text-valenzuela-blue px-2.5 py-1 rounded-lg border border-blue-200 text-[11px]">${escapeHtml(p.tracking_number || 'TRK-PENDING')}</span>
                                        </div>
                                        <p class="text-slate-600 line-clamp-2">${escapeHtml(p.description || '')}</p>
                                        <div class="flex justify-between items-center pt-2 border-t border-slate-200 text-[11px] text-slate-400">
                                            <span>Category: ${escapeHtml(p.category || 'General')}</span>
                                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-bold uppercase text-[10px]">${escapeHtml(p.status || 'pending')}</span>
                                        </div>
                                    </div>
                                `).join('');
                            }
                            if (res.feedback && res.feedback.length > 0) {
                                html += '<h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mt-5 mb-3">My Feedback & Comments (' + res.feedback.length + ')</h4>';
                                html += res.feedback.map(f => `
                                    <div class="p-4 bg-slate-50 hover:bg-slate-100/80 rounded-2xl border border-slate-200 text-xs space-y-2 mb-3 transition-colors">
                                        <div class="flex justify-between items-start">
                                            <span class="font-bold text-slate-800">${escapeHtml(f.consultation_title || 'General Feedback')}</span>
                                            <span class="font-mono bg-slate-200 text-slate-700 px-2 py-0.5 rounded text-[10px]">${escapeHtml(f.tracking_token || '')}</span>
                                        </div>
                                        <p class="text-slate-700">${escapeHtml(f.message)}</p>
                                        ${f.admin_response ? `<div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-xl text-valenzuela-blue font-medium"><strong>Response:</strong> ${escapeHtml(f.admin_response)}</div>` : ''}
                                    </div>
                                `).join('');
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
        function closeMyActivityModal() {
            document.getElementById('my-activity-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
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
    </script>
</body>
</html>
