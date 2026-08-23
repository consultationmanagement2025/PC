<?php
require_once __DIR__ . '/../db.php';
$stmt = $conn->prepare("INSERT INTO feedback (
    guest_name, guest_email, guest_phone, consultation_id, rating, category, message, 
    sentiment_tag, sentiment_score, topic_tags, tracking_token, feedback_hash, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reviewed')");

if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n";
} else {
    echo "Prepare succeeded!\n";
    $name = 'Maria Santos';
    $email = 'maria@example.com';
    $phone = '09171234567';
    $cid = 1;
    $rating = 5;
    $category = 'Waste Management';
    $msg = 'Great program!';
    $tag = 'positive';
    $score = 2.5;
    $topicTags = '["Environment"]';
    $token = 'FDBK-TEST-123';
    $hash = 'hash12345';
    $stmt->bind_param('sssisssdssss', $name, $email, $phone, $cid, $rating, $category, $msg, $tag, $score, $topicTags, $token, $hash);
    if ($stmt->execute()) {
        echo "Insert succeeded! Insert ID: " . $stmt->insert_id . "\n";
    } else {
        echo "Execute failed: " . $stmt->error . "\n";
    }
}
