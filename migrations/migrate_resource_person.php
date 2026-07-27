<?php
/**
 * Migration script to add resource person support
 * Run this once to update the database schema
 */
require_once __DIR__ . '/db.php';

echo "<h2>Resource Person Migration</h2>";

// 1. Add Google OAuth columns
$checkGoogleId = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
if ($checkGoogleId->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER email");
    echo "✓ Added google_id column<br>";
} else {
    echo "- google_id column already exists<br>";
}

$checkGoogleToken = $conn->query("SHOW COLUMNS FROM users LIKE 'google_token'");
if ($checkGoogleToken->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN google_token TEXT DEFAULT NULL AFTER google_id");
    echo "✓ Added google_token column<br>";
} else {
    echo "- google_token column already exists<br>";
}

// 2. Add resource person specific columns
$checkExpertise = $conn->query("SHOW COLUMNS FROM users LIKE 'expertise_areas'");
if ($checkExpertise->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN expertise_areas TEXT DEFAULT NULL AFTER verification_status");
    echo "✓ Added expertise_areas column<br>";
} else {
    echo "- expertise_areas column already exists<br>";
}

$checkQualifications = $conn->query("SHOW COLUMNS FROM users LIKE 'qualifications'");
if ($checkQualifications->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN qualifications TEXT DEFAULT NULL AFTER expertise_areas");
    echo "✓ Added qualifications column<br>";
} else {
    echo "- qualifications column already exists<br>";
}

$checkDepartment = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if ($checkDepartment->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL AFTER qualifications");
    echo "✓ Added department column<br>";
} else {
    echo "- department column already exists<br>";
}

// 3. Note: verification_status already exists in main schema, no need to add
echo "- verification_status column already exists in main schema<br>";

$checkApprovedBy = $conn->query("SHOW COLUMNS FROM users LIKE 'approved_by'");
if ($checkApprovedBy->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN approved_by INT DEFAULT NULL AFTER verification_status");
    echo "✓ Added approved_by column<br>";
} else {
    echo "- approved_by column already exists<br>";
}

$checkApprovedAt = $conn->query("SHOW COLUMNS FROM users LIKE 'approved_at'");
if ($checkApprovedAt->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER approved_by");
    echo "✓ Added approved_at column<br>";
} else {
    echo "- approved_at column already exists<br>";
}

// 4. Update role column to include 'resource person'
$checkRoleType = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($checkRoleType->num_rows > 0) {
    $row = $checkRoleType->fetch_assoc();
    $type = $row['Type'];
    
    // Check if 'resource person' is already in the ENUM
    if (strpos($type, 'resource person') === false) {
        // Modify ENUM to include 'resource person'
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('citizen','admin','administrator','super admin','superadmin','staff','resource person','resource_person') NOT NULL DEFAULT 'citizen'");
        echo "✓ Updated role ENUM to include 'resource person'<br>";
    } else {
        echo "- role ENUM already includes 'resource person'<br>";
    }
}

echo "<h3>Migration completed successfully!</h3>";
?>
