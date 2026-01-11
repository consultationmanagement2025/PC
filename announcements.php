<?php
/**
 * Announcements module
 * Provides functions to create and retrieve announcements
 */

require_once 'db.php';

function initializeAnnouncementsTable() {
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        image_path VARCHAR(255),
        admin_id INT,
        admin_user VARCHAR(255),
        visibility VARCHAR(50) DEFAULT 'public',
        status VARCHAR(50) DEFAULT 'published',
        liked_by LONGTEXT DEFAULT '[]',
        saved_by LONGTEXT DEFAULT '[]',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        error_log('Failed to create announcements table: ' . $conn->error);
        return false;
    }
    
    // Add columns if they don't exist
    $checkCols = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'announcements' AND COLUMN_NAME IN ('liked_by', 'saved_by', 'image_path')";
    $result = $conn->query($checkCols);
    $existingCols = [];
    while ($row = $result->fetch_assoc()) {
        $existingCols[] = $row['COLUMN_NAME'];
    }
    
    if (!in_array('liked_by', $existingCols)) {
        $conn->query("ALTER TABLE announcements ADD COLUMN liked_by LONGTEXT DEFAULT '[]'");
    }
    if (!in_array('saved_by', $existingCols)) {
        $conn->query("ALTER TABLE announcements ADD COLUMN saved_by LONGTEXT DEFAULT '[]'");
    }
    if (!in_array('image_path', $existingCols)) {
        $conn->query("ALTER TABLE announcements ADD COLUMN image_path VARCHAR(255)");
    }
    
    return true;
}

function createAnnouncement($admin_id, $admin_user, $title, $content, $visibility = 'public', $image_path = null) {
    global $conn;
    initializeAnnouncementsTable();

    $stmt = $conn->prepare("INSERT INTO announcements (title, content, image_path, admin_id, admin_user, visibility, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $status = 'published';
    $stmt->bind_param('ssissss', $title, $content, $image_path, $admin_id, $admin_user, $visibility, $status);
    $res = $stmt->execute();
    $stmt->close();
    return $res ? $conn->insert_id : false;
}

function getLatestAnnouncements($limit = 10) {
    global $conn;
    initializeAnnouncementsTable();

    $stmt = $conn->prepare("SELECT id, title, content, image_path, admin_user, created_at FROM announcements WHERE status = 'published' AND visibility = 'public' ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) {
        // Fallback if columns don't exist
        $stmt = $conn->prepare("SELECT id, title, content, admin_user, created_at FROM announcements WHERE status = 'published' AND visibility = 'public' ORDER BY created_at DESC LIMIT ?");
    }
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Ensure columns exist in result
    foreach ($rows as &$row) {
        if (!isset($row['liked_by'])) $row['liked_by'] = '[]';
        if (!isset($row['saved_by'])) $row['saved_by'] = '[]';
        if (!isset($row['image_path'])) $row['image_path'] = null;
    }
    return $rows;
}

function getAnnouncements($limit = 50, $offset = 0) {
    global $conn;
    initializeAnnouncementsTable();

    $stmt = $conn->prepare("SELECT id, title, content, image_path, admin_user, liked_by, saved_by, created_at FROM announcements WHERE status = 'published' ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) {
        // Fallback if columns don't exist
        $stmt = $conn->prepare("SELECT id, title, content, admin_user, created_at FROM announcements WHERE status = 'published' ORDER BY created_at DESC LIMIT ? OFFSET ?");
    }
    if (!$stmt) return [];
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Ensure columns exist in result
    foreach ($rows as &$row) {
        if (!isset($row['liked_by'])) $row['liked_by'] = '[]';
        if (!isset($row['saved_by'])) $row['saved_by'] = '[]';
        if (!isset($row['image_path'])) $row['image_path'] = null;
    }
    return $rows;
}

function getAnnouncementById($id) {
    global $conn;
    initializeAnnouncementsTable();
    
    $stmt = $conn->prepare("SELECT id, title, content, admin_user, liked_by, saved_by, created_at FROM announcements WHERE id = ? AND status = 'published'");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function toggleAnnouncementLike($ann_id, $user_id) {
    global $conn;
    $ann = getAnnouncementById($ann_id);
    if (!$ann) return false;
    
    $liked = json_decode($ann['liked_by'] ?? '[]', true) ?? [];
    if (in_array($user_id, $liked)) {
        $liked = array_diff($liked, [$user_id]);
    } else {
        $liked[] = $user_id;
    }
    
    $liked_json = json_encode(array_values($liked));
    $stmt = $conn->prepare("UPDATE announcements SET liked_by = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('si', $liked_json, $ann_id);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

function toggleAnnouncementSave($ann_id, $user_id) {
    global $conn;
    $ann = getAnnouncementById($ann_id);
    if (!$ann) return false;
    
    $saved = json_decode($ann['saved_by'] ?? '[]', true) ?? [];
    if (in_array($user_id, $saved)) {
        $saved = array_diff($saved, [$user_id]);
    } else {
        $saved[] = $user_id;
    }
    
    $saved_json = json_encode(array_values($saved));
    $stmt = $conn->prepare("UPDATE announcements SET saved_by = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('si', $saved_json, $ann_id);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

?>
