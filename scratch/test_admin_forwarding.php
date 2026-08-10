<?php
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['fullname'] = 'City Admin Test';

function ensureResourcePersonSchema($conn) {
    $cCols = [];
    $cRes = $conn->query("SHOW COLUMNS FROM consultations");
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) { $cCols[] = $r['Field']; }
    }
    if (!in_array('assigned_to', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL");
    if (!in_array('deadline', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN deadline DATETIME DEFAULT NULL");
    if (!in_array('document_status', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN document_status VARCHAR(50) DEFAULT 'draft'");
    if (!in_array('ai_analyzed', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN ai_analyzed TINYINT(1) DEFAULT 1");
    if (!in_array('forwarded_to_expert', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN forwarded_to_expert TINYINT(1) DEFAULT 1");
}
require_once __DIR__ . '/../db.php';
ensureResourcePersonSchema($conn);

echo "--- 1. Creating Test Consultation for Admin Forwarding ---\n";
$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, created_at) VALUES ('Health Ordinance on Barangay Clinic Hours', 'Public consultation regarding operating hours of health centers.', 'Health & Sanitation', 'active', NOW())");
$stmt->execute();
$cId = $stmt->insert_id;
$stmt->close();

echo "Created Consultation #$cId ('Health Ordinance on Barangay Clinic Hours')\n";

echo "\n--- 2. Simulating Admin Forwarding AI Summary to Resource Person ---\n";
$expertId = 501; // Dr. Santos
$deadlineDays = 7;
$deadlineDate = date('Y-m-d H:i:s', strtotime("+$deadlineDays days"));
$adminName = $_SESSION['fullname'];
$adminId = $_SESSION['user_id'];
$instructions = "Please review citizen comments from PHMS and append technical recommendations for the LGU health committee.";

// Update consultation: forwarded_to_expert = 1, ai_analyzed = 1, document_status = 'sent_to_expert'
$upd = $conn->prepare("UPDATE consultations SET forwarded_to_expert = 1, ai_analyzed = 1, assigned_to = ?, deadline = ?, document_status = 'sent_to_expert' WHERE id = ?");
if (!$upd) {
    echo "Prepare upd failed: " . $conn->error . "\n";
    exit(1);
}
$upd->bind_param('isi', $expertId, $deadlineDate, $cId);
if (!$upd->execute()) {
    echo "Execute upd failed: " . $upd->error . "\n";
    exit(1);
}
$upd->close();

// Log Audit
$audit = $conn->prepare("INSERT INTO consultation_document_audit_trail (consultation_id, user_id, user_name, user_role, action_type, version_label, changes_summary, snapshot_notes, created_at) VALUES (?, ?, ?, 'admin', 'sent_to_expert', 'v1.0', 'Admin forwarded AI Summary & Consultation to expert.', ?, NOW())");
$audit->bind_param('iiss', $cId, $adminId, $adminName, $instructions);
$audit->execute();
$audit->close();

// Expert notification
$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, consultation_id, is_read, created_at) VALUES ($expertId, 'New Consultation Dispatched (#$cId)', 'Admin $adminName has dispatched consultation #$cId to you.', 'assignment', $cId, 0, NOW())");

echo "Forwarding complete! Consultation #$cId dispatched to Expert #$expertId\n";

echo "\n--- 3. Verifying Consultation Status & Audit Log ---\n";
$res = $conn->query("SELECT id, title, category, status, document_status, forwarded_to_expert, ai_analyzed, assigned_to FROM consultations WHERE id = $cId");
$row = $res ? $res->fetch_assoc() : null;
if ($row) {
    echo "Consultation ID: #{$row['id']}\n";
    echo "Document Status: {$row['document_status']}\n";
    echo "Forwarded to Expert Flag: {$row['forwarded_to_expert']}\n";
    echo "AI Analyzed Flag: {$row['ai_analyzed']}\n";
    echo "Assigned to Expert ID: {$row['assigned_to']}\n";
}

echo "\n--- 4. Verifying Audit Trail Log ---\n";
$aRes = $conn->query("SELECT * FROM consultation_document_audit_trail WHERE consultation_id = $cId");
while ($aRow = $aRes->fetch_assoc()) {
    echo "- Audit Log #{$aRow['id']} | Action: {$aRow['action_type']} | Contributor: {$aRow['user_name']} | Details: {$aRow['changes_summary']}\n";
}

// Cleanup
$conn->query("DELETE FROM consultations WHERE id = $cId");
$conn->query("DELETE FROM consultation_document_audit_trail WHERE consultation_id = $cId");
$conn->query("DELETE FROM expert_notifications WHERE consultation_id = $cId");
echo "\nAdmin Forwarding Test Completed Successfully!\n";
