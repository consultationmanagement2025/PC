<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../'));
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, '.git') !== false || strpos($path, 'node_modules') !== false || strpos($path, 'vendor') !== false) continue;
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array($ext, ['php', 'js', 'json', 'sql', 'html', 'txt', 'md'])) continue;
    
    $content = file_get_contents($path);
    if (stripos($content, 'Legal Assistance') !== false || stripos($content, 'Community Legal') !== false) {
        echo "Found in: $path\n";
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (stripos($line, 'Legal Assistance') !== false || stripos($line, 'Community Legal') !== false) {
                echo "   Line " . ($i + 1) . ": " . trim(substr($line, 0, 120)) . "\n";
            }
        }
    }
}
