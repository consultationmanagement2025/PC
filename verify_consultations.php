<?php
require_once 'db.php';
require_once 'DATABASE/consultations.php';

initializeConsultationsTable();

// Get active consultations
$result = $conn->query("SELECT id, title, category, start_date, end_date, status FROM consultations WHERE status='active' ORDER BY end_date DESC");

if ($result && $result->num_rows > 0) {
    echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 8px; font-family: monospace;'>";
    echo "ACTIVE CONSULTATIONS IN DATABASE:\n";
    echo "================================\n\n";
    while ($row = $result->fetch_assoc()) {
        $days_left = ceil((strtotime($row['end_date']) - time()) / (60 * 60 * 24));
        echo "ID: " . $row['id'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Category: " . $row['category'] . "\n";
        echo "Start: " . $row['start_date'] . "\n";
        echo "End: " . $row['end_date'] . "\n";
        echo "Days Left: $days_left days\n";
        echo "Status: " . $row['status'] . "\n";
        echo "---\n";
    }
    echo "\n✓ Real-time calculation confirmed: Days_left = ceil((strtotime(end_date) - time()) / 86400)\n";
    echo "</pre>";
} else {
    echo "<div style='background: #ffe5e5; padding: 20px; border-radius: 8px;'>";
    echo "<strong>No active consultations found in database.</strong><br>";
    echo "The 'Upcoming Consultations' section will only show if there are consultations with status='active'.";
    echo "</div>";
}
?>
