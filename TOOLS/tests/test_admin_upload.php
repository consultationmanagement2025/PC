<?php
session_start();
require_once __DIR__ . '/../../db.php';

// Simple admin upload test
echo "<h2>Admin Upload Test</h2>";

// Check if admin is logged in
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin' && $current_role !== 'administrator' && $current_role !== 'super admin' && $current_role !== 'superadmin') {
    echo "<p style='color: red;'>Access denied - not admin</p>";
    echo "<p>Please <a href='../../login.php'>login as admin</a> first</p>";
    exit;
}

echo "<p style='color: green;'>Admin access confirmed</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    echo "<h3>Upload Attempt:</h3>";
    echo "<pre>" . print_r($_FILES['test_image'], true) . "</pre>";
    
    if ($_FILES['test_image']['error'] === UPLOAD_ERR_OK) {
        // Test the exact same upload function as admin
        $upload_dir = __DIR__ . '/../../ASSETS/images/consultations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = 'test_admin_' . time() . '_' . uniqid() . '.' . pathinfo($_FILES['test_image']['name'], PATHINFO_EXTENSION);
        $filepath = $upload_dir . $filename;
        
        echo "<p>Attempting to move file to: $filepath</p>";
        
        if (move_uploaded_file($_FILES['test_image']['tmp_name'], $filepath)) {
            echo "<p style='color: green;'>✓ Upload successful!</p>";
            echo "<p>File saved as: ASSETS/images/consultations/$filename</p>";
            
            // Test creating a consultation with this image
            require_once __DIR__ . '/../../DATABASE/consultations.php';
            $test_title = 'Test Consultation with Image ' . date('Y-m-d H:i:s');
            $test_desc = 'This is a test consultation to verify image upload works';
            $image_path = 'ASSETS/images/consultations/' . $filename;
            
            echo "<p>Testing database insertion with image path: $image_path</p>";
            
            $id = createConsultation($test_title, $test_desc, 'Test', date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+7 days')), $_SESSION['user_id'], 0, $image_path);
            
            if ($id) {
                echo "<p style='color: green;'>✓ Consultation created with ID: $id</p>";
                echo "<p><a href='../diagnostics/debug_consultations.php'>Check consultations</a></p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to create consultation</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Failed to move uploaded file</p>";
            echo "<p>Upload error: " . $_FILES['test_image']['error'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Upload error code: " . $_FILES['test_image']['error'] . "</p>";
    }
} else {
    ?>
    <h3>Test Admin Image Upload</h3>
    <form method="POST" enctype="multipart/form-data">
        <p>
            <label>Select an image:</label><br>
            <input type="file" name="test_image" accept="image/*" required>
        </p>
        <button type="submit">Test Upload</button>
    </form>
    
    <h3>Debug Info:</h3>
    <p>PHP Upload Max Filesize: <?php echo ini_get('upload_max_filesize'); ?></p>
    <p>PHP Post Max Size: <?php echo ini_get('post_max_size'); ?></p>
    <p>File Uploads Enabled: <?php echo ini_get('file_uploads') ? 'YES' : 'NO'; ?></p>
    <p>Upload Directory: <?php echo __DIR__ . '/../../ASSETS/images/consultations/'; ?></p>
    <p>Directory Exists: <?php echo file_exists(__DIR__ . '/../../ASSETS/images/consultations/') ? 'YES' : 'NO'; ?></p>
    <p>Directory Writable: <?php echo is_writable(__DIR__ . '/../../ASSETS/images/consultations/') ? 'YES' : 'NO'; ?></p>
    <?php
}
?>
