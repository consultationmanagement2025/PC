<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT id, phms_hearing_id, approval_status, status, is_processed FROM hearing_queue ORDER BY id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
