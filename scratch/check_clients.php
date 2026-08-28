<?php
define('DB_SERVERS_CHECK', 1);
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT client_id, client_name, source_system, scopes FROM integration_clients");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        echo "Client ID {$r['client_id']}: {$r['client_name']} ({$r['source_system']}) -> Scopes: {$r['scopes']}\n";
    }
} else {
    echo "No clients found or error: " . $conn->error . "\n";
}
