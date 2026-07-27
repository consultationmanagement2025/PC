<?php
echo json_encode([
    'current_time' => date('Y-m-d H:i:s'),
    'current_timestamp' => time(),
    'timezone' => date_default_timezone_get()
], JSON_PRETTY_PRINT);
?>
