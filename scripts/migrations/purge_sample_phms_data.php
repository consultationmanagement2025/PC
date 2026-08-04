<?php
require_once __DIR__ . '/../../db.php';

echo "Purging stale sample/mock PHMS hearing feedback records from local database...\n";
if ($conn->query("TRUNCATE TABLE hearing_queue")) {
    echo "Successfully purged and truncated hearing_queue table.\n";
} else {
    echo "Error truncating hearing_queue table: " . $conn->error . "\n";
}
