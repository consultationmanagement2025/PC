<?php
/**
 * Create database and tables for CAP101 (development helper)
 * Run from browser: http://localhost/CAP101/PC/create_db_and_tables.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'pc_db';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($mysqli->connect_error) {
    die('Could not connect to MySQL: ' . $mysqli->connect_error);
}

// Create database if not exists
if ($mysqli->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") === false) {
    die('Error creating database: ' . $mysqli->error);
}

// Select database
$mysqli->select_db($DB_NAME);

echo "Database `$DB_NAME` exists or was created successfully.<br>";

// Now include db.php and table initializers to create tables
require_once __DIR__ . '/db.php';

// Call known initialize functions if available
$created = [];
if (file_exists(__DIR__ . '/DATABASE/consultations.php')) {
    require_once __DIR__ . '/DATABASE/consultations.php';
    if (function_exists('initializeConsultationsTable')) {
        if (initializeConsultationsTable()) {
            $created[] = 'consultations';
        }
    }
}

if (file_exists(__DIR__ . '/DATABASE/feedback.php')) {
    require_once __DIR__ . '/DATABASE/feedback.php';
    if (function_exists('initializeFeedbackTable')) {
        if (initializeFeedbackTable()) {
            $created[] = 'feedback';
        }
    } else {
        // fallback: create a simple feedback table if function absent
        $sql = "CREATE TABLE IF NOT EXISTS feedback (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            message LONGTEXT,
            feedback_type VARCHAR(100),
            allow_email_notifications TINYINT(1) DEFAULT 0,
            consultation_topic VARCHAR(255) DEFAULT NULL,
            consultation_id INT DEFAULT NULL,
            rating TINYINT DEFAULT NULL,
            attachment_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($conn->query($sql) === TRUE) $created[] = 'feedback (fallback)';
    }
}

// Create posts, users, etc. if migration scripts exist
$possible = [
    '/DATABASE/posts.php',
    '/DATABASE/audit-log.php',
    '/DATABASE/notifications.php'
];
foreach ($possible as $p) {
    $path = __DIR__ . $p;
    if (file_exists($path)) {
        require_once $path;
        // attempt to call an initializer if name matches pattern
        $fn = null;
        if (preg_match('#/([^/]+)\.php$#', $p, $m)) {
            $base = $m[1];
            $candidate = 'initialize' . str_replace('-', '', ucwords($base, '-')) . 'Table';
            if (function_exists($candidate)) { if ($candidate()) $created[] = $base; }
        }
    }
}

if (!empty($created)) {
    echo "Created tables: " . implode(', ', $created) . "<br>";
} else {
    echo "No table initializers found or tables already exist.<br>";
}

echo '<p>Done. Check phpMyAdmin and refresh. If you still don\'t see the database, ensure MySQL is running in XAMPP and credentials in <code>db.php</code> are correct.</p>';

?>