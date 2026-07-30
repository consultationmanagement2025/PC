<?php
/**
 * Consultation Feedback API
 * Handles submission and retrieval of public consultation feedback
 */
header('Content-Type: application/json');
$base_dir = __DIR__ . '/..';
if (file_exists($base_dir . '/db.php')) require_once $base_dir . '/db.php';
if (file_exists($base_dir . '/DATABASE/consultations.php')) require_once $base_dir . '/DATABASE/consultations.php';
if (file_exists($base_dir . '/DATABASE/user-logs.php')) require_once $base_dir . '/DATABASE/user-logs.php';
if (file_exists($base_dir . '/UTILS/security.php')) require_once $base_dir . '/UTILS/security.php';
if (file_exists($base_dir . '/UTILS/consultation_feedback_utils.php')) require_once $base_dir . '/UTILS/consultation_feedback_utils.php';
if (file_exists($base_dir . '/email_config_simple.php')) require_once $base_dir . '/email_config_simple.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim((string)$parts[0]);
    }
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function sanitizeDeviceToken($token) {
    $token = trim((string)$token);
    if (!preg_match('/^[A-Za-z0-9\-_]{16,64}$/', $token)) {
        return '';
    }
    return $token;
}

function generateVerificationCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function ensureEmailVerificationsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function storeVerificationCode($conn, $email, $code, $expiresInMinutes = 10) {
    ensureEmailVerificationsTable($conn);
    $deleteStmt = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
    if (!$deleteStmt) return false;
    $deleteStmt->bind_param("s", $email);
    $deleteStmt->execute();
    $deleteStmt->close();

    $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60));
    $stmt = $conn->prepare("INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("sss", $email, $code, $expiresAt);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function verifyCode($conn, $email, $code) {
    ensureEmailVerificationsTable($conn);
    $stmt = $conn->prepare("SELECT id FROM email_verifications WHERE email = ? AND code = ? AND expires_at > NOW() AND used = 0 LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result && $result->num_rows > 0;
    $stmt->close();

    if ($valid) {
        $updateStmt = $conn->prepare("UPDATE email_verifications SET used = 1 WHERE email = ? AND code = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("ss", $email, $code);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    return $valid;
}

function normalizeEmail($email) {
    return strtolower(trim((string)$email));
}

try {
    switch ($action) {
        // Get all feedback for a consultation
        case 'get_feedback':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $sql = "SELECT p.*, u.fullname, u.email FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    WHERE p.consultation_id = $consultation_id AND p.status = 'approved'
                    ORDER BY p.created_at DESC";
            
            $result = $conn->query($sql);
            $feedback = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $feedback[] = $row;
                }
            }

            echo json_encode(['success' => true, 'data' => $feedback, 'count' => count($feedback)]);
            break;

        // Submit feedback
        case 'submit_feedback':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['consultation_id', 'message'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                    exit;
                }
            }

            $consultation_id = (int)$data['consultation_id'];
            $messageRaw = trim((string)($data['message'] ?? ''));
            $category = trim((string)($data['category'] ?? 'General Feedback'));
            $user_id = $_SESSION['user_id'];
            $username = $_SESSION['fullname'] ?? 'Citizen';
            $user_email = $_SESSION['email'] ?? '';

            if ($consultation_id <= 0 || $messageRaw === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID and message are required']);
                exit;
            }

            if (isSpamFeedback($username, $user_email, $messageRaw)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Feedback appears spammy or inappropriate. Please revise your submission.']);
                exit;
            }

            $checkDup = $conn->prepare("SELECT id FROM posts WHERE consultation_id = ? AND user_id = ? AND content = ? LIMIT 1");
            if ($checkDup) {
                $checkDup->bind_param('iis', $consultation_id, $user_id, $messageRaw);
                $checkDup->execute();
                $dupRes = $checkDup->get_result();
                if ($dupRes && $dupRes->num_rows > 0) {
                    $checkDup->close();
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'Duplicate feedback detected for this consultation.']);
                    exit;
                }
                $checkDup->close();
            }

            $analysis = analyzeFeedbackText($messageRaw);
            $message = $conn->real_escape_string($messageRaw);
            $sentimentTag = $conn->real_escape_string($analysis['sentiment']);
            $sentimentScore = (float)$analysis['score'];
            $urgency = $conn->real_escape_string($analysis['urgency']);
            $topicsJson = $conn->real_escape_string(json_encode($analysis['topics']));

            $sql = "INSERT INTO posts (consultation_id, user_id, content, status, category, ai_sentiment_tag, ai_sentiment_score, ai_urgency, ai_topics, created_at)
                    VALUES ($consultation_id, $user_id, '$message', 'pending', '$category', '$sentimentTag', $sentimentScore, '$urgency', '$topicsJson', NOW())";

            if ($conn->query($sql)) {
                $post_id = $conn->insert_id;
                
                // Log the action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        $user_id,
                        $username,
                        'submit_feedback',
                        'create',
                        'feedback',
                        $post_id,
                        'User submitted feedback for consultation #' . $consultation_id,
                        'success',
                        json_encode(['consultation_id' => $consultation_id, 'category' => $category, 'sentiment' => $analysis['sentiment'], 'topics' => $analysis['topics']])
                    );
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback submitted successfully and awaits approval',
                    'feedback_id' => $post_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error submitting feedback']);
            }
            break;

        // Submit 2-option survey vote (agree/disagree)
        case 'submit_vote':
            $data = json_decode(file_get_contents('php://input'), true);
            $consultation_id = (int)($data['consultation_id'] ?? 0);
            $vote_option = strtolower(trim((string)($data['vote_option'] ?? '')));
            $reason_text = isset($data['reason_text']) ? trim((string)$data['reason_text']) : null;

            if (!$consultation_id || !in_array($vote_option, ['agree', 'disagree'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid vote request']);
                exit;
            }

            $policyStmt = $conn->prepare("SELECT response_mode, allow_guest_quick_vote, allow_guest_verified_vote FROM consultations WHERE id = ? LIMIT 1");
            $policy = null;
            if ($policyStmt) {
                $policyStmt->bind_param('i', $consultation_id);
                $policyStmt->execute();
                $policyRes = $policyStmt->get_result();
                $policy = $policyRes ? $policyRes->fetch_assoc() : null;
                $policyStmt->close();
            }
            $responseMode = strtolower((string)($policy['response_mode'] ?? 'hybrid'));
            $allowGuestQuickVote = isset($policy['allow_guest_quick_vote']) ? ((int)$policy['allow_guest_quick_vote'] === 1) : true;
            if ($responseMode === 'feedback') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Survey voting is disabled for this consultation']);
                exit;
            }

            // Logged-in users: normal vote flow
            if (isset($_SESSION['user_id'])) {
                $user_id = (int)$_SESSION['user_id'];
                $ok = submitConsultationVote($consultation_id, $user_id, $vote_option, $reason_text);
                if (!$ok) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to submit vote']);
                    exit;
                }

                if (function_exists('logUserAction')) {
                    logUserAction(
                        $user_id,
                        $_SESSION['fullname'] ?? 'User',
                        'submit_consultation_vote',
                        'update',
                        'consultation_vote',
                        $consultation_id,
                        'User voted on consultation survey',
                        'success',
                        json_encode(['consultation_id' => $consultation_id, 'vote_option' => $vote_option])
                    );
                }

                $stats = getConsultationVoteStats($consultation_id);
                echo json_encode([
                    'success' => true,
                    'message' => 'Vote recorded successfully',
                    'data' => array_merge($stats, ['user_vote' => $vote_option])
                ]);
                exit;
            }

            // Guest vote flow (no OTP): one vote per device + per-IP soft cap
            $device_token = sanitizeDeviceToken($data['device_token'] ?? '');
            if ($device_token === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid device token is required for guest voting']);
                exit;
            }
            if (!$allowGuestQuickVote) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Quick guest voting is disabled for this consultation']);
                exit;
            }

            $checkStmt = $conn->prepare("SELECT vote_option FROM consultation_guest_votes WHERE consultation_id = ? AND device_token = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('is', $consultation_id, $device_token);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                $existing = $checkRes ? $checkRes->fetch_assoc() : null;
                $checkStmt->close();
                if ($existing) {
                    $stats = getConsultationVoteStats($consultation_id);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Device already voted for this consultation',
                        'data' => array_merge($stats, ['user_vote' => strtolower((string)$existing['vote_option']), 'guest_vote' => true])
                    ]);
                    exit;
                }
            }

            $ip_hash = hash('sha256', getClientIp());
            $user_agent_hash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $ipCheckStmt = $conn->prepare("SELECT id FROM consultation_guest_votes WHERE consultation_id = ? AND ip_hash = ? LIMIT 1");
            if ($ipCheckStmt) {
                $ipCheckStmt->bind_param('is', $consultation_id, $ip_hash);
                $ipCheckStmt->execute();
                $ipCheckRes = $ipCheckStmt->get_result();
                $hasExistingIpVote = (bool)($ipCheckRes && $ipCheckRes->fetch_assoc());
                $ipCheckStmt->close();
                if ($hasExistingIpVote) {
                    $stats = getConsultationVoteStats($consultation_id);
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'This network has already voted for this consultation',
                        'data' => array_merge($stats, ['guest_vote' => true, 'already_voted' => true])
                    ]);
                    exit;
                }
            }

            $ok = submitConsultationGuestVote($consultation_id, $device_token, $vote_option, null, $ip_hash, $user_agent_hash, 0, $reason_text);
            if (!$ok) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to submit guest vote']);
                exit;
            }

            $stats = getConsultationVoteStats($consultation_id);
            echo json_encode([
                'success' => true,
                'message' => 'Vote recorded successfully',
                'data' => array_merge($stats, ['user_vote' => $vote_option, 'guest_vote' => true])
            ]);
            break;

        case 'send_vote_otp':
            $data = json_decode(file_get_contents('php://input'), true);
            $consultation_id = (int)($data['consultation_id'] ?? 0);
            $vote_option = strtolower(trim((string)($data['vote_option'] ?? '')));
            $email = normalizeEmail($data['email'] ?? '');
            $device_token = sanitizeDeviceToken($data['device_token'] ?? '');
            $reason_text = isset($data['reason_text']) ? trim((string)$data['reason_text']) : null;

            if (!$consultation_id || !in_array($vote_option, ['agree', 'disagree'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid vote request']);
                exit;
            }
            if ($device_token === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid device token is required']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid email is required']);
                exit;
            }

            $policyStmt = $conn->prepare("SELECT response_mode, allow_guest_verified_vote FROM consultations WHERE id = ? LIMIT 1");
            $policy = null;
            if ($policyStmt) {
                $policyStmt->bind_param('i', $consultation_id);
                $policyStmt->execute();
                $policyRes = $policyStmt->get_result();
                $policy = $policyRes ? $policyRes->fetch_assoc() : null;
                $policyStmt->close();
            }
            $responseMode = strtolower((string)($policy['response_mode'] ?? 'hybrid'));
            $allowGuestVerifiedVote = isset($policy['allow_guest_verified_vote']) ? ((int)$policy['allow_guest_verified_vote'] === 1) : true;
            if ($responseMode === 'feedback' || !$allowGuestVerifiedVote) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Verified guest voting is disabled for this consultation']);
                exit;
            }

            $otpCode = generateVerificationCode();
            if (!storeVerificationCode($conn, $email, $otpCode, 10)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to generate verification code']);
                exit;
            }

            $subject = 'Consultation Vote Verification Code';
            $body = "Your verification code for consultation vote is: {$otpCode}\n\n";
            $body .= "This code will expire in 10 minutes.\n";
            $body .= "If you did not request this, you may ignore this email.";
            $sent = sendGmailEmailSimple($email, $subject, $body, false);
            if ($sent !== true) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to send verification email']);
                exit;
            }

            $_SESSION['guest_vote_pending'] = $_SESSION['guest_vote_pending'] ?? [];
            $_SESSION['guest_vote_pending'][$consultation_id . '|' . $device_token . '|' . $email] = [
                'vote_option' => $vote_option,
                'expires_at' => time() + 600
            ];

            echo json_encode(['success' => true, 'message' => 'Verification code sent. Check your email.']);
            break;

        case 'submit_guest_vote':
            $data = json_decode(file_get_contents('php://input'), true);
            $consultation_id = (int)($data['consultation_id'] ?? 0);
            $email = normalizeEmail($data['email'] ?? '');
            $code = trim((string)($data['code'] ?? ''));
            $device_token = sanitizeDeviceToken($data['device_token'] ?? '');

            if (!$consultation_id || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code) !== 6 || $device_token === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid verification request']);
                exit;
            }

            $policyStmt = $conn->prepare("SELECT response_mode, allow_guest_verified_vote FROM consultations WHERE id = ? LIMIT 1");
            $policy = null;
            if ($policyStmt) {
                $policyStmt->bind_param('i', $consultation_id);
                $policyStmt->execute();
                $policyRes = $policyStmt->get_result();
                $policy = $policyRes ? $policyRes->fetch_assoc() : null;
                $policyStmt->close();
            }
            $responseMode = strtolower((string)($policy['response_mode'] ?? 'hybrid'));
            $allowGuestVerifiedVote = isset($policy['allow_guest_verified_vote']) ? ((int)$policy['allow_guest_verified_vote'] === 1) : true;
            if ($responseMode === 'feedback' || !$allowGuestVerifiedVote) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Verified guest voting is disabled for this consultation']);
                exit;
            }

            $pendingKey = $consultation_id . '|' . $device_token . '|' . $email;
            $pending = $_SESSION['guest_vote_pending'][$pendingKey] ?? null;
            if (!$pending || !isset($pending['vote_option'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No pending vote found. Request a new code.']);
                exit;
            }
            if (time() > (int)($pending['expires_at'] ?? 0)) {
                unset($_SESSION['guest_vote_pending'][$pendingKey]);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Verification code expired. Request a new code.']);
                exit;
            }

            if (!verifyCode($conn, $email, $code)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
                exit;
            }

            $vote_option = strtolower(trim((string)$pending['vote_option']));
            $ip_hash = hash('sha256', getClientIp());
            $user_agent_hash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

            $existingStmt = $conn->prepare("SELECT vote_option FROM consultation_guest_votes WHERE consultation_id = ? AND device_token = ? LIMIT 1");
            $hasExistingDeviceVote = false;
            $existingVoteOption = null;
            if ($existingStmt) {
                $existingStmt->bind_param('is', $consultation_id, $device_token);
                $existingStmt->execute();
                $existingRes = $existingStmt->get_result();
                $existing = $existingRes ? $existingRes->fetch_assoc() : null;
                $hasExistingDeviceVote = (bool)$existing;
                $existingVoteOption = $existing ? strtolower((string)($existing['vote_option'] ?? '')) : null;
                $existingStmt->close();
            }

            if ($hasExistingDeviceVote) {
                $stats = getConsultationVoteStats($consultation_id);
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Device already voted for this consultation',
                    'data' => array_merge($stats, ['user_vote' => $existingVoteOption, 'guest_vote' => true, 'already_voted' => true])
                ]);
                exit;
            }

            $ipCheckStmt = $conn->prepare("SELECT id FROM consultation_guest_votes WHERE consultation_id = ? AND ip_hash = ? LIMIT 1");
            if ($ipCheckStmt) {
                $ipCheckStmt->bind_param('is', $consultation_id, $ip_hash);
                $ipCheckStmt->execute();
                $ipCheckRes = $ipCheckStmt->get_result();
                $hasExistingIpVote = (bool)($ipCheckRes && $ipCheckRes->fetch_assoc());
                $ipCheckStmt->close();
                if ($hasExistingIpVote) {
                    $stats = getConsultationVoteStats($consultation_id);
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'This network has already voted for this consultation',
                        'data' => array_merge($stats, ['guest_vote' => true, 'already_voted' => true])
                    ]);
                    exit;
                }
            }

            $ok = submitConsultationGuestVote($consultation_id, $device_token, $vote_option, $email, $ip_hash, $user_agent_hash, 1, $reason_text);
            if (!$ok) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to submit verified guest vote']);
                exit;
            }

            unset($_SESSION['guest_vote_pending'][$pendingKey]);
            $stats = getConsultationVoteStats($consultation_id);
            echo json_encode([
                'success' => true,
                'message' => 'Verified vote recorded successfully',
                'data' => array_merge($stats, ['user_vote' => $vote_option, 'guest_vote' => true, 'otp_verified' => true])
            ]);
            break;

        case 'get_all_vote_stats':
            $res = $conn->query("
                SELECT consultation_id, vote_option, COUNT(*) as total
                FROM (
                    SELECT consultation_id, LOWER(vote_option) as vote_option FROM consultation_votes
                    UNION ALL
                    SELECT consultation_id, LOWER(vote_option) as vote_option FROM consultation_guest_votes
                ) all_votes
                GROUP BY consultation_id, vote_option
            ");
            $byConsultation = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $cid = (int)$row['consultation_id'];
                    $opt = strtolower(trim($row['vote_option']));
                    $cnt = (int)$row['total'];
                    if (!isset($byConsultation[$cid])) {
                        $byConsultation[$cid] = ['agree_votes' => 0, 'disagree_votes' => 0, 'total_votes' => 0];
                    }
                    if ($opt === 'agree') {
                        $byConsultation[$cid]['agree_votes'] += $cnt;
                    } else if ($opt === 'disagree') {
                        $byConsultation[$cid]['disagree_votes'] += $cnt;
                    }
                    $byConsultation[$cid]['total_votes'] += $cnt;
                }
            }
            echo json_encode(['success' => true, 'data' => $byConsultation]);
            break;

        // Get consultation survey vote stats
        case 'get_vote_stats':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $stats = getConsultationVoteStats($consultation_id);
            $user_vote = null;
            if (isset($_SESSION['user_id'])) {
                $user_vote = getUserConsultationVote($consultation_id, (int)$_SESSION['user_id']);
            }
            echo json_encode([
                'success' => true,
                'data' => array_merge($stats, ['user_vote' => $user_vote])
            ]);
            break;

        // Get consultation statistics for display
        case 'get_stats':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $stats = getConsultationStats($consultation_id);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        // Get recent feedback
        case 'get_recent':
            $limit = (int)($_GET['limit'] ?? 5);
            $sql = "SELECT p.*, u.fullname, c.title as consultation_title FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    LEFT JOIN consultations c ON p.consultation_id = c.id 
                    WHERE p.status = 'approved'
                    ORDER BY p.created_at DESC 
                    LIMIT $limit";
            
            $result = $conn->query($sql);
            $feedback = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $feedback[] = $row;
                }
            }

            echo json_encode(['success' => true, 'data' => $feedback]);
            break;

        // Get consultation feedback count
        case 'get_feedback_count':
            $consultation_id = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultation_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                exit;
            }

            $sql = "SELECT COUNT(*) as count FROM posts WHERE consultation_id = $consultation_id AND status = 'approved'";
            $result = $conn->query($sql);
            
            if ($result) {
                $row = $result->fetch_assoc();
                echo json_encode(['success' => true, 'count' => $row['count']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error retrieving count']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
