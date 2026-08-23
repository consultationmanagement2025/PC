<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("DESCRIBE consultations");
while ($row = $res->fetch_assoc()) {
    if ($row['Field'] === 'status') {
        print_r($row);
    }
}
