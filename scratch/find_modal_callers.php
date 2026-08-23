<?php
$content = file_get_contents(__DIR__ . '/../admin-side/app-features.js');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, 'renderAiCommitteeBriefModalHtml') !== false || strpos($line, 'pfq-ai-brief-modal') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
