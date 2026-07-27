<?php
/**
 * Migration: Fix `documents` table schema to match Document Management expectations.
 * Run: php migrate_fix_documents_table.php
 */
require_once __DIR__ . '/db.php';

echo "Checking documents table schema...\n";

$res = $conn->query("SHOW COLUMNS FROM documents");
if (!$res) {
    echo "No 'documents' table found — creating from schema.\n";
    require_once __DIR__ . '/DATABASE/document-management.php';
    initializeDocumentsTable();
    echo "Created documents table.\n";
    exit(0);
}

$cols = [];
while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];

$needed = [
    'consultation_id' => "INT NOT NULL DEFAULT 0",
    'reference_number' => "VARCHAR(50) NOT NULL",
    'original_filename' => "VARCHAR(255) NOT NULL",
    'stored_filename' => "VARCHAR(255) NOT NULL",
    'file_type' => "VARCHAR(50) NOT NULL",
    'file_size' => "INT NOT NULL",
    'upload_date' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'uploaded_by' => "INT NOT NULL",
    'document_type' => "ENUM('consultation_form','attachment','response','final_document') DEFAULT 'consultation_form'",
    'status' => "ENUM('draft','submitted','reviewed','approved','rejected') DEFAULT 'submitted'",
    'description' => "TEXT"
];

$altered = false;
foreach ($needed as $col => $def) {
    if (!in_array($col, $cols)) {
        $sql = "ALTER TABLE documents ADD COLUMN $col $def";
        echo "Adding column $col... ";
        if ($conn->query($sql) === TRUE) {
            echo "OK\n";
            $altered = true;
        } else {
            echo "FAILED: " . $conn->error . "\n";
        }
    }
}

if ($altered) {
    echo "Schema updated.\n";
} else {
    echo "No changes needed.\n";
}

// Add indexes
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_consultation ON documents(consultation_id)",
    "CREATE INDEX IF NOT EXISTS idx_reference ON documents(reference_number)",
    "CREATE INDEX IF NOT EXISTS idx_status ON documents(status)"
];
foreach ($indexes as $ix) {
    // MySQL doesn't support IF NOT EXISTS for CREATE INDEX; check first
    preg_match('/ON documents\(([^)]+)\)/', $ix, $m);
    $col = $m[1] ?? null;
    if ($col) {
        $res = $conn->query("SHOW INDEX FROM documents WHERE Column_name = '$col'");
        if ($res && $res->num_rows > 0) {
            continue;
        }
    }
    // Derive index name
    if (stripos($ix, 'idx_consultation') !== false) $iname = 'idx_consultation';
    elseif (stripos($ix, 'idx_reference') !== false) $iname = 'idx_reference';
    else $iname = 'idx_status';
    $sql = "ALTER TABLE documents ADD INDEX $iname ($col)";
    echo "Adding index $iname... ";
    if ($conn->query($sql) === TRUE) echo "OK\n"; else echo "FAILED: " . $conn->error . "\n";
}

echo "Migration complete.\n";

?>
