import os
import re

print("=== 1. Updating DATABASE/audit-log.php for Fallback User Mapping ===")

db_audit_paths = [
    r"c:\xampp\htdocs\CAP101\PC\DATABASE\audit-log.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\audit-log.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\DATABASE\audit-log.php",
]

fallback_user_logic = """function logAction($admin_id, $admin_user, $action, $entity_type = null, $entity_id = null, $old_value = null, $new_value = null, $status = 'success', $details = null) {
    global $conn;
    
    // Ensure table exists
    initializeAuditTable();
    
    if (!$admin_id && isset($_SESSION['user_id'])) {
        $admin_id = (int)$_SESSION['user_id'];
    }
    if ((!$admin_user || trim($admin_user) === '') && !empty($_SESSION['fullname'])) {
        $admin_user = $_SESSION['fullname'];
    }
    if ((!$admin_user || trim($admin_user) === '') && !empty($_SESSION['email'])) {
        $admin_user = $_SESSION['email'];
    }
    if (!$admin_user || trim($admin_user) === '') {
        $admin_user = 'System / Citizen';
    }"""

for db_path in db_audit_paths:
    if os.path.exists(db_path):
        with open(db_path, 'r', encoding='utf-8') as f:
            c = f.read()
        c = re.sub(r"function logAction\(\$admin_id, \$admin_user, \$action[\s\S]*?initializeAuditTable\(\);", fallback_user_logic, c)
        with open(db_path, 'w', encoding='utf-8') as f:
            f.write(c)
        print(f"Updated logAction fallback logic in {db_path}")

print("\n=== 2. Injecting logAction into API/consultations_api.php ===")

consult_api_paths = [
    r"c:\xampp\htdocs\CAP101\PC\API\consultations_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\API\consultations_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\API\consultations_api.php",
]

for api_path in consult_api_paths:
    if not os.path.exists(api_path):
        continue
    with open(api_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # Make sure audit-log.php is required
    if "require_once __DIR__ . '/../DATABASE/audit-log.php'" not in c and "require_once __DIR__ . '/DATABASE/audit-log.php'" not in c:
        c = c.replace("require_once __DIR__ . '/../DATABASE/consultations.php';", "require_once __DIR__ . '/../DATABASE/consultations.php';\nif (file_exists(__DIR__ . '/../DATABASE/audit-log.php')) require_once __DIR__ . '/../DATABASE/audit-log.php';")

    # Add audit log on consultation creation
    c = c.replace(
        "echo json_encode(['success' => true, 'message' => 'Consultation created successfully', 'data' => $created]);",
        "if (function_exists('logAction')) { logAction($_SESSION['user_id'] ?? 1, $_SESSION['fullname'] ?? 'Admin User', 'Posted Consultation', 'consultation', $result, null, null, 'success', \"Posted new public consultation: '\" . ($data['title'] ?? '') . \"'\"); }\n            echo json_encode(['success' => true, 'message' => 'Consultation created successfully', 'data' => $created]);"
    )

    # Add audit log on voting
    c = c.replace(
        "echo json_encode(['success' => true, 'message' => 'Vote recorded successfully', 'stats' => $stats]);",
        "if (function_exists('logAction')) { logAction($_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'Citizen Voter', 'Voted in Survey', 'survey_vote', $consultation_id, null, null, 'success', \"Voted '\" . ($choice) . \"' on consultation #{$consultation_id}\"); }\n            echo json_encode(['success' => true, 'message' => 'Vote recorded successfully', 'stats' => $stats]);"
    )

    with open(api_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated consultation logging in {api_path}")

print("\n=== 3. Injecting logAction into API/documents_api.php ===")

doc_api_paths = [
    r"c:\xampp\htdocs\CAP101\PC\API\documents_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\API\documents_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\API\documents_api.php",
]

for doc_path in doc_api_paths:
    if not os.path.exists(doc_path):
        continue
    with open(doc_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # Add audit log on document download
    c = c.replace(
        "header('Content-Type: ' . $doc['file_type']);",
        "if (function_exists('logAction')) { logAction($_SESSION['user_id'] ?? 0, $_SESSION['fullname'] ?? 'User', 'Downloaded Document File', 'document', $id, null, null, 'success', \"Downloaded document file: '\" . ($doc['title'] ?? 'Document') . \"' (\" . ($doc['file_name'] ?? '') . \")\"); }\n        header('Content-Type: ' . $doc['file_type']);"
    )

    with open(doc_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated document download logging in {doc_path}")

print("\n=== 4. Injecting logAction into API/feedback_api.php ===")

fb_api_paths = [
    r"c:\xampp\htdocs\CAP101\PC\API\feedback_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\API\feedback_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\API\feedback_api.php",
]

for fb_path in fb_api_paths:
    if not os.path.exists(fb_path):
        continue
    with open(fb_path, 'r', encoding='utf-8') as f:
        c = f.read()

    c = c.replace(
        "echo json_encode(['success' => (bool)$ok, 'message' => \"Successfully approved all pending PHMS ingestion packages.\"]);",
        "if (function_exists('logAction')) { logAction($_SESSION['user_id'] ?? 1, $_SESSION['fullname'] ?? 'Superadmin', 'Approved PHMS Data Packages', 'phms_ingestion', 0, null, null, 'success', 'Approved & merged all pending PHMS data ingestion packages into PCMS'); }\n                echo json_encode(['success' => (bool)$ok, 'message' => \"Successfully approved all pending PHMS ingestion packages.\"]);"
    )
    c = c.replace(
        "echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Ingestion package approved and merged.' : 'Failed to approve ingestion package.']);",
        "if (function_exists('logAction')) { logAction($_SESSION['user_id'] ?? 1, $_SESSION['fullname'] ?? 'Superadmin', 'Approved PHMS Data Package', 'phms_ingestion', $queue_id, null, null, 'success', \"Approved & merged PHMS queue item #{$queue_id} into PCMS\"); }\n                echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Ingestion package approved and merged.' : 'Failed to approve ingestion package.']);"
    )

    with open(fb_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated PHMS approval logging in {fb_path}")

print("\nDone integrating full audit logging!")
