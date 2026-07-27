<?php
/**
 * generate_consultation_documents.php
 *
 * Utility to generate consultation summary documents (PDF/DOCX) and
 * register them in the Document Management (`documents`) table.
 *
 * Behavior:
 * - Attempts to use Dompdf if available to create PDF.
 * - Attempts to use PhpWord if available to create DOCX.
 * - If libraries are not installed, generation for that format is skipped.
 * - Records created files in `uploads/documents/` and inserts rows into `documents`.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/document-management.php';

function resolveConsultationImagePathForPDF(string $rawPath): ?string {
    $raw = trim((string)($rawPath ?? ''));
    if ($raw === '') {
        return null;
    }

    // Try direct path first
    if (file_exists(__DIR__ . '/../' . $raw)) {
        return $raw;
    }

    // Try common path variations
    $candidates = [
        $raw,
        'ASSETS/images/consultations/' . basename($raw),
        'images/consultations/' . basename($raw),
        'uploads/consultations/' . basename($raw),
        '../' . $raw,
    ];

    foreach ($candidates as $candidate) {
        if (file_exists(__DIR__ . '/../' . $candidate)) {
            return $candidate;
        }
    }

    return null;
}

function generateConsultationDocuments(int $consultation_id, array $options = []) : array {
    global $conn;
    $options = array_merge([
        'pdf' => true,
        'docx' => false,
        'created_by' => $_SESSION['user_id'] ?? 0
    ], $options);

    initializeDocumentsTable();

    $consultation_id = (int)$consultation_id;
    $stmt = $conn->prepare("SELECT * FROM consultations WHERE id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Database error: ' . $conn->error);
    $stmt->bind_param('i', $consultation_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) throw new Exception('Consultation not found');

    // Build professional HTML summary for the consultation
    $title = htmlspecialchars($row['title'] ?? 'Consultation');
    $user_name = htmlspecialchars($row['user_name'] ?? 'Anonymous');
    $user_email = htmlspecialchars($row['user_email'] ?? '');
    $created = htmlspecialchars($row['created_at'] ?? '');
    $tracking = htmlspecialchars($row['tracking_number'] ?? '');
    $description = htmlspecialchars($row['description'] ?? '');
    $category = htmlspecialchars($row['category'] ?? 'General');
    $status = htmlspecialchars($row['status'] ?? 'Pending');
    $image_path = $row['image_path'] ?? '';

    // Determine whether this document was created by an admin/staff account and get admin name
    $isAdminCreated = false;
    $adminName = 'Mr. Jojo'; // Default admin name
    $createdBy = (int)($options['created_by'] ?? 0);
    if ($createdBy > 0) {
        $uStmt = $conn->prepare("SELECT role, name FROM users WHERE id = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param('i', $createdBy);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            $uRow = $uRes ? $uRes->fetch_assoc() : null;
            $uStmt->close();
            if ($uRow && isset($uRow['role'])) {
                $role = strtolower(trim((string)$uRow['role']));
                if (in_array($role, ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'resource person', 'resource_person'], true)) {
                    $isAdminCreated = true;
                    if (!empty($uRow['name'])) {
                        $adminName = htmlspecialchars($uRow['name']);
                    }
                }
            }
        }
    }

    $logoDataUri = '';
    $logoPath = __DIR__ . '/../images/valenzuela-logo.png';
    if (!is_file($logoPath)) $logoPath = __DIR__ . '/../images/logo.webp';
    if (is_file($logoPath)) {
        $mime = mime_content_type($logoPath) ?: 'image/png';
        $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    $imageDataUri = '';
    if (!empty($image_path)) {
        $resolved_image = resolveConsultationImagePathForPDF($image_path);
        if ($resolved_image && file_exists(__DIR__ . '/../' . $resolved_image)) {
            $mimeImg = mime_content_type(__DIR__ . '/../' . $resolved_image) ?: 'image/png';
            $imageDataUri = 'data:' . $mimeImg . ';base64,' . base64_encode(file_get_contents(__DIR__ . '/../' . $resolved_image));
        }
    }

    $html = "<!doctype html><html><head><meta charset=\"utf-8\"><title>" . $title . "</title>";
    $html .= "<style>";
    $html .= "@page { margin: 25px 35px; }";
    $html .= "body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11pt; color: #1e293b; line-height: 1.6; margin: 0; padding: 0; }";
    $html .= ".header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border-bottom: 3px double #0033a0; padding-bottom: 12px; }";
    $html .= ".header-table td { vertical-align: middle; text-align: center; }";
    $html .= ".brand-logo { max-width: 85px; height: auto; margin-bottom: 6px; }";
    $html .= ".republic-text { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: 1px; }";
    $html .= ".city-text { font-size: 14pt; font-weight: 900; color: #0033a0; letter-spacing: 0.5px; margin: 2px 0; }";
    $html .= ".office-text { font-size: 10pt; font-weight: bold; color: #dc2626; text-transform: uppercase; margin-bottom: 4px; }";
    $html .= ".doc-title { font-size: 15pt; font-weight: 800; color: #0f172a; margin-top: 8px; text-transform: uppercase; }";
    $html .= ".section-banner { font-size: 10.5pt; font-weight: bold; color: #ffffff; background-color: #0033a0; padding: 6px 12px; margin: 16px 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 3px; }";
    $html .= ".meta-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }";
    $html .= ".meta-table td { padding: 8px 12px; border: 1px solid #cbd5e1; font-size: 10.5pt; vertical-align: top; }";
    $html .= ".meta-table .label { width: 28%; font-weight: bold; background-color: #f1f5f9; color: #334155; }";
    $html .= ".meta-table .value { width: 72%; color: #0f172a; }";
    $html .= ".meta-table .code-val { font-family: monospace; font-weight: bold; color: #0033a0; font-size: 11pt; }";
    $html .= ".description-box { background-color: #f8fafc; border: 1px solid #cbd5e1; border-left: 5px solid #0033a0; padding: 15px; font-size: 10.5pt; line-height: 1.7; color: #334155; white-space: pre-wrap; border-radius: 3px; margin-bottom: 20px; }";
    $html .= ".image-container { text-align: center; margin: 15px 0; }";
    $html .= ".image-container img { max-width: 95%; max-height: 380px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px; background: #fff; }";
    $html .= ".footer-table { width: 100%; border-collapse: collapse; margin-top: 25px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 8.5pt; color: #64748b; text-align: center; }";
    $html .= "</style>";
    $html .= "</head><body>";
    
    // Header with official letterhead
    $html .= "<table class='header-table'><tr><td>";
    if ($logoDataUri) {
        $html .= "<img class='brand-logo' src='" . $logoDataUri . "' alt='Valenzuela Logo'/>";
    }
    $html .= "<div class='republic-text'>Republic of the Philippines</div>";
    $html .= "<div class='city-text'>CITY GOVERNMENT OF VALENZUELA</div>";
    $html .= "<div class='office-text'>Public Consultation & Legislative Office</div>";
    $html .= "<div class='doc-title'>Consultation Submission Summary</div>";
    $html .= "</td></tr></table>";
    
    // Section 1: Reference info
    $html .= "<div class='section-banner'>1. Reference & Filing Information</div>";
    $html .= "<table class='meta-table'>";
    $html .= "<tr><td class='label'>Tracking Reference Code:</td><td class='value code-val'>" . $tracking . "</td></tr>";
    $html .= "<tr><td class='label'>Date Submitted:</td><td class='value'>" . date('F j, Y \a\t g:i A', strtotime($created)) . "</td></tr>";
    $html .= "<tr><td class='label'>Status:</td><td class='value'><strong>" . strtoupper($status) . "</strong></td></tr>";
    $html .= "<tr><td class='label'>Submitted By:</td><td class='value'>" . $user_name . ($user_email ? " (" . $user_email . ")" : "") . "</td></tr>";
    if ($isAdminCreated) {
        $html .= "<tr><td class='label'>Processed By Admin:</td><td class='value'>" . $adminName . "</td></tr>";
    }
    $html .= "</table>";

    // Section 2: Consultation details
    $html .= "<div class='section-banner'>2. Consultation Topic & Category</div>";
    $html .= "<table class='meta-table'>";
    $html .= "<tr><td class='label'>Title / Ordinance Topic:</td><td class='value'><strong>" . $title . "</strong></td></tr>";
    $html .= "<tr><td class='label'>Category:</td><td class='value'>" . $category . "</td></tr>";
    $html .= "</table>";

    // Section 3: Description
    $html .= "<div class='section-banner'>3. Detailed Description & Rationale</div>";
    $html .= "<div class='description-box'>" . $description . "</div>";

    // Section 4: Image Attachment if present
    if (!empty($imageDataUri)) {
        $html .= "<div class='section-banner'>4. Supporting Graphic Attachment</div>";
        $html .= "<div class='image-container'>";
        $html .= "<img src='" . $imageDataUri . "' alt='Consultation Attachment'>";
        $html .= "</div>";
    }
    
    // Footer
    $html .= "<table class='footer-table'><tr><td>";
    $html .= "This is an official record generated by the Valenzuela City Public Consultation & Management System (PCMS).<br>";
    $html .= "Reference Code: <strong>" . $tracking . "</strong> • Document Generated: " . date('F j, Y \a\t g:i A');
    $html .= "</td></tr></table>";
    
    $html .= "</body></html>";

    $saved = [];
    $upload_dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $reference = generateDocumentReference($consultation_id);
    $basename_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($title, 0, 40));
    $timestamp = date('Y-m-d_H-i-s');

    // Attempt PDF generation using Dompdf if available
    if ($options['pdf'] && class_exists('\Dompdf\Dompdf')) {
        try {
            $pdfName = sprintf('%s_%s_%s.pdf', $reference, $basename_safe, $timestamp);
            $pdfPath = $upload_dir . $pdfName;
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            $dompdf->render();
            $output = $dompdf->output();
            file_put_contents($pdfPath, $output);

            $size = filesize($pdfPath);
            $stmt = $conn->prepare("INSERT INTO documents (consultation_id, reference_number, original_filename, stored_filename, file_type, file_size, uploaded_by, document_type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log('Document insert prepare failed (PDF): ' . $conn->error);
            } else {
                $orig = $title . ' - Summary.pdf';
                $mime = 'application/pdf';
                $user_id = null; // Set to null for auto-generated documents to avoid foreign key issues
                $docType = 'final_document';
                $size = (int)$size;
                $description = $row['title'];
                $stmt->bind_param('isssissss', $consultation_id, $reference, $orig, $pdfName, $mime, $size, $user_id, $docType, $description);
                if (!$stmt->execute()) {
                    error_log('Document insert execute failed (PDF): ' . $stmt->error);
                }
                $stmt->close();
            }

            $saved[] = ['type' => 'pdf', 'path' => 'uploads/documents/' . $pdfName, 'size' => $size];
        } catch (Throwable $e) {
            error_log('PDF generation failed (Dompdf): ' . $e->getMessage());
        }
    }

    // Fallback: Use native PHP PDF generator if Dompdf is not available
    if ($options['pdf'] && !class_exists('\Dompdf\Dompdf')) {
        try {
            require_once __DIR__ . '/pdf_generator.php';
            $pdfName = sprintf('%s_%s_%s.pdf', $reference, $basename_safe, $timestamp);
            $pdfPath = $upload_dir . $pdfName;
            
            $pdf_data = [
                'id' => $consultation_id,
                'name' => $user_name,
                'email' => $user_email,
                'phone' => '',
                'topic' => $title,
                'category' => $category,
                'department' => 'N/A',
                'description' => $description,
                'created_at' => $created,
                'tracking_number' => $tracking
            ];
            
            $pdf_generator = new ConsultationPDFGenerator($consultation_id);
            $ok = $pdf_generator->save($pdf_data, $pdfPath);
            
            if ($ok) {
                $size = filesize($pdfPath);
                $stmt = $conn->prepare("INSERT INTO documents (consultation_id, reference_number, original_filename, stored_filename, file_type, file_size, uploaded_by, document_type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    error_log('Document insert prepare failed (Fallback PDF): ' . $conn->error);
                } else {
                    $orig = $title . ' - Summary.pdf';
                    $mime = 'application/pdf';
                    $user_id = null; // Set to null for auto-generated documents to avoid foreign key issues
                    $docType = 'final_document';
                    $size = (int)$size;
                    $stmt->bind_param('isssissss', $consultation_id, $reference, $orig, $pdfName, $mime, $size, $user_id, $docType, $row['title']);
                    if (!$stmt->execute()) {
                        error_log('Document insert execute failed (Fallback PDF): ' . $stmt->error);
                    }
                    $stmt->close();
                }
                $saved[] = ['type' => 'pdf', 'path' => 'uploads/documents/' . $pdfName, 'size' => $size];
            } else {
                error_log('Fallback PDF generation failed for consultation ' . $consultation_id);
            }
        } catch (Throwable $e) {
            error_log('Fallback PDF generation failed: ' . $e->getMessage());
        }
    }


    // Attempt DOCX generation using PhpWord if available
    if ($options['docx'] && class_exists('\PhpOffice\PhpWord\PhpWord')) {
        try {
            $docxName = sprintf('%s_%s_%s.docx', $reference, $basename_safe, $timestamp);
            $docxPath = $upload_dir . $docxName;
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();
            $section->addText($title, ['bold' => true, 'size' => 14]);
            $section->addText('Submitted by: ' . $user_name . ' <' . $user_email . '>');
            if ($tracking) $section->addText('Tracking #: ' . $tracking);
            $section->addText('Submitted: ' . $created);
            $section->addTextBreak(1);
            // Add description lines
            $desc_plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $description));
            foreach (explode("\n", $desc_plain) as $line) {
                $section->addText(trim($line));
            }

            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($docxPath);

            $size = filesize($docxPath);
            $stmt = $conn->prepare("INSERT INTO documents (consultation_id, reference_number, original_filename, stored_filename, file_type, file_size, uploaded_by, document_type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log('Document insert prepare failed (DOCX): ' . $conn->error);
            } else {
                $orig = $title . ' - Summary.docx';
                $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                $user_id = (int)$options['created_by'];
                if ($user_id <= 0) $user_id = null;
                $docType = 'final_document';
                $size = (int)$size;
                $stmt->bind_param('isssissss', $consultation_id, $reference, $orig, $docxName, $mime, $size, $user_id, $docType, $row['title']);
                if (!$stmt->execute()) {
                    error_log('Document insert execute failed (DOCX): ' . $stmt->error);
                }
                $stmt->close();
            }

            $saved[] = ['type' => 'docx', 'path' => 'uploads/documents/' . $docxName, 'size' => $size];
        } catch (Throwable $e) {
            error_log('DOCX generation failed: ' . $e->getMessage());
        }
    }

    return $saved;
}

// Backwards-compatible function name
function generateConsultationSummaryDocuments(int $consultation_id, array $options = []) : array {
    return generateConsultationDocuments($consultation_id, $options);
}

?>
