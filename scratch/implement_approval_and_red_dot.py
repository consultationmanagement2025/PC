import os

# 1. Update Database and PHP logic in DATABASE/feedback.php
feedback_db_files = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php'
]

print("=== UPDATING DATABASE/feedback.php FILES ===")
for fpath in feedback_db_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Ensure is_newly_approved column creation in initializeHearingQueueTable
    old_init = "return true;\n}"
    new_init_col = """    $checkColNew = $conn->query("SHOW COLUMNS FROM hearing_queue LIKE 'is_newly_approved'");
    if ($checkColNew && $checkColNew->num_rows === 0) {
        $conn->query("ALTER TABLE hearing_queue ADD COLUMN is_newly_approved TINYINT(1) DEFAULT 0");
    }
    return true;
}"""
    if "is_newly_approved" not in code:
        code = code.replace("return true;\n}", new_init_col)

    # Update getPhmsFeedbackQueueAsHearings to return is_newly_approved
    old_return_item = "'hearing_id' => (int)($row['phms_hearing_id'] ?: $row['queue_id']),"
    new_return_item = "'is_newly_approved' => (int)($row['is_newly_approved'] ?? 0),\n            'hearing_id' => (int)($row['phms_hearing_id'] ?: $row['queue_id']),"
    if old_return_item in code and "'is_newly_approved'" not in code:
        code = code.replace(old_return_item, new_return_item)

    # Update approvePhmsIngestion to set is_newly_approved = 1
    old_approve = "UPDATE hearing_queue SET approval_status = 'approved', status = 'completed' WHERE queue_id = ? OR phms_hearing_id = ?"
    new_approve = "UPDATE hearing_queue SET approval_status = 'approved', status = 'completed', is_newly_approved = 1 WHERE queue_id = ? OR phms_hearing_id = ?"
    code = code.replace(old_approve, new_approve)

    # Update approveAllPhmsIngestions to set is_newly_approved = 1
    old_approve_all = "UPDATE hearing_queue SET approval_status = 'approved', status = 'completed' WHERE approval_status = 'pending' OR approval_status IS NULL"
    new_approve_all = "UPDATE hearing_queue SET approval_status = 'approved', status = 'completed', is_newly_approved = 1 WHERE approval_status = 'pending' OR approval_status IS NULL"
    code = code.replace(old_approve_all, new_approve_all)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated DATABASE/feedback.php in:", fpath)


# 2. Update API/feedback_api.php files for clearing newly approved flag
feedback_api_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\feedback_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\feedback_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\feedback_api.php'
]

print("\n=== UPDATING API/feedback_api.php FILES ===")
for fpath in feedback_api_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Add phms_clear_newly_approved action
    new_action = """        case 'phms_clear_newly_approved':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $queue_id = (int)($data['hearing_id'] ?? $data['queue_id'] ?? $_GET['hearing_id'] ?? 0);
            if ($queue_id > 0) {
                $conn->query("UPDATE hearing_queue SET is_newly_approved = 0 WHERE queue_id = {$queue_id} OR phms_hearing_id = {$queue_id}");
            }
            echo json_encode(['success' => true]);
            break;"""

    if "phms_clear_newly_approved" not in code and "switch ($action) {" in code:
        code = code.replace("switch ($action) {", "switch ($action) {\n" + new_action)
        # Update allowed read actions array to include phms_clear_newly_approved
        code = code.replace("'phms_sync', 'stats',", "'phms_sync', 'phms_clear_newly_approved', 'stats',")

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated API/feedback_api.php in:", fpath)

print("Finished applying PHP & DB updates!")
