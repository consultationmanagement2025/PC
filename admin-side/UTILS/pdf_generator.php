<?php
/**
 * Simple PDF Generator for Consultation Summaries
 * Uses native PHP to generate basic PDF files
 */

class ConsultationPDFGenerator {
    private $filename;
    private $content = '';
    
    public function __construct($consultation_id) {
        $this->filename = 'consultation_summary_' . $consultation_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
    }
    
    /**
     * Generate PDF content for consultation
     */
    public function generateConsultationPDF($consultation_data) {
        // If Dompdf is available, prefer generating via HTML for better layout and image support
        if (class_exists('\Dompdf\\Dompdf')) {
            // Build a simple HTML version mirroring the improved template
            $title = htmlspecialchars($consultation_data['title'] ?? 'Consultation');
            $user_name = htmlspecialchars($consultation_data['name'] ?? $consultation_data['user_name'] ?? 'Anonymous');
            $user_email = htmlspecialchars($consultation_data['email'] ?? $consultation_data['user_email'] ?? '');
            $created = htmlspecialchars($consultation_data['created_at'] ?? date('Y-m-d H:i:s'));
            $tracking = htmlspecialchars($consultation_data['tracking_number'] ?? $consultation_data['tracking_no'] ?? '');
            $description = htmlspecialchars($consultation_data['description'] ?? '');

            // Determine admin-created by checking a few possible keys and the users table
            $isAdminCreated = false;
            $possibleUserKeys = ['created_by', 'created_by_user', 'user_id', 'created_by_id'];
            $createdBy = 0;
            foreach ($possibleUserKeys as $k) {
                if (!empty($consultation_data[$k])) { $createdBy = (int)$consultation_data[$k]; break; }
            }
            if ($createdBy > 0) {
                global $conn;
                $uStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
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
                        }
                    }
                }
            }

            $logoDataUri = '';
            if ($isAdminCreated) {
                $logoPath = __DIR__ . '/../images/valenzuela-logo.png';
                if (!is_file($logoPath)) $logoPath = __DIR__ . '/../images/logo.webp';
                if (is_file($logoPath)) {
                    $mime = mime_content_type($logoPath) ?: 'image/png';
                    $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
                }
            }

            $html = "<!doctype html><html><head><meta charset=\"utf-8\"><title>" . $title . "</title><style>body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#222;padding:24px}header{text-align:center;margin-bottom:18px}img.brand{max-width:180px;margin:0 auto 8px;display:block}h1{font-size:18px;margin:6px 0}h2{font-size:13px;margin:8px 0;color:#555}section{margin-bottom:12px}.meta{display:flex;margin-bottom:6px}.label{width:140px;font-weight:600;color:#111}.value{flex:1;color:#333}.desc{background:#fff;padding:12px;border-left:4px solid #2563eb;white-space:pre-wrap}</style></head><body>";
            $html .= "<header>";
            if ($logoDataUri) $html .= "<img class=\"brand\" src=\"" . $logoDataUri . "\" alt=\"Valenzuela Logo\" />";
            $html .= "<div><h1>Consultation Submission Summary</h1><div style=\"color:#666;font-size:11px\">Public Consultation Office</div></div></header>";
            $html .= "<section><div class=\"meta\"><div class=\"label\">Reference Number:</div><div class=\"value\">" . $tracking . "</div></div><div class=\"meta\"><div class=\"label\">Date Submitted:</div><div class=\"value\">" . date('F j, Y g:i A', strtotime($created)) . "</div></div></section>";
            $html .= "<div style=\"font-weight:600;background:#1f2937;color:#fff;padding:8px 12px;display:block;margin:8px 0\">Submitted By</div>";
            $html .= "<section><div class=\"meta\"><div class=\"label\">Name:</div><div class=\"value\">" . $user_name . "</div></div><div class=\"meta\"><div class=\"label\">Email:</div><div class=\"value\">" . $user_email . "</div></div></section>";
            $html .= "<div style=\"font-weight:600;background:#1f2937;color:#fff;padding:8px 12px;display:block;margin:8px 0\">Consultation Details</div>";
            $html .= "<section><div class=\"meta\"><div class=\"label\">Topic:</div><div class=\"value\">" . $title . "</div></div></section>";
            $html .= "<div style=\"font-weight:600;background:#1f2937;color:#fff;padding:8px 12px;display:block;margin:8px 0\">Submission Details</div>";
            $html .= "<div class=\"desc\">" . $description . "</div>";
            $html .= "</body></html>";

            // Render with Dompdf
            try {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return $dompdf->output();
            } catch (Throwable $e) {
                // Fall through to raw generator on failure
            }
        }

        // PDF Header (basic PDF structure)
        $this->content = "%PDF-1.4\n";
        
        // Add catalog and pages
        $obj_id = 1;
        $this->content .= "$obj_id 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
        
        $obj_id++;
        $page_obj = $obj_id;
        $this->content .= "$obj_id 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
        
        $obj_id++;
        $this->content .= "$obj_id 0 obj\n<<\n/Type /Page\n/Parent $page_obj 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n";
        
        // Page content stream
        $obj_id++;
        $content_stream = $this->createPageContent($consultation_data);
        $this->content .= "$obj_id 0 obj\n<<\n/Length " . strlen($content_stream) . "\n>>\nstream\n$content_stream\nendstream\nendobj\n";
        
        // Font object (Helvetica)
        $obj_id++;
        $this->content .= "$obj_id 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
        
        // Cross-reference table
        $xref_offset = strlen($this->content);
        $this->content .= "xref\n0 " . ($obj_id + 1) . "\n0000000000 65535 f \n";
        
        for ($i = 1; $i <= $obj_id; $i++) {
            $pos = strpos($this->content, "$i 0 obj");
            $this->content .= sprintf("%010d 00000 n \n", $pos);
        }
        
        // Trailer
        $this->content .= "trailer\n<<\n/Size " . ($obj_id + 1) . "\n/Root 1 0 R\n>>\nstartxref\n$xref_offset\n%%EOF";
        
        return $this->content;
    }
    
    /**
     * Create page content with consultation details
     */
    private function createPageContent($data) {
        $leftMargin = 54;
        $title = $this->escapeString($data['topic'] ?? ($data['title'] ?? 'Consultation Summary'));
        $user_name = $this->escapeString($data['name'] ?? ($data['user_name'] ?? 'Anonymous'));
        $user_email = $this->escapeString($data['email'] ?? ($data['user_email'] ?? ''));
        $status = $this->escapeString(ucfirst($data['status'] ?? 'Active'));
        $category = $this->escapeString($data['category'] ?? 'General');
        $created = $this->escapeString(isset($data['created_at']) ? date('F j, Y, g:i A', strtotime($data['created_at'])) : date('F j, Y, g:i A'));
        $tracking = $this->escapeString($data['tracking_number'] ?? ($data['tracking_no'] ?? ('CONSULT-' . str_pad($data['id'] ?? 0, 6, '0', STR_PAD_LEFT))));
        $adminName = $this->escapeString($data['admin_name'] ?? 'Mr. Jojo');
        $description = $this->escapeString($data['description'] ?? 'No description provided.');

        $content = "BT\n";
        
        // Header Title
        $content .= "/F1 16 Tf\n";
        $content .= "$leftMargin 740 Td\n";
        $content .= "(CITY OF VALENZUELA) Tj\n";
        $content .= "0 -22 Td\n";
        $content .= "/F1 14 Tf\n";
        $content .= "(Public Consultation Office Summary) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "/F1 10 Tf\n";
        $content .= "(Official Record of Consultation Document) Tj\n";
        $content .= "0 -22 Td\n";
        $content .= "(----------------------------------------------------------------------------------------------------) Tj\n";
        $content .= "0 -25 Td\n";

        // Reference Information
        $content .= "/F1 11 Tf\n";
        $content .= "(Reference Number: $tracking) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "(Date Created: $created) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "(Status: $status) Tj\n";
        $content .= "0 -25 Td\n";

        // Created By Section
        $content .= "/F1 12 Tf\n";
        $content .= "(CREATED BY) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "/F1 11 Tf\n";
        $content .= "(Admin Name: $adminName) Tj\n";
        if ($user_name && $user_name !== 'Anonymous') {
            $content .= "0 -18 Td\n";
            $content .= "(Citizen Name: $user_name) Tj\n";
        }
        if ($user_email) {
            $content .= "0 -18 Td\n";
            $content .= "(Citizen Email: $user_email) Tj\n";
        }
        $content .= "0 -25 Td\n";

        // Consultation Details Section
        $content .= "/F1 12 Tf\n";
        $content .= "(CONSULTATION DETAILS) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "/F1 11 Tf\n";
        $content .= "(Topic: $title) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "(Category: $category) Tj\n";
        $content .= "0 -25 Td\n";

        // Description Section
        $content .= "/F1 12 Tf\n";
        $content .= "(DESCRIPTION) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "/F1 10 Tf\n";

        $wrappedDesc = wordwrap($description, 80, "\n");
        $lines = explode("\n", $wrappedDesc);
        $lineCount = 0;
        foreach ($lines as $line) {
            if ($lineCount > 18) break; // Limit lines to fit clean on single page
            $content .= "(" . trim($line) . ") Tj\n";
            $content .= "0 -14 Td\n";
            $lineCount++;
        }

        // Footer at bottom of page
        $content .= "0 -25 Td\n";
        $content .= "/F1 9 Tf\n";
        $content .= "(----------------------------------------------------------------------------------------------------) Tj\n";
        $content .= "0 -14 Td\n";
        $content .= "(This is an official record of your consultation submission to the City of Valenzuela.) Tj\n";
        $content .= "0 -12 Td\n";
        $content .= "(Retain this document for your records. Tracking #: $tracking) Tj\n";

        $content .= "ET\n";
        return $content;
    }
    
    /**
     * Escape special characters for PDF
     */
    private function escapeString($string) {
        $string = strip_tags(html_entity_decode((string)$string));
        $search = ['\\', '(', ')', "\n", "\r"];
        $replace = ['\\\\', '\\(', '\\)', ' ', ''];
        return str_replace($search, $replace, $string);
    }
    
    /**
     * Save PDF to file
     */
    public function save($consultation_data, $save_path) {
        $pdf_content = $this->generateConsultationPDF($consultation_data);
        return file_put_contents($save_path, $pdf_content) !== false;
    }
    
    /**
     * Get filename
     */
    public function getFilename() {
        return $this->filename;
    }
}
