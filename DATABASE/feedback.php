<?php
require_once __DIR__ . '/../db.php';

// Initialize feedback table
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
        status ENUM('new', 'reviewed', 'responded', 'closed') DEFAULT 'new',
        admin_response LONGTEXT,
        admin_respondent INT,
        responded_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL,
        FOREIGN KEY (admin_respondent) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        error_log("Error creating feedback table: " . $conn->error);
        return false;
    }
}

// Submit feedback
function submitFeedback($guest_name, $guest_email, $guest_phone, $consultation_id, $rating, $category, $message) {
    global $conn;
    
    initializeFeedbackTable();
    
    $consultation_id = (int)$consultation_id;
    $rating = (int)$rating;

    $stmt = $conn->prepare("INSERT INTO feedback (guest_name, guest_email, guest_phone, consultation_id, rating, category, message, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
    if (!$stmt) {
        error_log("Error preparing submitFeedback: " . $conn->error);
        return false;
    }

    $stmt->bind_param('sssisss', $guest_name, $guest_email, $guest_phone, $consultation_id, $rating, $category, $message);
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $stmt->close();
        return $id;
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
    
    if (isset($filters['search']) && $filters['search']) {
        $search = $conn->real_escape_string($filters['search']);
        $where .= " AND (message LIKE '%$search%' OR username LIKE '%$search%')";
    }
    
    $sql = "SELECT * FROM feedback 
            WHERE $where 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($sql);
    $feedback = [];
    
    if ($result->num_rows > 0) {
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
            AVG(rating) as avg_rating
            FROM feedback WHERE consultation_id = $consultation_id";
    
    $result = $conn->query($sql);
    
    if ($result) {
        return $result->fetch_assoc();
    }
    
    return null;
}

?>
