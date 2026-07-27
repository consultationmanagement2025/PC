<?php
// Run this script once to update the users table for ID verification
require_once __DIR__ . '/db.php';

$sql = "ALTER TABLE users 
    ADD COLUMN valid_id_path VARCHAR(255) AFTER role,
    ADD COLUMN verification_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER valid_id_path;";

if ($conn->query($sql) === TRUE) {
    echo "Users table updated successfully.";
} else {
    echo "Error updating table: " . $conn->error;
}
