<?php
require_once __DIR__ . '/../db.php';

echo "=== ALL FEEDBACK ROWS IN DB ===\n";
$res = $conn->query("SELECT f.id, f.consultation_id, c.title as consultation_title, f.guest_name, f.category, f.message, f.rating, f.sentiment_tag, f.sentiment_score FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id ORDER BY f.consultation_id, f.id");

while ($row = $res->fetch_assoc()) {
    echo "FDBK #{$row['id']} [Consult #{$row['consultation_id']} - {$row['consultation_title']}]\n";
    echo "  Author: {$row['guest_name']} | Rating: {$row['rating']} | Category: {$row['category']} | SentimentTag: '{$row['sentiment_tag']}' | Score: {$row['sentiment_score']}\n";
    echo "  Message: {$row['message']}\n";
    echo "--------------------------------------------------\n";
}
