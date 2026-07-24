<?php
/**
 * Database Connection Test
 * This file tests if the database is connected and tables exist
 */

session_start();
require_once '../db.php';
require_once '../DATABASE/announcements.php';
require_once '../DATABASE/posts.php';
require_once '../DATABASE/audit-log.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .test-item { margin: 10px 0; padding: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Database Connection Test</h1>
    
    <?php
    
    // Test 1: Database Connection
    echo '<div class="test-item">';
    echo '<h3>Test 1: Database Connection</h3>';
    if ($conn && !$conn->connect_error) {
        echo '<p class="success">✓ Database connected successfully</p>';
    } else {
        echo '<p class="error">✗ Database connection failed: ' . $conn->connect_error . '</p>';
    }
    echo '</div>';
    
    // Test 2: Announcements Table
    echo '<div class="test-item">';
    echo '<h3>Test 2: Announcements Table</h3>';
    if (initializeAnnouncementsTable()) {
        echo '<p class="success">✓ Announcements table exists/created</p>';
        
        // Count announcements
        $result = $conn->query("SELECT COUNT(*) as count FROM announcements");
        if ($result) {
            $row = $result->fetch_assoc();
            echo '<p class="info">Total announcements: ' . $row['count'] . '</p>';
        }
    } else {
        echo '<p class="error">✗ Announcements table error</p>';
    }
    echo '</div>';
    
    // Test 3: Posts Table
    echo '<div class="test-item">';
    echo '<h3>Test 3: Posts Table</h3>';
    if (initializePostsTable()) {
        echo '<p class="success">✓ Posts table exists/created</p>';
        
        // Count posts
        $result = $conn->query("SELECT COUNT(*) as count FROM posts");
        if ($result) {
            $row = $result->fetch_assoc();
            echo '<p class="info">Total posts: ' . $row['count'] . '</p>';
        }
    } else {
        echo '<p class="error">✗ Posts table error</p>';
    }
    echo '</div>';
    
    // Test 4: Audit Logs Table
    echo '<div class="test-item">';
    echo '<h3>Test 4: Audit Logs Table</h3>';
    if (initializeAuditTable()) {
        echo '<p class="success">✓ Audit logs table exists/created</p>';
        
        // Count logs
        $result = $conn->query("SELECT COUNT(*) as count FROM audit_logs");
        if ($result) {
            $row = $result->fetch_assoc();
            echo '<p class="info">Total audit logs: ' . $row['count'] . '</p>';
        }
    } else {
        echo '<p class="error">✗ Audit logs table error</p>';
    }
    echo '</div>';
    
    // Test 5: Get Latest Announcements
    echo '<div class="test-item">';
    echo '<h3>Test 5: Fetch Latest Announcements</h3>';
    $anns = getLatestAnnouncements(5);
    if ($anns !== false && is_array($anns)) {
        echo '<p class="success">✓ Can fetch announcements (' . count($anns) . ' found)</p>';
        if (count($anns) > 0) {
            echo '<ul>';
            foreach ($anns as $ann) {
                echo '<li>' . htmlspecialchars($ann['title']) . ' by ' . htmlspecialchars($ann['admin_user']) . '</li>';
            }
            echo '</ul>';
        }
    } else {
        echo '<p class="error">✗ Failed to fetch announcements</p>';
    }
    echo '</div>';
    
    // Test 6: Get Posts
    echo '<div class="test-item">';
    echo '<h3>Test 6: Fetch Posts</h3>';
    $posts = getPosts(5, 0);
    if ($posts !== false && is_array($posts)) {
        echo '<p class="success">✓ Can fetch posts (' . count($posts) . ' found)</p>';
        if (count($posts) > 0) {
            echo '<ul>';
            foreach ($posts as $post) {
                echo '<li>' . htmlspecialchars($post['author']) . ': ' . htmlspecialchars(substr($post['content'], 0, 50)) . '...</li>';
            }
            echo '</ul>';
        }
    } else {
        echo '<p class="error">✗ Failed to fetch posts</p>';
    }
    echo '</div>';
    
    // Test 7: Get Audit Logs
    echo '<div class="test-item">';
    echo '<h3>Test 7: Fetch Audit Logs</h3>';
    $logs = getAuditLogs(5, 0, []);
    if ($logs !== false && is_array($logs)) {
        echo '<p class="success">✓ Can fetch audit logs (' . count($logs) . ' found)</p>';
        if (count($logs) > 0) {
            echo '<ul>';
            foreach ($logs as $log) {
                echo '<li>[' . $log['timestamp'] . '] ' . htmlspecialchars($log['admin_user']) . ' - ' . htmlspecialchars($log['action']) . '</li>';
            }
            echo '</ul>';
        }
    } else {
        echo '<p class="error">✗ Failed to fetch audit logs</p>';
    }
    echo '</div>';
    
    echo '<hr>';
    echo '<p><a href="user-portal.php">Go to User Portal</a> | <a href="system-template-full.php">Go to Admin Dashboard</a></p>';
    
    ?>
</body>
</html>
