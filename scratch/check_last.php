<?php
require 'db.php';
$res = $conn->query("SELECT id, title, category, description, created_at FROM consultations ORDER BY id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
