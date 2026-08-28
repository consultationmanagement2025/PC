import os

feedback_api_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\feedback_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\feedback_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\feedback_api.php'
]

for fpath in feedback_api_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Move $action assignment before $read_actions check
    old_block = """$read_actions = ['list', 'get', 'phms_list', 'phms_detail', 'phms_sync', 'stats', 'get_summary', 'debug'];
if (!in_array($action, $read_actions, true) && !$has_session_user && !in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');

$action = $_GET['action'] ?? 'list';"""

    new_block = """$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');

$read_actions = ['list', 'get', 'phms_list', 'phms_detail', 'phms_sync', 'stats', 'get_summary', 'debug'];
if (!in_array($action, $read_actions, true) && !$has_session_user && !in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}"""

    if old_block in code:
        code = code.replace(old_block, new_block)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Fixed action ordering in:", fpath)
    else:
        print("Block not found in:", fpath)
