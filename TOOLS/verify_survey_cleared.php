<?php
require_once __DIR__ . '/../db.php';

echo "=== Verifying Survey Fields Cleared ===\n\n";

$result = $conn->query("SELECT id, title, response_mode, survey_question, survey_option_a, survey_option_b FROM consultations WHERE id = 1");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Consultation ID: {$row['id']}\n";
    echo "Title: {$row['title']}\n";
    echo "Response Mode: " . ($row['response_mode'] ?? 'NULL') . "\n";
    echo "Survey Question: " . var_export($row['survey_question'], true) . "\n";
    echo "Survey Option A: " . var_export($row['survey_option_a'], true) . "\n";
    echo "Survey Option B: " . var_export($row['survey_option_b'], true) . "\n";
    
    // Check if empty
    $has_data = !empty($row['survey_question']) || !empty($row['survey_option_a']) || !empty($row['survey_option_b']);
    echo "\nHas survey data: " . ($has_data ? 'YES' : 'NO') . "\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
