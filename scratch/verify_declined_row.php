<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../db.php';

// Check any item with status = 'declined'
$res = $conn->query("SELECT id, title, status FROM consultations WHERE status = 'declined' OR status = 'rejected' LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;

if ($row) {
    echo "Found declined consultation in DB:\n";
    print_r($row);
} else {
    echo "No declined consultation found in DB.\n";
}
