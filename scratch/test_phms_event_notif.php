<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin-side/DATABASE/notifications.php';
require_once __DIR__ . '/../admin-side/DATABASE/feedback.php';

echo "=== SIMULATING INCOMING PHMS FEEDBACK EVENT ===\n";

$testHearing = [
    [
        'hearing_id' => 9991,
        'phms_registration_id' => 99910,
        'hearing_title' => 'Test Hearing: Public Feedback Integration Verification',
        'hearing_status' => 'completed',
        'feedback_count' => 3,
        'average_rating' => 4.8,
        'citizen_responses' => [
            ['name' => 'Classmate Test User', 'statement' => 'Feedback submitted from PHMS integration verification test.', 'rating' => 5]
        ]
    ]
];

try {
    $res = syncPhmsCollectionToDatabase($testHearing);
    echo "Sync Result: " . ($res ? "SUCCESS" : "FAILED") . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== LATEST NOTIFICATIONS IN DATABASE ===\n";
try {
    $notifs = getUserNotifications(0, 5);
    foreach ($notifs as $n) {
        echo "[#{$n['id']}] [{$n['type']}] {$n['created_at']} -> {$n['message']}\n";
    }
} catch (Throwable $e) {
    echo "Error getting notifs: " . $e->getMessage() . "\n";
}
