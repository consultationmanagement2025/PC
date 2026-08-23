<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, title, category FROM consultations");
while ($r = $res->fetch_assoc()) {
    $cid = $r['id'];
    $cmd = "C:\\xampp\\php\\php.exe -r \"\$_GET['action']='compile_committee_brief'; \$_GET['consultation_id']={$cid}; \$_GET['force']=1; require 'API/consultation_feedback_ai.php';\"";
    $output = shell_exec($cmd);
    $json = json_decode($output, true);
    if ($json && !empty($json['success'])) {
        $data = $json['data'];
        $tot = $data['stats']['total_submissions'] ?? 0;
        $probCount = count($data['problems'] ?? []);
        $solCount = count($data['solutions'] ?? []);
        $committee = $data['committee_assigned'] ?? $data['assigned_committee'] ?? 'N/A';
        echo "Consultation #{$cid} ('{$r['title']}'): Total FB: {$tot} | Problems: {$probCount} | Solutions: {$solCount} | Committee: {$committee}\n";
    } else {
        echo "Consultation #{$cid} ('{$r['title']}'): FAILED -> " . substr($output, 0, 100) . "\n";
    }
}
