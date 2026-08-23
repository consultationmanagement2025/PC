<?php
require_once __DIR__ . '/../DATABASE/feedback.php';

$before = getPendingPhmsApprovals();
echo "BEFORE PENDING COUNT: " . count($before) . "\n";

if (!empty($before)) {
    $sample = $before[0];
    echo "Sample item queue_id: " . $sample['queue_id'] . ", phms_hearing_id: " . $sample['phms_hearing_id'] . "\n";
    
    $ok = approvePhmsIngestion($sample['queue_id']);
    echo "approvePhmsIngestion(" . $sample['queue_id'] . ") RESULT: " . ($ok ? "TRUE" : "FALSE") . "\n";
}

$after = getPendingPhmsApprovals();
echo "AFTER PENDING COUNT: " . count($after) . "\n";
