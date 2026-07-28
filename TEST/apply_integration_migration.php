<?php
require_once __DIR__ . '/../db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../DATABASE/add_integration_tables.sql');
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        echo "SUCCESS: Integration database tables migrated successfully.\n";
    } else {
        echo "ERROR: Migration failed: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
