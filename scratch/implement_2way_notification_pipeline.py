import os

fwd_api_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\forward_to_resource_person.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\forward_to_resource_person.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\forward_to_resource_person.php'
]

save_api_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\save_inline_expert_input.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\save_inline_expert_input.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\save_inline_expert_input.php'
]

print("=== 1. UPDATING FORWARD_TO_RESOURCE_PERSON.PHP (ADMIN -> RESOURCE PERSON NOTIFICATION) ===")

fwd_notif_code = """// Dispatch Notification to Target Resource Person(s)
$targetUserIds = [];
if ($resource_person_id > 0) {
    $targetUserIds[] = $resource_person_id;
} else {
    $rpRes = $conn->query("SELECT id FROM users WHERE LOWER(role) IN ('resource person', 'resource_person', 'staff')");
    if ($rpRes) {
        while ($rpRow = $rpRes->fetch_assoc()) {
            $targetUserIds[] = (int)$rpRow['id'];
        }
    }
}

$cTitle = 'Consultation';
$tCode = 'TRK-' . str_pad($consultation_id, 6, '0', STR_PAD_LEFT);
$cCheck = $conn->query("SELECT title, tracking_number FROM consultations WHERE id = $consultation_id LIMIT 1");
if ($cCheck && $cRow = $cCheck->fetch_assoc()) {
    $cTitle = $cRow['title'];
    if (!empty($cRow['tracking_number'])) $tCode = $cRow['tracking_number'];
}

foreach ($targetUserIds as $rpUid) {
    $notifTitle = "📋 New Consultation Dispatched ($tCode)";
    $notifMsg = "Admin $admin_name has dispatched consultation '$cTitle - $tCode' (ID #$consultation_id) for your expert review & master document annotation.";
    
    // Expert notifications table
    $stmtExp = $conn->prepare("INSERT INTO expert_notifications (user_id, title, message, type, consultation_id, is_read, created_at) VALUES (?, ?, ?, 'assignment', ?, 0, NOW())");
    if ($stmtExp) {
        $stmtExp->bind_param('issi', $rpUid, $notifTitle, $notifMsg, $consultation_id);
        $stmtExp->execute();
        $stmtExp->close();
    }

    // Main notifications table
    $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'assignment', 0, NOW())");
    if ($stmtNotif) {
        $fullMsg = "📋 New Consultation Dispatched: $notifMsg";
        $stmtNotif->bind_param('is', $rpUid, $fullMsg);
        $stmtNotif->execute();
        $stmtNotif->close();
    }
}"""

for fpath in fwd_api_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Replace old notification query
    old_exp_query = """if ($resource_person_id > 0) {
    @$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, consultation_id, is_read, created_at) VALUES ($resource_person_id, 'New Consultation Dispatched (#$consultation_id)', 'Admin $admin_name has dispatched consultation #$consultation_id to you for expert annotation.', 'assignment', $consultation_id, 0, NOW())");
}"""

    if old_exp_query in code:
        code = code.replace(old_exp_query, fwd_notif_code)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated Admin -> Resource Person notification in:", fpath)
    elif "$targetUserIds" not in code:
        # Fallback replacement
        anchor = "echo json_encode(["
        if anchor in code:
            code = code.replace(anchor, fwd_notif_code + "\n\n" + anchor)
            with open(fpath, 'w', encoding='utf-8') as f:
                f.write(code)
            print("Appended Admin -> Resource Person notification in:", fpath)


print("\n=== 2. UPDATING SAVE_INLINE_EXPERT_INPUT.PHP (RESOURCE PERSON -> ADMIN NOTIFICATION) ===")

save_notif_code = """// Create System Notification for Admin (Resource Person -> Admin Pass Back)
if (file_exists(__DIR__ . '/../DATABASE/notifications.php')) {
    require_once __DIR__ . '/../DATABASE/notifications.php';
    $cTitle = (string)($consultation['title'] ?? 'Consultation');
    $tCode = !empty($consultation['tracking_number']) ? $consultation['tracking_number'] : ('TRK-' . str_pad($consultation_id, 6, '0', STR_PAD_LEFT));
    $adminNotifMsg = "📝 Expert Annotation Completed: Resource Person {$user_name} has annotated and passed back consultation '{$cTitle} - {$tCode}' ({$new_version_label}). Ready for ORTS forwarding!";
    if (function_exists('createNotification')) {
        createNotification(0, $adminNotifMsg, 'annotation');
    }
}"""

for fpath in save_api_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    anchor = "// Log Audit Trail Entry"
    if anchor in code and "Expert Annotation Completed" not in code:
        code = code.replace(anchor, save_notif_code + "\n\n" + anchor)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated Resource Person -> Admin notification in:", fpath)

print("Finished updating 2-way notification pipeline!")
