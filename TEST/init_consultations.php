<?php
require_once 'db.php';
require_once 'DATABASE/consultations.php';
require_once 'DATABASE/feedback.php';

// Initialize tables
initializeConsultationsTable();
initializeFeedbackTable();

// Add sample consultation
$consultation_id = createConsultation(
    'EDSA Widening Project Consultation',
    'Public consultation regarding the proposed EDSA widening project and its impact on businesses.',
    'infrastructure',
    '2026-02-01',
    '2026-02-28',
    1,
    100
);

echo "✓ Consultation created (ID: $consultation_id)<br>";

// Add sample feedback
$feedback_id = submitFeedback(
    2,
    'Juan Dela Cruz',
    $consultation_id,
    null,
    5,
    'positive',
    'Great initiative! The widening of EDSA will definitely help with traffic congestion.'
);

echo "✓ Feedback submitted (ID: $feedback_id)<br>";

// Get stats
$stats = getFeedbackStats();
echo "<br><strong>Feedback Stats:</strong>";
echo "<pre>" . json_encode($stats, JSON_PRETTY_PRINT) . "</pre>";

?>
