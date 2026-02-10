<?php
/**
 * Quick Test - Create Sample Data
 * This creates test announcements and posts for demonstration
 */

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../announcements.php';
require_once __DIR__ . '/../DATABASE/posts.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['fullname'] = 'Test Admin';
$_SESSION['role'] = 'admin';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Test - Create Sample Data</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        <?php
        // test_create_sample_data.php - removed to prevent creation of dummy/sample data
        http_response_code(410);
        echo 'This endpoint has been removed.';
        exit;
</head>
