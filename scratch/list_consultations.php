<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT id, title, type, status FROM consultations ORDER BY id DESC LIMIT 10");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
