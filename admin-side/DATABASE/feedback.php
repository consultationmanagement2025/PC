<?php
require_once __DIR__ . '/../db.php';

// Initialize feedback table with enhanced schema for lifecycle tracking
function initializeFeedbackTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS feedback (
        id INT PRIMARY KEY AUTO_INCREMENT,
        guest_name VARCHAR(255),
        guest_email VARCHAR(255),
        guest_phone VARCHAR(15),
        consultation_id INT,
        rating INT CHECK(rating >= 1 AND rating <= 5),
        category VARCHAR(100),
        message LONGTEXT,
        sentiment_tag VARCHAR(20) DEFAULT NULL,
        sentiment_score DECIMAL(6,2) DEFAULT NULL,
        topic_tags JSON,
        analysis_summary LONGTEXT,
        allow_email_notifications TINYINT(1) DEFAULT 0,
        attachment_path VARCHAR(255) DEFAULT NULL,
        feedback_hash VARCHAR(64) UNIQUE,
        status ENUM('new', 'reviewed', 'responded', 'closed') DEFAULT 'new',
        admin_response LONGTEXT,
        admin_respondent INT,
        responded_at TIMESTAMP NULL,
        
        -- Feedback Lifecycle Tracking
        lifecycle_stage ENUM('received', 'analyzed', 'considered_in_policy', 'outcome_published') DEFAULT 'received',
        themes JSON,
        issue_priority INT CHECK(issue_priority >= 1 AND issue_priority <= 5),
        policy_link_id INT,
        policy_link_type VARCHAR(50),
        impact_summary LONGTEXT,
        
        -- Citizen Tracking
        tracking_token VARCHAR(64) UNIQUE,
        is_archived TINYINT(1) DEFAULT 0,
        archived_at DATETIME DEFAULT NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL,
        FOREIGN KEY (admin_respondent) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_lifecycle (lifecycle_stage),
        INDEX idx_priority (issue_priority),
        INDEX idx_tracking (tracking_token),
        INDEX idx_policy_link (policy_link_id),
        INDEX idx_sentiment (rating),
        INDEX idx_sentiment_tag (sentiment_tag),
        INDEX idx_archived_at (archived_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS submission_type ENUM('survey', 'proposal', 'comment') DEFAULT 'comment'");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS committee_assigned VARCHAR(150) DEFAULT NULL");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS barangay VARCHAR(150) DEFAULT NULL");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS sentiment_tag VARCHAR(20) DEFAULT NULL");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS sentiment_score DECIMAL(6,2) DEFAULT NULL");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS topic_tags JSON");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS analysis_summary LONGTEXT");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS allow_email_notifications TINYINT(1) DEFAULT 0");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) DEFAULT NULL");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS feedback_hash VARCHAR(64) UNIQUE");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS lifecycle_stage ENUM('received', 'analyzed', 'considered_in_policy', 'outcome_published') DEFAULT 'received'");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS themes JSON");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS issue_priority INT CHECK(issue_priority >= 1 AND issue_priority <= 5)");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS policy_link_id INT");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS policy_link_type VARCHAR(50)");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS impact_summary LONGTEXT");
        $conn->query("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS tracking_token VARCHAR(64) UNIQUE");
        return true;
    }
}

// Submit feedback (with automatic tracking token generation)
function submitFeedback($guest_name, $guest_email, $guest_phone, $consultation_id, $rating, $category, $message, $allow_email_notifications = 0) {
    global $conn;
    
    initializeFeedbackTable();
    
    $consultation_id = (int)$consultation_id;
    $rating = (int)$rating;
    $guest_name = trim($guest_name);
    $guest_email = strtolower(trim($guest_email));
    $message = trim($message);
    $category = trim($category) ?: 'General Feedback';

    // Prevent duplicate feedback for the same consultation and same message from same email
    $feedback_hash = hash('sha256', $consultation_id . '|' . $guest_email . '|' . mb_substr($message, 0, 256));
    $duplicateStmt = $conn->prepare("SELECT id FROM feedback WHERE consultation_id = ? AND guest_email = ? AND feedback_hash = ? LIMIT 1");
    if ($duplicateStmt) {
        $duplicateStmt->bind_param('iss', $consultation_id, $guest_email, $feedback_hash);
        $duplicateStmt->execute();
        $duplicateResult = $duplicateStmt->get_result();
        if ($duplicateResult && $duplicateResult->num_rows > 0) {
            $duplicateStmt->close();
            return ['error' => 'duplicate'];
        }
        $duplicateStmt->close();
    }

    // Generate unique tracking token and compute severity metadata
    $tracking_token = bin2hex(random_bytes(32));
    $analysis = analyzeFeedbackText($message);
    $topic_tags = json_encode($analysis['topics']);
    $analysis_summary = $analysis['summary'] ?? null;
    $sentiment_tag = $analysis['sentiment'];
    $sentiment_score = $analysis['score'];

    $stmt = $conn->prepare("INSERT INTO feedback (guest_name, guest_email, guest_phone, consultation_id, rating, category, message, sentiment_tag, sentiment_score, topic_tags, analysis_summary, allow_email_notifications, feedback_hash, status, lifecycle_stage, tracking_token, is_archived)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'received', ?, 0)");
    if (!$stmt) {
        error_log("Error preparing submitFeedback: " . $conn->error);
        return false;
    }

    $stmt->bind_param('sssissssdsisss', $guest_name, $guest_email, $guest_phone, $consultation_id, $rating, $category, $message, $sentiment_tag, $sentiment_score, $topic_tags, $analysis_summary, $allow_email_notifications, $feedback_hash, $tracking_token);
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $stmt->close();
        return ['id' => $id, 'tracking_token' => $tracking_token, 'sentiment' => $sentiment_tag, 'topics' => $analysis['topics']];
    }

    error_log("Error submitting feedback: " . $stmt->error);
    $stmt->close();
    return false;
}

// Get all feedback
function getFeedback($filters = [], $limit = 50, $offset = 0) {
    global $conn;
    
    initializeFeedbackTable();
    
    $where = "1=1";
    
    if (isset($filters['id']) && $filters['id']) {
        $id = (int)$filters['id'];
        $where .= " AND id = $id";
    }
    
    if (isset($filters['status']) && $filters['status']) {
        $status = $conn->real_escape_string($filters['status']);
        $where .= " AND status = '$status'";
    }
    
    if (isset($filters['consultation_id']) && $filters['consultation_id']) {
        $consultation_id = (int)$filters['consultation_id'];
        $where .= " AND consultation_id = $consultation_id";
    }
    
    if (isset($filters['rating']) && $filters['rating']) {
        $rating = (int)$filters['rating'];
        $where .= " AND rating = $rating";
    }
    
    if (isset($filters['sentiment_tag']) && $filters['sentiment_tag']) {
        $sentiment_tag = $conn->real_escape_string($filters['sentiment_tag']);
        $where .= " AND sentiment_tag = '$sentiment_tag'";
    }
    
    if (isset($filters['search']) && $filters['search']) {
        $search = $conn->real_escape_string($filters['search']);
        $where .= " AND (message LIKE '%$search%' OR guest_name LIKE '%$search%' OR guest_email LIKE '%$search%' OR category LIKE '%$search%')";
    }
    
    $sql = "SELECT * FROM feedback 
            WHERE $where 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($sql);
    $feedback = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $feedback[] = $row;
        }
    }
    
    return $feedback;
}

// Get feedback count
function getFeedbackCount($filters = []) {
    global $conn;
    
    initializeFeedbackTable();
    
    $where = "1=1";
    
    if (isset($filters['status']) && $filters['status']) {
        $status = $conn->real_escape_string($filters['status']);
        $where .= " AND status = '$status'";
    }
    
    if (isset($filters['consultation_id']) && $filters['consultation_id']) {
        $consultation_id = (int)$filters['consultation_id'];
        $where .= " AND consultation_id = $consultation_id";
    }
    
    if (isset($filters['sentiment_tag']) && $filters['sentiment_tag']) {
        $sentiment_tag = $conn->real_escape_string($filters['sentiment_tag']);
        $where .= " AND sentiment_tag = '$sentiment_tag'";
    }
    
    $sql = "SELECT COUNT(*) as count FROM feedback WHERE $where";
    $result = $conn->query($sql);
    
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    return 0;
}

// Update feedback status
function updateFeedbackStatus($id, $status) {
    global $conn;
    
    $id = (int)$id;

    $stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    if (!$stmt) {
        error_log("Error preparing updateFeedbackStatus: " . $conn->error);
        return false;
    }
    $stmt->bind_param('si', $status, $id);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("Error updating feedback status: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

// Respond to feedback
function respondToFeedback($id, $response, $admin_id) {
    global $conn;
    
    $id = (int)$id;
    $admin_id = (int)$admin_id;

    $stmt = $conn->prepare("UPDATE feedback
            SET admin_response = ?,
                admin_respondent = ?,
                status = 'responded',
                responded_at = NOW()
            WHERE id = ?");
    if (!$stmt) {
        error_log("Error preparing respondToFeedback: " . $conn->error);
        return false;
    }
    $stmt->bind_param('sii', $response, $admin_id, $id);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("Error responding to feedback: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

function archiveFeedback($id) {
    global $conn;
    $id = (int)$id;
    $stmt = $conn->prepare("UPDATE feedback SET is_archived = 1, archived_at = NOW() WHERE id = ?");
    if (!$stmt) {
        error_log("Error preparing archiveFeedback: " . $conn->error);
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("Error archiving feedback: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

function forwardFeedbackToCommittee($id, $committee, $admin_id = 1, $notes = '') {
    global $conn;
    $id = (int)$id;
    $committee = trim((string)$committee);
    $admin_id = (int)$admin_id;
    $notes = trim((string)$notes);

    $stmt = $conn->prepare("UPDATE feedback 
            SET committee_assigned = ?, 
                status = 'forwarded', 
                admin_respondent = ?, 
                impact_summary = CONCAT(IFNULL(impact_summary, ''), '\n[Forwarded to ', ?, ']: ', ?)
            WHERE id = ?");
    if (!$stmt) {
        error_log("Error preparing forwardFeedbackToCommittee: " . $conn->error);
        return false;
    }
    $stmt->bind_param('sissi', $committee, $admin_id, $committee, $notes, $id);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("Error forwarding feedback to committee: " . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

function analyzeFeedbackText($text) {
    $text = trim((string)$text);
    $result = [
        'sentiment' => 'neutral',
        'score' => 0.0,
        'topics' => [],
        'summary' => null,
    ];
    if ($text === '') {
        return $result;
    }
    
    $positive = [
        'good' => 2, 'great' => 3, 'excellent' => 3, 'satisfied' => 2, 'helpful' => 2,
        'thank' => 1, 'thanks' => 1, 'support' => 2, 'safe' => 2, 'improved' => 2,
        'maayos' => 2, 'maganda' => 2, 'salamat' => 1
    ];
    $negative = [
        'bad' => -2, 'worst' => -3, 'slow' => -2, 'problem' => -2, 'issue' => -2,
        'unsafe' => -2, 'dirty' => -2, 'corrupt' => -3, 'failed' => -2, 'delayed' => -2,
        'mabagal' => -2, 'marumi' => -2, 'pangit' => -3, 'hindi' => -1
    ];
    $topicsMap = [
        'infrastructure' => ['road', 'roads', 'drainage', 'flood', 'pothole', 'traffic', 'kalsada', 'baha'],
        'health' => ['health', 'hospital', 'clinic', 'medicine', 'doctor', 'kalusugan', 'ospital'],
        'education' => ['school', 'student', 'teacher', 'education', 'paaralan', 'guro'],
        'safety' => ['safety', 'police', 'crime', 'security', 'kaligtasan', 'pulis'],
        'environment' => ['garbage', 'waste', 'pollution', 'environment', 'basura', 'kalikasan'],
        'governance' => ['service', 'permit', 'office', 'queue', 'process', 'serbisyo', 'pila']
    ];
    
    $lc = mb_strtolower($text, 'UTF-8');
    $clean = preg_replace('/[^a-z0-9\s]/u', ' ', $lc);
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    $score = 0;
    foreach ($words as $w) {
        if (isset($positive[$w])) {
            $score += $positive[$w];
        }
        if (isset($negative[$w])) {
            $score += $negative[$w];
        }
    }

    $topicCounts = [];
    foreach ($topicsMap as $topic => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($lc, $keyword) !== false) {
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            }
        }
    }
    arsort($topicCounts);
    $topics = array_slice(array_keys($topicCounts), 0, 3);

    if ($score > 1) {
        $result['sentiment'] = 'positive';
    } elseif ($score < -1) {
        $result['sentiment'] = 'negative';
    }
    $result['score'] = round($score, 2);
    $result['topics'] = $topics;

    $summaryParts = [];
    if (!empty($topics)) {
        $summaryParts[] = 'Topics: ' . implode(', ', $topics);
    }
    if ($result['sentiment'] === 'negative') {
        $summaryParts[] = 'Tone: Concern/negative';
    } elseif ($result['sentiment'] === 'positive') {
        $summaryParts[] = 'Tone: Positive';
    } else {
        $summaryParts[] = 'Tone: Neutral';
    }
    $result['summary'] = implode('; ', $summaryParts);

    return $result;
}
// Get feedback statistics
function getFeedbackStats() {
    global $conn;
    
    initializeFeedbackTable();
    
    $sql = "SELECT 
            COUNT(*) as total_feedback,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_feedback,
            SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded_feedback,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as excellent_count,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as good_count,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as average_count,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as poor_count,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as very_poor_count
            FROM feedback";
    
    $result = $conn->query($sql);
    
    if ($result) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get feedback by consultation
function getFeedbackByConsultation($consultation_id) {
    global $conn;
    
    initializeFeedbackTable();
    
    $consultation_id = (int)$consultation_id;
    
    $sql = "SELECT 
            COUNT(*) as total,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN sentiment_tag = 'positive' THEN 1 ELSE 0 END) as positive_count,
            SUM(CASE WHEN sentiment_tag = 'neutral' THEN 1 ELSE 0 END) as neutral_count,
            SUM(CASE WHEN sentiment_tag = 'negative' THEN 1 ELSE 0 END) as negative_count,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived_count
            FROM feedback WHERE consultation_id = $consultation_id";
    
    $result = $conn->query($sql);
    
    if ($result) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Initialize hearing_queue table if not exists for PHMS integration
function initializeHearingQueueTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS hearing_queue (
      queue_id INT(11) NOT NULL AUTO_INCREMENT,
      phms_hearing_id INT(11) DEFAULT NULL,
      phms_registration_id INT(11) DEFAULT NULL,
      full_name VARCHAR(150) NOT NULL,
      email VARCHAR(150) DEFAULT NULL,
      status VARCHAR(40) NOT NULL DEFAULT 'queued',
      external_ref VARCHAR(128) DEFAULT NULL,
      source_system VARCHAR(64) DEFAULT 'PHMS',
      consultation_id INT(11) DEFAULT NULL,
      payload_json LONGTEXT DEFAULT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
      PRIMARY KEY (queue_id),
      KEY idx_hearing (phms_hearing_id),
      KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    return true;
}

// Retrieve PHMS feedback queue items
function getPhmsFeedbackQueue($filters = [], $limit = 50, $offset = 0) {
    global $conn;
    initializeHearingQueueTable();
    seedPhmsHearingQueueIfEmpty(false);

    $where = "1=1";
    if (!empty($filters['status'])) {
        $st = $conn->real_escape_string($filters['status']);
        $where .= " AND status = '$st'";
    }

    if (!empty($filters['search'])) {
        $search = $conn->real_escape_string($filters['search']);
        $where .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR external_ref LIKE '%$search%' OR payload_json LIKE '%$search%')";
    }

    $limit = (int)$limit;
    $offset = (int)$offset;

    $sql = "SELECT hq.*, c.title as consultation_title
            FROM hearing_queue hq
            LEFT JOIN consultations c ON hq.consultation_id = c.id
            WHERE $where
            ORDER BY hq.created_at DESC
            LIMIT $limit OFFSET $offset";

    $res = $conn->query($sql);
    $items = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

/**
/**
 * Convert local hearing_queue table items into standard PHMS hearing response structure.
 * Preserves last usable cached data when live PHMS service is unreachable.
 */
function getPhmsFeedbackQueueAsHearings($filters = [], $limit = 50, $offset = 0) {
    global $conn;
    initializeHearingQueueTable();
    $items = getPhmsFeedbackQueue($filters, $limit, $offset);
    $hearings = [];
    foreach ($items as $row) {
        $payload = [];
        if (!empty($row['payload_json'])) {
            $payload = is_string($row['payload_json']) ? json_decode($row['payload_json'], true) : $row['payload_json'];
        }
        if (!empty($payload['citizen_responses']) && is_array($payload['citizen_responses'])) {
            $citizenResponses = $payload['citizen_responses'];
        } else {
            $citizenFeedback = isset($payload['citizen_feedback']) && is_array($payload['citizen_feedback']) ? $payload['citizen_feedback'] : [];
            $citizenResponses = array_map(function($fb, $idx) {
                return [
                    'feedback_id' => $idx + 1,
                    'citizen_name' => $fb['citizen_name'] ?? $fb['name'] ?? 'Anonymous Citizen',
                    'rating' => (float)($fb['rating'] ?? 3.5),
                    'tone' => $fb['tone'] ?? $fb['sentiment'] ?? 'Neutral',
                    'testimony' => $fb['testimony'] ?? $fb['statement'] ?? '',
                    'submitted_at' => $fb['submitted_at'] ?? $fb['date'] ?? date('Y-m-d'),
                    'publication_status' => 'published'
                ];
            }, $citizenFeedback, array_keys($citizenFeedback));
        }

        $hearings[] = [
            'hearing_id' => (int)($row['phms_hearing_id'] ?: $row['queue_id']),
            'hearing_title' => $payload['hearing_title'] ?? $row['full_name'] ?? 'Public Hearing',
            'hearing_status' => strtolower($payload['hearing_status'] ?? $row['status'] ?? 'completed'),
            'hearing_date' => $payload['hearing_date'] ?? date('Y-m-d H:i:s', strtotime($row['created_at'] ?? 'now')),
            'feedback_count' => (int)($payload['feedback_count'] ?? count($citizenResponses)),
            'average_rating' => (float)($payload['avg_rating'] ?? $payload['average_rating'] ?? 3.5),
            'published_count' => (int)($payload['published_count'] ?? count($citizenResponses)),
            'pending_count' => (int)($payload['pending_count'] ?? 0),
            'latest_feedback_at' => $payload['latest_feedback_at'] ?? $row['updated_at'] ?? date('Y-m-d H:i:s'),
            'citizen_responses' => $citizenResponses
        ];
    }
    return $hearings;
}

/**
 * Synchronizes local hearing_queue table with the returned PHMS hearings collection snapshot.
 * Replaces stale PCMS PHMS records using a database transaction.
 */
function syncPhmsCollectionToDatabase(array $hearings) {
    global $conn;
    initializeHearingQueueTable();

    $returnedHearingIds = [];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO hearing_queue 
            (phms_hearing_id, phms_registration_id, full_name, email, status, external_ref, source_system, consultation_id, payload_json) 
            VALUES (?, ?, ?, ?, ?, ?, 'PHMS', ?, ?) 
            ON DUPLICATE KEY UPDATE 
                full_name = VALUES(full_name), 
                email = VALUES(email), 
                status = VALUES(status), 
                external_ref = VALUES(external_ref), 
                payload_json = VALUES(payload_json), 
                created_at = CURRENT_TIMESTAMP");

        foreach ($hearings as $h) {
            $hearingId = (int)($h['hearing_id'] ?? $h['id'] ?? 0);
            if ($hearingId <= 0) continue;

            $returnedHearingIds[] = $hearingId;

            $regId = (int)($h['phms_registration_id'] ?? $h['registration_id'] ?? 5000 + $hearingId);
            $fullName = $h['hearing_title'] ?? $h['title'] ?? $h['full_name'] ?? ('Public Hearing #' . $hearingId);
            $email = $h['email'] ?? 'phms-integration@valenzuela.gov.ph';
            $status = strtolower($h['hearing_status'] ?? $h['status'] ?? 'completed');
            $extRef = $h['external_ref'] ?? ('PHMS-HEARING-' . $hearingId);
            $consultationId = (int)($h['consultation_id'] ?? 1);
            $payloadJson = json_encode($h);

            if ($stmt) {
                $stmt->bind_param("iissssis", $hearingId, $regId, $fullName, $email, $status, $extRef, $consultationId, $payloadJson);
                $stmt->execute();
            }
        }
        // Add system notification for received PHMS hearing feedback records
        require_once __DIR__ . '/notifications.php';
        foreach ($hearings as $h) {
            $hearingId = (int)($h['hearing_id'] ?? $h['id'] ?? 0);
            if ($hearingId <= 0) continue;
            $title = $conn->real_escape_string($h['hearing_title'] ?? $h['title'] ?? $h['full_name'] ?? ('Public Hearing #' . $hearingId));
            $fbCount = (int)($h['feedback_count'] ?? 0);
            $msg = "🔗 PHMS Feedback Received: Ingested {$fbCount} citizen hearing feedback response(s) for \"{$title}\".";
            
            $chkRes = $conn->query("SELECT id FROM notifications WHERE message LIKE '%" . substr($title, 0, 30) . "%' AND created_at >= CURDATE() LIMIT 1");
            if (!$chkRes || $chkRes->num_rows === 0) {
                createNotification(0, $msg, 'phms_integration');
            }
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("Failed to sync PHMS hearings transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Server-side cURL helper to fetch Citizen Feedback from PHMS API integration.
 * Reads PHMS_BASE_URL and PCMS_INTEGRATION_TOKEN from config/environment.
 * Token is NEVER exposed to browser JS or client-side code.
 */
function fetchPhmsFeedbackFromApi($hearingId = null, $limit = 50, $offset = 0) {
    if (!defined('PHMS_BASE_URL')) {
        $baseUrl = function_exists('app_env') ? app_env('PHMS_BASE_URL', app_env('PHMS_URL', 'https://phms.spvalenzuela.com')) : (getenv('PHMS_BASE_URL') ?: 'https://phms.spvalenzuela.com');
    } else {
        $baseUrl = PHMS_BASE_URL;
    }

    if (!defined('PCMS_INTEGRATION_TOKEN')) {
        $token = function_exists('app_env') 
            ? app_env('PCMS_INTEGRATION_TOKEN', app_env('LGU2_PHMS_TOKEN', 'phms_live_2d6f8a4c1e9057b3a9c5e7f2b4d80156')) 
            : (getenv('PCMS_INTEGRATION_TOKEN') ?: (getenv('LGU2_PHMS_TOKEN') ?: 'phms_live_2d6f8a4c1e9057b3a9c5e7f2b4d80156'));
    } else {
        $token = PCMS_INTEGRATION_TOKEN;
    }

    $baseUrl = rtrim($baseUrl, '/');
    if ($hearingId !== null && $hearingId !== '') {
        $url = $baseUrl . '/api/v1/feedback.php?hearing_id=' . urlencode($hearingId);
    } else {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $url = $baseUrl . '/api/v1/feedback.php?limit=' . $limit . '&offset=' . $offset;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // If live API succeeds (HTTP 200 and valid JSON)
    if ($response !== false && empty($curlErr) && $httpCode === 200) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && !empty($decoded['success']) && isset($decoded['data'])) {
            // When fetching full collection (no hearingId specified), synchronize DB transaction snapshot
            if ($hearingId === null || $hearingId === '') {
                $hearings = $decoded['data']['hearings'] ?? [];
                syncPhmsCollectionToDatabase($hearings);
            }
            return $decoded;
        }
    }

    // Return explicit failure if PHMS is unreachable or returns error
    return [
        'success' => false,
        'message' => 'Unable to load PHMS feedback.',
        'http_code' => $httpCode ?: 503,
        'data' => null
    ];
}

// Update status of a PHMS queue item
function updatePhmsQueueStatus($queue_id, $status) {
    global $conn;
    initializeHearingQueueTable();

    $queue_id = (int)$queue_id;
    $status = $conn->real_escape_string($status);

    $stmt = $conn->prepare("UPDATE hearing_queue SET status = ? WHERE queue_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $status, $queue_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

?>
