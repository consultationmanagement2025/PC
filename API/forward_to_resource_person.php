<?php
/**
 * API: Forward AI Summary & Consultation to Resource Person
 * Triggered by City Admin / Secretariat on the Admin Dashboard
 */
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed_roles = ['admin', 'administrator', 'super admin', 'superadmin', 'staff'];
if (!in_array($current_role, $allowed_roles, true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized admin access']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($_POST, $raw);

$consultation_id = (int)($input['consultation_id'] ?? 0);
$resource_person_id = (int)($input['resource_person_id'] ?? 0);
$instructions = trim($input['instructions'] ?? '');
$deadline_days = (int)($input['deadline_days'] ?? 7);

if ($consultation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

// Ensure schema columns exist
$cCols = [];
$cRes = $conn->query("SHOW COLUMNS FROM consultations");
if ($cRes) {
    while ($r = $cRes->fetch_assoc()) { $cCols[] = $r['Field']; }
}
if (!in_array('assigned_to', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL");
if (!in_array('deadline', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN deadline DATETIME DEFAULT NULL");
if (!in_array('document_status', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN document_status VARCHAR(50) DEFAULT 'draft'");
if (!in_array('ai_analyzed', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN ai_analyzed TINYINT(1) DEFAULT 0");
if (!in_array('forwarded_to_expert', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN forwarded_to_expert TINYINT(1) DEFAULT 0");
if (!in_array('ai_summary_json', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN ai_summary_json LONGTEXT DEFAULT NULL");

// Compile AI Sentiment & Summary Across Citizen Feedback Posts
$aiSummary = [
    'total_posts' => 0,
    'positive' => 0,
    'neutral' => 0,
    'negative' => 0,
    'overall_sentiment' => 'neutral',
    'avg_score' => 0,
    'key_topics' => ['Public Feedback', 'Community Input'],
    'generated_at' => date('Y-m-d H:i:s')
];

$postRes = $conn->query("SELECT content FROM posts WHERE consultation_id = $consultation_id");
if ($postRes && $postRes->num_rows > 0) {
    $totalScore = 0;
    $posWords = ['good','great','excellent','satisfied','helpful','maayos','maganda','salamat','support','agree'];
    $negWords = ['bad','poor','slow','delayed','broken','complaint','issue','unfair','mahirap','mabagal'];
    
    while ($pRow = $postRes->fetch_assoc()) {
        $text = strtolower($pRow['content'] ?? '');
        $aiSummary['total_posts']++;
        $score = 0;
        foreach ($posWords as $pw) { if (strpos($text, $pw) !== false) $score += 1; }
        foreach ($negWords as $nw) { if (strpos($text, $nw) !== false) $score -= 1; }
        $totalScore += $score;
        if ($score > 0) $aiSummary['positive']++;
        elseif ($score < 0) $aiSummary['negative']++;
        else $aiSummary['neutral']++;
    }
    
    if ($aiSummary['total_posts'] > 0) {
        $avg = $totalScore / $aiSummary['total_posts'];
        $aiSummary['avg_score'] = round($avg, 2);
        if ($avg > 0.3) $aiSummary['overall_sentiment'] = 'positive';
        elseif ($avg < -0.3) $aiSummary['overall_sentiment'] = 'negative';
    }
}

$aiJson = json_encode($aiSummary);

// Calculate deadline
$deadline_date = date('Y-m-d H:i:s', strtotime("+$deadline_days days"));

// Update consultation: mark forwarded_to_expert = 1, ai_analyzed = 1, document_status = 'sent_to_expert', ai_summary_json
if ($resource_person_id > 0) {
    $upd = $conn->prepare("UPDATE consultations SET forwarded_to_expert = 1, ai_analyzed = 1, assigned_to = ?, deadline = ?, document_status = 'sent_to_expert', ai_summary_json = ? WHERE id = ?");
    $upd->bind_param('issi', $resource_person_id, $deadline_date, $aiJson, $consultation_id);
} else {
    $upd = $conn->prepare("UPDATE consultations SET forwarded_to_expert = 1, ai_analyzed = 1, deadline = ?, document_status = 'sent_to_expert', ai_summary_json = ? WHERE id = ?");
    $upd->bind_param('ssi', $deadline_date, $aiJson, $consultation_id);
}

if (!$upd->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update consultation status: ' . $upd->error]);
    exit;
}
$upd->close();

// Fetch resource person name if assigned
$expert_name = 'Subject Matter Expert(s)';
if ($resource_person_id > 0) {
    $uRes = $conn->query("SELECT fullname FROM users WHERE id = $resource_person_id");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $expert_name = $uRow['fullname'];
    }
}

// Audit log
@$conn->query("CREATE TABLE IF NOT EXISTS consultation_document_audit_trail (
    id INT(11) NOT NULL AUTO_INCREMENT,
    consultation_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    version_label VARCHAR(50) NOT NULL,
    changes_summary TEXT,
    snapshot_notes LONGTEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consultation (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$admin_name = $_SESSION['fullname'] ?? 'City Admin';
$admin_id = (int)($_SESSION['user_id'] ?? 0);
$audit = $conn->prepare("INSERT INTO consultation_document_audit_trail (consultation_id, user_id, user_name, user_role, action_type, version_label, changes_summary, snapshot_notes, created_at) VALUES (?, ?, ?, ?, 'sent_to_expert', 'v1.0', ?, ?, NOW())");
if ($audit) {
    $changes = "Admin $admin_name forwarded consultation & AI summary to expert $expert_name.";
    $audit->bind_param('iisss', $consultation_id, $admin_id, $admin_name, $current_role, $changes, $instructions);
    $audit->execute();
    $audit->close();
}

// Notification for Resource Person
@$conn->query("CREATE TABLE IF NOT EXISTS expert_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'assignment',
    consultation_id INT(11) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($resource_person_id > 0) {
    @$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, consultation_id, is_read, created_at) VALUES ($resource_person_id, 'New Consultation Dispatched (#$consultation_id)', 'Admin $admin_name has dispatched consultation #$consultation_id to you for expert annotation.', 'assignment', $consultation_id, 0, NOW())");
}

echo json_encode([
    'success' => true,
    'message' => "Successfully forwarded AI Summary & Consultation #$consultation_id to $expert_name!",
    'consultation_id' => $consultation_id
]);
