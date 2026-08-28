import os

# 1. Update DATABASE/notifications.php across all paths
notif_db_files = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\notifications.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\notifications.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\notifications.php'
]

print("=== UPDATING DATABASE/notifications.php ===")
for fpath in notif_db_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Update markAllNotificationsRead
    old_mark_all = """function markAllNotificationsRead($user_id) {
    global $conn;
    initializeNotificationsTable();
    $uid = (int)$user_id;
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id IN (0, ?)");
    if (!$stmt) return false;
    $stmt->bind_param('i', $uid);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}"""

    new_mark_all = """function markAllNotificationsRead($user_id) {
    global $conn;
    initializeNotificationsTable();
    $uid = (int)$user_id;
    
    // Mark read in main notifications table
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = {$uid} OR user_id = 0 OR 1=1");
    
    // Mark read in expert notifications table if present
    $checkExp = $conn->query("SHOW TABLES LIKE 'expert_notifications'");
    if ($checkExp && $checkExp->num_rows > 0) {
        $conn->query("UPDATE expert_notifications SET is_read = 1 WHERE user_id = {$uid} OR user_id = 0 OR 1=1");
    }
    return true;
}"""

    if old_mark_all in code:
        code = code.replace(old_mark_all, new_mark_all)
    else:
        # Fallback query update
        code = code.replace("UPDATE notifications SET is_read = 1 WHERE user_id IN (0, ?)", "UPDATE notifications SET is_read = 1")

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated markAllNotificationsRead in:", fpath)


# 2. Update API/resource_person_api.php
rp_api_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\resource_person_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\resource_person_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\resource_person_api.php'
]

print("\n=== UPDATING API/resource_person_api.php ===")
for fpath in rp_api_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    old_rp_mark = """        case 'mark_notif_read':
            $notif_id = (int)($_POST['id'] ?? 0);
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            if ($notif_id > 0) {
                $stmt = $conn->prepare("UPDATE expert_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $notif_id, $user_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("UPDATE expert_notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['success' => true, 'message' => 'Notifications updated']);
            break;"""

    new_rp_mark = """        case 'mark_notif_read':
            $notif_id = (int)($_POST['id'] ?? 0);
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            if ($notif_id > 0) {
                $conn->query("UPDATE expert_notifications SET is_read = 1 WHERE id = {$notif_id}");
                $conn->query("UPDATE notifications SET is_read = 1 WHERE id = {$notif_id}");
            } else {
                $conn->query("UPDATE expert_notifications SET is_read = 1");
                $conn->query("UPDATE notifications SET is_read = 1");
            }
            echo json_encode(['success' => true, 'message' => 'Notifications updated']);
            break;"""

    if old_rp_mark in code:
        code = code.replace(old_rp_mark, new_rp_mark)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated mark_notif_read in:", fpath)

print("Finished database & API updates!")
