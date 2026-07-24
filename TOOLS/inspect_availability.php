<?php
require_once __DIR__ . '/../db.php';
$conn = dbEnsureConnection();
$res = $conn->query("DESCRIBE consultation_availability");
if (!$res) {
    echo json_encode(['error' => $conn->error]);
    exit(0);
}
$cols = [];
while ($r = $res->fetch_assoc()) $cols[] = $r;
echo json_encode($cols, JSON_PRETTY_PRINT);
