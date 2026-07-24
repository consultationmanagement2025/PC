<?php
echo "=== Attempting to install Dompdf via Composer ===\n\n";

// Check if composer is available
$composer_check = shell_exec('composer --version 2>&1');
if (strpos($composer_check, 'Composer') !== false) {
    echo "✓ Composer is available\n";
    echo "Version: $composer_check\n\n";
    
    echo "Installing Dompdf...\n";
    $output = shell_exec('composer require dompdf/dompdf 2>&1');
    echo $output;
    
    echo "\n\nChecking if Dompdf is now available...\n";
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('\Dompdf\Dompdf')) {
        echo "✓ Dompdf is now available!\n";
    } else {
        echo "✗ Dompdf still not available\n";
    }
} else {
    echo "✗ Composer is not available\n";
    echo "Please install Composer first: https://getcomposer.org/download/\n";
    echo "Then run: composer require dompdf/dompdf\n";
}
?>
