<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, category, status, document_status, committee_assigned, created_at FROM consultations ORDER BY id DESC LIMIT 20");
while ($row = $res->fetch_assoc()) {
    $cid = (int)$row['id'];
    
    // Count feedback/posts/votes
    $fbCount = 0;
    $fbRes = $conn->query("SELECT COUNT(*) as cnt FROM feedback WHERE consultation_id = $cid");
    if ($fbRes && $r = $fbRes->fetch_assoc()) $fbCount = (int)$r['cnt'];

    $postsCount = 0;
    $pRes = $conn->query("SELECT COUNT(*) as cnt FROM posts WHERE consultation_id = $cid");
    if ($pRes && $r = $pRes->fetch_assoc()) $postsCount = (int)$r['cnt'];

    $votesCount = 0;
    $vRes = $conn->query("SELECT COUNT(*) as cnt FROM consultation_votes WHERE consultation_id = $cid");
    if ($vRes && $r = $vRes->fetch_assoc()) $votesCount = (int)$r['cnt'];

    echo "ID: #{$row['id']} | Title: {$row['title']} | Status: {$row['status']} | DocStatus: {$row['document_status']} | Committee: '{$row['committee_assigned']}' | Submissions: Feedback=$fbCount, Posts=$postsCount, Votes=$votesCount\n";
}
