<?php
require 'db.php';
$res = $conn->query("SELECT id, title, created_at FROM consultations ORDER BY id DESC");
while ($r = $res->fetch_assoc()) {
    echo $r['id'] . ' | ' . $r['title'] . ' | ' . $r['created_at'] . "\n";
}
