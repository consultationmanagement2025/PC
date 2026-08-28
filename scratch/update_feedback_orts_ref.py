import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php'
]

old_block = """                    $refNo = "CONSULT-" . sprintf("%06d", (int)$consultation_id);
                    sendFeedbackToOrts((int)$consultation_id, $refNo, $message, $guest_name, $fbType);"""

new_block = """                    $refNo = "CONSULT-" . sprintf("%06d", (int)$consultation_id);
                    if (isset($conn) && $conn instanceof mysqli) {
                        $cStmt = $conn->prepare("SELECT tracking_number, external_ref FROM consultations WHERE id = ? LIMIT 1");
                        if ($cStmt) {
                            $cStmt->bind_param('i', $consultation_id);
                            $cStmt->execute();
                            $cRes = $cStmt->get_result();
                            if ($cRow = $cRes->fetch_assoc()) {
                                if (!empty($cRow['tracking_number'])) {
                                    $refNo = trim($cRow['tracking_number']);
                                } elseif (!empty($cRow['external_ref'])) {
                                    $refNo = trim($cRow['external_ref']);
                                }
                            }
                            $cStmt->close();
                        }
                    }
                    sendFeedbackToOrts((int)$consultation_id, $refNo, $message, $guest_name, $fbType);"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        print(f"Missing: {fpath}")
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()
    if old_block in content:
        content = content.replace(old_block, new_block)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated {fpath}")
    else:
        print(f"Pattern not found in {fpath}")

print("Done updating DATABASE/feedback.php files.")
