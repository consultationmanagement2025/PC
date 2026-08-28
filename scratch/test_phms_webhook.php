<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_SERVERS_CHECK', 1);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer phms_live_2d6f8a4c1e9057b3a9c5e7f2b4d80156';

$payload = [
    'event' => 'phms_feedback_submission',
    'source_system' => 'PHMS',
    'phms_hearing_id' => 9821,
    'phms_registration_id' => 8812,
    'full_name' => 'Citizen Classmate Submission',
    'email' => 'classmate@valenzuela.gov.ph',
    'status' => 'pending_approval',
    'external_ref' => 'PHMS-FB-9821',
    'consultation_id' => 1,
    'citizen_responses' => [
        [
            'name' => 'Classmate Citizen',
            'statement' => 'Submitting real-time PHMS public hearing feedback for drainage improvement ordinance.',
            'rating' => 5
        ]
    ]
];

$_POST['payload_json'] = json_encode($payload);

echo "=== EXECUTING INBOUND WEBHOOK ENDPOINT (API/v1/events.php) ===\n";
try {
    require __DIR__ . '/../API/v1/events.php';
} catch (Throwable $e) {
    echo "Caught Error: " . $e->getMessage() . "\n";
}

echo "\n=== LATEST SYSTEM NOTIFICATIONS ===\n";
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';
$notifs = getUserNotifications(0, 3);
foreach ($notifs as $n) {
    echo "[#{$n['id']}] [{$n['type']}] {$n['created_at']} -> {$n['message']}\n";
}
