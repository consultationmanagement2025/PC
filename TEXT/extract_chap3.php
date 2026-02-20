<?php
$docx = __DIR__ . DIRECTORY_SEPARATOR . 'chap-3.docx';
$out = __DIR__ . DIRECTORY_SEPARATOR . 'chap-3-extracted.txt';

if (!file_exists($docx)) {
    fwrite(STDERR, "Missing file: {$docx}\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "Failed to open DOCX\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

if ($xml === false) {
    fwrite(STDERR, "Missing word/document.xml in DOCX\n");
    exit(1);
}

$xml = preg_replace('/<w:tab[^>]*\/>/i', "\t", $xml);
$xml = preg_replace('/<\/w:p>/i', "\n", $xml);
$text = preg_replace('/<[^>]+>/', '', $xml);
$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$text = preg_replace("/\n{3,}/", "\n\n", $text);

file_put_contents($out, $text);
echo $out;
