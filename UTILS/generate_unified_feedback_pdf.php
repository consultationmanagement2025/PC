<?php
function generateUnifiedFeedbackPdfDoc($categories, $mergeId, $timestamp, $userName = 'System Admin') {
    $uploadDir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $filename = 'Unified_Citizen_Feedback_Summary_' . $mergeId . '.pdf';
    $filePath = $uploadDir . $filename;

    $exactEndingText = "Compiled automatically by PCM‑AI Integration System — synchronized with PHMS and Consultation Management modules. Merged feedback sets are locked from re‑analysis to preserve data integrity.";

    // Option A: Use Dompdf if available
    if (class_exists('\\Dompdf\\Dompdf')) {
        try {
            $html = buildUnifiedPdfHtml($categories, $mergeId, $timestamp, $userName, $exactEndingText);
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            $dompdf->render();
            file_put_contents($filePath, $dompdf->output());

            return [
                'success' => true,
                'pdf_filename' => $filename,
                'pdf_path' => 'uploads/documents/' . $filename,
                'pdf_url' => 'download-document.php?file=' . urlencode($filename)
            ];
        } catch (Exception $e) {
            error_log("Dompdf failed for unified feedback PDF: " . $e->getMessage());
        }
    }

    // Option B: Native Pure-PHP PDF Stream Generator
    $pdfStream = generateNativeUnifiedPdfStream($categories, $mergeId, $timestamp, $userName, $exactEndingText);
    file_put_contents($filePath, $pdfStream);

    return [
        'success' => true,
        'pdf_filename' => $filename,
        'pdf_path' => 'uploads/documents/' . $filename,
        'pdf_url' => 'download-document.php?file=' . urlencode($filename)
    ];
}

function buildUnifiedPdfHtml($categories, $mergeId, $timestamp, $userName, $exactEndingText) {
    $formattedDate = date('F j, Y, g:i A', strtotime($timestamp));

    $categoriesHtml = '';
    foreach ($categories as $catIdx => $cat) {
        $catName = htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8');
        $totalEntries = (int)$cat['total_entries'];

        $consultationsHtml = '';
        foreach ($cat['consultations'] as $c) {
            $title = htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8');
            $source = htmlspecialchars($c['source'], ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars($c['date'], ENT_QUOTES, 'UTF-8');
            $count = (int)$c['entries_count'];
            $rating = number_format((float)$c['avg_rating'], 1);
            $sentiment = strtoupper(htmlspecialchars($c['dominant_sentiment'], ENT_QUOTES, 'UTF-8'));

            $insightsBullets = '';
            foreach ($c['summarized_insights'] as $msg) {
                $insightsBullets .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
            }

            $consultationsHtml .= "
                <div style='margin-bottom: 12px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;'>
                    <div style='font-weight: bold; font-size: 13px; color: #0f172a; margin-bottom: 4px;'>
                        &bull; {$title} <span style='font-size: 10px; padding: 2px 6px; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-weight: normal;'>{$source}</span>
                    </div>
                    <div style='font-size: 11px; color: #475569; margin-bottom: 6px;'>
                        <strong>Date:</strong> {$date} &nbsp;|&nbsp; 
                        <strong>Feedback Entries:</strong> {$count} &nbsp;|&nbsp; 
                        <strong>Average Rating:</strong> &#9733; {$rating} &nbsp;|&nbsp; 
                        <strong>Public Tone:</strong> <span style='font-weight: bold; color: " . ($sentiment === 'POSITIVE' ? '#16a34a' : ($sentiment === 'NEGATIVE' ? '#dc2626' : '#d97706')) . ";'>{$sentiment}</span>
                    </div>
                    <div style='font-size: 11px; color: #334155;'>
                        <strong style='color: #1e293b;'>Key Summarized Insights & Sentiments:</strong>
                        <ul style='margin: 4px 0 0 18px; padding: 0;'>{$insightsBullets}</ul>
                    </div>
                </div>
            ";
        }

        $categoriesHtml .= "
            <div style='margin-bottom: 20px;'>
                <div style='background: #1e293b; color: #ffffff; padding: 8px 12px; font-size: 14px; font-weight: bold; border-radius: 4px;'>
                    " . ($catIdx + 1) . ". {$catName} ({$totalEntries} Feedback Entries)
                </div>
                <div style='padding-top: 10px;'>
                    {$consultationsHtml}
                </div>
            </div>
        ";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Unified Citizen Feedback Summary</title>
        <style>
            body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 20px; line-height: 1.5; }
            .header { border-bottom: 2px solid #b91c1c; padding-bottom: 10px; margin-bottom: 20px; }
            .title { font-size: 20px; font-weight: bold; color: #991b1b; margin: 0; }
            .subtitle { font-size: 12px; color: #64748b; margin-top: 4px; }
            .meta-box { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; font-size: 11px; }
            .footer { margin-top: 30px; padding: 12px; background: #fffbe6; border: 1px solid #ffe58f; border-radius: 6px; font-size: 10px; color: #78350f; font-weight: bold; text-align: center; }
        </style>
    </head>
    <body>
        <div class='header'>
            <div style='font-size: 10px; font-weight: bold; color: #64748b; text-transform: uppercase;'>City Government of Valenzuela &bull; Public Consultation & Hearing System</div>
            <h1 class='title'>Unified Citizen Feedback Summary</h1>
            <div class='subtitle'>Cross-System Synchronized Feedback Analysis & Audit Data Lock Record</div>
        </div>

        <div class='meta-box'>
            <strong>Merge ID:</strong> {$mergeId} &nbsp;|&nbsp; 
            <strong>Compilation Timestamp:</strong> {$formattedDate} &nbsp;|&nbsp; 
            <strong>Compiled By:</strong> " . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . "
        </div>

        {$categoriesHtml}

        <div class='footer'>
            {$exactEndingText}
        </div>
    </body>
    </html>
    ";
}

function generateNativeUnifiedPdfStream($categories, $mergeId, $timestamp, $userName, $exactEndingText) {
    $lines = [];
    $lines[] = "CITY GOVERNMENT OF VALENZUELA";
    $lines[] = "UNIFIED CITIZEN FEEDBACK SUMMARY";
    $lines[] = "Synchronized PCMS & PHMS Feedback Analysis & Audit Data Lock";
    $lines[] = "----------------------------------------------------------------------------------------------------";
    $lines[] = "Merge ID: " . $mergeId;
    $lines[] = "Compilation Timestamp: " . date('F j, Y, g:i A', strtotime($timestamp));
    $lines[] = "Compiled By: " . $userName;
    $lines[] = "----------------------------------------------------------------------------------------------------";
    $lines[] = "";

    foreach ($categories as $catIdx => $cat) {
        $lines[] = "CATEGORY " . ($catIdx + 1) . ": " . strtoupper($cat['category_name']) . " (" . $cat['total_entries'] . " Entries)";
        $lines[] = "====================================================================================================";

        foreach ($cat['consultations'] as $c) {
            $lines[] = "  * " . $c['title'] . " [" . $c['source'] . "]";
            $lines[] = "    Date: " . $c['date'] . " | Entries: " . $c['entries_count'] . " | Avg Rating: " . number_format($c['avg_rating'], 1) . " | Tone: " . strtoupper($c['dominant_sentiment']);
            $lines[] = "    Key Insights:";

            foreach ($c['summarized_insights'] as $msg) {
                // Word wrap bullet text
                $wrapped = wordwrap("- " . $msg, 85, "\n      ");
                $lines[] = "      " . $wrapped;
            }
            $lines[] = "";
        }
        $lines[] = "";
    }

    $lines[] = "----------------------------------------------------------------------------------------------------";
    $lines[] = wordwrap($exactEndingText, 90, "\n");
    $lines[] = "----------------------------------------------------------------------------------------------------";

    // Build pure PDF 1.4 binary stream
    $pdf = "%PDF-1.4\n";
    $pdf .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
    $pdf .= "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
    $pdf .= "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n";

    // Build Content Stream
    $content = "BT\n/F1 9 Tf\n54 740 Td\n14 TL\n";
    
    foreach ($lines as $idx => $line) {
        // Sanitize characters for Type1 Helvetica PDF font
        $clean = preg_replace('/[^\x20-\x7E]/', ' ', $line);
        $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
        
        if ($idx === 1) { // Title font
            $content .= "/F1 14 Tf\n({$clean}) Tj\nT*\n/F1 9 Tf\n";
        } else {
            $content .= "({$clean}) Tj\nT*\n";
        }
    }
    $content .= "ET\n";

    $pdf .= "4 0 obj\n<<\n/Length " . strlen($content) . "\n>>\nstream\n" . $content . "\nendstream\nendobj\n";
    $pdf .= "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    
    for ($i = 1; $i <= 5; $i++) {
        $pos = strpos($pdf, "$i 0 obj");
        $pdf .= sprintf("%010d 00000 n \n", $pos);
    }

    $pdf .= "trailer\n<<\n/Size 6\n/Root 1 0 R\n>>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}
