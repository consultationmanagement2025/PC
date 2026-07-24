<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';

echo "=== Debugging PDF Generation ===\n\n";

// Check if Dompdf is available
echo "1. Checking Dompdf availability:\n";
if (class_exists('\Dompdf\Dompdf')) {
    echo "   ✓ Dompdf is available\n";
} else {
    echo "   ✗ Dompdf is NOT available - will use fallback\n";
}

// Get latest consultation
$result = $conn->query("SELECT * FROM consultations ORDER BY created_at DESC LIMIT 1");
if (!$result || $result->num_rows === 0) {
    die("No consultations found.\n");
}

$consultation = $result->fetch_assoc();
$consultation_id = $consultation['id'];
echo "\n2. Consultation ID: $consultation_id\n";
echo "   Title: " . $consultation['title'] . "\n";
echo "   Description length: " . strlen($consultation['description']) . " characters\n";
echo "   Image path: " . ($consultation['image_path'] ?? 'none') . "\n";

// Generate HTML without creating PDF
echo "\n3. Generating HTML content...\n";
$_SESSION['user_id'] = 1;

// Manually build the HTML to see what's being generated
$title = htmlspecialchars($consultation['title'] ?? 'Consultation');
$description = htmlspecialchars($consultation['description'] ?? '');
$created = htmlspecialchars($consultation['created_at'] ?? '');
$tracking = htmlspecialchars($consultation['tracking_number'] ?? '');
$category = htmlspecialchars($consultation['category'] ?? 'General');
$status = htmlspecialchars($consultation['status'] ?? 'Pending');
$image_path = $consultation['image_path'] ?? '';

$adminName = 'Mr. Jojo';

$html = "<!doctype html><html><head><meta charset=\"utf-8\"><title>" . $title . "</title>";
$html .= "<style>";
$html .= "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12pt; color: #333; line-height: 1.8; margin: 0; padding: 40px; }";
$html .= "header { border-bottom: 3px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 30px; text-align: center; }";
$html .= ".brand-logo { display: block; margin: 0 auto 12px auto; max-width: 200px; height: auto; }";
$html .= ".city-seal { text-align: center; margin-bottom: 10px; font-size: 14pt; font-weight: 700; color: #1f2937; }";
$html .= ".title { text-align: center; font-size: 20pt; font-weight: 800; color: #1f2937; margin: 12px 0; }";
$html .= ".subtitle { text-align: center; font-size: 12pt; color: #6b7280; margin-bottom: 20px; }";
$html .= "section { margin-bottom: 24px; }";
$html .= ".section-title { font-size: 14pt; font-weight: 700; color: #ffffff; background: #1f2937; padding: 12px 16px; margin: 20px 0 12px 0; display:block; border-radius: 4px; }";
$html .= ".meta-row { display: flex; margin-bottom: 12px; align-items: baseline; }";
$html .= ".meta-label { font-weight: 700; width: 180px; color: #1f2937; font-size: 11pt; }";
$html .= ".meta-value { flex: 1; color: #374151; font-size: 11pt; }";
$html .= ".divider { border-top: 2px solid #e5e7eb; margin: 24px 0; }";
$html .= ".description { background: #f9fafb; padding: 20px; border-left: 5px solid #2563eb; white-space: pre-wrap; font-size: 11pt; line-height: 1.8; border-radius: 4px; }";
$html .= ".footer { text-align: center; font-size: 10pt; color: #6b7280; margin-top: 32px; border-top: 2px solid #e5e7eb; padding-top: 16px; }";
$html .= "</style>";
$html .= "</head><body>";

$html .= "<header>";
$html .= "<div class='city-seal'>CITY OF VALENZUELA</div>";
$html .= "<div class='title'>Consultation Submission Summary</div>";
$html .= "<div class='subtitle'>Public Consultation Office</div>";
$html .= "</header>";

$html .= "<section>";
$html .= "<div class='meta-row'><div class='meta-label'>Reference Number:</div><div class='meta-value'>" . $tracking . "</div></div>";
$html .= "<div class='meta-row'><div class='meta-label'>Date Created:</div><div class='meta-value'>" . date('F j, Y g:i A', strtotime($created)) . "</div></div>";
$html .= "<div class='meta-row'><div class='meta-label'>Status:</div><div class='meta-value'>" . ucfirst($status) . "</div></div>";
$html .= "</section>";

$html .= "<div class='section-title'>Created By</div>";
$html .= "<section>";
$html .= "<div class='meta-row'><div class='meta-label'>Admin Name:</div><div class='meta-value'>" . $adminName . "</div></div>";
$html .= "</section>";

$html .= "<div class='section-title'>Consultation Details</div>";
$html .= "<section>";
$html .= "<div class='meta-row'><div class='meta-label'>Topic:</div><div class='meta-value'>" . $title . "</div></div>";
$html .= "<div class='meta-row'><div class='meta-label'>Category:</div><div class='meta-value'>" . $category . "</div></div>";
$html .= "</section>";

$html .= "<div class='section-title'>Description</div>";
$html .= "<div class='description'>" . $description . "</div>";

$html .= "<div class='footer'>";
$html .= "<p>This is an official record of your consultation submission to the City of Valenzuela.</p>";
$html .= "<p>Retain this document for your records. Reference Number: " . $tracking . "</p>";
$html .= "</div>";

$html .= "</body></html>";

echo "   HTML length: " . strlen($html) . " characters\n";
echo "   First 500 characters of HTML:\n";
echo substr($html, 0, 500) . "...\n";

// Save HTML to file for inspection
$html_debug_file = __DIR__ . '/../uploads/debug_html.html';
file_put_contents($html_debug_file, $html);
echo "\n   HTML saved to: $html_debug_file\n";

// Try to generate PDF with Dompdf if available
if (class_exists('\Dompdf\Dompdf')) {
    echo "\n4. Testing Dompdf PDF generation...\n";
    try {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $dompdf->render();
        $output = $dompdf->output();
        
        $test_pdf = __DIR__ . '/../uploads/test_dompdf.pdf';
        file_put_contents($test_pdf, $output);
        echo "   ✓ PDF generated: " . filesize($test_pdf) . " bytes\n";
        echo "   Saved to: $test_pdf\n";
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
}
?>
