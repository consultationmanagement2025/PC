<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

$pending = getPendingPhmsApprovals();
echo "getPendingPhmsApprovals() returned count: " . count($pending) . "\n";
foreach ($pending as $p) {
    echo "Queue ID: " . ($p['queue_id'] ?? 'N/A') . " | Title: " . ($p['hearing_title'] ?? $p['full_name'] ?? 'N/A') . " | Status: " . ($p['status'] ?? 'N/A') . " | Approval Status: " . ($p['approval_status'] ?? 'N/A') . "\n";
}
