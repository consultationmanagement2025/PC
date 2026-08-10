<?php
/**
 * Save Inline Expert Input API
 * Appends/updates inline expert recommendations directly in the master consultation document,
 * maintains version history/audit trail, and updates single consolidated master document.
 */
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$allowed_roles = ['resource person', 'resource_person', 'staff', 'admin', 'administrator', 'super admin', 'superadmin'];
if (!in_array($current_role, $allowed_roles, true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access role']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Parse JSON or POST data
$raw_input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($_POST, $raw_input);

$consultation_id = isset($input['consultation_id']) ? (int)$input['consultation_id'] : 0;
$executive_summary = isset($input['executive_summary']) ? trim($input['executive_summary']) : '';
$technical_rationale = isset($input['technical_rationale']) ? trim($input['technical_rationale']) : '';
$legal_alignment = isset($input['legal_alignment']) ? trim($input['legal_alignment']) : '';
$proposed_revisions = isset($input['proposed_revisions']) ? trim($input['proposed_revisions']) : '';
$signoff_status = isset($input['signoff_status']) ? trim($input['signoff_status']) : 'ready_for_committee';

if ($consultation_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

if (empty($technical_rationale) && empty($executive_summary)) {
    echo json_encode(['success' => false, 'message' => 'Please provide executive summary or technical rationale']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? 'Resource Person';

// Fetch current consultation details
$stmt = $conn->prepare("SELECT id, title, document_version, expert_notes FROM consultations WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $consultation_id);
$stmt->execute();
$cRes = $stmt->get_result();
$consultation = $cRes ? $cRes->fetch_assoc() : null;
$stmt->close();

if (!$consultation) {
    echo json_encode(['success' => false, 'message' => 'Consultation not found']);
    exit;
}

// Build structured expert notes JSON / Array
$expert_input_payload = [
    'updated_by' => $user_name,
    'user_id' => $user_id,
    'timestamp' => date('Y-m-d H:i:s'),
    'executive_summary' => $executive_summary,
    'technical_rationale' => $technical_rationale,
    'legal_alignment' => $legal_alignment,
    'proposed_revisions' => $proposed_revisions,
    'signoff_status' => $signoff_status
];

$expert_notes_json = json_encode($expert_input_payload, JSON_PRETTY_PRINT);

// Determine new version label (e.g., v1.0 -> v1.1)
$vQuery = $conn->query("SELECT COUNT(*) as cnt FROM consultation_document_audit_trail WHERE consultation_id = $consultation_id AND action_type = 'inline_annotation_added'");
$vRow = $vQuery ? $vQuery->fetch_assoc() : null;
$annotation_count = ($vRow ? (int)$vRow['cnt'] : 0) + 1;
$new_version_label = 'v1.' . $annotation_count;

// Update Master Consultation Record
$updStmt = $conn->prepare("UPDATE consultations SET expert_notes = ?, document_version = ?, document_status = 'expert_annotated', expert_last_updated_by = ?, expert_last_updated_at = NOW(), status = 'completed' WHERE id = ?");
if (!$updStmt) {
    echo json_encode(['success' => false, 'message' => 'Database update prepare error: ' . $conn->error]);
    exit;
}

$updStmt->bind_param('ssii', $expert_notes_json, $new_version_label, $user_id, $consultation_id);
if (!$updStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update consultation: ' . $updStmt->error]);
    exit;
}
$updStmt->close();

// Log Audit Trail Entry
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

$auditStmt = $conn->prepare("INSERT INTO consultation_document_audit_trail (consultation_id, user_id, user_name, user_role, action_type, version_label, changes_summary, snapshot_notes, created_at) VALUES (?, ?, ?, ?, 'inline_annotation_added', ?, ?, ?, NOW())");
if ($auditStmt) {
    $changes = "Inline expert notes added by $user_name ($current_role). Technical recommendations appended.";
    $auditStmt->bind_param('iisssss', $consultation_id, $user_id, $user_name, $current_role, $new_version_label, $changes, $expert_notes_json);
    $auditStmt->execute();
    $auditStmt->close();
}

// Log resolution report record for single master document tracking
$filename = "master_consultation_doc_" . $consultation_id . ".pdf";
$notes_summary = "Master Document Updated ($new_version_label): " . mb_substr($technical_rationale, 0, 150) . "...";

@$conn->query("INSERT INTO resolution_reports (consultation_id, uploaded_by, file_path, notes, version_label, status, created_at) VALUES ($consultation_id, $user_id, '$filename', '" . $conn->real_escape_string($notes_summary) . "', '$new_version_label', 'pending_review', NOW())");

// Create Expert Notification for Secretariat
@$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, consultation_id, is_read, created_at) VALUES ($user_id, 'Master Document Updated ($new_version_label)', 'Your inline expert input was appended to Master Document #$consultation_id ($new_version_label).', 'inline_annotation', $consultation_id, 0, NOW())");

echo json_encode([
    'success' => true,
    'message' => "Master document updated successfully to $new_version_label! Inline expert recommendations appended cleanly.",
    'version' => $new_version_label,
    'consultation_id' => $consultation_id
]);
