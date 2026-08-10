<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';

echo "Testing logUserAction...\n";
$res1 = logUserAction(101, 'Maria Santos', 'Submitted Feedback', 'citizen_feedback', 'consultation', 1, 'Submitted feedback regarding waste segregation ordinance in Barangay Malinta', 'success');
echo "logUserAction result: " . ($res1 ? 'TRUE' : 'FALSE') . "\n";

echo "Testing getUserLogs...\n";
$uLogs = getUserLogs(10, 0);
echo "Total user_logs returned: " . count($uLogs) . "\n";
foreach ($uLogs as $l) {
    echo "- [{$l['timestamp']}] User: {$l['username']} | Action: {$l['action']} | Type: {$l['action_type']} | Details: {$l['description']}\n";
}
