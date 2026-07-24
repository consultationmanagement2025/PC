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
        // Use relative positioning (Td) for consistent text layout with improved spacing
        $pageWidth = 612;
        $leftMargin = 72;
        $centerX = intval($pageWidth / 2);
        $content = "BT\n/F1 14 Tf\n"; // Increased font size from 12 to 14

        // Government-style header with logo placeholder
        $content .= "$leftMargin 720 Td\n"; // Moved down from 750 to 720
        $content .= "(CITY OF VALENZUELA) Tj\n";
        $content .= "0 -20 Td\n"; // Increased spacing from 15 to 20
        $content .= "1 0 0 1 " . ($centerX - $leftMargin) . " 0 Tm\n(Public Consultation Office) Tj\n";

        // Reset matrix and move down
        $content .= "-1 0 0 -1 0 0 Tm\n";
        $content .= "$leftMargin -45 Td\n"; // Increased from 35 to 45

        // Title (centered)
        $title = $this->escapeString('Consultation Submission Summary');
        $content .= "1 0 0 1 " . ($centerX - $leftMargin) . " 0 Tm\n($title) Tj\n";

        // Move to next line for reference
        $content .= "-1 0 0 -1 0 0 Tm\n"; // Reset matrix
        $content .= "$leftMargin -35 Td\n"; // Increased from 28 to 35

        // Reference (left-aligned)
        $ref = 'Reference Number: CONSULT-' . str_pad(isset($data['id']) ? $data['id'] : 0, 6, '0', STR_PAD_LEFT);
        $content .= "(" . $this->escapeString($ref) . ") Tj\n";
        $content .= "0 -22 Td\n"; // Increased from 18 to 22

        // Date (left-aligned)
        $dateStr = isset($data['created_at']) ? date('F j, Y, g:i A', strtotime($data['created_at'])) : date('F j, Y, g:i A');
        $content .= "(Date Created: " . $this->escapeString($dateStr) . ") Tj\n";
        $content .= "0 -22 Td\n"; // Increased from 20 to 22

        // Status (left-aligned)
        $status = isset($data['status']) ? ucfirst($data['status']) : 'Pending';
        $content .= "(Status: " . $this->escapeString($status) . ") Tj\n";
        $content .= "0 -30 Td\n"; // Increased spacing

        // Separator (centered)
        $sep = str_repeat('-', 45);
        $content .= "1 0 0 1 " . ($centerX - $leftMargin - 72) . " 0 Tm\n(" . $this->escapeString($sep) . ") Tj\n";

        // Reset to left margin for next section
        $content .= "-1 0 0 -1 0 0 Tm\n";
        $content .= "144 -35 Td\n"; // Increased from 26 to 35

        // Created By header
        $content .= "(Created By:) Tj\n";
        $content .= "0 -22 Td\n"; // Increased from 18 to 22

        // Admin name (indented)
        $adminName = $this->escapeString($data['admin_name'] ?? 'Mr. Jojo');
        $content .= "18 0 Td\n(Admin Name: $adminName) Tj\n";
        $content .= "-18 -30 Td\n"; // Increased spacing

        // Consultation Details header
        $content .= "(Consultation Details:) Tj\n";
        $content .= "0 -22 Td\n"; // Increased from 18 to 22

        // Topic (indented)
        $topic = $this->escapeString($data['title'] ?? ($data['topic'] ?? 'N/A'));
        $content .= "18 0 Td\n(Topic: $topic) Tj\n";
        $content .= "-18 -20 Td\n"; // Increased from 16 to 20

        // Category (indented)
        $category = $this->escapeString($data['category'] ?? 'N/A');
        $content .= "(Category: $category) Tj\n";
        $content .= "0 -20 Td\n"; // Increased from 16 to 20

        // Department (indented)
        $dept = $this->escapeString($data['department'] ?? 'N/A');
        $content .= "(Department: $dept) Tj\n";
        $content .= "-18 -30 Td\n"; // Increased spacing

        // Description header
        $content .= "(Description:) Tj\n";
        $content .= "0 -20 Td\n"; // Increased from 16 to 20

        // Handle long description (wrap text at ~75 chars for better spacing)
        $description = wordwrap($this->escapeString($data['description'] ?? 'N/A'), 75, "\n");
        $lines = explode("\n", $description);
        foreach ($lines as $line) {
            $content .= "($line) Tj\n";
            $content .= "0 -18 Td\n"; // Increased from 14 to 18
        }

        // Official footer
        $content .= "-1 0 0 -1 0 0 Tm\n";
        $content .= "$leftMargin -40 Td\n"; // Increased from 30 to 40
        $content .= "(This is an official record of your consultation submission to the City of Valenzuela.) Tj\n";
        $content .= "0 -18 Td\n";
        $content .= "(Retain this document for your records.) Tj\n";

        $content .= "ET\n";
        return $content;
    }
    
    /**
     * Escape special characters for PDF
     */
    private function escapeString($string) {
        // Simple escaping for PDF content
        $search = ['\\', '(', ')', "\n", "\r"];
        $replace = ['\\\\', '\\(', '\\)', '\\n', ''];
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
