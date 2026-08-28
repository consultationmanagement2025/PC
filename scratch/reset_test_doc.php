<?php
require_once __DIR__ . '/../db.php';
$conn->query("UPDATE documents SET downloads = 0 WHERE id = 77");
echo "Reset test doc downloads to 0 successfully.\n";
