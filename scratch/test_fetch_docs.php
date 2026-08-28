<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/documents.php';

$res = getConsultationDocumentsForAdminList(5, 0);
echo "Fetched " . count($res) . " docs from getConsultationDocumentsForAdminList.\n";
if (!empty($res[0])) {
    echo "First doc ID: " . $res[0]['id'] . " | Title: " . $res[0]['title'] . " | Downloads: " . $res[0]['downloads'] . "\n";
}
