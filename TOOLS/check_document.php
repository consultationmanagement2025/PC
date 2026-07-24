<?php
require_once __DIR__ . '/../db.php';

echo "=== Checking Documents Table ===\n";

// Check if documents exist
$result = $conn->query("SELECT * FROM documents ORDER BY upload_date DESC LIMIT 5");
if (!$result) {
    echo "Error querying documents: " . $conn->error . "\n";
} else {
    $count = $result->num_rows;
    echo "Found $count document(s) in database\n";
    
    if ($count > 0) {
        echo "\nRecent documents:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- ID: {$row['id']}, Consultation ID: {$row['consultation_id']}, File: {$row['stored_filename']}, Size: {$row['file_size']} bytes\n";
        }
    }
}

// Check uploaded_by column definition
echo "\n=== Checking uploaded_by column ===\n";
$col_check = $conn->query("SHOW COLUMNS FROM documents LIKE 'uploaded_by'");
if ($col_check) {
    $col = $col_check->fetch_assoc();
    echo "uploaded_by column definition: " . $col['Type'] . ", Null: " . $col['Null'] . "\n";
}

// Check if file exists
echo "\n=== Checking PDF files ===\n";
$upload_dir = __DIR__ . '/../uploads/documents/';
if (is_dir($upload_dir)) {
    $files = glob($upload_dir . '*.pdf');
    echo "Found " . count($files) . " PDF file(s) in uploads/documents/\n";
    foreach ($files as $file) {
        echo "- " . basename($file) . " (" . filesize($file) . " bytes)\n";
    }
} else {
    echo "uploads/documents directory does not exist\n";
}
?>
