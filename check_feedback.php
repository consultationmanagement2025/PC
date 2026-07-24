<?php
require 'db.php';

echo "=== Feedback Data for Consultations 50-51 ===\n";

// Check feedback
$result = $conn->query('SELECT COUNT(*) as cnt FROM feedback WHERE consultation_id IN (50,51)');
$row = $result->fetch_assoc();
echo 'Feedback records: ' . $row['cnt'] . "\n";

// Check feedback details
$result = $conn->query('SELECT id, consultation_id, user_name, rating, created_at FROM feedback WHERE consultation_id IN (50,51) ORDER BY created_at DESC');
if ($result && $result->num_rows > 0) {
    echo "\nFeedback Details:\n";
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  Feedback ID %d: Consultation %d, Rating: %s, User: %s\n",
            $row['id'], $row['consultation_id'], $row['rating'] ?? 'N/A', $row['user_name']);
    }
}

// Check all files related to consultations 50 and 51
echo "\n=== All Files for Consultations 50-51 ===\n";
$uploadDir = __DIR__ . '/uploads/documents';
$files50 = glob($uploadDir . '/CONSULT-000050*');
$files51 = glob($uploadDir . '/CONSULT-000051*');
$files50_new = glob($uploadDir . '/consultation_summary_50*');
$files51_new = glob($uploadDir . '/consultation_summary_51*');

$allFiles = array_merge($files50, $files51, $files50_new, $files51_new);
$allFiles = array_unique($allFiles);

foreach ($allFiles as $file) {
    $size = filesize($file);
    echo sprintf("  - %s (%d bytes)\n", basename($file), $size);
}

echo "\nTotal files: " . count($allFiles) . "\n";
?>
