<?php
define('DB_SERVERS_CHECK', 1);
require_once __DIR__ . '/../db.php';

$sql = "SELECT client_id, scopes FROM integration_clients";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $cid = (int)$r['client_id'];
        $scopes = json_decode($r['scopes'], true) ?: [];
        $added = false;
        foreach (['events:write', 'events:read', 'feedback:read', 'feedback:write'] as $s) {
            if (!in_array($s, $scopes, true)) {
                $scopes[] = $s;
                $added = true;
            }
        }
        if ($added) {
            $jsonStr = json_encode($scopes);
            $stmt = $conn->prepare("UPDATE integration_clients SET scopes = ? WHERE client_id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $jsonStr, $cid);
                $stmt->execute();
                $stmt->close();
                echo "Updated scopes for client_id {$cid}
";
            }
        }
    }
}
echo "Database client scopes updated successfully.
";
