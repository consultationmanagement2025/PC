<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/unified_feedback_compilation_utils.php';

echo "=== TESTING PHMS INTEGRATION & FEEDBACK MERGING ===\n\n";

// 1. Check hearing_queue table schema and rows
$res = $conn->query("SELECT id, phms_hearing_id, full_name, email, source_system, approval_status, status FROM hearing_queue ORDER BY id DESC LIMIT 5");
echo "Current hearing_queue entries in database:\n";
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        print_r($r);
    }
} else {
    echo "No existing hearing_queue entries. Creating a test PHMS live hearing payload...\n";
    $testJson = json_encode([
        'hearing_title' => 'PHMS Live Public Hearing on Bike Lanes & Transit Safety',
        'responses' => [
            ['citizen_name' => 'Engr. Juan Dela Cruz', 'sentiment' => 'positive', 'testimony' => 'Strongly support protected bike lane barriers along McArthur Highway.'],
            ['citizen_name' => 'Maria Santos', 'sentiment' => 'neutral', 'testimony' => 'Recommend adding LED solar warning lights near pedestrian crossings.']
        ]
    ]);
    
    $stmt = $conn->prepare("INSERT INTO hearing_queue (phms_hearing_id, full_name, email, external_ref, source_system, payload_json, approval_status, status) VALUES (?, 'PHMS Public Hearing Participant', 'phms@valenzuela.gov.ph', 'PHMS-LIVE-888', 'PHMS', ?, 'approved', 'approved')");
    $phmsId = 888101;
    $stmt->bind_param("is", $phmsId, $testJson);
    $stmt->execute();
    $stmt->close();
    echo "Created mock PHMS hearing entry #{$phmsId}.\n";
}

// 2. Test compileUnifiedConsultationFeedback for Consultation #2
echo "\n--- Testing Unified PHMS + PCMS Feedback Merger ---\n";
$unified = compileUnifiedConsultationFeedback(2, $conn);

echo "Unified Feedback Summary:\n";
echo "Total Submissions: " . ($unified['total_submissions'] ?? 0) . "\n";
echo "PCMS Portal Submissions: " . ($unified['pcms_portal_count'] ?? 0) . "\n";
echo "PHMS Live Hearing Submissions: " . ($unified['phms_hearing_count'] ?? 0) . "\n";
echo "Dominant Sentiment: " . ($unified['dominant_sentiment'] ?? 'N/A') . "\n";
