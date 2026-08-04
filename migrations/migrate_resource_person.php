<?php
/**
 * Migration script to add resource person support
 * Run this once to update the database schema
 */
require_once dirname(__DIR__) . '/db.php';

echo "<h2>Resource Person Migration</h2>";

// 1. Add Google OAuth columns
$checkGoogleId = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
if ($checkGoogleId && $checkGoogleId->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER email");
    echo "✓ Added google_id column<br>";
} else {
    echo "- google_id column already exists<br>";
}

$checkGoogleToken = $conn->query("SHOW COLUMNS FROM users LIKE 'google_token'");
if ($checkGoogleToken && $checkGoogleToken->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN google_token TEXT DEFAULT NULL AFTER google_id");
    echo "✓ Added google_token column<br>";
} else {
    echo "- google_token column already exists<br>";
}

// 2. Add resource person specific columns to users
$checkExpertise = $conn->query("SHOW COLUMNS FROM users LIKE 'expertise_areas'");
if ($checkExpertise && $checkExpertise->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN expertise_areas TEXT DEFAULT NULL");
    echo "✓ Added expertise_areas column<br>";
} else {
    echo "- expertise_areas column already exists<br>";
}

$checkQualifications = $conn->query("SHOW COLUMNS FROM users LIKE 'qualifications'");
if ($checkQualifications && $checkQualifications->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN qualifications TEXT DEFAULT NULL");
    echo "✓ Added qualifications column<br>";
} else {
    echo "- qualifications column already exists<br>";
}

$checkDepartment = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if ($checkDepartment && $checkDepartment->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL");
    echo "✓ Added department column<br>";
} else {
    echo "- department column already exists<br>";
}

$checkPhone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($checkPhone && $checkPhone->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL");
    echo "✓ Added phone column<br>";
} else {
    echo "- phone column already exists<br>";
}

$checkApprovedBy = $conn->query("SHOW COLUMNS FROM users LIKE 'approved_by'");
if ($checkApprovedBy && $checkApprovedBy->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN approved_by INT DEFAULT NULL");
    echo "✓ Added approved_by column<br>";
} else {
    echo "- approved_by column already exists<br>";
}

$checkApprovedAt = $conn->query("SHOW COLUMNS FROM users LIKE 'approved_at'");
if ($checkApprovedAt && $checkApprovedAt->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN approved_at DATETIME DEFAULT NULL");
    echo "✓ Added approved_at column<br>";
} else {
    echo "- approved_at column already exists<br>";
}

// 3. Update role column to include 'resource person'
$checkRoleType = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($checkRoleType && $checkRoleType->num_rows > 0) {
    $row = $checkRoleType->fetch_assoc();
    $type = $row['Type'];
    if (strpos($type, 'resource person') === false && strpos($type, 'resource_person') === false) {
        $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'citizen'");
        echo "✓ Updated role column<br>";
    } else {
        echo "- role column supports resource person<br>";
    }
}

// 4. Add assigned_to to consultations table
$checkAssignedTo = $conn->query("SHOW COLUMNS FROM consultations LIKE 'assigned_to'");
if ($checkAssignedTo && $checkAssignedTo->num_rows == 0) {
    $conn->query("ALTER TABLE consultations ADD COLUMN assigned_to INT(11) DEFAULT NULL AFTER admin_id");
    echo "✓ Added assigned_to column to consultations<br>";
} else {
    echo "- assigned_to column already exists in consultations<br>";
}

// 5. Create resolution_reports table
$conn->query("CREATE TABLE IF NOT EXISTS resolution_reports (
    id INT(11) NOT NULL AUTO_INCREMENT,
    consultation_id INT(11) NOT NULL,
    uploaded_by INT(11) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consultation_id (consultation_id),
    KEY idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ Verified resolution_reports table<br>";

// 6. Create info_requests table
$conn->query("CREATE TABLE IF NOT EXISTS info_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    consultation_id INT(11) NOT NULL,
    requested_by INT(11) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'responded', 'closed') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consultation_id (consultation_id),
    KEY idx_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ Verified info_requests table<br>";

echo "<h3>Migration completed successfully!</h3>";
?>
