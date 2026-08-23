<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, fullname, email, role FROM users WHERE fullname LIKE '%jojo%' OR email LIKE '%jojo%'");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        echo json_encode($r) . "\n";
    }
} else {
    echo "No user matching 'jojo' found in database.\n";
}
