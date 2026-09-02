<?php
require_once 'db.php';
$conn = dbEnsureConnection();

echo "=== CHECKING FEEDBACK & VOTES & POSTS ===\n";

$res1 = $conn->query("SELECT COUNT(*) as cnt FROM `feedback`");
echo "feedback count: " . ($res1 ? $res1->fetch_assoc()['cnt'] : 'error') . "\n";

$res2 = $conn->query("SELECT COUNT(*) as cnt FROM `consultation_votes`");
echo "consultation_votes count: " . ($res2 ? $res2->fetch_assoc()['cnt'] : 'error') . "\n";

$res3 = $conn->query("SELECT COUNT(*) as cnt FROM `consultation_guest_votes`");
echo "consultation_guest_votes count: " . ($res3 ? $res3->fetch_assoc()['cnt'] : 'error') . "\n";

$res4 = $conn->query("SELECT COUNT(*) as cnt FROM `posts`");
echo "posts count: " . ($res4 ? $res4->fetch_assoc()['cnt'] : 'error') . "\n";

echo "\n--- FEEDBACK SAMPLES ---\n";
if ($res1) {
    $fRes = $conn->query("SELECT * FROM `feedback` LIMIT 10");
    while ($row = $fRes->fetch_assoc()) {
        print_r($row);
    }
}

echo "\n--- VOTES SAMPLES ---\n";
if ($res2) {
    $vRes = $conn->query("SELECT * FROM `consultation_votes` LIMIT 10");
    while ($row = $vRes->fetch_assoc()) {
        print_r($row);
    }
}

echo "\n--- GUEST VOTES SAMPLES ---\n";
if ($res3) {
    $gvRes = $conn->query("SELECT * FROM `consultation_guest_votes` LIMIT 10");
    while ($row = $gvRes->fetch_assoc()) {
        print_r($row);
    }
}

echo "\n--- POSTS / COMMENTS SAMPLES ---\n";
if ($res4) {
    $pRes = $conn->query("SELECT * FROM `posts` LIMIT 10");
    while ($row = $pRes->fetch_assoc()) {
        print_r($row);
    }
}
?>