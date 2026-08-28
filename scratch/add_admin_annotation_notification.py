import os

target_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\save_inline_expert_input.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\save_inline_expert_input.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\save_inline_expert_input.php'
]

notif_code_snippet = """// Create System Notification for Admin
if (file_exists(__DIR__ . '/../DATABASE/notifications.php')) {
    require_once __DIR__ . '/../DATABASE/notifications.php';
    $cTitle = (string)($consultation['title'] ?? 'Consultation');
    $trackingCode = !empty($consultation['tracking_number']) ? $consultation['tracking_number'] : ('TRK-' . str_pad($consultation_id, 6, '0', STR_PAD_LEFT));
    $adminNotifMsg = "📝 Expert Annotation Added: {$user_name} annotated consultation '{$cTitle} - {$trackingCode}' ({$new_version_label})";
    if (function_exists('createNotification')) {
        createNotification(0, $adminNotifMsg, 'annotation');
    }
}
"""

print("=== ADDING ADMIN NOTIFICATION TRIGGER TO SAVE_INLINE_EXPERT_INPUT.PHP ===")

for fpath in target_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    target_anchor = "// Create Expert Notification for Secretariat"
    if target_anchor in code and "adminNotifMsg" not in code:
        code = code.replace(target_anchor, notif_code_snippet + "\n" + target_anchor)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated notification trigger in:", fpath)
    else:
        print("Already updated or anchor not found in:", fpath)

print("Finished updating save_inline_expert_input.php!")
