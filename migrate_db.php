<?php
require 'db.php';

// Check if columns exist and add them if they don't
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN username VARCHAR(100)");
    echo "Added username column<br>";
}

$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(500)");
    echo "Added profile_photo column<br>";
}

$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'language'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'en'");
    echo "Added language column<br>";
}

$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'theme'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN theme VARCHAR(10) DEFAULT 'light'");
    echo "Added theme column<br>";
}

$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'email_notif'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN email_notif BOOLEAN DEFAULT 1");
    echo "Added email_notif column<br>";
}

$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'announcement_notif'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN announcement_notif BOOLEAN DEFAULT 1");
    echo "Added announcement_notif column<br>";
}

$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'feedback_notif'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN feedback_notif BOOLEAN DEFAULT 1");
    echo "Added feedback_notif column<br>";
}

echo "Database migration complete!";
?>
