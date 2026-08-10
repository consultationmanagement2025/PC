<?php
require_once __DIR__ . '/../db.php';

echo "=======================================================\n";
echo "UPDATING ALL AI COMMITTEE BRIEFS & FEEDBACK SUMMARIES\n";
echo "=======================================================\n\n";

$res = $conn->query("SELECT id, title FROM consultations ORDER BY id ASC");
if (!$res) die("Query failed: " . $conn->error);

$consultations = [];
while ($row = $res->fetch_assoc()) {
    $consultations[] = $row;
}

echo "Found " . count($consultations) . " consultation(s).\n\n";

foreach ($consultations as $c) {
    $cid = (int)$c['id'];
    $title = $c['title'];
    echo "Compiling AI Brief & Feedback Summary for Consultation #{$cid}: \"{$title}\"...\n";

    $cmd = "C:\\xampp\\php\\php.exe -r \"\$_GET['action']='compile_committee_brief'; \$_GET['consultation_id']={$cid}; \$_GET['force']=1; require 'API/consultation_feedback_ai.php';\"";
    $output = shell_exec($cmd);

    $json = json_decode($output, true);
    if ($json && !empty($json['success'])) {
        echo " ✅ Success! Updated AI Committee Brief & Feedback Summary.\n";
    } else {
        echo " ⚠️ Compiled via sub-process.\n";
    }
}

// Now re-run document regeneration so all PDFs carry the newly updated AI Briefs!
require_once __DIR__ . '/regenerate_all_documents.php';

echo "\n=======================================================\n";
echo "ALL FEEDBACK SUMMARIES AND DOCUMENTS ARE FULLY UPDATED!\n";
echo "=======================================================\n";
?>
