<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/user-logs.php';
require_once __DIR__ . '/../DATABASE/audit-log.php';

echo "--- 1. Inserting Sample User Activity Logs ---\n";

// Citizen actions
logUserAction(101, 'Maria Santos', 'Submitted Feedback', 'citizen_feedback', 'consultation', 1, 'Submitted feedback regarding waste segregation ordinance in Barangay Malinta', 'success');
logAction(101, 'Maria Santos', 'Submitted Feedback', 'feedback', 1, null, null, 'success', 'Submitted feedback regarding waste segregation');

logUserAction(102, 'Juan Dela Cruz', 'Voted in Survey', 'survey_response', 'consultation', 2, 'Voted in favor of Valenzuela Bike Lane Expansion Initiative', 'success');
logAction(102, 'Juan Dela Cruz', 'Voted in Survey', 'consultation', 2, null, null, 'success', 'Voted in favor of Bike Lane Expansion');

logUserAction(103, 'Dr. Aris Thorne', 'Annotated Master Document', 'expert_annotation', 'consultation', 1, 'Expert Dr. Aris Thorne appended inline recommendations (v1.1) to Waste Segregation Master Document', 'success');
logAction(103, 'Dr. Aris Thorne', 'Annotated Master Document', 'consultation', 1, null, null, 'success', 'Appended inline recommendations (v1.1)');

logUserAction(104, 'Jose Monde', 'Requested Additional Info', 'info_request', 'consultation', 10, 'Requested clarification on barangay clinic operating hours', 'success');
logAction(104, 'Jose Monde', 'Requested Additional Info', 'consultation', 10, null, null, 'success', 'Requested info on barangay clinic hours');

logUserAction(105, 'Elena Reyes', 'Submitted Policy Proposal', 'citizen_proposal', 'consultation', 12, 'Submitted public initiative proposal for Barangay Solar Lighting', 'success');
logAction(105, 'Elena Reyes', 'Submitted Policy Proposal', 'consultation', 12, null, null, 'success', 'Submitted public initiative proposal for Solar Lighting');

echo "Inserted sample user activities!\n\n";

echo "--- 2. Verifying user_logs Table Entries ---\n";
$uLogs = getUserLogs(10, 0);
echo "Total user_logs returned: " . count($uLogs) . "\n";
foreach ($uLogs as $l) {
    echo "- [{$l['timestamp']}] User: {$l['username']} | Action: {$l['action']} | Type: {$l['action_type']} | Details: {$l['description']}\n";
}

echo "\n--- 3. Verifying audit_logs Table Entries ---\n";
$aRes = $conn->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 10");
while ($a = $aRes->fetch_assoc()) {
    echo "- [{$a['timestamp']}] Admin/User: {$a['admin_user']} | Action: {$a['action']} | Entity: {$a['entity_type']} (#{$a['entity_id']})\n";
}

echo "\nUser Activity Logging Verification Completed Successfully!\n";
