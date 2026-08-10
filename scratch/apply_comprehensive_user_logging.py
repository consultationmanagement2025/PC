import os

# 1. Update login.php
login_path = r'c:\xampp\htdocs\CAP101\PC\login.php'
if os.path.exists(login_path):
    with open(login_path, 'r', encoding='utf-8') as f:
        code = f.read()

    old_log_login = 'logAction($user[\'id\'], $user[\'fullname\'], "User Login", "user", $user[\'id\'], null, null, \'success\', "Email verified login from IP: " . $_SERVER[\'REMOTE_ADDR\']);'
    new_log_login = """$roleNormCheck = strtolower(str_replace([' ', '_'], '', ($user['role'] ?? '')));
                if (in_array($roleNormCheck, ['admin', 'administrator', 'superadmin', 'staff', 'barangaystaff'], true)) {
                    logAction($user['id'], $user['fullname'], "Admin Login", "user", $user['id'], null, null, 'success', "Admin login from IP: " . $_SERVER['REMOTE_ADDR']);
                } else {
                    if (file_exists(__DIR__ . '/DATABASE/user-logs.php')) {
                        require_once __DIR__ . '/DATABASE/user-logs.php';
                        if (function_exists('logUserAction')) {
                            logUserAction($user['id'], $user['fullname'], "User Login", "auth", "user", $user['id'], "Citizen/Expert logged into system", 'success');
                        }
                    }
                    logAction($user['id'], $user['fullname'], "User Login", "user", $user['id'], null, null, 'success', "User login from IP: " . $_SERVER['REMOTE_ADDR']);
                }"""
    if old_log_login in code:
        code = code.replace(old_log_login, new_log_login)
        with open(login_path, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated login.php logging logic.")

# 2. Update API/save_inline_expert_input.php
inline_path = r'c:\xampp\htdocs\CAP101\PC\API\save_inline_expert_input.php'
if os.path.exists(inline_path):
    with open(inline_path, 'r', encoding='utf-8') as f:
        code = f.read()

    target_pos = "echo json_encode(["
    logging_snippet = """// Log User & Audit Activity
if (file_exists(__DIR__ . '/../DATABASE/user-logs.php')) {
    require_once __DIR__ . '/../DATABASE/user-logs.php';
    if (function_exists('logUserAction')) {
        logUserAction($user_id, $user_name, 'Annotated Master Document', 'expert_annotation', 'consultation', $consultation_id, "Expert $user_name appended inline recommendations ($new_version_label) to consultation #$consultation_id", 'success');
    }
}
if (file_exists(__DIR__ . '/../DATABASE/audit-log.php')) {
    require_once __DIR__ . '/../DATABASE/audit-log.php';
    if (function_exists('logAction')) {
        logAction($user_id, $user_name, 'Annotated Master Document', 'consultation', $consultation_id, null, null, 'success', "Expert $user_name appended inline recommendations ($new_version_label)");
    }
}

"""
    if target_pos in code and 'Annotated Master Document' not in code:
        code = code.replace(target_pos, logging_snippet + target_pos)
        with open(inline_path, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated API/save_inline_expert_input.php with user activity logging.")

# 3. Update DATABASE/feedback.php
feedback_path = r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php'
if os.path.exists(feedback_path):
    with open(feedback_path, 'r', encoding='utf-8') as f:
        code = f.read()

    target = "return ['id' => $id, 'tracking_token' => $tracking_token, 'sentiment' => $sentiment_tag, 'topics' => $analysis['topics']];"
    logging_snippet = """if (file_exists(__DIR__ . '/user-logs.php')) {
            require_once __DIR__ . '/user-logs.php';
            if (function_exists('logUserAction')) {
                $uid = $_SESSION['user_id'] ?? null;
                logUserAction($uid, $guest_name, 'Submitted Feedback', 'citizen_feedback', 'consultation', $consultation_id, "Submitted feedback on consultation #$consultation_id", 'success', $message);
            }
        }
        if (file_exists(__DIR__ . '/audit-log.php')) {
            require_once __DIR__ . '/audit-log.php';
            if (function_exists('logAction')) {
                $uid = $_SESSION['user_id'] ?? null;
                logAction($uid, $guest_name, 'Submitted Feedback', 'feedback', $consultation_id, null, null, 'success', 'Submitted citizen feedback');
            }
        }
        """
    if target in code and 'Submitted Feedback' not in code:
        code = code.replace(target, logging_snippet + target)
        with open(feedback_path, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated DATABASE/feedback.php with feedback submission logging.")

# 4. Update API/resource_person_api.php
rp_api_path = r'c:\xampp\htdocs\CAP101\PC\API\resource_person_api.php'
if os.path.exists(rp_api_path):
    with open(rp_api_path, 'r', encoding='utf-8') as f:
        code = f.read()

    info_target = "echo json_encode(['success' => true, 'message' => 'Information request submitted successfully!']);"
    info_log = """if (file_exists(__DIR__ . '/../DATABASE/user-logs.php')) {
            require_once __DIR__ . '/../DATABASE/user-logs.php';
            if (function_exists('logUserAction')) {
                logUserAction($user_id, $user_name, 'Requested Additional Info', 'info_request', 'consultation', $consultation_id, "Resource person requested info for consultation #$consultation_id", 'success');
            }
        }
        if (file_exists(__DIR__ . '/../DATABASE/audit-log.php')) {
            require_once __DIR__ . '/../DATABASE/audit-log.php';
            if (function_exists('logAction')) {
                logAction($user_id, $user_name, 'Requested Additional Info', 'consultation', $consultation_id, null, null, 'success', 'Requested additional info');
            }
        }
        """
    if info_target in code and 'Requested Additional Info' not in code:
        code = code.replace(info_target, info_log + info_target)
        with open(rp_api_path, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated API/resource_person_api.php with info request logging.")
