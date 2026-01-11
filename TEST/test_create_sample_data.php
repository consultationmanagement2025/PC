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
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .container { max-width: 800px; margin: 0 auto; }
        .test-item { margin: 15px 0; padding: 15px; border: 1px solid #ccc; background: white; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Test - Create Sample Data</h1>
        
        <?php
        
        // Test 1: Create a sample announcement
        echo '<div class="test-item">';
        echo '<h3>Test 1: Create Sample Announcement</h3>';
        $annId = createAnnouncement(1, 'Test Admin', 'Welcome to PCMP', 'This is a test announcement. Please check the user portal to see if this appears in the Updates section.', 'public');
        if ($annId) {
            echo '<p class="success">✓ Announcement created with ID: ' . $annId . '</p>';
        } else {
            echo '<p class="error">✗ Failed to create announcement</p>';
        }
        echo '</div>';
        
        // Test 2: Create a sample post
        echo '<div class="test-item">';
        echo '<h3>Test 2: Create Sample Post</h3>';
        $postId = createPost(1, 'Test User', 'This is a test post from a user. Does the admin see this?');
        if ($postId) {
            echo '<p class="success">✓ Post created with ID: ' . $postId . '</p>';
        } else {
            echo '<p class="error">✗ Failed to create post</p>';
        }
        echo '</div>';
        
        // Test 3: Verify we can fetch the announcement
        echo '<div class="test-item">';
        echo '<h3>Test 3: Fetch Latest Announcements</h3>';
        $anns = getLatestAnnouncements(10);
        echo '<p class="info">Found ' . count($anns) . ' announcement(s)</p>';
        if (count($anns) > 0) {
            echo '<ul>';
            foreach ($anns as $ann) {
                echo '<li><strong>' . htmlspecialchars($ann['title']) . '</strong> by ' . htmlspecialchars($ann['admin_user']) . '</li>';
                echo '<li style="color: #666; margin-bottom: 10px;">' . htmlspecialchars(substr($ann['content'], 0, 100)) . '...</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        
        // Test 4: Verify we can fetch the posts
        echo '<div class="test-item">';
        echo '<h3>Test 4: Fetch Posts</h3>';
        $posts = getPosts(10, 0);
        echo '<p class="info">Found ' . count($posts) . ' post(s)</p>';
        if (count($posts) > 0) {
            echo '<ul>';
            foreach ($posts as $post) {
                echo '<li><strong>' . htmlspecialchars($post['author']) . '</strong>: ' . htmlspecialchars(substr($post['content'], 0, 100)) . '...</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        
        // Test 5: Check audit logs
        echo '<div class="test-item">';
        echo '<h3>Test 5: Check Audit Logs</h3>';
        $logs = getAuditLogs(10, 0, []);
        echo '<p class="info">Found ' . count($logs) . ' audit log(s)</p>';
        if (count($logs) > 0) {
            echo '<ul>';
            foreach ($logs as $log) {
                echo '<li>[' . $log['timestamp'] . '] <strong>' . htmlspecialchars($log['admin_user']) . '</strong>: ' . htmlspecialchars($log['action']) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: orange;"><i>Note: No audit logs yet. They will be created when actions are performed through the web interface.</i></p>';
        }
        echo '</div>';
        
        echo '<hr>';
        echo '<h2>Next Steps:</h2>';
        echo '<ol>';
        echo '<li><a href="user-portal.php">Go to User Portal</a> - You should see the test announcement in the "Updates" section</li>';
        echo '<li><a href="system-template-full.php">Go to Admin Dashboard</a> - You should see the test post in "User Posts" section</li>';
        echo '<li>Try posting your own announcement and concerns to see real-time updates</li>';
        echo '<li>Check the audit logs to see all activities being tracked</li>';
        echo '</ol>';
        
        ?>
    </div>
</body>
</html>
