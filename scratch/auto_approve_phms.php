<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

echo "Running approveAllPhmsIngestions()...\n";
$ok = approveAllPhmsIngestions();
echo "Result: " . ($ok ? "Success" : "Failed") . "\n";

$pending = getPendingPhmsApprovals();
echo "New pending count: " . count($pending) . "\n";
