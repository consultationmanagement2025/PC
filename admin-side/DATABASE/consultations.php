<?php

require_once __DIR__ . '/../db.php';



// Initialize consultations table

function initializeConsultationsTable() {

    global $conn;

    

    $sql = "CREATE TABLE IF NOT EXISTS consultations (

        id INT PRIMARY KEY AUTO_INCREMENT,

        title VARCHAR(255) NOT NULL,

        description LONGTEXT NOT NULL,

        category VARCHAR(100),

        status ENUM('draft','pending','scheduled','active','viewed','replied','completed','closed','archived') DEFAULT 'active',

        type ENUM('admin','user') DEFAULT 'admin',

        start_date DATETIME,

        end_date DATETIME,

        admin_id INT,

        user_name VARCHAR(255),

        user_email VARCHAR(255),

        allow_email_notifications TINYINT(1) DEFAULT 1,

        expected_posts INT DEFAULT 0,

        views INT DEFAULT 0,

        posts_count INT DEFAULT 0,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        image_path VARCHAR(255),

        source_url VARCHAR(255),

        tracking_number VARCHAR(32) DEFAULT NULL,

        INDEX idx_status (status),

        INDEX idx_type (type),

        INDEX idx_dates (start_date, end_date),

        INDEX idx_admin (admin_id),

        UNIQUE INDEX idx_tracking_number (tracking_number),

        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    

    $conn->query($sql);

    

    // Check and add missing columns
    $required_columns = [
        'admin_id' => "INT",
        'assigned_to' => "INT DEFAULT NULL",
        'type' => "ENUM('admin','user') DEFAULT 'admin'",
        'user_name' => "VARCHAR(255)",
        'user_email' => "VARCHAR(255)",
        'allow_email_notifications' => "TINYINT(1) DEFAULT 1",
        'expected_posts' => "INT DEFAULT 0",
        'views' => "INT DEFAULT 0",
        'posts_count' => "INT DEFAULT 0",
        'image_path' => "VARCHAR(255)",
        'source_url' => "VARCHAR(255)",
        'response_mode' => "ENUM('feedback','survey','hybrid') DEFAULT 'hybrid'",
        'survey_question' => "VARCHAR(255) DEFAULT NULL",
        'survey_option_a' => "VARCHAR(100) DEFAULT 'Agree'",
        'survey_option_b' => "VARCHAR(100) DEFAULT 'Disagree'",
        'allow_guest_quick_vote' => "TINYINT(1) DEFAULT 1",
        'allow_guest_verified_vote' => "TINYINT(1) DEFAULT 1",
        'availability_id' => "INT DEFAULT NULL",
        'requested_date' => "DATE DEFAULT NULL",
        'requested_start_time' => "TIME DEFAULT NULL",
        'requested_end_time' => "TIME DEFAULT NULL",
        'scheduled_date' => "DATE DEFAULT NULL",
        'scheduled_start_time' => "TIME DEFAULT NULL",
        'scheduled_end_time' => "TIME DEFAULT NULL",
        'meeting_platform' => "VARCHAR(50) DEFAULT NULL",
        'meeting_link' => "VARCHAR(500) DEFAULT NULL",
        'schedule_status' => "ENUM('requested','confirmed','rescheduled','cancelled') DEFAULT 'requested'",
        'tracking_number' => "VARCHAR(32) DEFAULT NULL",
        'outcome' => "ENUM('solved','needs-follow-up','escalated') DEFAULT NULL",
        'remarks' => "LONGTEXT DEFAULT NULL"
    ];
    
    $result = $conn->query("SHOW COLUMNS FROM consultations");
    $existing_columns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
    }
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            $conn->query("ALTER TABLE consultations ADD COLUMN $column $definition");
        }
    }

    // Ensure tracking number has a unique index for lookup performance.
    $trackingIndex = $conn->query("SHOW INDEX FROM consultations WHERE Key_name = 'idx_tracking_number'");
    if ($trackingIndex && $trackingIndex->num_rows === 0) {
        $conn->query("ALTER TABLE consultations ADD UNIQUE INDEX idx_tracking_number (tracking_number)");
    }

    // Ensure status enum includes "scheduled" for date-driven lifecycle.
    $statusCol = $conn->query("SHOW COLUMNS FROM consultations LIKE 'status'");
    if ($statusCol && $statusCol->num_rows > 0) {
        $statusInfo = $statusCol->fetch_assoc();
        $statusType = strtolower((string)($statusInfo['Type'] ?? ''));
        if (strpos($statusType, "'scheduled'") === false) {
            $conn->query("ALTER TABLE consultations MODIFY COLUMN status ENUM('draft','pending','scheduled','active','viewed','replied','completed','closed','archived') DEFAULT 'active'");
        }
    }

    initializeConsultationVotesTable();

    if ($conn->query($sql) === TRUE) {

        return true;

    } else {

        error_log("Error creating consultations table: " . $conn->error);

        return false;

    }

}

function deriveConsultationStatus($start_date, $end_date, $fallback = 'active') {
    $startTs = $start_date ? strtotime((string)$start_date) : false;
    $endTs = $end_date ? strtotime((string)$end_date) : false;
    $nowTs = time();

    if ($startTs !== false && $nowTs < $startTs) {
        return 'scheduled';
    }
    if ($endTs !== false && $nowTs >= $endTs) {
        return 'closed';
    }
    if ($startTs !== false || $endTs !== false) {
        return 'active';
    }

    return $fallback;
}

function syncConsultationStatuses() {
    global $conn;

    initializeConsultationsTable();
    $sql = "UPDATE consultations
            SET status = CASE
                WHEN start_date IS NOT NULL AND NOW() < start_date THEN 'scheduled'
                WHEN end_date IS NOT NULL AND NOW() >= end_date THEN 'closed'
                ELSE 'active'
            END
            WHERE status IN ('scheduled','active','closed')";
    $conn->query($sql);
}



// Initialize consultation vote table (2-option survey)
function initializeConsultationVotesTable() {

    global $conn;



    $sql = "CREATE TABLE IF NOT EXISTS consultation_votes (

        id INT PRIMARY KEY AUTO_INCREMENT,

        consultation_id INT NOT NULL,

        user_id INT NOT NULL,

        vote_option ENUM('agree', 'disagree') NOT NULL,

        reason_text TEXT DEFAULT NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY uq_consultation_user (consultation_id, user_id),

        INDEX idx_consultation (consultation_id),

        INDEX idx_vote_option (vote_option),

        CONSTRAINT fk_vote_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,

        CONSTRAINT fk_vote_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";



    if (!$conn->query($sql)) {

        error_log("Error creating consultation_votes table: " . $conn->error);

        return false;

    }



    return true;

}

// Initialize guest consultation vote table (no-login anti-abuse voting)
function initializeConsultationGuestVotesTable() {

    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS consultation_guest_votes (

        id INT PRIMARY KEY AUTO_INCREMENT,

        consultation_id INT NOT NULL,

        guest_email VARCHAR(255) DEFAULT NULL,

        device_token VARCHAR(64) NOT NULL,

        vote_option ENUM('agree', 'disagree') NOT NULL,

        reason_text TEXT DEFAULT NULL,

        otp_verified TINYINT(1) NOT NULL DEFAULT 0,

        ip_hash CHAR(64) DEFAULT NULL,

        user_agent_hash CHAR(64) DEFAULT NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY uq_guest_consultation_device (consultation_id, device_token),

        INDEX idx_guest_consultation (consultation_id),

        INDEX idx_guest_vote_option (vote_option),

        CONSTRAINT fk_guest_vote_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {

        error_log("Error creating consultation_guest_votes table: " . $conn->error);

        return false;

    }

    // Migration for existing installs
    $colDevice = $conn->query("SHOW COLUMNS FROM consultation_guest_votes LIKE 'device_token'");
    if ($colDevice && $colDevice->num_rows === 0) {
        $conn->query("ALTER TABLE consultation_guest_votes ADD COLUMN device_token VARCHAR(64) NOT NULL DEFAULT '' AFTER guest_email");
    }
    $conn->query("UPDATE consultation_guest_votes SET device_token = CONCAT('legacy_', id) WHERE device_token IS NULL OR device_token = ''");

    $colReasonGuest = $conn->query("SHOW COLUMNS FROM consultation_guest_votes LIKE 'reason_text'");
    if ($colReasonGuest && $colReasonGuest->num_rows === 0) {
        $conn->query("ALTER TABLE consultation_guest_votes ADD COLUMN reason_text TEXT DEFAULT NULL AFTER vote_option");
    }

    $colGuestEmail = $conn->query("SHOW COLUMNS FROM consultation_guest_votes LIKE 'guest_email'");
    if ($colGuestEmail && $colGuestEmail->num_rows > 0) {
        $conn->query("ALTER TABLE consultation_guest_votes MODIFY guest_email VARCHAR(255) DEFAULT NULL");
    }

    $idxEmail = $conn->query("SHOW INDEX FROM consultation_guest_votes WHERE Key_name = 'uq_guest_consultation_email'");
    if ($idxEmail && $idxEmail->num_rows > 0) {
        $conn->query("ALTER TABLE consultation_guest_votes DROP INDEX uq_guest_consultation_email");
    }

    $idxDevice = $conn->query("SHOW INDEX FROM consultation_guest_votes WHERE Key_name = 'uq_guest_consultation_device'");
    if ($idxDevice && $idxDevice->num_rows === 0) {
        $conn->query("ALTER TABLE consultation_guest_votes ADD UNIQUE KEY uq_guest_consultation_device (consultation_id, device_token)");
    }

    return true;
}



function getConsultationVoteStats($consultation_id) {

    global $conn;



    initializeConsultationVotesTable();
    initializeConsultationGuestVotesTable();

    $consultation_id = (int)$consultation_id;



    $stmt = $conn->prepare("SELECT

            (SELECT COUNT(*) FROM consultation_votes WHERE consultation_id = ? AND vote_option = 'agree')
            + (SELECT COUNT(*) FROM consultation_guest_votes WHERE consultation_id = ? AND vote_option = 'agree') AS agree_votes,

            (SELECT COUNT(*) FROM consultation_votes WHERE consultation_id = ? AND vote_option = 'disagree')
            + (SELECT COUNT(*) FROM consultation_guest_votes WHERE consultation_id = ? AND vote_option = 'disagree') AS disagree_votes,

            (SELECT COUNT(*) FROM consultation_votes WHERE consultation_id = ?)
            + (SELECT COUNT(*) FROM consultation_guest_votes WHERE consultation_id = ?) AS total_votes");

    if (!$stmt) {

        error_log("Error preparing getConsultationVoteStats: " . $conn->error);

        return ['agree_votes' => 0, 'disagree_votes' => 0, 'total_votes' => 0, 'agree_percent' => 0, 'disagree_percent' => 0];

    }



    $stmt->bind_param('iiiiii', $consultation_id, $consultation_id, $consultation_id, $consultation_id, $consultation_id, $consultation_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result ? $result->fetch_assoc() : null;

    $stmt->close();



    $agree = (int)($row['agree_votes'] ?? 0);

    $disagree = (int)($row['disagree_votes'] ?? 0);

    $total = (int)($row['total_votes'] ?? 0);



    $agreePercent = $total > 0 ? round(($agree / $total) * 100, 1) : 0;

    $disagreePercent = $total > 0 ? round(($disagree / $total) * 100, 1) : 0;



    return [

        'agree_votes' => $agree,

        'disagree_votes' => $disagree,

        'total_votes' => $total,

        'agree_percent' => $agreePercent,

        'disagree_percent' => $disagreePercent

    ];

}



function getUserConsultationVote($consultation_id, $user_id) {

    global $conn;



    initializeConsultationVotesTable();

    $consultation_id = (int)$consultation_id;

    $user_id = (int)$user_id;



    $stmt = $conn->prepare("SELECT vote_option FROM consultation_votes WHERE consultation_id = ? AND user_id = ? LIMIT 1");

    if (!$stmt) {

        error_log("Error preparing getUserConsultationVote: " . $conn->error);

        return null;

    }



    $stmt->bind_param('ii', $consultation_id, $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result ? $result->fetch_assoc() : null;

    $stmt->close();



    return $row['vote_option'] ?? null;

}



function submitConsultationVote($consultation_id, $user_id, $vote_option, $reason_text = null) {

    global $conn;



    initializeConsultationVotesTable();



    $consultation_id = (int)$consultation_id;

    $user_id = (int)$user_id;

    $vote_option = strtolower(trim((string)$vote_option));



    if (!in_array($vote_option, ['agree', 'disagree'], true)) {

        return false;

    }



    $stmt = $conn->prepare("INSERT INTO consultation_votes (consultation_id, user_id, vote_option, reason_text)

        VALUES (?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE vote_option = VALUES(vote_option), reason_text = COALESCE(VALUES(reason_text), reason_text), updated_at = CURRENT_TIMESTAMP");

    if (!$stmt) {

        error_log("Error preparing submitConsultationVote: " . $conn->error);

        return false;

    }



    $stmt->bind_param('iiss', $consultation_id, $user_id, $vote_option, $reason_text);

    $ok = $stmt->execute();

    if (!$ok) {

        error_log("Error submitting consultation vote: " . $stmt->error);

    }

    $stmt->close();



    return $ok;

}

function submitConsultationGuestVote($consultation_id, $device_token, $vote_option, $guest_email = null, $ip_hash = null, $user_agent_hash = null, $otp_verified = 0, $reason_text = null) {

    global $conn;

    initializeConsultationGuestVotesTable();

    $consultation_id = (int)$consultation_id;
    $device_token = trim((string)$device_token);
    $guest_email = $guest_email !== null ? strtolower(trim((string)$guest_email)) : null;
    $vote_option = strtolower(trim((string)$vote_option));
    $ip_hash = $ip_hash ? trim((string)$ip_hash) : null;
    $user_agent_hash = $user_agent_hash ? trim((string)$user_agent_hash) : null;
    $otp_verified = (int)$otp_verified === 1 ? 1 : 0;
    $reason_text = $reason_text !== null ? trim((string)$reason_text) : null;

    if (!$consultation_id || $device_token === '') {
        return false;
    }
    if (!in_array($vote_option, ['agree', 'disagree'], true)) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO consultation_guest_votes (consultation_id, guest_email, device_token, vote_option, otp_verified, ip_hash, user_agent_hash, reason_text)

        VALUES (?, ?, ?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE vote_option = VALUES(vote_option),
            guest_email = COALESCE(VALUES(guest_email), guest_email),
            otp_verified = GREATEST(otp_verified, VALUES(otp_verified)),
            ip_hash = VALUES(ip_hash),
            user_agent_hash = VALUES(user_agent_hash),
            reason_text = COALESCE(VALUES(reason_text), reason_text),
            updated_at = CURRENT_TIMESTAMP");
    if (!$stmt) {
        error_log("Error preparing submitConsultationGuestVote: " . $conn->error);
        return false;
    }

    $stmt->bind_param('isssisss', $consultation_id, $guest_email, $device_token, $vote_option, $otp_verified, $ip_hash, $user_agent_hash, $reason_text);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log("Error submitting consultation guest vote: " . $stmt->error);
    }
    $stmt->close();

    return $ok;
}



// Create new consultation

function createConsultation($title, $description, $category, $start_date, $end_date, $admin_id, $expected_posts = 0, $image_path = null, $user_name = null, $user_email = null, $allow_email_notifications = 1, $type = 'admin', $status_override = null, $source_url = null, $response_mode = 'hybrid', $survey_question = null, $survey_option_a = 'Agree', $survey_option_b = 'Disagree', $allow_guest_quick_vote = 1, $allow_guest_verified_vote = 1) {

    global $conn;

    

    initializeConsultationsTable();



    $admin_id = (int)$admin_id;

    $expected_posts = (int)$expected_posts;

    $allow_email_notifications = (int)$allow_email_notifications;

    $img = $image_path ?? '';

    $uname = $user_name ?? '';

    $uemail = $user_email ?? '';

    $src_url = $source_url ?? '';

    $status = $status_override ? $status_override : (($type === 'user') ? 'pending' : deriveConsultationStatus($start_date, $end_date, 'active'));
    $response_mode = in_array($response_mode, ['feedback', 'survey', 'hybrid'], true) ? $response_mode : 'hybrid';
    $survey_question = $survey_question ? trim((string)$survey_question) : null;
    $survey_option_a = trim((string)$survey_option_a) !== '' ? trim((string)$survey_option_a) : 'Agree';
    $survey_option_b = trim((string)$survey_option_b) !== '' ? trim((string)$survey_option_b) : 'Disagree';
    $allow_guest_quick_vote = (int)$allow_guest_quick_vote ? 1 : 0;
    $allow_guest_verified_vote = (int)$allow_guest_verified_vote ? 1 : 0;



    $stmt = $conn->prepare("INSERT INTO consultations (title, description, category, start_date, end_date, admin_id, expected_posts, status, type, image_path, user_name, user_email, allow_email_notifications, source_url, response_mode, survey_question, survey_option_a, survey_option_b, allow_guest_quick_vote, allow_guest_verified_vote)

        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {

        error_log("Error preparing createConsultation: " . $conn->error);

        return false;

    }



    $stmt->bind_param('sssssiisisssisssssii', $title, $description, $category, $start_date, $end_date, $admin_id, $expected_posts, $status, $type, $img, $uname, $uemail, $allow_email_notifications, $src_url, $response_mode, $survey_question, $survey_option_a, $survey_option_b, $allow_guest_quick_vote, $allow_guest_verified_vote);



    if ($stmt->execute()) {

        $id = $conn->insert_id;

        $stmt->close();

        assignConsultationTrackingNumber($id);

        return $id;

    }



    error_log("Error creating consultation: " . $stmt->error);

    $stmt->close();

    return false;

}



// Get all consultations

function getConsultations($status = null, $limit = 50, $offset = 0) {

    global $conn;

    

    initializeConsultationsTable();
    syncConsultationStatuses();

    

    $where = "1=1";

    if ($status) {

        $status = $conn->real_escape_string($status);

        $where = "status = '$status'";

    }

    

    $sql = "SELECT * FROM consultations 

            WHERE $where 

            ORDER BY created_at DESC 

            LIMIT $limit OFFSET $offset";

    

    error_log("DEBUG getConsultations: SQL = $sql");

    

    $result = $conn->query($sql);

    $consultations = [];

    

    error_log("DEBUG getConsultations: Result num_rows = " . ($result ? $result->num_rows : 'false'));

    

    if ($result && $result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {

            // Get posts count for this consultation

            $post_count = getConsultationPostsCount($row['id']);

            $row['posts_count'] = $post_count;

            $consultations[] = $row;

            error_log("DEBUG getConsultations: Found consultation ID " . $row['id'] . ", type=" . $row['type'] . ", status=" . $row['status']);

        }

    }

    

    error_log("DEBUG getConsultations: Returning " . count($consultations) . " consultations");

    return $consultations;

}



// Get single consultation

function getConsultationById($id) {

    global $conn;

    

    initializeConsultationsTable();
    syncConsultationStatuses();

    

    $id = (int)$id;

    $sql = "SELECT * FROM consultations WHERE id = $id";

    $result = $conn->query($sql);

    

    if ($result->num_rows > 0) {

        $consultation = $result->fetch_assoc();

        $consultation['posts_count'] = getConsultationPostsCount($id);
        $consultation['vote_stats'] = getConsultationVoteStats($id);

        return $consultation;

    }

    

    return null;

}



function generateConsultationTrackingNumber($consultation_id) {
    $consultation_id = (int)$consultation_id;
    return sprintf('CONSULT-%06d', $consultation_id);
}

function assignConsultationTrackingNumber($consultation_id) {
    global $conn;

    initializeConsultationsTable();

    $consultation_id = (int)$consultation_id;
    $tracking_number = generateConsultationTrackingNumber($consultation_id);

    $stmt = $conn->prepare("UPDATE consultations SET tracking_number = ? WHERE id = ? AND (tracking_number IS NULL OR tracking_number = '')");
    if ($stmt) {
        $stmt->bind_param('si', $tracking_number, $consultation_id);
        $stmt->execute();
        $stmt->close();
    }

    return $tracking_number;
}

function getConsultationByTrackingNumber($tracking_number) {
    global $conn;

    initializeConsultationsTable();
    syncConsultationStatuses();

    $tracking_number = trim((string)$tracking_number);
    if ($tracking_number === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM consultations WHERE tracking_number = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $tracking_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $consultation = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($consultation) {
        $consultation['posts_count'] = getConsultationPostsCount((int)$consultation['id']);
        $consultation['vote_stats'] = getConsultationVoteStats((int)$consultation['id']);
    }

    return $consultation;
}

// Get consultation posts count

function getConsultationPostsCount($consultation_id) {

    global $conn;

    

    $consultation_id = (int)$consultation_id;

    $sql = "SELECT COUNT(*) as count FROM posts WHERE consultation_id = $consultation_id";

    $result = $conn->query($sql);

    

    if ($result) {

        $row = $result->fetch_assoc();

        return $row['count'];

    }

    

    return 0;

}



// Update consultation

function updateConsultation($id, $title, $description, $category, $status, $start_date, $end_date, $response_mode = 'hybrid', $survey_question = null, $survey_option_a = 'Agree', $survey_option_b = 'Disagree', $allow_guest_quick_vote = 1, $allow_guest_verified_vote = 1) {

    global $conn;

    

    $id = (int)$id;
    $response_mode = in_array($response_mode, ['feedback', 'survey', 'hybrid'], true) ? $response_mode : 'hybrid';
    $survey_question = $survey_question ? trim((string)$survey_question) : null;
    $survey_option_a = trim((string)$survey_option_a) !== '' ? trim((string)$survey_option_a) : 'Agree';
    $survey_option_b = trim((string)$survey_option_b) !== '' ? trim((string)$survey_option_b) : 'Disagree';
    $allow_guest_quick_vote = (int)$allow_guest_quick_vote ? 1 : 0;
    $allow_guest_verified_vote = (int)$allow_guest_verified_vote ? 1 : 0;
    $status = deriveConsultationStatus($start_date, $end_date, 'active');



    $stmt = $conn->prepare("UPDATE consultations 

            SET title = ?,

                description = ?,

                category = ?,

                status = ?,

                start_date = ?,

                end_date = ?,

                response_mode = ?,

                survey_question = ?,

                survey_option_a = ?,

                survey_option_b = ?,

                allow_guest_quick_vote = ?,

                allow_guest_verified_vote = ?

            WHERE id = ?");

    if (!$stmt) {

        error_log("Error preparing updateConsultation: " . $conn->error);

        return false;

    }



    $stmt->bind_param('sssssssssssii', $title, $description, $category, $status, $start_date, $end_date, $response_mode, $survey_question, $survey_option_a, $survey_option_b, $allow_guest_quick_vote, $allow_guest_verified_vote, $id);

    $ok = $stmt->execute();

    if (!$ok) {

        error_log("Error updating consultation: " . $stmt->error);

    }

    $stmt->close();

    return $ok;

}



// Close consultation

function closeConsultation($id) {

    global $conn;

    

    $id = (int)$id;

    $sql = "UPDATE consultations SET status = 'closed' WHERE id = $id";

    

    return $conn->query($sql) === TRUE;

}



// Delete consultation

function deleteConsultation($id) {

    global $conn;

    

    $id = (int)$id;

    

    // Delete associated posts first

    $sql = "DELETE FROM posts WHERE consultation_id = $id";

    $conn->query($sql);

    

    // Delete consultation

    $sql = "DELETE FROM consultations WHERE id = $id";

    

    return $conn->query($sql) === TRUE;

}



// Get active consultations count

function getActiveConsultationsCount() {

    global $conn;
    syncConsultationStatuses();

    

    $sql = "SELECT COUNT(*) as count FROM consultations WHERE status = 'active'";

    $result = $conn->query($sql);

    

    if ($result) {

        $row = $result->fetch_assoc();

        return $row['count'];

    }

    

    return 0;

}



// Get consultation statistics

function getConsultationStats($consultation_id) {

    global $conn;

    

    $consultation_id = (int)$consultation_id;

    

    $sql = "SELECT 

            COUNT(*) as total_posts,

            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_posts,

            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_posts,

            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_posts,

            COUNT(DISTINCT user_id) as unique_contributors

            FROM posts WHERE consultation_id = $consultation_id";

    

    $result = $conn->query($sql);

    

    if ($result) {

        return $result->fetch_assoc();

    }

    

    return null;

}



?>
