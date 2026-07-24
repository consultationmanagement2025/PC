<?php
require_once __DIR__ . '/../db.php';

echo "=== Checking Consultation Response Mode ===\n\n";

// Check the latest consultation
$result = $conn->query("SELECT id, title, response_mode, survey_question, survey_option_a, survey_option_b FROM consultations ORDER BY created_at DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "Recent consultations:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}\n";
        echo "Title: {$row['title']}\n";
        echo "Response Mode: " . ($row['response_mode'] ?? 'NULL') . "\n";
        echo "Survey Question: " . ($row['survey_question'] ?? 'NULL') . "\n";
        echo "Survey Option A: " . ($row['survey_option_a'] ?? 'NULL') . "\n";
        echo "Survey Option B: " . ($row['survey_option_b'] ?? 'NULL') . "\n";
        echo "---\n";
    }
} else {
    echo "No consultations found.\n";
}
?>
