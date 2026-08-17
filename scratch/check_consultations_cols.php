<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SHOW COLUMNS FROM consultations");
$cols = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $cols[] = $r['Field'];
    }
}
echo "Consultations Table Columns:\n" . implode(', ', $cols) . "\n";
echo "Has admin_response? " . (in_array('admin_response', $cols) ? "YES" : "NO") . "\n";
echo "Has remarks? " . (in_array('remarks', $cols) ? "YES" : "NO") . "\n";
