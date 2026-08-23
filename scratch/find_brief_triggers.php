<?php
$content = file_get_contents(__DIR__ . '/../admin-side/app-features.js');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, 'pfpShowAiCommitteeBriefModal') !== false || strpos($line, 'compile_committee_brief') !== false || strpos($line, 'pfpGenerateAIBrief') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
