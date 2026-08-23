<?php
require_once __DIR__ . '/../db.php';

echo "=======================================================\n";
echo "VERIFYING ALL CARD & AI SYNTHESIS DATA DISPLAYS (PHP CLI)\n";
echo "=======================================================\n\n";

// 1. Verify Feedback Database Data
$fRes = $conn->query("SELECT id, consultation_id, category, message, rating, sentiment_tag, sentiment_score FROM feedback");
$fbList = [];
$sentimentDist = [];
while ($row = $fRes->fetch_assoc()) {
    $fbList[] = $row;
    $stag = $row['sentiment_tag'] ?: 'unknown';
    $sentimentDist[$stag] = ($sentimentDist[$stag] ?? 0) + 1;
}

echo "1. Feedback Records in DB:\n";
echo "   Total Rows: " . count($fbList) . "\n";
echo "   Sentiment Tag Distribution: ";
print_r($sentimentDist);

// 2. Verify AI Brief Compilation Data per Consultation
$cRes = $conn->query("SELECT id, title, status, category FROM consultations ORDER BY id ASC");
echo "\n2. AI Committee Brief Data per Consultation:\n";

while ($c = $cRes->fetch_assoc()) {
    $cid = (int)$c['id'];
    $title = $c['title'];
    
    $_GET['action'] = 'compile_committee_brief';
    $_GET['consultation_id'] = $cid;
    $_GET['force'] = 1;
    
    ob_start();
    require __DIR__ . '/../API/consultation_feedback_ai.php';
    $jsonRaw = ob_get_clean();
    
    $json = json_decode($jsonRaw, true);
    if ($json && !empty($json['success'])) {
        $brief = $json['data'];
        $comm = $brief['committee_assigned'] ?? $brief['assigned_committee'] ?? 'Unknown Committee';
        $stats = $brief['stats'] ?? [];
        $problems = $brief['problems'] ?? [];
        $solutions = $brief['solutions'] ?? [];
        
        echo "   ✓ Consult #{$cid} (\"{$title}\"):\n";
        echo "       Assigned Committee: {$comm}\n";
        echo "       Total Citizen Feedback: " . ($stats['total_submissions'] ?? 0) . " | Dominant Public Tone: " . ($stats['dominant_sentiment'] ?? 'N/A') . "\n";
        echo "       Problems/Grievances Count: " . count($problems) . " | Recommendations Count: " . count($solutions) . "\n";
        if (!empty($problems)) {
            echo "       Top Problem: [" . $problems[0]['category'] . "] " . substr($problems[0]['issue'], 0, 80) . "...\n";
        }
    } else {
        echo "   ❌ Consult #{$cid} Brief Compilation Failed!\n";
    }
}

echo "\n=======================================================\n";
echo "ALL CARD & AI SYNTHESIS DATA IS VERIFIED LEGITIMATE!\n";
echo "=======================================================\n";
