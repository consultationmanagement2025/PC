<?php
echo "=== Manual Dompdf Installation Guide ===\n\n";

echo "Since Composer is not available, you can manually install Dompdf:\n\n";

echo "1. Download Dompdf from: https://github.com/dompdf/dompdf/releases\n";
echo "2. Extract the zip file\n";
echo "3. Copy the contents to: c:\\xampp\\htdocs\\CAP101\\PC\\vendor\\dompdf\\dompdf\n\n";

echo "Alternatively, you can use the following PowerShell command to download and extract:\n\n";

$ps_script = <<<'POWERSHELL'
# Download Dompdf
$url = "https://github.com/dompdf/dompdf/archive/refs/tags/v2.0.3.zip"
$output = "C:\xampp\htdocs\CAP101\PC\dompdf.zip"

# Download the file
Invoke-WebRequest -Uri $url -OutFile $output

# Extract to vendor directory
$extractPath = "C:\xampp\htdocs\CAP101\PC\vendor\dompdf"
if (!(Test-Path $extractPath)) {
    New-Item -ItemType Directory -Path $extractPath -Force
}

# Extract the zip
Expand-Archive -Path $output -DestinationPath $extractPath -Force

# Move files to correct location
$source = "C:\xampp\htdocs\CAP101\PC\vendor\dompdf\dompdf-2.0.3"
$dest = "C:\xampp\htdocs\CAP101\PC\vendor\dompdf\dompdf"
if (Test-Path $source) {
    Move-Item -Path "$source\*" -Destination $dest -Force
    Remove-Item -Path $source -Force
}

# Clean up
Remove-Item -Path $output -Force

Write-Host "Dompdf installed successfully!"
POWERSHELL;

echo "Save this as a .ps1 file and run it in PowerShell:\n\n";
echo $ps_script;

echo "\n\nAfter installation, the PDF generation will use Dompdf instead of the fallback,\n";
echo "which will provide much better formatting and spacing.\n";
?>
