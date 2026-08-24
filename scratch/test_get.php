<?php
require_once __DIR__ . '/../DATABASE/consultations.php';

$items = getConsultations(null, 100, 0);
echo "Total items returned by getConsultations(): " . count($items) . "\n\n";

foreach ($items as $c) {
    echo "ID: {$c['id']} | Title: " . substr($c['title'], 0, 30) . " | Status: '{$c['status']}' | Type: {$c['type']}\n";
}
