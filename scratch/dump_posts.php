<?php
require 'db.php';
$res = $conn->query("SELECT * FROM posts");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        print_r($r);
    }
}
