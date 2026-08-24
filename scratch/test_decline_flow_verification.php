<?php
require_once __DIR__ . '/../db.php';

echo "=== DECLINE FLOW INTEGRATION VERIFICATION ===\n\n";

$phpPath = 'C:\\xampp\\php\\php.exe';

function testEndpoint($file) {
    global $phpPath;
    $cmd = "$phpPath -r \"
        \$_SERVER['REQUEST_METHOD'] = 'POST';
        \$_GET['action'] = 'decline_submission';
        \$_POST = ['id' => 1, 'reason' => 'Decline Test via Subprocess'];
        require '$file';
    \"";
    return shell_exec($cmd);
}

echo "1. Root API: " . testEndpoint('API/consultations_api.php') . "\n";
echo "2. Admin API: " . testEndpoint('admin/API/consultations_api.php') . "\n";
echo "3. Admin-Side API: " . testEndpoint('admin-side/API/consultations_api.php') . "\n";

$res = $conn->query("SELECT id, status, admin_response, remarks, updated_at FROM consultations WHERE id = 1 LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
echo "\n4. Final Database Record state for ID 1:\n" . json_encode($row, JSON_PRETTY_PRINT) . "\n";

if ($row && $row['status'] === 'declined') {
    echo "\n>>> VERIFICATION SUCCESSFUL: Consultation ID 1 status is set to 'declined' in DB! <<<\n";
} else {
    echo "\n>>> VERIFICATION FAILED: Status is not declined! <<<\n";
}
