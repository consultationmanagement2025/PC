<?php
require_once 'admin-side/db.php';
$res1 = $conn->query("SELECT COUNT(*) as cnt FROM documents");
$docCount = $res1 ? $res1->fetch_assoc()['cnt'] : 0;

$res2 = $conn->query("SELECT COUNT(*) as cnt FROM consultations WHERE file_url IS NOT NULL AND file_url != ''");
$attachedCount = $res2 ? $res2->fetch_assoc()['cnt'] : 0;

echo "Documents table count: " . $docCount . "\n";
echo "Consultations with file_url: " . $attachedCount . "\n";
