import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\API\documents_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\documents_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\documents_api.php'
]

old_auth_block = """$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin = ($current_role === 'admin' || $current_role === 'administrator');
$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true);
if (!$is_admin && !$is_super_admin && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin, Super Admin or Staff access required']);
    exit;
}"""

old_auth_block_2 = """$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin = ($current_role === 'admin' || $current_role === 'administrator');
$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['staff', 'resource person', 'resource_person'], true);
if (!$is_admin && !$is_super_admin && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin, Super Admin or Staff access required']);
    exit;
}"""

new_auth_block = """$current_role = isset($_SESSION['role']) ? strtolower(trim((string)$_SESSION['role'])) : '';
$has_session_user = !empty($_SESSION['user_id']) || !empty($_SESSION['fullname']) || !empty($_SESSION['email']) || !empty($_SESSION['user']);

if (!$current_role && $has_session_user && isset($conn) && $conn instanceof mysqli) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $email = (string)($_SESSION['email'] ?? $_SESSION['user'] ?? '');
    if ($uid > 0 || $email !== '') {
        $stmt = $conn->prepare("SELECT role FROM users WHERE (id = ? AND id > 0) OR (email = ? AND email != '') LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $uid, $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $current_role = strtolower(trim((string)$row['role']));
                $_SESSION['role'] = $current_role;
            }
        }
    }
}

$role_clean = str_replace(['_', '-'], ' ', $current_role);

$allowed_roles = [
    'admin', 'administrator', 'super admin', 'superadmin', 'system administrator', 'system admin',
    'staff', 'barangay staff', 'barangay_staff', 'barangay', 'lgu staff', 'lgu_staff', 'lgu', 'official',
    'resource person', 'resource_person', 'city admin', 'city_admin', 'secretariat', 'expert', 'user', 'citizen'
];

$is_admin = in_array($role_clean, ['admin', 'administrator', 'super admin', 'superadmin', 'system administrator', 'system admin', 'city admin'], true);
$is_super_admin = in_array($role_clean, ['super admin', 'superadmin'], true);
$is_staff = in_array($role_clean, ['staff', 'barangay staff', 'lgu staff', 'official', 'resource person', 'secretariat', 'expert'], true);

$is_authorized = $has_session_user || in_array($current_role, $allowed_roles, true) || in_array($role_clean, $allowed_roles, true);

if (!$is_authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Logged in user access required']);
    exit;
}"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        print(f"File missing: {fpath}")
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    content = content.replace("session_start();", "if (session_status() === PHP_SESSION_NONE) {\n    session_start();\n}")

    if old_auth_block in content:
        content = content.replace(old_auth_block, new_auth_block)
        print(f"Updated auth block in {fpath}")
    elif old_auth_block_2 in content:
        content = content.replace(old_auth_block_2, new_auth_block)
        print(f"Updated auth block 2 in {fpath}")
    else:
        print(f"Could not find exact auth block in {fpath}")

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(content)

print("Done updating documents_api.php files.")
