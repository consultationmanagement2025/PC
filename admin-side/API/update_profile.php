<?php

session_start();

require_once __DIR__ . '/../db.php';



header('Content-Type: application/json');

/**
 * Resize image to specified dimensions
 * @param string $source Path to source image
 * @param string $destination Path to save resized image
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @param string $extension Image extension
 * @return bool Success status
 */
function resizeImage($source, $destination, $maxWidth, $maxHeight, $extension) {
    try {
        $imageInfo = getimagesize($source);
        if (!$imageInfo) {
            error_log('Failed to get image info');
            return false;
        }

        list($width, $height) = $imageInfo;
        $mime = $imageInfo['mime'];

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($source);
                break;
            default:
                error_log('Unsupported image type: ' . $mime);
                return false;
        }

        if (!$sourceImage) {
            error_log('Failed to create image resource');
            return false;
        }

        $destinationImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
            $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
            imagefilledrectangle($destinationImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($destinationImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $success = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($destinationImage, $destination, 90);
                break;
            case 'png':
                $success = imagepng($destinationImage, $destination, 9);
                break;
            case 'gif':
                $success = imagegif($destinationImage, $destination);
                break;
            case 'webp':
                $success = imagewebp($destinationImage, $destination, 90);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($destinationImage);

        if (!$success) {
            error_log('Failed to save resized image');
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log('Image resize error: ' . $e->getMessage());
        return false;
    }
}



if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(['success' => false, 'message' => 'Not logged in']);

    exit;

}



$user_id = (int)$_SESSION['user_id'];

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';



try {

    if ($action === 'update_profile') {

        $fullname = trim((string)($_POST['fullname'] ?? ''));

        $email = trim((string)($_POST['email'] ?? ''));

        $username = trim((string)($_POST['username'] ?? ''));



        if ($fullname === '' || $email === '') {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Name and email are required']);

            exit;

        }



        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Invalid email']);

            exit;

        }



        $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');

        if (!$check) {

            throw new Exception('Database error');

        }

        $check->bind_param('si', $email, $user_id);

        $check->execute();

        $check->store_result();

        if ($check->num_rows > 0) {

            $check->close();

            http_response_code(409);

            echo json_encode(['success' => false, 'message' => 'Email is already in use']);

            exit;

        }

        $check->close();



        $stmt = $conn->prepare('UPDATE users SET fullname = ?, email = ?, username = ? WHERE id = ?');

        if (!$stmt) {

            throw new Exception('Database error');

        }

        $stmt->bind_param('sssi', $fullname, $email, $username, $user_id);

        $ok = $stmt->execute();

        $stmt->close();



        if ($ok) {

            $_SESSION['fullname'] = $fullname;

            $_SESSION['email'] = $email;

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);

        } else {

            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);

        }

        exit;

    }



    if ($action === 'change_password') {

        $current_password = (string)($_POST['current_password'] ?? '');

        $new_password = (string)($_POST['new_password'] ?? '');

        $confirm_password = (string)($_POST['confirm_password'] ?? '');



        if ($current_password === '' || $new_password === '' || $confirm_password === '') {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'All password fields are required']);

            exit;

        }



        if ($new_password !== $confirm_password) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);

            exit;

        }



        if (strlen($new_password) < 6) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);

            exit;

        }



        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');

        if (!$stmt) {

            throw new Exception('Database error');

        }

        $stmt->bind_param('i', $user_id);

        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result ? $result->fetch_assoc() : null;

        $stmt->close();



        if (!$user || !isset($user['password']) || !password_verify($current_password, $user['password'])) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);

            exit;

        }



        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');

        if (!$update) {

            throw new Exception('Database error');

        }

        $update->bind_param('si', $new_hash, $user_id);

        $ok = $update->execute();

        $update->close();



        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Password changed successfully' : 'Failed to change password']);

        exit;

    }



    if ($action === 'save_preferences') {

        $language = trim((string)($_POST['language'] ?? 'en'));

        $theme = trim((string)($_POST['theme'] ?? 'light'));

        $email_notif = isset($_POST['email_notif']) ? 1 : 0;

        $announcement_notif = isset($_POST['announcement_notif']) ? 1 : 0;

        $feedback_notif = isset($_POST['feedback_notif']) ? 1 : 0;



        $stmt = $conn->prepare('UPDATE users SET language = ?, theme = ?, email_notif = ?, announcement_notif = ?, feedback_notif = ? WHERE id = ?');

        if (!$stmt) {

            throw new Exception('Database error');

        }

        $stmt->bind_param('ssiiii', $language, $theme, $email_notif, $announcement_notif, $feedback_notif, $user_id);

        $ok = $stmt->execute();

        $stmt->close();



        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Preferences saved successfully' : 'Failed to save preferences']);

        exit;

    }



    if ($action === 'upload_photo') {

        if (!isset($_FILES['photo'])) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'No file provided']);

            exit;

        }



        $file = $_FILES['photo'];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Upload failed']);

            exit;

        }



        $max_size = 5 * 1024 * 1024;

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!isset($file['size']) || (int)$file['size'] > $max_size) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'File is too large (max 5MB)']);

            exit;

        }



        if (!isset($file['type']) || !in_array($file['type'], $allowed_types, true)) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, and WebP files are allowed']);

            exit;

        }



        $upload_dir = dirname(__DIR__) . '/images/profiles/';

        if (!is_dir($upload_dir)) {

            if (!mkdir($upload_dir, 0755, true)) {

                throw new Exception('Failed to create upload directory');

            }

        }

        if (!is_writable($upload_dir)) {

            throw new Exception('Upload directory is not writable');

        }



        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($ext === '') $ext = 'jpg';

        $filename = 'u' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        $filepath = $upload_dir . $filename;

        $photo_path = 'images/profiles/' . $filename;

        // Resize image to 600x600px before saving
        $resized = resizeImage($file['tmp_name'], $filepath, 600, 600, $ext);
        if (!$resized) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process image']);
            exit;
        }



        $stmt = $conn->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');

        if (!$stmt) {

            throw new Exception('Database error');

        }

        $stmt->bind_param('si', $photo_path, $user_id);

        $ok = $stmt->execute();

        $stmt->close();



        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Photo updated successfully' : 'Photo uploaded but database update failed', 'photo_path' => $photo_path]);

        exit;

    }



    http_response_code(400);

    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

}

