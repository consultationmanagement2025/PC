<?php
require_once __DIR__ . '/../DATABASE/feedback.php';

$sampleHearings = [
    ['hearing_id' => 154, 'hearing_title' => 'Consultation on Drainage Upgrades for Flood Control', 'hearing_status' => 'completed'],
    ['hearing_id' => 131, 'hearing_title' => 'Public Hearing: Local Market Vendor Guidelines', 'hearing_status' => 'completed']
];

echo "PENDING BEFORE SYNC: " . count(getPendingPhmsApprovals()) . "\n";
syncPhmsCollectionToDatabase($sampleHearings);
echo "PENDING AFTER SYNC: " . count(getPendingPhmsApprovals()) . "\n";
