<?php
session_start();
require '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Handle profile update
if ($action === 'update_profile') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if (!$fullname || !$email) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        exit;
    }

    // Check if email is already taken by another user
    $check = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use']);
        exit;
    }

    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET fullname=?, email=?, username=? WHERE id=?");
    $stmt->bind_param("sssi", $fullname, $email, $username, $user_id);

    if ($stmt->execute()) {
        $_SESSION['fullname'] = $fullname;
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
}

// Handle password change
else if ($action === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$current_password || !$new_password || !$confirm_password) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit;
    }

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    // Update to new password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $update->bind_param("si", $new_hash, $user_id);

    if ($update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }
}

// Handle photo upload
else if ($action === 'upload_photo') {
    if (!isset($_FILES['photo'])) {
        echo json_encode(['success' => false, 'message' => 'No file provided']);
        exit;
    }

    $file = $_FILES['photo'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File upload incomplete',
            UPLOAD_ERR_NO_FILE => 'No file selected',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory missing',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension'
        ];
        $message = $error_messages[$file['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'No file provided. Debug: $_FILES=' . json_encode($_FILES)]);
        exit;
    }

    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, and WebP files are allowed']);
        exit;
    }

    // Create the upload directory
    $upload_dir = dirname(__DIR__) . '/images/profiles/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit;
        }
    }
            echo json_encode(['success' => false, 'message' => $message . ' (Debug: error code ' . $file['error'] . ', $_FILES=' . json_encode($_FILES) . ')']);
    // Ensure the directory is writable
    if (!is_writable($upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit;
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            echo json_encode(['success' => false, 'message' => 'File is too large (max 5MB). Debug: size=' . $file['size']]);
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update user profile photo path
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, and WebP files are allowed. Debug: type=' . $file['type']]);
        $stmt = $conn->prepare("UPDATE users SET profile_photo=? WHERE id=?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("si", $photo_path, $user_id);
        if ($stmt->execute()) {
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Debug: path=' . $upload_dir]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Photo uploaded but database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
            echo json_encode(['success' => false, 'message' => 'Upload directory is not writable. Debug: path=' . $upload_dir]);

// Handle preference save
else if ($action === 'save_preferences') {
    $language = $_POST['language'] ?? 'en';
    $theme = $_POST['theme'] ?? 'light';
    $email_notif = isset($_POST['email_notif']) ? 1 : 0;
    $announcement_notif = isset($_POST['announcement_notif']) ? 1 : 0;
    $feedback_notif = isset($_POST['feedback_notif']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE users SET language=?, theme=?, email_notif=?, announcement_notif=?, feedback_notif=? WHERE id=?");
    $stmt->bind_param("ssiiii", $language, $theme, $email_notif, $announcement_notif, $feedback_notif, $user_id);

                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        echo json_encode(['success' => true, 'message' => 'Preferences saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save preferences']);
    }
}

                echo json_encode(['success' => false, 'message' => 'Photo uploaded but database update failed. Debug: SQL error=' . $stmt->error]);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Debug: tmp_name=' . $file['tmp_name'] . ', dest=' . $filepath . ', perms=' . substr(sprintf('%o', fileperms($upload_dir)), -4)]);
