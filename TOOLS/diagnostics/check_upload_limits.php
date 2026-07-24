<?php
echo "<h2>PHP Upload Configuration Check</h2>";

echo "<h3>Upload Limits:</h3>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>memory_limit: " . ini_get('memory_limit') . "</p>";

echo "<h3>File Upload Status:</h3>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "</p>";

echo "<h3>Test Upload Directory:</h3>";
$upload_dir = __DIR__ . '/../../ASSETS/images/consultations/';
echo "<p>Directory: $upload_dir</p>";
echo "<p>Exists: " . (file_exists($upload_dir) ? 'YES' : 'NO') . "</p>";
echo "<p>Writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "</p>";

if (!file_exists($upload_dir)) {
    echo "<p style='color: red;'>Creating directory...</p>";
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p style='color: green;'>Directory created successfully</p>";
    } else {
        echo "<p style='color: red;'>Failed to create directory</p>";
    }
}

echo "<h3>Current Session:</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session status: " . session_status() . "</p>";

echo "<h3>Test File Upload:</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<p>Upload detected:</p>";
    echo "<pre>" . print_r($_FILES['test_file'], true) . "</pre>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $test_path = $upload_dir . 'test_' . time() . '.jpg';
        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $test_path)) {
            echo "<p style='color: green;'>Test upload successful!</p>";
        } else {
            echo "<p style='color: red;'>Failed to move uploaded file</p>";
        }
    }
} else {
    ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" accept="image/*">
        <button type="submit">Test Upload</button>
    </form>
    <?php
}
?>
