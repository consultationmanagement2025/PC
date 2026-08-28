import os

events_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\v1\events.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\v1\events.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\v1\events.php'
]

feedback_db_files = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php'
]

print("=== UPDATING API/v1/events.php FILES ===")
for fpath in events_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    notif_code = """
// Create system notification for ingested PHMS event
if (file_exists(__DIR__ . '/../../DATABASE/notifications.php')) {
    require_once __DIR__ . '/../../DATABASE/notifications.php';
} elseif (file_exists(__DIR__ . '/../DATABASE/notifications.php')) {
    require_once __DIR__ . '/../DATABASE/notifications.php';
}
if (function_exists('createNotification')) {
    $notifTitle = !empty($fullName) ? $fullName : ("PHMS Hearing #" . $phmsId);
    $notifMsg = "🏢 New PHMS Citizen Hearing Feedback Received: '{$notifTitle}' (Event: {$event})";
    createNotification(0, $notifMsg, 'phms_feedback');
}
"""

    if "createNotification" not in code:
        code = code.replace("echo json_encode([", notif_code + "\necho json_encode([")
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Added notification trigger to:", fpath)
    else:
        print("Notification already present in:", fpath)


print("\n=== UPDATING DATABASE/feedback.php FILES ===")
for fpath in feedback_db_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    sync_notif = """
        $conn->commit();
        if (file_exists(__DIR__ . '/notifications.php')) {
            require_once __DIR__ . '/notifications.php';
        }
        if (function_exists('createNotification') && count($hearings) > 0) {
            $syncCount = count($hearings);
            createNotification(0, "🏢 PHMS Integration Sync: {$syncCount} Citizen Hearing Feedback items ingested into Public Feedback Queue.", "phms_feedback");
        }
        return true;"""

    if "$conn->commit();" in code and "createNotification(0, \"🏢 PHMS Integration Sync:" not in code:
        code = code.replace("$conn->commit();\n        return true;", sync_notif)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Added sync notification to:", fpath)
    else:
        print("Sync notification already present or pattern not matched in:", fpath)

print("Finished updating files!")
