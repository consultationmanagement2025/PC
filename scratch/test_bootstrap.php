<?php
require_once __DIR__ . '/../API/v1/bootstrap.php';
echo "BOOTSTRAP OK: request_id=" . $GLOBALS['integration_request_id'] . "\n";
echo "Database Connection: " . ($GLOBALS['integration_conn'] ? "Connected" : "Failed") . "\n";
