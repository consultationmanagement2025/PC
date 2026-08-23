<?php
$content = file_get_contents(__DIR__ . '/../admin-side/app-features.js');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, 'AppData.consultations =') !== false || strpos($line, 'AppData.consultations.push') !== false || strpos($line, 'fetchConsultations') !== false || strpos($line, 'loadConsultations') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
