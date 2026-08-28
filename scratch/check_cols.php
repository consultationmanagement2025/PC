<?php
require 'db.php';

echo "=== DOCUMENTS TABLE COLUMNS ===\n";
$r = $conn->query("SHOW COLUMNS FROM documents");
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n=== ADMIN_DOCUMENTS TABLE COLUMNS ===\n";
$r2 = $conn->query("SHOW COLUMNS FROM admin_documents");
if ($r2) {
    while($row = $r2->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
