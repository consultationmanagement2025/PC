import os

fb_db_path = r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php'
with open(fb_db_path, 'r', encoding='utf-8') as f:
    code = f.read()

safe_migration = """
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM feedback");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
    }
    if (!in_array('submission_type', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN submission_type ENUM('survey', 'proposal', 'comment') DEFAULT 'comment'");
    if (!in_array('committee_assigned', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN committee_assigned VARCHAR(150) DEFAULT NULL");
    if (!in_array('barangay', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN barangay VARCHAR(150) DEFAULT NULL");
    if (!in_array('sentiment_tag', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN sentiment_tag VARCHAR(20) DEFAULT NULL");
    if (!in_array('sentiment_score', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN sentiment_score DECIMAL(6,2) DEFAULT NULL");
    if (!in_array('topic_tags', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN topic_tags JSON");
    if (!in_array('analysis_summary', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN analysis_summary LONGTEXT");
    if (!in_array('allow_email_notifications', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN allow_email_notifications TINYINT(1) DEFAULT 0");
    if (!in_array('attachment_path', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL");
    if (!in_array('feedback_hash', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN feedback_hash VARCHAR(64) DEFAULT NULL");
    if (!in_array('is_archived', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
    if (!in_array('archived_at', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN archived_at DATETIME DEFAULT NULL");
    if (!in_array('lifecycle_stage', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN lifecycle_stage ENUM('received', 'analyzed', 'considered_in_policy', 'outcome_published') DEFAULT 'received'");
    if (!in_array('themes', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN themes JSON");
    if (!in_array('issue_priority', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN issue_priority INT DEFAULT NULL");
    if (!in_array('policy_link_id', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN policy_link_id INT DEFAULT NULL");
    if (!in_array('policy_link_type', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN policy_link_type VARCHAR(50) DEFAULT NULL");
    if (!in_array('impact_summary', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN impact_summary LONGTEXT");
    if (!in_array('tracking_token', $cols)) @$conn->query("ALTER TABLE feedback ADD COLUMN tracking_token VARCHAR(64) DEFAULT NULL");
"""

if "$conn->query(\"ALTER TABLE feedback ADD COLUMN IF NOT EXISTS submission_type" in code:
    idx = code.find("$conn->query(\"ALTER TABLE feedback ADD COLUMN IF NOT EXISTS submission_type")
    idx_end = code.find("return true;", idx) + 12
    code = code[:idx] + safe_migration + "\n        return true;\n    }" + code[idx_end:]
    with open(fb_db_path, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated safe column migration in DATABASE/feedback.php.")
