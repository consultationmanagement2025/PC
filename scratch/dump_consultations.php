<?php
require 'db.php';
$res = $conn->query("SELECT id, title, category, description, status, created_at FROM consultations ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . "\nTitle: " . $r['title'] . "\nCategory: " . $r['category'] . "\nStatus: " . $r['status'] . "\nCreated: " . $r['created_at'] . "\n---\n";
}
