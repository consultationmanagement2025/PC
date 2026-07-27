<?php
require 'db.php';

echo "=== Survey Data for Consultations 50 and 51 ===\n\n";

// Check survey questions
$result = $conn->query('SELECT * FROM survey_questions WHERE 1=1');
echo 'Total survey questions: ' . ($result ? $result->num_rows : 0) . "\n";

// Check survey responses
$result = $conn->query('SELECT * FROM survey_responses WHERE 1=1');
echo 'Total survey responses: ' . ($result ? $result->num_rows : 0) . "\n";

// Check survey templates
$result = $conn->query('SELECT * FROM survey_templates WHERE 1=1');
echo 'Total survey templates: ' . ($result ? $result->num_rows : 0) . "\n";

if ($result && $result->num_rows > 0) {
    echo "\nSurvey Templates:\n";
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  - ID: %d, Title: %s, Consultation: %s\n", 
            $row['id'] ?? '', 
            $row['title'] ?? '', 
            $row['consultation_id'] ?? 'N/A');
    }
}

// Check survey responses for consultations 50 and 51
echo "\n=== Survey Responses for Consultations 50-51 ===\n";
$result = $conn->query('SELECT * FROM survey_responses WHERE survey_id IN (SELECT id FROM survey_templates WHERE consultation_id IN (50, 51))');
if ($result) {
    echo 'Responses found: ' . $result->num_rows . "\n";
}

// Check uploads directory for survey files
$uploadDir = __DIR__ . '/uploads/documents';
$files = glob($uploadDir . '/*survey*');
echo "\nSurvey-related files on disk: " . count($files) . "\n";

// Check consultations table for survey references
echo "\n=== Checking consultations 50 and 51 ===\n";
$result = $conn->query('SELECT id, title, category, description FROM consultations WHERE id IN (50, 51)');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf("Consultation %d: %s (Category: %s)\n", $row['id'], $row['title'], $row['category']);
    }
}
?>
