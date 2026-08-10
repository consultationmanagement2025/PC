<?php
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 999;
$_SESSION['fullname'] = 'Test Admin';
require_once __DIR__ . '/../db.php';

echo "--- 1. Initializing Audit Trail & Master Document Schema ---\n";
function ensureResourcePersonSchema($conn) {
    $cCols = [];
    $cRes = $conn->query("SHOW COLUMNS FROM consultations");
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) { $cCols[] = $r['Field']; }
    }
    if (!in_array('assigned_to', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL");
    if (!in_array('deadline', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN deadline DATETIME DEFAULT NULL");
    if (!in_array('expert_notes', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN expert_notes LONGTEXT DEFAULT NULL");
    if (!in_array('document_version', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN document_version VARCHAR(50) DEFAULT 'v1.0'");
    if (!in_array('document_status', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN document_status VARCHAR(50) DEFAULT 'draft'");
    if (!in_array('expert_last_updated_by', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN expert_last_updated_by INT(11) DEFAULT NULL");
    if (!in_array('expert_last_updated_at', $cCols)) @$conn->query("ALTER TABLE consultations ADD COLUMN expert_last_updated_at DATETIME DEFAULT NULL");
}
ensureResourcePersonSchema($conn);
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

echo "\n--- 2. Creating Test Consultation ---\n";
$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, created_at) VALUES ('Test Policy on Urban Drainage', 'Public hearing consultation regarding Valenzuela flood control.', 'Infrastructure', 'active', NOW())");
$stmt->execute();
$testId = $stmt->insert_id;
$stmt->close();

echo "Inserted test consultation ID #$testId\n";

echo "\n--- 3. Simulating Inline Expert Annotation Input ---\n";
$expertPayload = [
    'consultation_id' => $testId,
    'executive_summary' => 'Strong technical endorsement for expanding catchment basins in Marulas.',
    'technical_rationale' => 'Hydrological data confirms 35% reduction in flood retention time upon completion.',
    'legal_alignment' => 'Compliant with DPWH standards and RA 10173 data privacy rules.',
    'proposed_revisions' => 'Recommend amending Section 4 to include quarterly dredging schedules.',
    'signoff_status' => 'ready_for_committee'
];

$expertJson = json_encode($expertPayload, JSON_PRETTY_PRINT);
$newVersion = 'v1.1';
$userId = 999;
$userName = 'Dr. Juan Dela Cruz (Hydrology Expert)';

$upd = $conn->prepare("UPDATE consultations SET expert_notes = ?, document_version = ?, document_status = 'expert_annotated', expert_last_updated_by = ?, expert_last_updated_at = NOW(), status = 'completed' WHERE id = ?");
if (!$upd) {
    echo "Prepare failed: " . $conn->error . "\n";
    exit(1);
}
$upd->bind_param('ssii', $expertJson, $newVersion, $userId, $testId);
if (!$upd->execute()) {
    echo "Execute failed: " . $upd->error . "\n";
    exit(1);
}
$upd->close();

$audit = $conn->prepare("INSERT INTO consultation_document_audit_trail (consultation_id, user_id, user_name, user_role, action_type, version_label, changes_summary, snapshot_notes, created_at) VALUES (?, ?, ?, 'Resource Person', 'inline_annotation_added', ?, 'Inline expert notes added by Dr. Juan Dela Cruz.', ?, NOW())");
$audit->bind_param('iisss', $testId, $userId, $userName, $newVersion, $expertJson);
$audit->execute();
$audit->close();

echo "Inline expert input saved & Master Document updated to $newVersion!\n";

echo "\n--- 4. Verifying Consultation Master Document Record ---\n";
$res = $conn->query("SELECT id, title, document_version, document_status, expert_notes FROM consultations WHERE id = $testId");
$row = $res ? $res->fetch_assoc() : null;
if ($row) {
    echo "Consultation ID: #{$row['id']}\n";
    echo "Master Doc Version: {$row['document_version']}\n";
    echo "Document Status: {$row['document_status']}\n";
    echo "Expert Notes Preview: " . mb_substr($row['expert_notes'], 0, 120) . "...\n";
}

echo "\n--- 5. Verifying Document Audit Trail Log ---\n";
$aRes = $conn->query("SELECT * FROM consultation_document_audit_trail WHERE consultation_id = $testId");
while ($aRow = $aRes->fetch_assoc()) {
    echo "- Audit Log #{$aRow['id']} | Version {$aRow['version_label']} | Contributor: {$aRow['user_name']} ({$aRow['user_role']}) | Action: {$aRow['action_type']} | Date: {$aRow['created_at']}\n";
}

// Cleanup test record
$conn->query("DELETE FROM consultations WHERE id = $testId");
$conn->query("DELETE FROM consultation_document_audit_trail WHERE consultation_id = $testId");
echo "\nTest completed successfully.\n";
