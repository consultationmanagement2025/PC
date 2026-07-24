<?php
require __DIR__ . '/../db.php';
$r = $conn->query('SHOW CREATE TABLE users');
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo 'Error: ' . $conn->error;
}
?>