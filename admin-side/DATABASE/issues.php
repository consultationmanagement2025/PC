<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/valenzuela-geo.php';

function initializeIssuesTable() {
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS issue_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reference_no VARCHAR(40) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'general',
        status ENUM('new','validated','resolved','rejected') NOT NULL DEFAULT 'new',
        priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
        barangay VARCHAR(120) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        reported_by_name VARCHAR(255) DEFAULT NULL,
        reported_by_email VARCHAR(255) DEFAULT NULL,
        validation_notes TEXT DEFAULT NULL,
        resolution_notes TEXT DEFAULT NULL,
        validated_by INT DEFAULT NULL,
        resolved_by INT DEFAULT NULL,
        validated_at DATETIME DEFAULT NULL,
        resolved_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_issue_reference_no (reference_no),
        INDEX idx_issue_status (status),
        INDEX idx_issue_priority (priority),
        INDEX idx_issue_category (category),
        INDEX idx_issue_created_at (created_at),
        CONSTRAINT fk_issue_validated_by FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_issue_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return $conn->query($sql) === true;
}

function generateIssueReference() {
    return 'ISS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function listIssues($filters = [], $limit = 200, $offset = 0) {
    global $conn;
    initializeIssuesTable();

    $where = "1=1";
    if (!empty($filters['status'])) {
        $status = $conn->real_escape_string($filters['status']);
        $where .= " AND status = '$status'";
    }
    if (!empty($filters['priority'])) {
        $priority = $conn->real_escape_string($filters['priority']);
        $where .= " AND priority = '$priority'";
    }
    if (!empty($filters['category'])) {
        $category = $conn->real_escape_string($filters['category']);
        $where .= " AND category = '$category'";
    }
    if (!empty($filters['search'])) {
        $search = $conn->real_escape_string($filters['search']);
        $where .= " AND (reference_no LIKE '%$search%' OR title LIKE '%$search%' OR address LIKE '%$search%' OR barangay LIKE '%$search%')";
    }

    $limit = max(1, min(1000, (int)$limit));
    $offset = max(0, (int)$offset);
    $sql = "SELECT * FROM issue_reports WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function createIssue($payload, $reporterName = null, $reporterEmail = null) {
    global $conn;
    initializeIssuesTable();

    $title = trim((string)($payload['title'] ?? ''));
    $description = trim((string)($payload['description'] ?? ''));
    $category = trim((string)($payload['category'] ?? 'general'));
    $priority = trim((string)($payload['priority'] ?? 'normal'));
    $barangay = trim((string)($payload['barangay'] ?? ''));
    $address = trim((string)($payload['address'] ?? ''));
    $lat = isset($payload['latitude']) ? (float)$payload['latitude'] : null;
    $lng = isset($payload['longitude']) ? (float)$payload['longitude'] : null;

    if ($title === '' || $description === '' || $lat === null || $lng === null) {
        return ['ok' => false, 'message' => 'Title, description and map coordinates are required'];
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return ['ok' => false, 'message' => 'Invalid latitude or longitude'];
    }
    if (!isPointInsideValenzuela($lat, $lng)) {
        return ['ok' => false, 'message' => 'Location must be within City of Valenzuela boundaries'];
    }
    if (!in_array($priority, ['low', 'normal', 'high'], true)) {
        $priority = 'normal';
    }

    $reference = generateIssueReference();
    $stmt = $conn->prepare("
        INSERT INTO issue_reports (
            reference_no, title, description, category, status, priority, barangay, address, latitude, longitude, reported_by_name, reported_by_email
        ) VALUES (?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Database prepare failed: ' . $conn->error];
    }

    $stmt->bind_param(
        'sssssssddss',
        $reference,
        $title,
        $description,
        $category,
        $priority,
        $barangay,
        $address,
        $lat,
        $lng,
        $reporterName,
        $reporterEmail
    );
    $ok = $stmt->execute();
    if (!$ok) {
        $msg = $stmt->error ?: 'Failed to save issue';
        $stmt->close();
        return ['ok' => false, 'message' => $msg];
    }
    $id = (int)$conn->insert_id;
    $stmt->close();

    return ['ok' => true, 'id' => $id, 'reference_no' => $reference];
}

function updateIssueStatus($id, $status, $notes = '', $actorId = null) {
    global $conn;
    initializeIssuesTable();

    $id = (int)$id;
    $status = strtolower(trim((string)$status));
    if ($id <= 0 || !in_array($status, ['validated', 'resolved', 'rejected'], true)) {
        return ['ok' => false, 'message' => 'Invalid issue ID or status'];
    }

    $notes = trim((string)$notes);
    $actorId = $actorId ? (int)$actorId : null;

    if ($status === 'validated') {
        $stmt = $conn->prepare("UPDATE issue_reports SET status='validated', validation_notes=?, validated_by=?, validated_at=NOW() WHERE id=?");
        if (!$stmt) return ['ok' => false, 'message' => 'Database prepare failed'];
        $stmt->bind_param('sii', $notes, $actorId, $id);
    } elseif ($status === 'resolved') {
        $stmt = $conn->prepare("UPDATE issue_reports SET status='resolved', resolution_notes=?, resolved_by=?, resolved_at=NOW() WHERE id=?");
        if (!$stmt) return ['ok' => false, 'message' => 'Database prepare failed'];
        $stmt->bind_param('sii', $notes, $actorId, $id);
    } else {
        $stmt = $conn->prepare("UPDATE issue_reports SET status='rejected', validation_notes=? WHERE id=?");
        if (!$stmt) return ['ok' => false, 'message' => 'Database prepare failed'];
        $stmt->bind_param('si', $notes, $id);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return ['ok' => (bool)$ok, 'message' => $ok ? 'Updated' : 'Failed to update issue status'];
}

