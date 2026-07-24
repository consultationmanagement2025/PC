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
                if (in_array($role, ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'barangay staff', 'barangay_staff', 'barangay'], true)) {
                    $isAdminCreated = true;
                    if (!empty($uRow['name'])) {
                        $adminName = htmlspecialchars($uRow['name']);
                    }
                }
            }
        }
    }

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
    
    // Header with official branding (centered text heading)
    $html .= "<header>";
    // Embed Valenzuela logo for admin-created documents
    if ($isAdminCreated) {
        $logoPath = __DIR__ . '/../images/valenzuela-logo.png';
        if (!is_file($logoPath)) $logoPath = __DIR__ . '/../images/logo.webp';
        if (is_file($logoPath)) {
            $mime = mime_content_type($logoPath) ?: 'image/png';
            $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            $html .= "<img class='brand-logo' src='" . $logoDataUri . "' alt='Valenzuela Logo'/>";
        }
    }
    $html .= "<div class='city-seal'>CITY OF VALENZUELA</div>";
    $html .= "<div class='title'>Consultation Submission Summary</div>";
    $html .= "<div class='subtitle'>Public Consultation Office</div>";
    $html .= "</header>";
    
    // Reference and metadata
    $html .= "<section>";
    $html .= "<div class='meta-row'><div class='meta-label'>Reference Number:</div><div class='meta-value'>" . $tracking . "</div></div>";
    $html .= "<div class='meta-row'><div class='meta-label'>Date Created:</div><div class='meta-value'>" . date('F j, Y g:i A', strtotime($created)) . "</div></div>";
    $html .= "<div class='meta-row'><div class='meta-label'>Status:</div><div class='meta-value'>" . ucfirst($status) . "</div></div>";
    $html .= "</section>";

    // Created by information
    $html .= "<div class='section-title'>Created By</div>";
    $html .= "<section>";
    $html .= "<div class='meta-row'><div class='meta-label'>Admin Name:</div><div class='meta-value'>" . $adminName . "</div></div>";
    $html .= "</section>";

    // Consultation details
    $html .= "<div class='section-title'>Consultation Details</div>";
    $html .= "<section>";
    $html .= "<div class='meta-row'><div class='meta-label'>Topic:</div><div class='meta-value'>" . $title . "</div></div>";
    $html .= "<div class='meta-row'><div class='meta-label'>Category:</div><div class='meta-value'>" . $category . "</div></div>";
    $html .= "</section>";

    // Consultation Image
    if (!empty($image_path)) {
        // Resolve the image path similar to how it's done in public-portal.php
        $resolved_image = resolveConsultationImagePathForPDF($image_path);
        if ($resolved_image && file_exists(__DIR__ . '/../' . $resolved_image)) {
            $imageDataUri = 'data:image/' . pathinfo($resolved_image, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents(__DIR__ . '/../' . $resolved_image));
            $html .= "<div class='section-title'>Consultation Image</div>";
            $html .= "<section>";
            $html .= "<img src='" . $imageDataUri . "' style='max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px;' alt='Consultation Image'>";
            $html .= "</section>";
        }
    }

    // Description/Message
    $html .= "<div class='section-title'>Description</div>";
    $html .= "<div class='description'>" . $description . "</div>";
    
    // Footer
    $html .= "<div class='footer'>";
    $html .= "<p>This is an official record of your consultation submission to the City of Valenzuela.</p>";
    $html .= "<p>Retain this document for your records. Reference Number: " . $tracking . "</p>";
    $html .= "</div>";
    
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
