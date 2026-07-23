<?php
/**
 * API to create announcements with optional image upload
 */
session_start();
require_once __DIR__ . '/../DATABASE/announcements.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';

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

// Only allow admins
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if (empty($title) || empty($content)) {
    echo json_encode(['error' => 'Title and content required']);
    exit();
}

$admin_id = $_SESSION['user_id'] ?? null;
$admin_user = $_SESSION['fullname'] ?? 'System';
$image_path = null;

// Handle image upload if provided
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($uploaded_file['type'], $allowed_types)) {
        echo json_encode(['error' => 'Invalid image type']);
        exit();
    }
    
    if ($uploaded_file['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo json_encode(['error' => 'Image too large (max 5MB)']);
        exit();
    }
    
    // Create images directory if it doesn't exist
    $upload_dir = __DIR__ . '/../images/announcements';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    $filename = 'ann_' . time() . '_' . uniqid() . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;
    
    // Resize image to 600x600px before saving
    $resized = resizeImage($uploaded_file['tmp_name'], $filepath, 600, 600, $ext);
    if ($resized) {
        $image_path = 'images/announcements/' . $filename;
    }
}

// Create announcement
$ann_id = createAnnouncement($admin_id, $admin_user, $title, $content, 'public', $image_path);

if ($ann_id) {
    // Log admin action
    logAction($admin_id, $admin_user, 'created_announcement', 'announcement', $ann_id, null, null, 'success', 'Admin created announcement: ' . $title);
    
    echo json_encode([
        'success' => true,
        'id' => $ann_id,
        'image_path' => $image_path
    ]);
} else {
    echo json_encode(['error' => 'Failed to create announcement']);
}
?>
