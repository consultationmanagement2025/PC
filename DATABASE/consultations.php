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
        status ENUM('draft', 'active', 'closed', 'archived') DEFAULT 'draft',
        start_date DATETIME,
        end_date DATETIME,
        admin_id INT,
        expected_posts INT DEFAULT 0,
        views INT DEFAULT 0,
        posts_count INT DEFAULT 0,
        user_id INT,
        user_email VARCHAR(255),
        allow_email_notifications TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        image_path VARCHAR(255),
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        error_log("Error creating consultations table: " . $conn->error);
        return false;
    }
}

// Create new consultation
function createConsultation($title, $description, $category, $start_date, $end_date, $admin_id, $expected_posts = 0) {
    global $conn;
    
    initializeConsultationsTable();
    
    $title = $conn->real_escape_string($title);
    $description = $conn->real_escape_string($description);
    $category = $conn->real_escape_string($category);
    
    $sql = "INSERT INTO consultations (title, description, category, start_date, end_date, admin_id, expected_posts, status)
            VALUES ('$title', '$description', '$category', '$start_date', '$end_date', $admin_id, $expected_posts, 'active')";
    
    if ($conn->query($sql) === TRUE) {
        return $conn->insert_id;
    } else {
        error_log("Error creating consultation: " . $conn->error);
        return false;
    }
}

// Get all consultations
function getConsultations($status = null, $limit = 50, $offset = 0) {
    global $conn;
    
    initializeConsultationsTable();
    
    $where = "1=1";
    if ($status) {
        $status = $conn->real_escape_string($status);
        $where = "status = '$status'";
    }
    
    $sql = "SELECT * FROM consultations 
            WHERE $where 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($sql);
    $consultations = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get posts count for this consultation
            $post_count = getConsultationPostsCount($row['id']);
            $row['posts_count'] = $post_count;
            $consultations[] = $row;
        }
    }
    
    return $consultations;
}

// Get single consultation
function getConsultationById($id) {
    global $conn;
    
    initializeConsultationsTable();
    
    $id = (int)$id;
    $sql = "SELECT * FROM consultations WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $consultation = $result->fetch_assoc();
        $consultation['posts_count'] = getConsultationPostsCount($id);
        return $consultation;
    }
    
    return null;
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
function updateConsultation($id, $title, $description, $category, $status, $start_date, $end_date) {
    global $conn;
    
    $id = (int)$id;
    $title = $conn->real_escape_string($title);
    $description = $conn->real_escape_string($description);
    $category = $conn->real_escape_string($category);
    $status = $conn->real_escape_string($status);
    
    $sql = "UPDATE consultations 
            SET title = '$title', 
                description = '$description', 
                category = '$category', 
                status = '$status',
                start_date = '$start_date',
                end_date = '$end_date'
            WHERE id = $id";
    
    return $conn->query($sql) === TRUE;
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
