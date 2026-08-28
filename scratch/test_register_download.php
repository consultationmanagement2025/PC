<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/documents.php';

$docId = 77;

// Register download
$stmt = $conn->prepare("UPDATE documents SET downloads = downloads + 1 WHERE id = ?");
$stmt->bind_param('i', $docId);
$stmt->execute();
$stmt->close();

$res = getConsultationDocumentsForAdminList(5, 0);
echo "After download registration:\n";
foreach ($res as $d) {
    if ((int)$d['id'] === $docId) {
        echo "Doc #" . $d['id'] . " | Title: " . $d['title'] . " | New Downloads: " . $d['downloads'] . "\n";
    }
}
