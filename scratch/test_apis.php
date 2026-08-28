<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin-side/DATABASE/consultations.php';
require_once __DIR__ . '/../admin-side/DATABASE/feedback.php';

echo "=== CONSULTATIONS API DIRECT TEST ===\n";
try {
    $consultations = getConsultations([], 200, 0);
    echo "Consultations count: " . count($consultations) . "\n";
} catch (Throwable $e) {
    echo "Consultations Error: " . $e->getMessage() . "\n";
}

echo "\n=== FEEDBACK API DIRECT TEST ===\n";
try {
    $feedback = getFeedback([], 200, 0);
    echo "Feedback count: " . count($feedback) . "\n";
} catch (Throwable $e) {
    echo "Feedback Error: " . $e->getMessage() . "\n";
}
