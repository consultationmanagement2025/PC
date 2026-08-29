<?php



session_start();
require_once 'UTILS/session_check.php';



require 'DATABASE/audit-log.php';



require 'DATABASE/user-logs.php';



require 'DATABASE/posts.php';



require 'DATABASE/notifications.php';



require 'DATABASE/consultations.php';

  

require 'DATABASE/feedback.php';



require_once 'UTILS/security.php';
require_once 'DATABASE/document-management.php';
require_once 'UTILS/pdf_generator.php';
require_once __DIR__ . '/email_config.php';



// Use strtolower and trim to be safe



$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';



$is_admin = ($current_role === 'admin' || $current_role === 'administrator');
$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_resource_person = ($current_role === 'resource person' || $current_role === 'resource_person' || $current_role === 'staff');
$is_admin_or_super = ($is_admin || $is_super_admin);
$is_read_only_super_admin = $is_super_admin;

$sidebar_display_name = trim((string)($_SESSION['fullname'] ?? 'Admin User'));
if ($sidebar_display_name === '') {
    $sidebar_display_name = 'Admin User';
}
$sidebar_role_label = 'User';
if ($is_super_admin) {
    echo '<style>
        /* Remove horizontal scrollbar track while allowing smooth scroll */
        .no-scrollbar::-webkit-scrollbar,
        [class*="overflow-x"]::-webkit-scrollbar,
        [id*="container"]::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .no-scrollbar,
        [class*="overflow-x"],
        [id*="container"] {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
        /* Hide action buttons in dashboard for super admin read-only */
        #dashboard-section .btn { display: none !important; }
        #dashboard-section button:not(.btn):not([onclick*=\'openModuleReportModal\']) { display: none !important; }
        #dashboard-section [onclick*=\'manage\'] { display: none !important; }
        </style>';
}







// --- Consultation & Feedback Management Dashboard Data ---



$consult_total = 0;



$consult_open = 0;



$consult_scheduled = 0;



$consultations = [];



$feedbackList = [];

// --- Reports data (used by the Reports section) ---
$report_overall = [
    'consultations_total' => 0,
    'pending_review' => 0,
    'survey_responses' => 0,
    'feedback_total' => 0,
    'feedback_avg_rating' => 0.0
];
$report_status_breakdown = [];
$report_category_breakdown = [];
$report_recent_consultations = [];
$report_recent_feedback = [];

if (file_exists('db.php')) {
    require_once 'db.php';

    // Get current user ID for resource person filtering
    $current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $is_resource_person = isset($_SESSION['role']) && in_array(strtolower($_SESSION['role']), ['resource person', 'resource_person', 'staff']);

    // Overall consultation counts
    $consultation_filter = $is_resource_person ? "WHERE assigned_to = $current_user_id OR assigned_to IS NULL" : "";
    $row = $conn->query("SELECT
                COUNT(*) AS consultations_total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_review
            FROM consultations $consultation_filter")->fetch_assoc();
    if ($row) {
        $report_overall['consultations_total'] = (int)($row['consultations_total'] ?? 0);
        $report_overall['pending_review'] = (int)($row['pending_review'] ?? 0);
    }

    // Survey responses (guest + user votes)
    $row = $conn->query("SELECT
                (SELECT COUNT(*) FROM consultation_votes) + (SELECT COUNT(*) FROM consultation_guest_votes) AS survey_responses
            ")->fetch_assoc();
    if ($row) {
        $report_overall['survey_responses'] = (int)($row['survey_responses'] ?? 0);
    }

    // Feedback totals and average rating
    $row = $conn->query("SELECT COUNT(*) AS total, AVG(COALESCE(rating,0)) AS avg_rating FROM feedback")->fetch_assoc();
    if ($row) {
        $report_overall['feedback_total'] = (int)($row['total'] ?? 0);
        $report_overall['feedback_avg_rating'] = (float)($row['avg_rating'] ?? 0.0);
    }

    // Status breakdown
    $res = $conn->query("SELECT status, COUNT(*) AS total FROM consultations $consultation_filter GROUP BY status ORDER BY total DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $report_status_breakdown[] = ['status' => $r['status'], 'total' => (int)$r['total']];
        }
    }

    // Top categories
    $res = $conn->query("SELECT COALESCE(category,'Uncategorized') AS category, COUNT(*) AS total FROM consultations $consultation_filter GROUP BY category ORDER BY total DESC LIMIT 10");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $report_category_breakdown[] = ['category' => $r['category'], 'total' => (int)$r['total']];
        }
    }

    // Recent consultations
    $res = $conn->query("SELECT id, title, category, status, type, created_at FROM consultations $consultation_filter ORDER BY created_at DESC LIMIT 6");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $report_recent_consultations[] = $r;
        }
    }

    // Recent feedback
    $res = $conn->query("SELECT id, guest_name, category, rating, status, created_at FROM feedback ORDER BY created_at DESC LIMIT 6");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $report_recent_feedback[] = $r;
        }
    }
}





function analyzeReportSentiment($text) {
    $text = mb_strtolower(trim((string)$text), 'UTF-8');
    if ($text === '') {
        return 'neutral';
    }

    $positive = ['good','great','excellent','satisfied','thank','thanks','salamat','appreciate','helpful','improved','improvement','maayos','maganda','mabuti','recommend','support'];
    $negative = ['bad','poor','problem','issue','disappointed','frustrated','slow','dangerous','corrupt','complaint','hindi','masama','mabagal','marumi','pangit','worse','unfair'];

    $score = 0;
    foreach ($positive as $word) {
        if (strpos($text, $word) !== false) {
            $score++;
        }
    }
    foreach ($negative as $word) {
        if (strpos($text, $word) !== false) {
            $score--;
        }
    }

    if ($score > 0) {
        return 'positive';
    }
    if ($score < 0) {
        return 'negative';
    }
    return 'neutral';
}

function buildModuleReportData($module, $conn) {
    $module = strtolower(trim((string)$module));
    $generated_at = date('Y-m-d H:i:s');

    $consultation_total = 0;
    $pending_review = 0;
    $survey_responses = 0;
    $feedback_total = 0;
    $feedback_avg_rating = 0.0;
    $users_total = 0;
    $active_users = 0;
    $posts_total = 0;
    $announcements_total = 0;

    // Check if module has any records
    $has_records = false;

    if ($conn) {
        $row = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_review FROM consultations")->fetch_assoc();
        if ($row) {
            $consultation_total = (int)($row['total'] ?? 0);
            $pending_review = (int)($row['pending_review'] ?? 0);
        }

        $row = $conn->query("SELECT (SELECT COUNT(*) FROM consultation_votes) + (SELECT COUNT(*) FROM consultation_guest_votes) AS survey_responses")->fetch_assoc();
        if ($row) {
            $survey_responses = (int)($row['survey_responses'] ?? 0);
        }

        $row = $conn->query("SELECT COUNT(*) AS total, AVG(COALESCE(rating,0)) AS avg_rating FROM feedback")->fetch_assoc();
        if ($row) {
            $feedback_total = (int)($row['total'] ?? 0);
            $feedback_avg_rating = (float)($row['avg_rating'] ?? 0.0);
        }

        $r = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_users FROM users");
        if ($r) {
            $row = $r->fetch_assoc();
            if ($row) {
                $users_total = (int)($row['total'] ?? 0);
                $active_users = (int)($row['active_users'] ?? 0);
            }
        }
        
        // Fetch all users for user management section
        $r = $conn->query("SELECT id, fullname, email, role, status, verification_status, created_at FROM users ORDER BY created_at DESC");
        if ($r) {
            $users = [];
            while ($row = $r->fetch_assoc()) {
                $users[] = $row;
            }
        } else {
            $users = [];
        }

        // Separate users by role for different sections
        $citizens = array_filter($users, function($u) {
            $role = strtolower($u['role'] ?? '');
            return !in_array($role, ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'resource person', 'resource_person']);
        });
        $citizens = array_values($citizens);

        $r = $conn->query("SELECT COUNT(*) AS total FROM posts");
        if ($r) {
            $row = $r->fetch_assoc();
            if ($row) {
                $posts_total = (int)($row['total'] ?? 0);
            }
        }

        $r = $conn->query("SELECT COUNT(*) AS total FROM announcements");
        if ($r) {
            $row = $r->fetch_assoc();
            if ($row) {
                $announcements_total = (int)($row['total'] ?? 0);
            }
        }
    }

    $sentiment_counts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
    if ($conn) {
        $res = $conn->query("SELECT message FROM feedback WHERE COALESCE(message,'') <> '' ORDER BY created_at DESC LIMIT 50");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $sentiment = analyzeReportSentiment((string)($row['message'] ?? ''));
                if (isset($sentiment_counts[$sentiment])) {
                    $sentiment_counts[$sentiment]++;
                }
            }
        }
    }

    $module_title = 'System-wide Report';
    $summary = [
        ['label' => 'Generated', 'value' => $generated_at],
        ['label' => 'Module', 'value' => ucfirst($module)],
    ];
    $details = [];

    switch ($module) {
        case 'consultations':
            $module_title = 'Consultation Module Report';
            if ($consultation_total === 0 && $survey_responses === 0 && $feedback_total === 0) {
                return ['error' => 'no_records', 'message' => 'No consultation records available to generate a report.'];
            }
            $summary[] = ['label' => 'Total Consultations', 'value' => $consultation_total];
            $summary[] = ['label' => 'Pending Review', 'value' => $pending_review];
            $summary[] = ['label' => 'Survey Responses', 'value' => $survey_responses];
            $summary[] = ['label' => 'Feedback Entries', 'value' => $feedback_total];
            $summary[] = ['label' => 'Average Rating', 'value' => number_format($feedback_avg_rating, 1)];
            $summary[] = ['label' => 'Participation Score', 'value' => ($consultation_total + $survey_responses + $feedback_total)];
            $details[] = ['label' => 'Sentiment Summary', 'value' => 'Positive: ' . $sentiment_counts['positive'] . ', Neutral: ' . $sentiment_counts['neutral'] . ', Negative: ' . $sentiment_counts['negative']];
            break;
        case 'feedback':
            $module_title = 'Feedback Module Report';
            if ($feedback_total === 0) {
                return ['error' => 'no_records', 'message' => 'No feedback records available to generate a report.'];
            }
            $summary[] = ['label' => 'Feedback Entries', 'value' => $feedback_total];
            $summary[] = ['label' => 'Average Rating', 'value' => number_format($feedback_avg_rating, 1)];
            $summary[] = ['label' => 'Positive Feedback', 'value' => $sentiment_counts['positive']];
            $summary[] = ['label' => 'Neutral Feedback', 'value' => $sentiment_counts['neutral']];
            $summary[] = ['label' => 'Negative Feedback', 'value' => $sentiment_counts['negative']];
            $summary[] = ['label' => 'Related Consultations', 'value' => $consultation_total];
            $details[] = ['label' => 'Sentiment Summary', 'value' => 'Positive: ' . $sentiment_counts['positive'] . ', Neutral: ' . $sentiment_counts['neutral'] . ', Negative: ' . $sentiment_counts['negative']];
            break;
        case 'users':
            $module_title = 'User Management Report';
            if ($users_total === 0 && $posts_total === 0 && $announcements_total === 0) {
                return ['error' => 'no_records', 'message' => 'No user records available to generate a report.'];
            }
            $summary[] = ['label' => 'Total Users', 'value' => $users_total];
            $summary[] = ['label' => 'Active Users', 'value' => $active_users];
            $summary[] = ['label' => 'Posts', 'value' => $posts_total];
            $summary[] = ['label' => 'Announcements', 'value' => $announcements_total];
            $details[] = ['label' => 'Engagement Snapshot', 'value' => 'Users: ' . $users_total . ', Posts: ' . $posts_total . ', Announcements: ' . $announcements_total];
            break;
        case 'reports':
            $module_title = 'Reports Module Report';
            if ($consultation_total === 0 && $survey_responses === 0 && $feedback_total === 0) {
                return ['error' => 'no_records', 'message' => 'No records available to generate a report.'];
            }
            $summary[] = ['label' => 'Consultations', 'value' => $consultation_total];
            $summary[] = ['label' => 'Pending Review', 'value' => $pending_review];
            $summary[] = ['label' => 'Survey Responses', 'value' => $survey_responses];
            $summary[] = ['label' => 'Feedback Entries', 'value' => $feedback_total];
            $details[] = ['label' => 'Sentiment Summary', 'value' => 'Positive: ' . $sentiment_counts['positive'] . ', Neutral: ' . $sentiment_counts['neutral'] . ', Negative: ' . $sentiment_counts['negative']];
            break;
        case 'dashboard':
        default:
            $module_title = 'System-wide Report';
            $summary[] = ['label' => 'Total Consultations', 'value' => $consultation_total];
            $summary[] = ['label' => 'Pending Review', 'value' => $pending_review];
            $summary[] = ['label' => 'Survey Responses', 'value' => $survey_responses];
            $summary[] = ['label' => 'Feedback Entries', 'value' => $feedback_total];
            $summary[] = ['label' => 'Average Rating', 'value' => number_format($feedback_avg_rating, 1)];
            $summary[] = ['label' => 'Users', 'value' => $users_total];
            $summary[] = ['label' => 'Active Users', 'value' => $active_users];
            $summary[] = ['label' => 'Posts', 'value' => $posts_total];
            $summary[] = ['label' => 'Announcements', 'value' => $announcements_total];
            $summary[] = ['label' => 'Participation Score', 'value' => ($consultation_total + $survey_responses + $feedback_total + $posts_total)];
            $details[] = ['label' => 'Sentiment Summary', 'value' => 'Positive: ' . $sentiment_counts['positive'] . ', Neutral: ' . $sentiment_counts['neutral'] . ', Negative: ' . $sentiment_counts['negative']];
            break;
    }

    return [
        'title' => $module_title,
        'module' => $module,
        'generated_at' => $generated_at,
        'summary' => $summary,
        'details' => $details,
    ];
}

function generateNativePDF($report_data, $file_path) {
    $title = $report_data['title'] ?? 'System Report';
    $generated = $report_data['generated_at'] ?? date('Y-m-d H:i:s');
    $module = ucfirst($report_data['module'] ?? 'Module');

    $escape = function($str) {
        $str = strip_tags((string)$str);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
    };

    $stream = "BT\n";
    $stream .= "/F1 18 Tf\n";
    $stream .= "50 740 Td\n";
    $stream .= "(" . $escape($title) . ") Tj\n";
    
    $stream .= "/F2 10 Tf\n";
    $stream .= "0 -22 Td\n";
    $stream .= "(Generated: " . $escape($generated) . "  |  Module: " . $escape($module) . ") Tj\n";
    
    $stream .= "0 -15 Td\n";
    $stream .= "(----------------------------------------------------------------------------------------------------) Tj\n";
    $stream .= "0 -25 Td\n";

    $stream .= "/F1 14 Tf\n";
    $stream .= "(SUMMARY) Tj\n";
    $stream .= "0 -20 Td\n";
    $stream .= "/F2 11 Tf\n";

    foreach ($report_data['summary'] as $item) {
        $label = $escape($item['label']);
        $value = $escape($item['value']);
        $stream .= "(" . $label . ": " . $value . ") Tj\n";
        $stream .= "0 -16 Td\n";
    }

    if (!empty($report_data['details'])) {
        $stream .= "0 -15 Td\n";
        $stream .= "/F1 14 Tf\n";
        $stream .= "(ADDITIONAL DETAILS) Tj\n";
        $stream .= "0 -20 Td\n";
        $stream .= "/F2 11 Tf\n";
        foreach ($report_data['details'] as $item) {
            $label = $escape($item['label']);
            $value = $escape($item['value']);
            $stream .= "(" . $label . ": " . $value . ") Tj\n";
            $stream .= "0 -16 Td\n";
        }
    }

    $stream .= "0 -35 Td\n";
    $stream .= "/F2 9 Tf\n";
    $stream .= "(Official System Generated Report - Valenzuela City Public Consultation System) Tj\n";
    $stream .= "ET\n";

    $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /MediaBox [0 0 612 792] /Contents 6 0 R >>\nendobj\n";
    $obj4 = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    
    $streamLen = strlen($stream);
    $obj6 = "6 0 obj\n<< /Length " . $streamLen . " >>\nstream\n" . $stream . "endstream\nendobj\n";

    $o1 = strlen("%PDF-1.4\n");
    $o2 = $o1 + strlen($obj1);
    $o3 = $o2 + strlen($obj2);
    $o4 = $o3 + strlen($obj3);
    $o5 = $o4 + strlen($obj4);
    $o6 = $o5 + strlen($obj5);

    $pdf = "%PDF-1.4\n" . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6;

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 7\n";
    $pdf .= "0000000000 65535 f \n";
    $pdf .= sprintf("%010d 00000 n \n", $o1);
    $pdf .= sprintf("%010d 00000 n \n", $o2);
    $pdf .= sprintf("%010d 00000 n \n", $o3);
    $pdf .= sprintf("%010d 00000 n \n", $o4);
    $pdf .= sprintf("%010d 00000 n \n", $o5);
    $pdf .= sprintf("%010d 00000 n \n", $o6);

    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

    return file_put_contents($file_path, $pdf) !== false;
}

function generateModuleReportFile($module, $format, $conn, $user_name = null) {
    $module = strtolower(trim((string)$module));
    $format = strtolower(trim((string)$format));
    
    error_log('Generating report: module=' . $module . ', format=' . $format);
    
    $report_data = buildModuleReportData($module, $conn);
    
    // Check if report data indicates no records
    if (isset($report_data['error']) && $report_data['error'] === 'no_records') {
        return [
            'success' => false,
            'message' => $report_data['message']
        ];
    }
    $timestamp = date('Ymd_His');
    $safe_module = preg_replace('/[^a-z0-9_-]+/i', '_', $module);
    $report_dir = __DIR__ . '/uploads/reports/';
    
    if (!is_dir($report_dir)) {
        error_log('Creating reports directory: ' . $report_dir);
        mkdir($report_dir, 0755, true);
    }

    $ext = 'pdf';
    if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
        $ext = 'xls';
    } elseif ($format === 'word' || $format === 'doc' || $format === 'docx') {
        $ext = 'doc';
    } elseif ($format === 'csv') {
        $ext = 'csv';
    } elseif ($format === 'text' || $format === 'txt') {
        $ext = 'txt';
    }

    $filename = $safe_module . '_report_' . $timestamp . '.' . $ext;
    $file_path = $report_dir . $filename;
    
    error_log('Report file path: ' . $file_path);

    $write_success = false;
    if ($format === 'excel' || $format === 'xls' || $format === 'xlsx') {
        $html = '<html><head><meta charset="UTF-8"></head><body>';
        $html .= '<h2>' . htmlspecialchars($report_data['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
        $html .= '<p>Generated: ' . htmlspecialchars($report_data['generated_at'], ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<table border="1" cellspacing="0" cellpadding="4">';
        foreach ($report_data['summary'] as $item) {
            $html .= '<tr><th align="left">' . htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') . '</th><td>' . htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $html .= '</table>';
        if (!empty($report_data['details'])) {
            $html .= '<h3>Additional Details</h3><table border="1" cellspacing="0" cellpadding="4">';
            foreach ($report_data['details'] as $item) {
                $html .= '<tr><th align="left">' . htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') . '</th><td>' . htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
            }
            $html .= '</table>';
        }
        $html .= '</body>
</html>';
        $write_success = file_put_contents($file_path, $html) !== false;
    } elseif ($format === 'word' || $format === 'doc' || $format === 'docx') {
        $html = '<html><head><meta charset="UTF-8"></head><body>';
        $html .= '<h1>' . htmlspecialchars($report_data['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        $html .= '<p><strong>Generated:</strong> ' . htmlspecialchars($report_data['generated_at'], ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<ul>';
        foreach ($report_data['summary'] as $item) {
            $html .= '<li><strong>' . htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html .= '</ul>';
        if (!empty($report_data['details'])) {
            $html .= '<h2>Additional Details</h2><ul>';
            foreach ($report_data['details'] as $item) {
                $html .= '<li><strong>' . htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</body>
</html>';
        $write_success = file_put_contents($file_path, $html) !== false;
    } elseif ($format === 'csv') {
        $fp = fopen($file_path, 'w');
        if ($fp) {
            fputcsv($fp, [$report_data['title']]);
            fputcsv($fp, ['Generated', $report_data['generated_at']]);
            fputcsv($fp, ['Module', ucfirst($report_data['module'])]);
            fputcsv($fp, []);
            fputcsv($fp, ['SUMMARY METRIC', 'VALUE']);
            foreach ($report_data['summary'] as $item) {
                fputcsv($fp, [$item['label'], $item['value']]);
            }
            if (!empty($report_data['details'])) {
                fputcsv($fp, []);
                fputcsv($fp, ['ADDITIONAL DETAILS', 'VALUE']);
                foreach ($report_data['details'] as $item) {
                    fputcsv($fp, [$item['label'], $item['value']]);
                }
            }
            fclose($fp);
            $write_success = true;
        }
    } elseif ($format === 'text' || $format === 'txt') {
        $lines = [$report_data['title'], 'Generated: ' . $report_data['generated_at'], 'Module: ' . ucfirst($report_data['module']), ''];
        foreach ($report_data['summary'] as $item) {
            $lines[] = $item['label'] . ': ' . $item['value'];
        }
        if (!empty($report_data['details'])) {
            $lines[] = '';
            $lines[] = 'Additional Details:';
            foreach ($report_data['details'] as $item) {
                $lines[] = $item['label'] . ': ' . $item['value'];
            }
        }
        $write_success = file_put_contents($file_path, implode("\n", $lines)) !== false;
    } else {
        // PDF default format
        $write_success = generateNativePDF($report_data, $file_path);
    }

    // Verify file was actually created
    if (!file_exists($file_path)) {
        error_log('Report file does not exist after write: ' . $file_path);
        return [
            'success' => false,
            'message' => 'Failed to create report file'
        ];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'download_url' => 'download-report.php?file=' . urlencode($filename),
        'title' => $report_data['title'],
    ];
}

// Handle AJAX requests for status updates

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_consultation_status') {

    header('Content-Type: application/json');

    

    $consultation_id = (int)($_POST['consultation_id'] ?? 0);

    $new_status = trim($_POST['status'] ?? '');

    

    $valid_statuses = ['draft', 'pending', 'active', 'viewed', 'replied', 'completed', 'closed', 'archived', 'rejected', 'declined', 'forwarded_orts'];

    

    if ($consultation_id <= 0 || !in_array($new_status, $valid_statuses)) {

        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);

        exit;

    }

    

    if (file_exists('db.php')) {

        require_once 'db.php';

        

        if ($new_status === 'declined' || $new_status === 'rejected') {
            $reason = trim($_POST['reason'] ?? $_POST['remarks'] ?? 'Submission declined by LGU Secretariat');
            $stmt = $conn->prepare("UPDATE consultations SET status = ?, admin_response = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('sssi', $new_status, $reason, $reason, $consultation_id);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE consultations SET status = ? WHERE id = ?");

        if ($stmt) {

            $stmt->bind_param('si', $new_status, $consultation_id);

            if ($stmt->execute()) {

                // Log the status change

                if (function_exists('logAudit')) {

                    logAudit('consultation_status_update', "Updated consultation #{$consultation_id} status to '{$new_status}'");

                }

                echo json_encode(['success' => true]);

            } else {

                echo json_encode(['success' => false, 'error' => 'Database error']);

            }

            $stmt->close();

        } else {

            echo json_encode(['success' => false, 'error' => 'Prepare failed']);

        }

    } else {

        echo json_encode(['success' => false, 'error' => 'Database not available']);

    }

    exit;
}

// Handle feedback status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_feedback_status') {
    header('Content-Type: application/json');

    if ($is_read_only_super_admin) {
        echo json_encode(['success' => false, 'error' => 'Read-only role: action not allowed for super admin']);
        exit;
    }

    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    
    $valid_statuses = ['new', 'reviewed', 'responded', 'closed'];
    
    if ($feedback_id <= 0 || !in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    if (file_exists('db.php')) {
        require_once 'db.php';
        
        $stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $new_status, $feedback_id);
            if ($stmt->execute()) {
                if (function_exists('logAudit')) {
                    logAudit('feedback_status_update', "Updated feedback #{$feedback_id} status to '{$new_status}'");
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Prepare failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database not available']);
    }
    exit;
}

// Handle feedback response update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_feedback_response') {
    header('Content-Type: application/json');

    if ($is_read_only_super_admin) {
        echo json_encode(['success' => false, 'error' => 'Read-only role: action not allowed for super admin']);
        exit;
    }

    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $response = trim($_POST['response'] ?? '');
    
    if ($feedback_id <= 0 || $response === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    if (file_exists('db.php')) {
        require_once 'db.php';
        
        $admin_respondent = (int)($_SESSION['user_id'] ?? 0);
        $responded_at = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("UPDATE feedback SET admin_response = ?, admin_respondent = ?, responded_at = ?, status = 'responded' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('sisi', $response, $admin_respondent, $responded_at, $feedback_id);
            if ($stmt->execute()) {
                if (function_exists('logAudit')) {
                    logAudit('feedback_response_added', "Added response to feedback #{$feedback_id}");
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Prepare failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database not available']);
    }
    exit;
}


// Handle module report generation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_module_report') {
    // Clear any output buffer to prevent HTML/whitespace contamination
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        error_log('Report generation failed: Invalid CSRF token');
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $module = strtolower(trim((string)($_POST['module'] ?? 'dashboard')));
    $format = strtolower(trim((string)($_POST['format'] ?? 'pdf')));
    if (!in_array($format, ['pdf', 'excel', 'word'], true)) {
        error_log('Report generation failed: Invalid format - ' . $format);
        echo json_encode(['success' => false, 'message' => 'Invalid format']);
        exit;
    }

    if (!in_array($module, ['dashboard','consultations','feedback','users','reports'], true)) {
        $module = 'dashboard';
    }

    if (file_exists('db.php')) {
        require_once 'db.php';
    }

    try {
        $result = generateModuleReportFile($module, $format, $conn ?? null, $_SESSION['fullname'] ?? null);
        error_log('Report generation result: ' . json_encode($result));
        ob_clean();
        echo json_encode($result);
    } catch (Exception $e) {
        error_log('Report generation exception: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()]);
    }
    exit;
}

// Handle export requests for consultations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_consultations') {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $format = strtolower(trim((string)($_POST['format'] ?? 'pdf')));
    if (!in_array($format, ['pdf', 'excel'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid format']);
        exit;
    }

    $mode = strtolower(trim((string)($_POST['mode'] ?? 'separate')));
    if (!in_array($mode, ['combined', 'separate'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid export mode']);
        exit;
    }

    $ids = $_POST['ids'] ?? [];
    if (is_string($ids)) {
        $ids = array_filter(array_map('trim', explode(',', $ids)));
    }
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        echo json_encode(['success' => false, 'message' => 'No consultations selected']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Missing user session']);
        exit;
    }
    $user_id = (int)$user_id;
    if ($user_id <= 0) $user_id = null;

    initializeDocumentsTable();

    $upload_dir = __DIR__ . '/uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $created = 0;
    $failed = [];

    $buildExcelHtml = function (array $consultation): string {
        $rows = [
            ['Consultation ID', $consultation['id'] ?? ''],
            ['Title', $consultation['title'] ?? ''],
            ['Category', $consultation['category'] ?? ''],
            ['Status', $consultation['status'] ?? ''],
            ['Start Date', $consultation['start_date'] ?? ''],
            ['End Date', $consultation['end_date'] ?? ''],
            ['Type', $consultation['type'] ?? ''],
            ['Expected Posts', $consultation['expected_posts'] ?? '0'],
            ['Views', $consultation['views'] ?? '0'],
            ['Posts Count', $consultation['posts_count'] ?? '0'],
            ['Created At', $consultation['created_at'] ?? ''],
            ['Updated At', $consultation['updated_at'] ?? ''],
            ['User Name', $consultation['user_name'] ?? ''],
            ['User Email', $consultation['user_email'] ?? ''],
            ['Description', $consultation['description'] ?? '']
        ];

        $html = "<html><head><meta charset=\"UTF-8\"></head><body>";
        $html .= "<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">";
        foreach ($rows as $row) {
            $label = htmlspecialchars((string)$row[0], ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars((string)$row[1], ENT_QUOTES, 'UTF-8');
            $html .= "<tr><th align=\"left\">{$label}</th><td>{$value}</td></tr>";
        }
        $html .= "</table></body>
</html>";
        return $html;
    };

    $buildCombinedExcelHtml = function (array $consultations): string {
        $html = "<html><head><meta charset=\"UTF-8\"></head><body>";
        $html .= "<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">";
        $html .= "<tr>";
        $headers = ['ID','Title','Category','Status','Start Date','End Date','Type','Expected Posts','Views','Posts Count','Created At','Updated At','User Name','User Email','Description'];
        foreach ($headers as $h) {
            $html .= "<th align=\"left\">" . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . "</th>";
        }
        $html .= "</tr>";
        foreach ($consultations as $c) {
            $row = [
                $c['id'] ?? '',
                $c['title'] ?? '',
                $c['category'] ?? '',
                $c['status'] ?? '',
                $c['start_date'] ?? '',
                $c['end_date'] ?? '',
                $c['type'] ?? '',
                $c['expected_posts'] ?? '0',
                $c['views'] ?? '0',
                $c['posts_count'] ?? '0',
                $c['created_at'] ?? '',
                $c['updated_at'] ?? '',
                $c['user_name'] ?? '',
                $c['user_email'] ?? '',
                $c['description'] ?? ''
            ];
            $html .= "<tr>";
            foreach ($row as $cell) {
                $html .= "<td>" . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table></body>
</html>";
        return $html;
    };

    if ($mode === 'combined') {
        $consultations = [];
        foreach ($ids as $id) {
            $consultation = getConsultationById($id);
            if ($consultation) {
                $consultations[] = $consultation;
            } else {
                $failed[] = (string)$id;
            }
        }

        if (!$consultations) {
            echo json_encode(['success' => false, 'message' => 'No valid consultations found', 'created' => 0, 'failed' => $failed]);
            exit;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $reference_number = 'CONSULT-BULK';

        if ($format === 'pdf') {
            $pdf_generator = new ConsultationPDFGenerator('bulk');
            $original_filename = "consultations_export_{$timestamp}.pdf";
            $stored_filename = "consultations_export_{$timestamp}.pdf";
            $file_path = $upload_dir . $stored_filename;

            $summary = [
                'id' => 'BULK',
                'name' => $_SESSION['fullname'] ?? 'Admin',
                'email' => $_SESSION['email'] ?? '',
                'phone' => '',
                'topic' => 'Consultations Export',
                'category' => '',
                'department' => '',
                'description' => 'Bulk export of selected consultations (' . count($consultations) . ' items).'
            ];

            $ok = $pdf_generator->save($summary, $file_path);
            if (!$ok) {
                echo json_encode(['success' => false, 'message' => 'Failed to generate PDF', 'created' => 0, 'failed' => $failed]);
                exit;
            }

            $file_type = 'application/pdf';
            $file_size = filesize($file_path);
            $document_type = 'final_document';
            $document_description = 'Admin bulk export of consultations (PDF summary)';
            $consultation_id_for_doc = $consultations[0]['id'];
        } else {
            $original_filename = "consultations_export_{$timestamp}.xls";
            $stored_filename = "consultations_export_{$timestamp}.xls";
            $file_path = $upload_dir . $stored_filename;

            $content = $buildCombinedExcelHtml($consultations);
            if (file_put_contents($file_path, $content) === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to generate Excel', 'created' => 0, 'failed' => $failed]);
                exit;
            }

            $file_type = 'application/vnd.ms-excel';
            $file_size = filesize($file_path);
            $document_type = 'final_document';
            $document_description = 'Admin bulk export of consultations (Excel)';
            $consultation_id_for_doc = $consultations[0]['id'];
        }

        $stmt = $conn->prepare("
            INSERT INTO documents (
                consultation_id, reference_number, original_filename,
                stored_filename, file_type, file_size, uploaded_by,
                document_type, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            @unlink($file_path);
            echo json_encode(['success' => false, 'message' => 'Failed to save document record', 'created' => 0, 'failed' => $failed]);
            exit;
        }

        $file_size = (int)$file_size;
        $stmt->bind_param(
            'isssissss',
            $consultation_id_for_doc,
            $reference_number,
            $original_filename,
            $stored_filename,
            $file_type,
            $file_size,
            $user_id,
            $document_type,
            $document_description
        );

        if ($stmt->execute()) {
            $created = 1;
        } else {
            @unlink($file_path);
            $failed = array_values(array_unique(array_merge($failed, $ids)));
            $created = 0;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'created' => $created, 'failed' => $failed]);
        exit;
    }

    foreach ($ids as $id) {
        $consultation = getConsultationById($id);
        if (!$consultation) {
            $failed[] = (string)$id;
            continue;
        }

        $reference_number = generateDocumentReference($id);
        $timestamp = date('Y-m-d_H-i-s');

        if ($format === 'pdf') {
            $pdf_generator = new ConsultationPDFGenerator($id);
            $original_filename = $pdf_generator->getFilename();
            $stored_filename = 'CONSULT-' . str_pad($id, 6, '0', STR_PAD_LEFT) . "_export_{$timestamp}.pdf";
            $file_path = $upload_dir . $stored_filename;

            $pdf_data = [
                'id' => $id,
                'name' => $consultation['user_name'] ?? ($_SESSION['fullname'] ?? 'Admin'),
                'email' => $consultation['user_email'] ?? '',
                'phone' => '',
                'topic' => $consultation['title'] ?? '',
                'category' => $consultation['category'] ?? '',
                'department' => 'N/A',
                'description' => $consultation['description'] ?? ''
            ];

            $ok = $pdf_generator->save($pdf_data, $file_path);
            if (!$ok) {
                $failed[] = (string)$id;
                continue;
            }

            $file_type = 'application/pdf';
            $file_size = filesize($file_path);
            $document_type = 'final_document';
            $document_description = 'Admin export of consultation (PDF)';
        } else {
            $original_filename = "consultation_export_{$id}_{$timestamp}.xls";
            $stored_filename = 'CONSULT-' . str_pad($id, 6, '0', STR_PAD_LEFT) . "_export_{$timestamp}.xls";
            $file_path = $upload_dir . $stored_filename;

            $content = $buildExcelHtml($consultation);
            if (file_put_contents($file_path, $content) === false) {
                $failed[] = (string)$id;
                continue;
            }

            $file_type = 'application/vnd.ms-excel';
            $file_size = filesize($file_path);
            $document_type = 'final_document';
            $document_description = 'Admin export of consultation (Excel)';
        }

        $stmt = $conn->prepare("
            INSERT INTO documents (
                consultation_id, reference_number, original_filename,
                stored_filename, file_type, file_size, uploaded_by,
                document_type, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            @unlink($file_path);
            $failed[] = (string)$id;
            continue;
        }

        $file_size = (int)$file_size;
        $stmt->bind_param(
            'isssissss',
            $id,
            $reference_number,
            $original_filename,
            $stored_filename,
            $file_type,
            $file_size,
            $user_id,
            $document_type,
            $document_description
        );

        if ($stmt->execute()) {
            $created++;
        } else {
            @unlink($file_path);
            $failed[] = (string)$id;
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'created' => $created, 'failed' => $failed]);
    exit;
}



// Handle email reply requests

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email_reply') {

    header('Content-Type: application/json');

    if ($is_read_only_super_admin) {
        echo json_encode(['success' => false, 'error' => 'Read-only role: action not allowed for super admin']);
        exit;
    }

    

    $consultation_id = (int)($_POST['consultation_id'] ?? 0);

    $email = trim($_POST['email'] ?? '');

    $subject = trim($_POST['subject'] ?? '');

    $message = trim($_POST['message'] ?? '');
    $meeting_platform = trim($_POST['meeting_platform'] ?? '');
    $meeting_link = trim($_POST['meeting_link'] ?? '');

    

    if ($consultation_id <= 0 || empty($email)) {

        echo json_encode(['success' => false, 'error' => 'Missing required fields']);

        exit;

    }

    if ($message === '') {
        $message = 'No additional remarks were provided.';
    }

    

    if (file_exists('db.php')) {

        require_once 'db.php';

        

        // Send email

        $email_subject = "Re: Your Consultation - " . $subject;

        $email_body = "Dear Valenzuela City Citizen,\n\n";

        $email_body .= "Thank you for your consultation submission. Here is our response:\n\n";

        $email_body .= $message . "\n\n";
        if ($meeting_platform !== '' || $meeting_link !== '') {
            $email_body .= "Meeting Details:\n";
            if ($meeting_platform !== '') {
                $email_body .= "Platform: " . $meeting_platform . "\n";
            }
            if ($meeting_link !== '') {
                $email_body .= "Link: " . $meeting_link . "\n";
            }
            $email_body .= "\n";
        }

        $email_body .= "Best regards,\n";

        $email_body .= "Valenzuela City Government\n";

        $email_body .= "Public Consultation Management Office\n\n";

        $email_body .= "This is an automated response to your consultation submission.";

        

        // Try to send email using the email system

        $mail_sent = false;
        $mail_error = '';

        if (function_exists('sendGmailEmail')) {

            $mail_sent = sendGmailEmail($email, $email_subject, $email_body, false, $mail_error);

        }

        

        if ($mail_sent) {

            // Update consultation status to 'replied'

            $stmt = $conn->prepare("UPDATE consultations SET status = 'replied' WHERE id = ?");

            if ($stmt) {

                $stmt->bind_param('i', $consultation_id);

                $stmt->execute();

                $stmt->close();

            }

            

            // Log the action

            if (function_exists('logAudit')) {

                logAudit('consultation_email_reply', "Sent email reply for consultation #{$consultation_id} to {$email}");

            }

            

            echo json_encode(['success' => true]);

        } else {

            $errorMessage = !empty($mail_error) ? $mail_error : 'Failed to send email';
            echo json_encode(['success' => false, 'error' => $errorMessage]);

        }

    } else {

        echo json_encode(['success' => false, 'error' => 'Database not available']);

    }

    exit;

}

// Handle schedule confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_schedule') {
    header('Content-Type: application/json');

    if ($is_read_only_super_admin) {
        echo json_encode(['success' => false, 'error' => 'Read-only role: action not allowed for super admin']);
        exit;
    }

    $consultation_id = (int)($_POST['consultation_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $scheduled_date = trim($_POST['scheduled_date'] ?? '');
    $scheduled_start = trim($_POST['scheduled_start'] ?? '');
    $scheduled_end = trim($_POST['scheduled_end'] ?? '');
    $meeting_platform = trim($_POST['meeting_platform'] ?? '');
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($consultation_id <= 0 || $email === '' || $scheduled_date === '' || $scheduled_start === '' || $scheduled_end === '') {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    if (file_exists('db.php')) {
        require_once 'db.php';
        $stmt = $conn->prepare("
            UPDATE consultations
            SET scheduled_date = ?, scheduled_start_time = ?, scheduled_end_time = ?,
                meeting_platform = ?, meeting_link = ?, schedule_status = 'confirmed', status = 'scheduled'
            WHERE id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('sssssi', $scheduled_date, $scheduled_start, $scheduled_end, $meeting_platform, $meeting_link, $consultation_id);
            $ok = $stmt->execute();
            $stmt->close();
        } else {
            $ok = false;
        }

        if (!$ok) {
            echo json_encode(['success' => false, 'error' => 'Failed to update schedule']);
            exit;
        }

        $email_subject = "Consultation Schedule Confirmation - " . $subject;
        $email_body = "Dear Citizen,\n\n";
        $email_body .= "Your consultation schedule has been confirmed.\n\n";
        $email_body .= "Date: " . $scheduled_date . "\n";
        $email_body .= "Time: " . $scheduled_start . " - " . $scheduled_end . "\n";
        if ($meeting_platform !== '') {
            $email_body .= "Platform: " . $meeting_platform . "\n";
        }
        if ($meeting_link !== '') {
            $email_body .= "Link: " . $meeting_link . "\n";
        }
        if ($notes !== '') {
            $email_body .= "Notes: " . $notes . "\n";
        }
        $email_body .= "\nBest regards,\nValenzuela City Government\nPublic Consultation Management Office";

        $mail_sent = false;
        if (function_exists('sendGmailEmail')) {
            $mail_sent = sendGmailEmail($email, $email_subject, $email_body, false);
        } else {
            $mail_sent = @mail($email, $email_subject, $email_body, "From: consultation@valenzuelacity.gov");
        }

        if ($mail_sent) {
            echo json_encode(['success' => true]);
        } else {
            $errorMessage = !empty($mail_error) ? $mail_error : 'Failed to send email';
            echo json_encode(['success' => false, 'error' => $errorMessage]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database not available']);
    }
    exit;
}





if (file_exists('db.php')) {



    require_once 'db.php';







    // Ensure core tables exist



    if (function_exists('initializeConsultationsTable')) {



        initializeConsultationsTable();



    }



    if (function_exists('initializeFeedbackTable')) {



        initializeFeedbackTable();



    }







    // Load consultations using helper so stats align with public portal



    if (function_exists('getConsultations')) {



        $consultations = getConsultations(null, 100, 0);



        foreach ($consultations as $c) {



            $consult_total++;



            if (($c['status'] ?? '') === 'active') {



                $consult_open++;



            }



            if (($c['status'] ?? '') === 'closed') {



                $consult_scheduled++;



            }



        }



    }







    // Load feedback entries grouped by consultation for Feedback Management
    $feedbackList = [];
    $consultationsFeedback = []; // New structure: consultations with their feedback
    
    if (function_exists('getFeedback')) {
        // Get all feedback
        $feedbackList = getFeedback([], 500, 0);
        
        // Build consultation feedback structure
        if (function_exists('getConsultationById')) {
            $consultationsWithFeedback = [];
            
            // Group feedback by consultation_id
            $feedbackByConsultation = [];
            foreach ($feedbackList as $feedback) {
                $consultId = (int)($feedback['consultation_id'] ?? 0);
                if ($consultId > 0) {
                    if (!isset($feedbackByConsultation[$consultId])) {
                        $feedbackByConsultation[$consultId] = [];
                    }
                    $feedbackByConsultation[$consultId][] = $feedback;
                }
            }
            
            // Build consultation list with feedback data
            foreach ($consultations as $consult) {
                $consultId = (int)($consult['id'] ?? 0);
                $feedbackCount = isset($feedbackByConsultation[$consultId]) ? count($feedbackByConsultation[$consultId]) : 0;
                $avgRating = 0;
                
                if ($feedbackCount > 0) {
                    $totalRating = 0;
                    $ratedCount = 0;
                    foreach ($feedbackByConsultation[$consultId] as $fb) {
                        if ($fb['rating'] !== null) {
                            $totalRating += (int)$fb['rating'];
                            $ratedCount++;
                        }
                    }
                    $avgRating = $ratedCount > 0 ? round($totalRating / $ratedCount, 1) : 0;
                }
                
                $consultationsWithFeedback[] = [
                    'consultation' => $consult,
                    'feedback_count' => $feedbackCount,
                    'feedback_list' => $feedbackByConsultation[$consultId] ?? [],
                    'avg_rating' => $avgRating
                ];
            }
            
            $consultationsFeedback = $consultationsWithFeedback;
        }
    }



}







// Load audit logs for display



$auditLogs = [];



$pageSize = 50;



$page = isset($_GET['audit_page']) ? (int)$_GET['audit_page'] : 1;



$offset = ($page - 1) * $pageSize;







$filters = [];



if (!empty($_GET['filter_admin'])) $filters['admin_user'] = $_GET['filter_admin'];



if (!empty($_GET['filter_action'])) $filters['action'] = $_GET['filter_action'];



if (!empty($_GET['filter_type'])) $filters['entity_type'] = $_GET['filter_type'];







$auditLogs = getAuditLogs($pageSize, $offset, $filters);



$totalLogs = getAuditLogCount($filters);



$totalPages = ceil($totalLogs / $pageSize);







// Split audit logs into admin vs user logs for tabbed display



$adminLogs = $auditLogs;



$totalAdminLogs = $totalLogs;







// Load user activity logs



$userLogs = function_exists('getUserLogs') ? getUserLogs($pageSize, 0, $filters) : [];



$totalUserLogs = function_exists('getUserLogsCount') ? getUserLogsCount($filters) : count($userLogs);







// Handle new announcement submission



// (now handled by AJAX in create_announcement.php)







// Mark post as reviewed by admin



if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_reviewed_post_id'])) {

    if ($is_read_only_super_admin) {

        header('Location: system-template-full.php');

        exit();

    }



    $postId = (int)$_POST['mark_reviewed_post_id'];



    $post = getPostById($postId);



    $admin_id = $_SESSION['user_id'] ?? null;



    $admin_user = $_SESSION['fullname'] ?? 'Admin';



    if ($post) {



        $user_id = $post['user_id'] ?? null;



        // Create notification to user



        if ($user_id) {



            createNotification($user_id, 'Your post has been reviewed by the administration.', 'notice');



        }



        if (function_exists('logAction')) {



            logAction($admin_id, $admin_user, "Marked post #$postId as reviewed", 'post', $postId, null, null, 'success', 'marked_reviewed');



        }



    }



    header('Location: system-template-full.php');



    exit();



}



$totalPages = ceil($totalLogs / $pageSize);



?>



<!DOCTYPE html>



<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        /* Consultation overview header + button layout */
        body.section-public-consultation .dashboard-header-content { display:flex; flex-wrap:wrap; gap:1rem; justify-content:space-between; align-items:center; }
        body.section-public-consultation .dashboard-title { min-width:220px; }
        body.section-public-consultation .dashboard-header-actions { display:flex; flex-wrap:wrap; gap:0.75rem; justify-content:flex-end; align-items:center; }
        body.section-public-consultation .dashboard-header-actions .btn-action { min-width:9rem; }
        body.section-public-consultation .dashboard-header-actions .btn-action .bi { margin-right:0.5rem; }
        body.section-public-consultation .dashboard-stats-grid { display:grid; gap:1rem; grid-template-columns: repeat(2,minmax(0,1fr)); }
        @media (min-width: 1024px) {
            body.section-public-consultation .dashboard-stats-grid { grid-template-columns: repeat(4,minmax(0,1fr)); }
        }
    </style>



    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">



    <meta http-equiv="X-UA-Compatible" content="ie=edge">



    <meta name="theme-color" content="#dc2626">



    <meta name="apple-mobile-web-app-capable" content="yes">



    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">



    <meta name="format-detection" content="telephone=no">



    <title>PCMS - Public Consultation Management Portal | City of Valenzuela</title>



    <meta name="description" content="Public Consultation Management Portal - City Government of Valenzuela, Metropolitan Manila">



    <meta name="keywords" content="PCMS, Valenzuela, Public Consultation, Consultation Management">



    <link rel="icon" type="image/png" href="images/logo.webp">



    <link rel="apple-touch-icon" href="images/logo.webp">



    <!-- Google Fonts: Plus Jakarta Sans & Inter (LACS Dashboard Font) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Inter', '"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                        }
                    }
                }
            };
        }
    </script>

    <style>
        *, ::before, ::after, html, body, button, input, select, textarea, table, th, td, h1, h2, h3, h4, h5, h6, p, span, div, a, li, label {
            font-family: 'Inter', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>

    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



    



    <!-- Prevent dark mode flicker - must run before page renders -->



    <script>



        window.__SESSION_LOGGED_IN__ = true;
        
        // Initialize current user data for JavaScript role checks
        window.__CURRENT_USER__ = {
            id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
            name: <?php echo json_encode($_SESSION['fullname'] ?? 'User'); ?>,
            email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
            role: <?php echo json_encode($_SESSION['role'] ?? 'citizen'); ?>
        };
        
        // Initialize role flags for JavaScript access control
        window.__IS_ADMIN__ = <?php echo json_encode($is_admin); ?>;
        window.__IS_SUPER_ADMIN__ = <?php echo json_encode($is_super_admin); ?>;
        window.__IS_RESOURCE_PERSON__ = <?php echo json_encode($is_resource_person); ?>;

        if (localStorage.getItem('theme') === 'dark') {



            document.documentElement.classList.add('dark');



        }



        



        // Check if user is logged in (for demo purposes)



        // In production, this would check actual session/token



        if (!window.__SESSION_LOGGED_IN__ && !localStorage.getItem('isLoggedIn') && !sessionStorage.getItem('isLoggedIn')) {



            // Redirect to login if not logged in



            window.location.href = 'login.php';



        }



        



        // Clear sidebar collapsed state for fresh start (can be removed after testing)



        // localStorage.removeItem('sidebarCollapsed');



    </script>



    



    <link rel="stylesheet" href="styles.css">



    



    <!-- Ensure sidebar is visible on desktop (when not collapsed) -->



    <style>
        /* Read-only state for super admin: dim and disable pointer interactions on action buttons */
        .readonly-locked { pointer-events: none; opacity: 0.65; }
        .readonly-locked span { cursor: not-allowed; }

        /* Make audit log tables responsive to avoid breaking layout */
        .audit-section .overflow-x-auto { overflow-x: auto; }
        .audit-section table { table-layout: fixed; width: 100%; }
        .audit-section th, .audit-section td { word-break: break-word; white-space: normal; }



        @media (min-width: 768px) {



            #sidebar:not(.sidebar-collapsed) {



                display: flex !important;



                transform: translateX(0) !important;



                position: relative !important;



            }



            /* Desktop: show sidebar toggle, hide mobile elements */



            .desktop-toggle {



                display: flex !important;



            }



            .mobile-toggle,



            .mobile-only {



                display: none !important;



            }



        }



        /* Mobile: hide desktop sidebar and toggle */



        @media (max-width: 767px) {



            #sidebar {



                display: none !important;



            }



            .desktop-toggle {



                display: none !important;



            }



            .mobile-toggle {



                display: flex !important;



            }



            .mobile-only {



                display: flex !important;



            }



        }



    </style>



    



    <!-- Ensure sidebar is visible on desktop (when not collapsed) -->



    <style>



        @media (min-width: 768px) {



            #sidebar:not(.sidebar-collapsed) {



                display: flex !important;



                transform: translateX(0) !important;



                position: relative !important;



            }



            /* Desktop: show sidebar toggle, hide mobile elements */



            .desktop-toggle {



                display: flex !important;



            }



            .mobile-toggle,



            .mobile-only {



                display: none !important;



            }



        }



        /* Mobile: hide desktop sidebar and toggle */



        @media (max-width: 767px) {



            #sidebar {



                display: none !important;



            }



            .desktop-toggle {



                display: none !important;



            }



            .mobile-toggle {



                display: flex !important;



            }



            .mobile-only {



                display: flex !important;



            }



        }



    </style>



    <style>



        /* Make wide tables scrollable on small screens */



        .responsive-table { overflow-x: auto; -webkit-overflow-scrolling: touch; }



    </style>



    <style>



        /* Override dropdown visibility */



        #profile-dropdown.hidden, #notifications-dropdown.hidden {



            display: none !important;



        }



        #profile-dropdown:not(.hidden), #notifications-dropdown:not(.hidden) {



            display: block !important;



            position: absolute !important;



            z-index: 9999 !important;



        }



        /* Better scroll indication for notifications */



        #notifications-list {



            scrollbar-width: thin;



            scrollbar-color: #e5e7eb #f9fafb;



        }



        #notifications-list::-webkit-scrollbar {



            width: 6px;



        }



        #notifications-list::-webkit-scrollbar-track {



            background: #f9fafb;



            border-radius: 3px;



        }



        #notifications-list::-webkit-scrollbar-thumb {



            background: #e5e7eb;



            border-radius: 3px;



        }



        #notifications-list::-webkit-scrollbar-thumb:hover {



            background: #d1d5db;



        }



    </style>



    <style>



        /* Override dropdown visibility */



        #profile-dropdown.hidden, #notifications-dropdown.hidden {



            display: none !important;



        }



        #profile-dropdown:not(.hidden), #notifications-dropdown:not(.hidden) {



            display: block !important;



            position: absolute !important;



            z-index: 9999 !important;



        }



    </style>



    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">



</head>



<body class="bg-gray-100 font-sans antialiased">



    <!-- Mobile open sidebar button -->



    <button id="open-mobile-sidebar" class="md:hidden fixed top-4 left-4 z-50 p-2 bg-red-700 text-white rounded-lg shadow-lg">



        <i class="bi bi-list"></i>



    </button>



    <!-- Mobile Sidebar Overlay -->



    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden opacity-0 pointer-events-none transition-all duration-300 ease-out"></div>



    



    <!-- Mobile Sidebar -->



    <div id="mobile-sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:hidden w-72 bg-gradient-to-b from-red-800 to-red-900 text-white z-50 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden flex flex-col shadow-2xl">



        <!-- Mobile sidebar header -->



        <div class="p-4 border-b border-red-700/50 sidebar-header">



            <div class="flex items-center justify-between">



                <div class="flex items-center space-x-3 sidebar-logo">



                    <div class="bg-white rounded-full p-1.5 shadow-lg">



                        <img src="images/logo.webp" alt="Valenzuela Logo" class="w-9 h-9 object-contain">



                    </div>



                    <div>



                        <h1 class="text-lg font-bold tracking-tight">PCMS</h1>



                        <p class="text-xs text-red-200">Consultation Management</p>



                    </div>



                </div>



                <button id="close-mobile-sidebar" class="text-white/80 p-2 hover:bg-red-700/50 hover:text-white rounded-lg transition-all duration-200 hover:rotate-90">



                    <i class="bi bi-x-lg text-xl"></i>



                </button>



            </div>



        </div>



        



        <!-- Mobile Navigation Menu (trimmed) -->



        <nav class="flex-1 py-4 px-3 overflow-y-auto">



            <!-- Public Consultation -->



            <div class="mt-2 mb-2 px-4">



                <p class="text-xs font-semibold text-red-300/80 uppercase tracking-wider">Public Consultation</p>



            </div>







            <a href="#" onclick="showSection('public-consultation')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1 bg-red-700">



                <i class="bi bi-people-fill mr-3 text-lg"></i>



                <span>Consultation Dashboard</span>



            </a>



            <a href="#" onclick="showSection('consultation-management')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">



                <i class="bi bi-journal-text mr-3 text-lg"></i>



                <span>Consultation Management</span>
                <span class="consultation-management-unread-icon ml-2 inline-flex items-center text-red-400 hidden" aria-hidden="true"><i class="bi bi-bell-fill"></i></span>



            </a>



            <a href="#" onclick="showSection('public-feedback-queue')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">



                <i class="bi bi-chat-square-text mr-3 text-lg"></i>



                <span>Public Feedback Queue</span>



            </a>

            <a href="#" onclick="showSection('pc-documents')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">
                <i class="bi bi-folder2-open mr-3 text-lg"></i>
                <span>Document Management</span>
            </a>

            <a href="#" onclick="showSection('reports')" class="nav-item flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1" data-section="reports">
                <i class="bi bi-file-earmark-bar-graph mr-3 text-lg"></i>
                <span>Reports</span>
            </a>







            <!-- Administration (keep) -->



            <div class="mt-4 mb-2 px-4">



                <p class="text-xs font-semibold text-red-300/80 uppercase tracking-wider">Administration</p>



            </div>



            <a href="#" onclick="showSection('users')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">



                <i class="bi bi-people mr-3 text-lg"></i>



                <span>User Management</span>



            </a>



            <a href="#" onclick="showSection('audit')" class="flex items-center px-4 py-3 text-white hover:bg-red-700/70 rounded-lg mb-1 transition-all duration-200 hover:translate-x-1">



                <i class="bi bi-shield-check mr-3 text-lg"></i>



                <span>Audit Log</span>



            </a>



        </nav>



        



        <!-- Mobile User Profile Section - Fixed at Bottom -->



        <div class="p-3 mt-auto border-t border-red-700/40">



            <!-- User Info -->



            <div class="flex items-center space-x-2.5 mb-2.5">



                <div class="w-9 h-9 rounded-full bg-red-700 flex items-center justify-center">



                    <i class="bi bi-person-fill text-white text-sm"></i>



                </div>



                <div class="flex-1 min-w-0">



                    <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($sidebar_display_name); ?></p>



                    <p class="text-xs text-red-300 truncate"><?php echo htmlspecialchars($sidebar_role_label); ?></p>



                </div>



            </div>



            



            <!-- Action Buttons - Side by Side -->



            <div class="flex gap-2">



                <a href="#" onclick="showSection('profile')" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium bg-red-700 hover:bg-red-600 text-white rounded-lg transition-colors">



                    <i class="bi bi-person-gear"></i>



                    <span>Profile</span>



                </a>



                <a href="#" onclick="logout()" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium bg-red-950 hover:bg-red-900 text-red-200 rounded-lg transition-colors">



                    <i class="bi bi-box-arrow-right"></i>



                    <span>Logout</span>



                </a>



            </div>



        </div>



    </div>



    



    <div class="flex h-screen overflow-hidden">



        <!-- Desktop Sidebar -->



        <aside id="sidebar" class="sidebar sidebar-expanded w-64 bg-gradient-to-b from-red-800 to-red-900 text-white flex-shrink-0 flex flex-col transition-all duration-300 ease-in-out animate-slide-in-left h-screen fixed md:relative z-30 -translate-x-full md:translate-x-0">



            <!-- Logo Section -->



            <div class="p-6 border-b border-red-700 animate-fade-in sidebar-logo">



                <a href="#" onclick="showSection('public-consultation')" class="flex items-center space-x-3 hover:opacity-80 transition-all duration-300 transform hover:scale-105 group">



                    <div class="bg-white rounded-full shadow-md flex items-center justify-center overflow-hidden transform transition-all duration-300 group-hover:scale-110 group-hover:rotate-6" style="width: 70px; height: 70px;">



                        <img src="images/logo.webp" alt="Valenzuela Logo" style="width: 100%; height: 100%;" class="object-contain">



                    </div>



                    <div class="transform transition-all duration-300 group-hover:translate-x-1 sidebar-text">



                        <h1 class="text-lg font-bold">PCMS</h1>



                        <p class="text-xs text-red-200">City of Valenzuela</p>



                    </div>



                </a>



            </div>



            



            <!-- Navigation Menu (trimmed) -->



            <nav class="flex-1 overflow-y-auto py-4">



                <div class="px-4 space-y-1">



                    <!-- Public Consultation -->



                    <div class="pt-2 pb-2 sidebar-text">



                        <p class="px-4 text-xs font-semibold text-red-300 uppercase tracking-wider">Public Consultation</p>



                    </div>



                    <a href="#" onclick="showSection('public-consultation')" class="nav-item" data-section="public-consultation">



                        <i class="bi bi-people-fill"></i>



                        <span class="sidebar-text">Consultation Dashboard</span>



                    </a>



                    <a href="#" onclick="showSection('consultation-management')" class="nav-item" data-section="consultation-management">



                        <i class="bi bi-journal-text"></i>



                        <span class="sidebar-text">Consultation Management</span>
                        <span class="consultation-management-unread-icon ml-2 inline-flex items-center text-red-400 hidden" aria-hidden="true"><i class="bi bi-bell-fill"></i></span>



                    </a>



                    <a href="#" onclick="showSection('public-feedback-queue')" class="nav-item" data-section="public-feedback-queue">



                        <i class="bi bi-chat-square-text"></i>



                        <span class="sidebar-text">Public Feedback Queue</span>



                    </a>

                    <a href="#" onclick="showSection('pc-documents')" class="nav-item" data-section="pc-documents">
                        <i class="bi bi-folder2-open"></i>
                        <span class="sidebar-text">Document Management</span>
                    </a>

                    <a href="#" onclick="showSection('reports')" class="nav-item" data-section="reports">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span class="sidebar-text">Reports</span>
                    </a>







                    <!-- Administration (keep user management) -->



                    <div class="pt-4 pb-2 sidebar-text">



                        <p class="px-4 text-xs font-semibold text-red-300 uppercase tracking-wider">Administration</p>



                    </div>



                    <!-- User Management -->
                    <a href="#" onclick="showSection('users')" class="nav-item" data-section="users">



                        <i class="bi bi-people"></i>



                        <span class="sidebar-text">User Management</span>



                    </a>



                    <a href="#" onclick="showSection('audit')" class="nav-item" data-section="audit">



                        <i class="bi bi-shield-check"></i>



                        <span class="sidebar-text">Audit Log</span>



                    </a>



                </div>



            </nav>



            



            <!-- User Info -->



            <div class="p-4 border-t border-red-700 sidebar-user">



                <div class="flex items-center space-x-3">



                    <div id="sidebar-profile-pic" class="bg-red-600 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0">



                        <i class="bi bi-person-fill text-white"></i>



                    </div>



                    <div class="flex-1 min-w-0 sidebar-text">



                        <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($sidebar_display_name); ?></p>



                        <p class="text-xs text-red-200 truncate"><?php echo htmlspecialchars($sidebar_role_label); ?></p>



                    </div>



                </div>



            </div>



        </aside>







        <!-- Main Content -->



        <div class="flex-1 flex flex-col overflow-x-hidden">



            <!-- Header / Navbar -->



            <nav class="bg-white shadow-md border-b border-gray-200 sticky top-0 z-40">



                <div class="px-4 sm:px-6 lg:px-8">



                    <div class="flex justify-between items-center h-16">



                        <!-- Left Side: Toggle buttons and Logo -->



                        <div class="flex items-center">



                            <!-- Sidebar Toggle Button (Desktop) - Always visible on md+ screens -->



                            <button id="sidebar-toggle" class="desktop-toggle items-center justify-center w-10 h-10 rounded-lg text-gray-600 bg-gray-50 hover:bg-gray-100 hover:text-red-600 focus:outline-none transition-all duration-200 border border-gray-200" title="Toggle Sidebar">



                                <i class="bi bi-layout-sidebar-inset text-xl"></i>



                            </button>



                            



                            <!-- Mobile Menu Button -->



                            <button id="mobile-menu-btn" class="mobile-toggle text-gray-600 hover:text-gray-900 focus:outline-none p-2 hover:bg-gray-100 rounded-lg transition-all duration-200">



                                <i class="bi bi-list text-2xl"></i>



                            </button>



                            



                            <!-- Logo (Mobile) -->



                            <div class="mobile-only flex items-center ml-2">



                                <img src="images/logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">



                            </div>

                        </div>





                        



                        <!-- Page Title & Breadcrumb -->



                        <div class="flex-1 flex items-center justify-center md:justify-start min-w-0">



                            <div class="ml-2 md:ml-4 min-w-0">



                                <h2 id="page-title" class="page-title text-base md:text-xl font-bold text-gray-800">Dashboard</h2>



                                <nav class="hidden md:flex text-sm text-gray-600 mt-1" aria-label="Breadcrumb">



                                    <a href="#" onclick="showSection('public-consultation')" class="hover:text-red-600">Home</a>



                                    <i class="bi bi-chevron-right mx-2 text-xs"></i>



                                    <span id="breadcrumb-current" class="text-gray-800 font-medium">Dashboard</span>



                                </nav>



                            </div>



                        </div>

 
                        



                        <!-- Right Side Actions hayss -->



                        <div class="flex items-center space-x-1 md:space-x-4">



                            <!-- Search Bar (Hidden on mobile) -->



                            <div class="hidden lg:block">



                                <div class="relative group">



                                    <input type="text" 



                                           id="quick-search"



                                           placeholder="Quick search documents... (Ctrl+K)"



                                           class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-200">



                                    <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 transition-all group-focus-within:text-red-600 group-focus-within:scale-110"></i>



                                </div>



                            </div>



                            



                            <!-- Dark Mode Toggle -->



                            <button id="theme-toggle" onclick="toggleTheme()" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition" title="Toggle dark mode">



                                <i class="bi bi-moon-fill text-lg md:text-xl dark-mode-icon"></i>



                                <i class="bi bi-sun-fill text-xl light-mode-icon hidden"></i>



                            </button>



                        



                            <!-- Notifications Bell -->



                            <div class="relative">



                                <button id="notifications-btn" onclick="toggleNotifDropdown(event)" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition relative" title="Notifications" style="cursor:pointer;">



                                    <i class="bi bi-bell text-lg md:text-xl"></i>



                                    <?php 
                                    if (file_exists(__DIR__ . '/DATABASE/notifications.php')) {
                                        require_once __DIR__ . '/DATABASE/notifications.php';
                                    } elseif (file_exists(__DIR__ . '/../DATABASE/notifications.php')) {
                                        require_once __DIR__ . '/../DATABASE/notifications.php';
                                    }
                                    $uid = (int)($_SESSION['user_id'] ?? 0);
                                    $serverUnreadCount = function_exists('getUnreadNotificationsCount') ? getUnreadNotificationsCount($uid) : 0;
                                    $serverNotifsList = function_exists('getUserNotifications') ? getUserNotifications($uid, 20) : [];
                                    ?>

                                    <span id="notif-badge" class="<?php echo $serverUnreadCount > 0 ? '' : 'hidden'; ?> absolute top-1 right-1 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
                                        <?php echo $serverUnreadCount > 99 ? '99+' : $serverUnreadCount; ?>
                                    </span>

                                </button>

                                <!-- Notifications Dropdown -->

                                <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 md:w-[380px] bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 flex flex-col overflow-hidden transition-all duration-200" style="z-index: 9999 !important; max-height: 480px;">
                                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                                        <h3 class="font-extrabold text-gray-900 text-sm md:text-base">Notifications</h3>
                                        <button type="button" onclick="pfpMarkAllNotificationsRead()" class="text-xs font-bold text-red-600 hover:text-red-700 transition cursor-pointer">Mark all read</button>
                                    </div>
                                    <div id="notifications-list" class="overflow-y-auto flex-1 min-h-0 divide-y divide-gray-100 max-h-[330px]">
                                        <?php if (!empty($serverNotifsList)): ?>
                                            <?php foreach ($serverNotifsList as $sn): 
                                                $isRead = !empty($sn['is_read']);
                                                $rawMsg = $sn['message'] ?? '';
                                                $msg = htmlspecialchars($rawMsg);
                                                $safeMsgAttr = str_replace(['\'', '"'], ['\\\'', '&quot;'], $rawMsg);
                                                $time = date('M d, Y H:i', strtotime($sn['created_at']));
                                                $title = 'System Notification';
                                                $type = strtolower($sn['type'] ?? 'info');
                                                $iconClass = 'bi-bell-fill text-blue-600 bg-blue-50 border-blue-100';

                                                if ($type === 'phms_feedback' || strpos($msg, 'PHMS') !== false) {
                                                    $title = '🏢 PHMS Hearing Feedback';
                                                    $iconClass = 'bi-building-fill-gear text-emerald-600 bg-emerald-50 border-emerald-100';
                                                } else if (strpos($msg, 'AI') !== false || $type === 'ai_brief') {
                                                    $title = '🤖 AI Committee Brief';
                                                    $iconClass = 'bi-robot text-purple-600 bg-purple-50 border-purple-100';
                                                } else if (strpos($msg, 'Feedback') !== false || strpos($msg, 'Proposal') !== false || $type === 'feedback') {
                                                    $title = '📩 Citizen Feedback';
                                                    $iconClass = 'bi-chat-left-text text-emerald-600 bg-emerald-50 border-emerald-100';
                                                } else if ($type === 'consultation' || strpos($msg, 'Survey') !== false) {
                                                    $title = '📊 Community Poll Update';
                                                    $iconClass = 'bi-square-poll text-amber-600 bg-amber-50 border-amber-100';
                                                }
                                            ?>
                                                <div data-id="<?php echo $sn['id']; ?>" onclick="pfpHandleNotificationClick(<?php echo $sn['id']; ?>, '<?php echo addslashes($type); ?>', '<?php echo $safeMsgAttr; ?>')" class="p-4 transition hover:bg-blue-50/70 flex items-start gap-3.5 relative cursor-pointer <?php echo !$isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'; ?>">
                                                    <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 mt-0.5 <?php echo $iconClass; ?>">
                                                        <i class="bi bi-bell text-base"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0 pr-3">
                                                        <div class="font-bold text-gray-900 text-xs leading-snug"><?php echo $title; ?></div>
                                                        <div class="text-xs text-gray-500 mt-0.5 leading-relaxed font-normal"><?php echo $msg; ?></div>
                                                        <div class="text-[11px] text-gray-400 mt-1 font-medium"><?php echo $time; ?></div>
                                                    </div>
                                                    <?php if (!$isRead): ?>
                                                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0 mt-1.5 ring-4 ring-red-50"></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="p-6 text-center text-gray-400 text-xs font-medium"><i class="bi bi-bell-slash text-2xl block mb-1 text-gray-300"></i> No notifications yet</div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="notifications-load-more-container" class="hidden px-4 py-3 bg-gray-50 border-t border-gray-100 text-center shrink-0 transition-all duration-200">
                                        <button id="btn-load-previous-notifs" type="button" onclick="pfpLoadPreviousNotifications()" class="w-full py-2 px-3 bg-white hover:bg-gray-100 text-red-600 border border-gray-200 rounded-xl text-xs font-extrabold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                            <i class="bi bi-clock-history text-sm"></i> View Previous Notifications
                                        </button>
                                    </div>
                                </div>



                            </div>



                        



                            <!-- User Profile Dropdown -->
                            <div class="relative">
                                <button id="profile-btn" type="button" class="flex items-center space-x-3 p-2 hover:bg-gray-100 rounded-lg transition" aria-haspopup="true" style="cursor:pointer; position:relative; z-index:10;">



                                    <div class="bg-red-600 rounded-full w-8 h-8 flex items-center justify-center text-white">



                                        <i class="bi bi-person-fill"></i>



                                    </div>



                                    <div class="hidden sm:block text-left">



                                        <p id="profile-name-display" class="text-sm font-medium text-gray-800 truncate max-w-[120px] md:max-w-none"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User')); ?></p>



                                        <p id="profile-role-display" class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>



                                    </div>



                                    <i class="bi bi-chevron-down text-gray-600 text-xs hidden sm:inline"></i>



                                </button>



                            



                                <!-- Profile Dropdown -->



                                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-xl border border-gray-200 z-50 animate-fade-in-up" style="background-color: white; z-index: 9999 !important;">



                                    <div class="p-4 border-b border-gray-200">



                                        <p id="profile-email-display" class="text-sm font-medium text-gray-800 break-all leading-tight"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>



                                        <p id="profile-dept-display" class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($_SESSION['department'] ?? ''); ?></p>



                                    </div>



                                    <div class="py-2">



                                        <a href="#" onclick="var nd=document.getElementById('notifications-dropdown'); if(nd)nd.classList.add('hidden'); var dd=document.getElementById('profile-dropdown'); if(dd)dd.classList.add('hidden'); showSection('profile'); return false;" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">



                                            <i class="bi bi-person mr-2"></i>My Profile



                                        </a>



                                        <a href="#" onclick="var nd=document.getElementById('notifications-dropdown'); if(nd)nd.classList.add('hidden'); var dd=document.getElementById('profile-dropdown'); if(dd)dd.classList.add('hidden'); showSection('settings'); return false;" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">



                                            <i class="bi bi-gear mr-2"></i>Settings



                                        </a>



                                        <a href="#" onclick="var nd=document.getElementById('notifications-dropdown'); if(nd)nd.classList.add('hidden'); var dd=document.getElementById('profile-dropdown'); if(dd)dd.classList.add('hidden'); showSection('help'); return false;" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">



                                            <i class="bi bi-question-circle mr-2"></i>Help & Support



                                        </a>



                                    </div>



                                    <div class="border-t border-gray-200 py-2">



                                        <a href="javascript:void(0);" onclick="logout(); return false;" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 cursor-pointer">



                                            <i class="bi bi-box-arrow-right mr-2"></i>Logout



                                        </a>



                                    </div>



                                </div>



                            </div>



                        </div>



                    </div>



                </div>



            </nav>







            <!-- Main Content Area -->



            <main class="flex-1 overflow-y-auto bg-gray-100 p-3 sm:p-4 lg:p-6">



                <!-- Content sections will be loaded here -->



                <div id="content-area">
                    <?php if ($is_super_admin): ?>
                    <!-- AUDIT LOG SECTION -->



                    <section id="audit-section" class="audit-section mb-6" style="display: none;">



                        <div class="bg-white rounded-lg shadow-md p-6">



                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">



                                <div>



                                    <h2 class="text-2xl font-bold text-gray-900">Audit Logs</h2>



                                    <p class="text-gray-600 text-sm mt-1">Track all administrative actions and system activities</p>



                                </div>



                                <div class="flex gap-2 w-full sm:w-auto">



                                    <button onclick="exportAuditLogs()" class="btn-secondary flex items-center justify-center gap-2 px-4 py-2 text-sm">



                                        <i class="bi bi-download"></i>



                                        <span class="hidden sm:inline">Export</span>



                                    </button>



                                </div>



                            </div>







                            <!-- Filters -->



                            <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">



                                <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">



                                    <i class="bi bi-funnel"></i> Filters



                                </h3>



                                <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">



                                    <div>



                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin User</label>



                                        <input type="text" name="filter_admin" placeholder="Filter by admin name" value="<?php echo htmlspecialchars($_GET['filter_admin'] ?? ''); ?>" class="input-field w-full">



                                    </div>



                                    <div>



                                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>



                                        <select name="filter_action" class="input-field w-full">



                                            <option value="">All Actions</option>



                                            <option value="login" <?php echo ($_GET['filter_action'] ?? '') === 'login' ? 'selected' : ''; ?>>Login</option>



                                            <option value="logout" <?php echo ($_GET['filter_action'] ?? '') === 'logout' ? 'selected' : ''; ?>>Logout</option>



                                            <option value="created" <?php echo ($_GET['filter_action'] ?? '') === 'created' ? 'selected' : ''; ?>>Created</option>



                                            <option value="updated" <?php echo ($_GET['filter_action'] ?? '') === 'updated' ? 'selected' : ''; ?>>Updated</option>



                                            <option value="deleted" <?php echo ($_GET['filter_action'] ?? '') === 'deleted' ? 'selected' : ''; ?>>Deleted</option>



                                            <option value="uploaded" <?php echo ($_GET['filter_action'] ?? '') === 'uploaded' ? 'selected' : ''; ?>>Uploaded</option>



                                        </select>



                                    </div>



                                    <div>



                                        <label class="block text-sm font-medium text-gray-700 mb-2">Entity Type</label>



                                        <select name="filter_type" class="input-field w-full">



                                            <option value="">All Types</option>



                                            <option value="user" <?php echo ($_GET['filter_type'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>



                                            <option value="document" <?php echo ($_GET['filter_type'] ?? '') === 'document' ? 'selected' : ''; ?>>Document</option>



                                            <option value="consultation" <?php echo ($_GET['filter_type'] ?? '') === 'consultation' ? 'selected' : ''; ?>>Consultation</option>



                                            <option value="system" <?php echo ($_GET['filter_type'] ?? '') === 'system' ? 'selected' : ''; ?>>System</option>



                                        </select>



                                    </div>



                                    <div class="sm:col-span-3 flex gap-2">



                                        <button type="submit" class="btn-primary flex items-center gap-2 px-4 py-2">



                                            <i class="bi bi-search"></i> Apply Filters



                                        </button>



                                        <a href="?audit_page=1" class="btn-secondary flex items-center gap-2 px-4 py-2">



                                            <i class="bi bi-arrow-clockwise"></i> Reset



                                        </a>



                                    </div>



                                </form>



                            </div>







                            <!-- Tabs for Admin/User Logs -->



                            <div class="mb-6 border-b border-gray-200">



                                <div class="flex gap-0">



                                    <button onclick="switchAuditTab('admin')" id="admin-tab-btn" class="px-6 py-3 font-medium text-gray-900 border-b-2 border-red-600 cursor-pointer hover:text-red-600">



                                        <i class="bi bi-shield-lock-fill mr-2"></i>Admin Actions <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full ml-2"><?php echo $totalAdminLogs; ?></span>



                                    </button>



                                    <button onclick="switchAuditTab('user')" id="user-tab-btn" class="px-6 py-3 font-medium text-gray-600 border-b-2 border-transparent cursor-pointer hover:text-gray-900">



                                        <i class="bi bi-people-fill mr-2"></i>User Activity <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full ml-2"><?php echo $totalUserLogs; ?></span>



                                    </button>



                                </div>



                            </div>







                            <!-- Admin Actions Table -->



                            <div id="admin-logs-section" class="max-h-[500px] overflow-y-auto overflow-x-auto relative shadow-inner rounded-xl border border-gray-200">



                                <?php if (empty($adminLogs)): ?>



                                    <div class="text-center py-12">



                                        <i class="bi bi-inbox text-5xl text-gray-300 block mb-3"></i>



                                        <p class="text-gray-500 text-lg">No admin actions found</p>



                                    </div>



                                <?php else: ?>



                                    <table class="w-full text-sm">



                                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10 shadow-xs">



                                            <tr>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Timestamp</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Admin User</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Action Taken</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Entity Type</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Entity ID</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">IP Address</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Details</th>



                                            </tr>



                                        </thead>



                                        <tbody class="divide-y divide-gray-200">



                                            <?php foreach ($adminLogs as $log): ?>



                                                <tr class="hover:bg-gray-50 transition-colors">



                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>



                                                    <td class="px-6 py-4 whitespace-nowrap">



                                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">



                                                            <i class="bi bi-shield-fill"></i>



                                                            <?php echo htmlspecialchars($log['username'] ?? ($log['admin_user'] ?? '')); ?>



                                                        </span>



                                                    </td>



                                                    <td class="px-6 py-4">



                                                        <?php

                                                            $actionText = function_exists('getAuditActionSummary') ? getAuditActionSummary($log) : ($log['details'] ?? $log['action'] ?? 'Performed an action');
                                                            $actionColor = 'gray';
                                                            $actionLower = strtolower((string)($log['action'] ?? ''));

                                                            if (strpos($actionLower, 'delete') !== false) $actionColor = 'red';
                                                            elseif (strpos($actionLower, 'create') !== false || strpos($actionLower, 'post') !== false || strpos($actionLower, 'announcement') !== false) $actionColor = 'green';
                                                            elseif (strpos($actionLower, 'update') !== false || strpos($actionLower, 'edit') !== false || strpos($actionLower, 'change') !== false) $actionColor = 'blue';
                                                            elseif (strpos($actionLower, 'login') !== false) $actionColor = 'indigo';

                                                        ?>



                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $actionColor; ?>-100 text-<?php echo $actionColor; ?>-800">



                                                            <?php echo htmlspecialchars($actionText); ?>



                                                        </span>



                                                    </td>

                                                    <td class="px-6 py-4 whitespace-nowrap">



                                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">



                                                            <?php echo htmlspecialchars(ucfirst($log['entity_type'] ?? 'N/A')); ?>



                                                        </span>



                                                    </td>



                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($log['entity_id'] ?? '-'); ?></td>



                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>



                                                    <td class="px-6 py-4">
 


                                                        <button onclick="showAuditDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" class="text-blue-600 hover:text-blue-800 font-medium text-sm">



                                                            View



                                                        </button>



                                                    </td>



                                                </tr>



                                            <?php endforeach; ?>



                                        </tbody>



                                    </table>



                                <?php endif; ?>



                            </div>







                            <!-- User Activity Table -->



                            <div id="user-logs-section" class="overflow-x-auto" style="display: none;">



                                <?php if (empty($userLogs)): ?>



                                    <div class="text-center py-12">



                                        <i class="bi bi-inbox text-5xl text-gray-300 block mb-3"></i>



                                        <p class="text-gray-500 text-lg">No user activities found</p>



                                    </div>



                                <?php else: ?>



                                    <table class="w-full text-sm">



                                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10 shadow-xs">



                                            <tr>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Timestamp</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">User</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Activity</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Type</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Entity ID</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">IP Address</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Status</th>



                                                <th class="px-6 py-3 text-left font-semibold text-gray-900">Details</th>



                                            </tr>



                                        </thead>



                                        <tbody class="divide-y divide-gray-200">



                                            <?php foreach ($userLogs as $log): ?>



                                                <tr class="hover:bg-gray-50 transition-colors">



                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>



                                                    <td class="px-6 py-4 whitespace-nowrap">



                                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">



                                                            <i class="bi bi-person-fill"></i>



                                                            <?php echo htmlspecialchars($log['admin_user']); ?>



                                                        </span>



                                                    </td>



                                                    <td class="px-6 py-4 whitespace-nowrap">



                                                        <?php



                                                            $actionColor = 'gray';



                                                            if (strpos(strtolower($log['action']), 'post') !== false) $actionColor = 'purple';



                                                            elseif (strpos(strtolower($log['action']), 'suggestion') !== false) $actionColor = 'indigo';



                                                        ?>



                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $actionColor; ?>-100 text-<?php echo $actionColor; ?>-800">



                                                            <?php echo htmlspecialchars($log['action']); ?>



                                                        </span>



                                                    </td>



                                                    <td class="px-6 py-4 whitespace-nowrap">



                                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">



                                                            <?php echo htmlspecialchars(ucfirst($log['entity_type'] ?? 'N/A')); ?>



                                                        </span>



                                                    </td>



                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($log['entity_id'] ?? '-'); ?></td>



                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>



                                                    <td class="px-6 py-4 whitespace-nowrap">



                                                        <?php



                                                            $statusColor = $log['status'] === 'success' ? 'green' : 'red';



                                                            $statusIcon = $log['status'] === 'success' ? 'check-circle-fill' : 'x-circle-fill';



                                                        ?>



                                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">



                                                            <i class="bi bi-<?php echo $statusIcon; ?>"></i>



                                                            <?php echo ucfirst($log['status']); ?>



                                                        </span>



                                                    </td>



                                                    <td class="px-6 py-4">



                                                        <button onclick="showAuditDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" class="text-blue-600 hover:text-blue-800 font-medium text-sm">



                                                            View



                                                        </button>



                                                    </td>



                                                </tr>



                                            <?php endforeach; ?>



                                        </tbody>



                                    </table>



                                <?php endif; ?>



                            </div>







                                    <!-- Pagination -->



                                    <div class="mt-6 flex items-center justify-between">



                                        <div class="text-sm text-gray-600">



                                            Showing <span class="font-medium"><?php echo (($page - 1) * $pageSize) + 1; ?></span> to 



                                            <span class="font-medium"><?php echo min($page * $pageSize, $totalLogs); ?></span> of 



                                            <span class="font-medium"><?php echo $totalLogs; ?></span> logs



                                        </div>



                                        <div class="flex gap-2">



                                            <?php if ($page > 1): ?>



                                                <a href="?audit_page=<?php echo $page - 1; ?><?php echo isset($_GET['filter_admin']) ? '&filter_admin=' . urlencode($_GET['filter_admin']) : ''; ?><?php echo isset($_GET['filter_action']) ? '&filter_action=' . urlencode($_GET['filter_action']) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($_GET['filter_type']) : ''; ?>" class="btn-secondary px-4 py-2">



                                                    <i class="bi bi-chevron-left"></i> Previous



                                                </a>



                                            <?php endif; ?>



                                            



                                            <div class="flex items-center gap-1">



                                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>



                                                    <a href="?audit_page=<?php echo $i; ?><?php echo isset($_GET['filter_admin']) ? '&filter_admin=' . urlencode($_GET['filter_admin']) : ''; ?><?php echo isset($_GET['filter_action']) ? '&filter_action=' . urlencode($_GET['filter_action']) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($_GET['filter_type']) : ''; ?>" class="px-3 py-1 rounded-lg text-sm font-medium transition-colors <?php echo $i === $page ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">



                                                        <?php echo $i; ?>



                                                    </a>



                                                <?php endfor; ?>



                                            </div>







                                            <?php if ($page < $totalPages): ?>



                                                <a href="?audit_page=<?php echo $page + 1; ?><?php echo isset($_GET['filter_admin']) ? '&filter_admin=' . urlencode($_GET['filter_admin']) : ''; ?><?php echo isset($_GET['filter_action']) ? '&filter_action=' . urlencode($_GET['filter_action']) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($_GET['filter_type']) : ''; ?>" class="btn-secondary px-4 py-2">



                                                    Next <i class="bi bi-chevron-right"></i>



                                                </a>



                                            <?php endif; ?>



                                        </div>



                                    </div>



                                </div>



                            </div>



                        </div>

                        </div>
                    </section>







                    <?php endif; ?>
                    <!-- DASHBOARD SECTION -->



                    <section id="dashboard-section" class="mb-6" style="display: none;">

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
                                <p class="text-gray-600 text-sm mt-1">Overview of consultations, feedback, users, and participation.</p>
                            </div>
                            <button type="button" onclick="openModuleReportModal('dashboard')" class="btn-outline px-4 py-2">
                                <i class="bi bi-file-earmark-bar-graph mr-2"></i>Generate Report
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">



                            <!-- Stats Cards -->



                            <div onclick="showSection('consultation-management-section')" style="cursor: pointer;" class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-red-600 hover:shadow-md transition-shadow">



                                <div class="text-gray-600 text-sm font-medium">Total Consultations</div>



                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo $consult_total ?? 0; ?></div>



                                <div class="text-gray-500 text-xs mt-2">All submissions</div>



                            </div>



                            <div onclick="showSection('consultation-management-section'); setTimeout(() => { var tab = document.getElementById('user-submissions-tab'); if(tab) tab.click(); }, 100);" style="cursor: pointer;" class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-600 hover:shadow-md transition-shadow">



                                <div class="text-gray-600 text-sm font-medium">Pending Review</div>



                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo $consult_open ?? 0; ?></div>



                                <div class="text-gray-500 text-xs mt-2">Citizen submissions awaiting approval</div>



                            </div>



                            <div onclick="showSection('public-feedback-queue')" style="cursor: pointer;" class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-600 hover:shadow-md transition-shadow">



                                <div class="text-gray-600 text-sm font-medium">Total Feedback</div>



                                <div class="text-3xl font-bold text-gray-900 mt-2"><?php echo count($feedbackList) ?? 0; ?></div>



                                <div class="text-gray-500 text-xs mt-2">Avg 0 per consultation</div>



                            </div>



                            <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-purple-600 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-gray-600 text-sm font-medium">Calendar</div>
                                    <div class="flex gap-1">
                                        <button onclick="dashboardCalendarChangeMonth(-1)" class="p-1 hover:bg-gray-100 rounded text-gray-600"><i class="bi bi-chevron-left"></i></button>
                                        <button onclick="dashboardCalendarChangeMonth(1)" class="p-1 hover:bg-gray-100 rounded text-gray-600"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                </div>
                                <div id="dashboard-calendar-label" class="text-center font-bold text-gray-900 text-sm mb-2"></div>
                                <div id="dashboard-calendar-grid" class="grid grid-cols-7 gap-1 text-xs"></div>
                            </div>



                        </div>



                        <!-- Consultation Forms Table for Superadmin -->

                        <?php

                        $isSurveyForm = function ($c) {

                            $mode = strtolower(trim((string)($c['response_mode'] ?? '')));

                            $surveyQuestion = trim((string)($c['survey_question'] ?? ''));

                            return $mode === 'survey' || ($surveyQuestion !== '' && $mode !== 'feedback');

                        };

                        $consultationForms = array_values(array_filter($consultations, function ($c) use ($isSurveyForm) {

                            return !$isSurveyForm($c);

                        }));

                        ?>

                        <div class="mt-6">

                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">

                                <h3 class="text-lg font-semibold text-gray-900">Consultation Forms</h3>

                            </div>

                            <div class="overflow-x-auto bg-white rounded-lg shadow-sm border border-gray-200">

                                <table class="w-full text-sm">

                                    <thead>

                                        <tr class="bg-gray-50 border-b border-gray-200">

                                            <th class="p-3 text-left font-semibold text-gray-900">Title</th>

                                            <th class="p-3 text-left font-semibold text-gray-900">Category</th>

                                            <th class="p-3 text-left font-semibold text-gray-900">Start</th>

                                            <th class="p-3 text-left font-semibold text-gray-900">End</th>

                                            <th class="p-3 text-left font-semibold text-gray-900">Status</th>

                                            <th class="p-3 text-left font-semibold text-gray-900">Action</th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php if (empty($consultationForms)): ?>

                                            <tr><td colspan="6" class="p-4 text-center text-gray-400">No consultation forms found.</td></tr>

                                        <?php else: foreach ($consultationForms as $c): ?>

                                            <?php $status = strtolower((string)($c['status'] ?? 'draft')); ?>

                                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">

                                                <td class="p-3"><?= htmlspecialchars($c['title'] ?? '-') ?></td>

                                                <td class="p-3"><?= htmlspecialchars($c['category'] ?? '-') ?></td>

                                                <td class="p-3 text-xs text-gray-600"><?php echo !empty($c['start_date']) ? htmlspecialchars(date('M d, Y', strtotime($c['start_date']))) : '-' ?></td>

                                                <td class="p-3 text-xs text-gray-600"><?php echo !empty($c['end_date']) ? htmlspecialchars(date('M d, Y', strtotime($c['end_date']))) : '-' ?></td>

                                                <td class="p-3">

                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium

                                                        <?= $status === 'active' ? 'bg-green-100 text-green-800' : (($status === 'closed') ? 'bg-gray-200 text-gray-800' : 'bg-yellow-100 text-yellow-800') ?>">

                                                        <?= htmlspecialchars(ucfirst($status)) ?>

                                                    </span>

                                                </td>

                                                <td class="p-3">

                                                    <button onclick="viewConsultation(<?= (int)$c['id'] ?>)" class="text-blue-600 hover:text-blue-800 text-xs font-medium transition-colors">View</button>

                                                </td>

                                            </tr>

                                        <?php endforeach; endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>



                    </section>



                    <!-- REPORTS SECTION -->
                    <section id="reports-section" class="mb-6" style="display: none;">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Reports</h2>
                                    <p class="text-gray-600 text-sm mt-1">Overall system activity, consultation trends, survey participation, and feedback summary.</p>
                                </div>
                                <button type="button" onclick="openModuleReportModal('reports')" class="btn-outline px-4 py-2">
                                    <i class="bi bi-file-earmark-bar-graph mr-2"></i>Generate Report
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="text-sm font-medium text-gray-600">Overall Submissions</div>
                                    <div id="reports-consultations-total" class="text-2xl font-bold text-gray-900 mt-2"><?php echo (int)$report_overall['consultations_total']; ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Consultations captured in the system</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="text-sm font-medium text-gray-600">Pending Review</div>
                                    <div id="reports-pending-review" class="text-2xl font-bold text-gray-900 mt-2"><?php echo (int)$report_overall['pending_review']; ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Waiting for admin/committee action</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="text-sm font-medium text-gray-600">Survey Responses</div>
                                    <div id="reports-survey-responses" class="text-2xl font-bold text-gray-900 mt-2"><?php echo (int)$report_overall['survey_responses']; ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Votes and survey submissions</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="text-sm font-medium text-gray-600">Feedback Entries</div>
                                    <div id="reports-feedback-total" class="text-2xl font-bold text-gray-900 mt-2"><?php echo (int)$report_overall['feedback_total']; ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Average rating: <span id="reports-feedback-avg"><?php echo number_format((float)$report_overall['feedback_avg_rating'], 1); ?></span></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Consultation Status Breakdown</h3>
                                    <?php if (empty($report_status_breakdown)): ?>
                                        <p class="text-sm text-gray-500">No consultation data available yet.</p>
                                    <?php else: ?>
                                        <div id="reports-status-breakdown" class="space-y-2">
                                            <?php foreach ($report_status_breakdown as $item): ?>
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="capitalize text-gray-700"><?php echo htmlspecialchars($item['status'] ?? 'unknown'); ?></span>
                                                    <span class="font-semibold text-gray-900"><?php echo (int)$item['total']; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="rounded-lg border border-gray-200 p-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Top Categories</h3>
                                    <?php if (empty($report_category_breakdown)): ?>
                                        <p class="text-sm text-gray-500">No category data available yet.</p>
                                    <?php else: ?>
                                        <div id="reports-category-breakdown" class="space-y-2">
                                            <?php foreach ($report_category_breakdown as $item): ?>
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-700"><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></span>
                                                    <span class="font-semibold text-gray-900"><?php echo (int)$item['total']; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Recent Consultations</h3>
                                    <?php if (empty($report_recent_consultations)): ?>
                                        <p class="text-sm text-gray-500">No recent consultations found.</p>
                                    <?php else: ?>
                                        <div id="reports-recent-consultations" class="space-y-2">
                                            <?php foreach ($report_recent_consultations as $item): ?>
                                                <div class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-b-0 last:pb-0">
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($item['title'] ?? '-'); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($item['category'] ?? '-'); ?> • <?php echo htmlspecialchars($item['type'] ?? '-'); ?></div>
                                                    </div>
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?php echo htmlspecialchars($item['status'] ?? '-'); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="rounded-lg border border-gray-200 p-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Recent Feedback</h3>
                                    <?php if (empty($report_recent_feedback)): ?>
                                        <p class="text-sm text-gray-500">No recent feedback found.</p>
                                    <?php else: ?>
                                        <div id="reports-recent-feedback" class="space-y-2">
                                            <?php foreach ($report_recent_feedback as $item): ?>
                                                <div class="flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-b-0 last:pb-0">
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($item['guest_name'] ?? '-'); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($item['category'] ?? '-'); ?> • Rating: <?php echo htmlspecialchars((string)($item['rating'] ?? '-')); ?></div>
                                                    </div>
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?php echo htmlspecialchars($item['status'] ?? '-'); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <script>
                                (function(){
                                    function updateReports(d){
                                        if(!d) return;
                                        try{
                                            var o = d.overall || d.data || {};
                                            function setSafeText(id, val) { var el = document.getElementById(id); if(el) el.innerText = val; }
                                            setSafeText('reports-consultations-total', o.consultations_total || 0);
                                            setSafeText('reports-pending-review', o.pending_review || 0);
                                            setSafeText('reports-survey-responses', o.survey_responses || 0);
                                            setSafeText('reports-feedback-total', o.feedback_total || 0);
                                            setSafeText('reports-feedback-avg', (o.feedback_avg_rating!==undefined)?parseFloat(o.feedback_avg_rating).toFixed(1):'0.0');

                                            var scont = document.getElementById('reports-status-breakdown');
                                            if(scont){ scont.innerHTML=''; ((d && d.status_breakdown)||(d && d.data && d.data.status_breakdown)||[]).forEach(function(it){ var div=document.createElement('div'); div.className='flex items-center justify-between text-sm'; div.innerHTML = '<span class="capitalize text-gray-700">'+(it.status||'')+'</span><span class="font-semibold text-gray-900">'+(it.total||0)+'</span>'; scont.appendChild(div); }); }

                                            var ccont = document.getElementById('reports-category-breakdown');
                                            if(ccont){ ccont.innerHTML=''; ((d && d.category_breakdown)||(d && d.data && d.data.category_breakdown)||[]).forEach(function(it){ var div=document.createElement('div'); div.className='flex items-center justify-between text-sm'; div.innerHTML = '<span class="text-gray-700">'+(it.category||'')+'</span><span class="font-semibold text-gray-900">'+(it.total||0)+'</span>'; ccont.appendChild(div); }); }

                                            var rcons = document.getElementById('reports-recent-consultations');
                                            if(rcons){ rcons.innerHTML=''; ((d && d.recent_consultations)||(d && d.data && d.data.recent_consultations)||[]).forEach(function(it){ var outer=document.createElement('div'); outer.className='flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-b-0 last:pb-0'; outer.innerHTML = '<div><div class="font-medium text-gray-900">'+(it.title||'-')+'</div><div class="text-xs text-gray-500">'+(it.category||'-')+' • '+(it.type||'-')+'</div></div><span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">'+(it.status||'-')+'</span>'; rcons.appendChild(outer); }); }

                                            var rfb = document.getElementById('reports-recent-feedback');
                                            if(rfb){ rfb.innerHTML=''; ((d && d.recent_feedback)||(d && d.data && d.data.recent_feedback)||[]).forEach(function(it){ var outer=document.createElement('div'); outer.className='flex items-center justify-between text-sm border-b border-gray-100 pb-2 last:border-b-0 last:pb-0'; outer.innerHTML = '<div><div class="font-medium text-gray-900">'+(it.guest_name||'-')+'</div><div class="text-xs text-gray-500">'+(it.category||'-')+' • Rating: '+(it.rating||'-')+'</div></div><span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">'+(it.status||'-')+'</span>'; rfb.appendChild(outer); }); }
                                        }catch(e){ console.warn('updateReports error', e); }
                                    }

                                    function fetchReports(){
                                        fetch('API/analytics_api.php').then(function(r){ return r.json(); }).then(function(res){ if(res && res.success && res.data){ updateReports(res.data); } }).catch(function(){});
                                    }

                                    if(typeof EventSource !== 'undefined'){
                                        try{
                                            var es = new EventSource('API/reports_sse.php');
                                            es.addEventListener('reports', function(e){ try{ updateReports(JSON.parse(e.data)); }catch(err){} });
                                            es.onerror = function(){ console.warn('SSE failed, switching to polling'); setTimeout(function(){ fetchReports(); setInterval(fetchReports,5000); },1000); es.close(); };
                                        }catch(err){ fetchReports(); setInterval(fetchReports,5000); }
                                    } else {
                                        fetchReports(); setInterval(fetchReports,5000);
                                    }
                                })();
                            </script>
                        </div>
                    </section>



                    <!-- USER MANAGEMENT SECTION -->



                    <section id="user-management-section" class="mb-6" style="display: none;">



                        <div class="bg-white rounded-lg shadow-md p-6">



                            <h2 class="text-2xl font-bold text-slate-800 mb-6">User Management</h2>

                            <!-- Filter Bar -->
                            <div class="filter-bar mb-6">
                                <div class="filter-group flex-1 min-w-[200px]">
                                    <label>Search Users</label>
                                    <input type="text" id="user-search" placeholder="Search by name or email..." onkeyup="filterUsers()">
                                </div>
                                <div class="filter-group">
                                    <label>Role</label>
                                    <select id="role-filter" onchange="filterUsers()">
                                        <option value="">All Roles</option>
                                        <option value="admin">Admin</option>
                                        <option value="staff">Staff</option>
                                        <option value="citizen">Citizen</option>
                                        <option value="resource person">Resource Person</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Status</label>
                                    <select id="status-filter" onchange="filterUsers()">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="pending">Pending</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>&nbsp;</label>
                                    <button type="button" onclick="openModuleReportModal('users')" class="btn-primary">
                                        <i class="fa-solid fa-file-earmark-bar-graph mr-1"></i> Generate Report
                                    </button>
                                </div>
                            </div>

                            <!-- Stat Cards -->
                            <?php
                                $allUsers = isset($users) ? $users : [];
                                $totalUsers = count($allUsers);
                                $adminCount = 0;
                                $citizenCount = 0;
                                $resourcePersonCount = 0;
                                
                                foreach ($allUsers as $u) {
                                    $role = strtolower($u['role'] ?? '');
                                    if ($role === 'admin' || $role === 'administrator' || $role === 'super admin' || $role === 'superadmin') {
                                        $adminCount++;
                                    } elseif ($role === 'citizen') {
                                        $citizenCount++;
                                    } elseif ($role === 'resource person' || $role === 'resource_person') {
                                        $resourcePersonCount++;
                                    }
                                }
                            ?>
                            <div class="stat-cards mb-6" style="grid-template-columns: repeat(3, 1fr); max-width: 600px;">
                                <div class="stat-card">
                                    <div class="label">Total Users</div>
                                    <div class="value blue"><?php echo $totalUsers; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="label">Administrators</div>
                                    <div class="value purple"><?php echo $adminCount; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="label">Citizens</div>
                                    <div class="value green"><?php echo $citizenCount + $resourcePersonCount; ?></div>
                                </div>
                            </div>

                            <!-- Resource Person Applications Tabs -->
                            <div class="bg-white rounded-xl border border-gray-200 p-1 flex gap-1 mb-6 shadow-sm">
                                <button onclick="showUserTab('citizens')" id="tab-citizens" class="flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 bg-valenzuela-red text-white shadow-sm">
                                    <i class="bi bi-people"></i> Citizen Submitters
                                </button>
                                <button onclick="showUserTab('pending')" id="tab-pending" class="flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 text-slate-600 hover:bg-gray-100">
                                    <i class="bi bi-clock-history"></i> Pending Applications
                                    <span id="pending-count" class="px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-700">0</span>
                                </button>
                                <button onclick="showUserTab('resource-persons')" id="tab-resource-persons" class="flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 text-slate-600 hover:bg-gray-100">
                                    <i class="bi bi-person-badge"></i> Resource Persons
                                </button>
                            </div>

                            <!-- Pending Applications Section -->
                            <div id="pending-applications-section" class="hidden">
                                <div class="mb-4">
                                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                        <i class="fa-solid fa-clock text-yellow-500"></i> Resource Person Applications
                                    </h3>
                                    <p class="text-slate-500 text-sm">Review and approve/reject resource person applications</p>
                                </div>
                                <div id="pending-applications-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div class="text-center py-12 text-slate-500 col-span-full">
                                        <i class="fa-solid fa-hourglass-split text-4xl mb-3 text-slate-300"></i>
                                        <p>Loading pending applications...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Resource Persons Section -->
                            <div id="resource-persons-section" class="hidden">
                                <div class="mb-4">
                                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                        <i class="fa-solid fa-user-check text-green-500"></i> Approved Resource Persons
                                    </h3>
                                    <p class="text-slate-500 text-sm">View all approved resource persons</p>
                                </div>
                                <div id="resource-persons-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div class="text-center py-12 text-slate-500 col-span-full">
                                        <i class="fa-solid fa-users text-4xl mb-3 text-slate-300"></i>
                                        <p>Loading resource persons...</p>
                                    </div>
                                </div>
                            </div>



                            



                            <!-- Citizen Submitters Section -->
                            <div id="citizens-section">
                                <div class="admin-card">
                                    <div class="admin-card-header">User Accounts</div>
                                    <div class="overflow-x-auto">
                                        <table class="admin-table" id="users-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                    $citizenUsers = isset($citizens) ? $citizens : [];
                                                    if (empty($citizenUsers)) {
                                                        echo '<tr><td colspan="5" class="text-center py-8 text-slate-500">No citizens found</td></tr>';
                                                    } else {
                                                        foreach ($citizenUsers as $u) {
                                                            $role = strtolower($u['role'] ?? 'citizen');
                                                            $roleBadge = '<span class="badge" style="background:#f3e8ff;color:#9333ea">Citizen</span>';
                                                            
                                                            $status = isset($u['status']) && $u['status'] === 'active' ? 'active' : 'inactive';
                                                            $statusBadge = $status === 'active' ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Inactive</span>';
                                                            
                                                            echo '<tr class="user-row" data-name="' . strtolower($u['fullname'] ?? '') . '" data-email="' . strtolower($u['email'] ?? '') . '" data-role="' . $role . '" data-status="' . $status . '">';
                                                            echo '<td class="font-medium">' . htmlspecialchars($u['fullname'] ?? 'N/A') . '</td>';
                                                            echo '<td>' . htmlspecialchars($u['email'] ?? 'N/A') . '</td>';
                                                            echo '<td>' . $roleBadge . '</td>';
                                                            echo '<td>' . $statusBadge . '</td>';
                                                            echo '<td>
                                                                <button class="action-btn edit" type="button" title="Edit" onclick="editUser(' . ($u['id'] ?? 0) . ')"><i class="fa-solid fa-pen text-sm"></i></button>
                                                            </td>';
                                                            echo '</tr>';
                                                        }
                                                    }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>



                            </div>



                        </div>



                    </section>

                    <script>
                    function showUserTab(tabName) {
                        var cSec = document.getElementById('citizens-section');
                        var pSec = document.getElementById('pending-applications-section');
                        var rSec = document.getElementById('resource-persons-section');
                        
                        var tCit = document.getElementById('tab-citizens');
                        var tPen = document.getElementById('tab-pending');
                        var tRes = document.getElementById('tab-resource-persons');

                        [tCit, tPen, tRes].forEach(function(btn) {
                            if (btn) {
                                btn.classList.remove('bg-valenzuela-red', 'text-white', 'shadow-sm', 'text-slate-600', 'hover:bg-gray-100');
                            }
                        });

                        if (cSec) cSec.classList.add('hidden');
                        if (pSec) pSec.classList.add('hidden');
                        if (rSec) rSec.classList.add('hidden');

                        if (tabName === 'citizens') {
                            if (cSec) cSec.classList.remove('hidden');
                            if (tCit) tCit.classList.add('bg-valenzuela-red', 'text-white', 'shadow-sm');
                            if (tPen) tPen.classList.add('text-slate-600', 'hover:bg-gray-100');
                            if (tRes) tRes.classList.add('text-slate-600', 'hover:bg-gray-100');
                        } else if (tabName === 'pending') {
                            if (pSec) pSec.classList.remove('hidden');
                            if (tPen) tPen.classList.add('bg-valenzuela-red', 'text-white', 'shadow-sm');
                            if (tCit) tCit.classList.add('text-slate-600', 'hover:bg-gray-100');
                            if (tRes) tRes.classList.add('text-slate-600', 'hover:bg-gray-100');
                            loadPendingResourcePersonApplications();
                        } else if (tabName === 'resource-persons') {
                            if (rSec) rSec.classList.remove('hidden');
                            if (tRes) tRes.classList.add('bg-valenzuela-red', 'text-white', 'shadow-sm');
                            if (tCit) tCit.classList.add('text-slate-600', 'hover:bg-gray-100');
                            if (tPen) tPen.classList.add('text-slate-600', 'hover:bg-gray-100');
                            loadApprovedResourcePersons();
                        }
                    }

                    function loadPendingResourcePersonApplications() {
                        var container = document.getElementById('pending-applications-list');
                        var badge = document.getElementById('pending-count');
                        if (!container) return;

                        container.innerHTML = '<div class="text-center py-12 text-slate-500 col-span-full"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 text-red-600"></i><p>Loading pending applications...</p></div>';

                        fetch('API/resource_person_api.php?action=list_pending')
                            .then(r => r.json())
                            .then(res => {
                                if (!res.success || !res.data) {
                                    container.innerHTML = '<div class="text-center py-8 text-slate-500 col-span-full"><p>Failed to load applications.</p></div>';
                                    return;
                                }
                                var apps = res.data;
                                if (badge) badge.innerText = apps.length;

                                if (apps.length === 0) {
                                    container.innerHTML = '<div class="text-center py-12 text-slate-500 col-span-full"><i class="fa-solid fa-circle-check text-4xl mb-3 text-emerald-400"></i><p class="font-medium text-slate-700">No pending resource person applications</p></div>';
                                    return;
                                }

                                container.innerHTML = apps.map(function(app) {
                                    return `
                                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4 hover:shadow-md transition">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-base">${escapeHtml(app.fullname || 'Applicant')}</h4>
                                                <p class="text-xs text-slate-500">${escapeHtml(app.email || '')}</p>
                                            </div>
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pending</span>
                                        </div>

                                        <div class="space-y-2 text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                            <p><strong class="text-slate-800">Department:</strong> ${escapeHtml(app.department || 'N/A')}</p>
                                            <p><strong class="text-slate-800">Phone:</strong> ${escapeHtml(app.phone || 'N/A')}</p>
                                            <p><strong class="text-slate-800">Expertise:</strong> ${escapeHtml(app.expertise_areas || 'N/A')}</p>
                                            <p><strong class="text-slate-800">Qualifications:</strong> ${escapeHtml(app.qualifications || 'N/A')}</p>
                                        </div>

                                        <div class="flex gap-2 pt-1">
                                            <button onclick="approveResourcePerson(${app.id})" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-3 rounded-xl transition flex items-center justify-center gap-1">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                            <button onclick="rejectResourcePerson(${app.id})" class="flex-1 bg-rose-100 hover:bg-rose-200 text-rose-700 text-xs font-semibold py-2 px-3 rounded-xl transition flex items-center justify-center gap-1">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        </div>
                                    </div>`;
                                }).join('');
                            })
                            .catch(err => {
                                container.innerHTML = '<div class="text-center py-8 text-slate-500 col-span-full"><p>Error connecting to server.</p></div>';
                            });
                    }

                    function approveResourcePerson(userId, fullname) {
                        const modalId = 'custom-approve-modal';
                        let existingModal = document.getElementById(modalId);
                        if (existingModal) existingModal.remove();

                        const nameStr = fullname ? `for <strong class="text-slate-900">${fullname}</strong>` : 'this Resource Person application';

                        const modal = document.createElement('div');
                        modal.id = modalId;
                        modal.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4';
                        modal.innerHTML = `
                            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-slate-200 text-center relative space-y-5">
                                <button onclick="document.getElementById('${modalId}').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-xl font-bold">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-3xl">
                                    <i class="bi bi-person-check-fill"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-slate-900">Approve Resource Person</h3>
                                    <p class="text-xs text-slate-500 mt-1">Are you sure you want to approve ${nameStr}? They will gain official access to the Expert Portal.</p>
                                </div>
                                <div class="flex gap-3 pt-2">
                                    <button onclick="document.getElementById('${modalId}').remove()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 px-4 rounded-2xl font-semibold text-xs transition">
                                        Cancel
                                    </button>
                                    <button onclick="confirmApproveResourcePerson(${userId}, '${modalId}')" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-3 px-4 rounded-2xl font-semibold text-xs transition shadow-md flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="bi bi-check-circle-fill"></i> Confirm Approval
                                    </button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(modal);
                    }

                    function confirmApproveResourcePerson(userId, modalId) {
                        var formData = new FormData();
                        formData.append('user_id', userId);

                        fetch('API/resource_person_api.php?action=approve', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(data => {
                            var modal = document.getElementById(modalId);
                            if (modal) modal.remove();
                            if (data.success) {
                                alert('Resource Person application approved successfully!');
                                if (typeof loadPendingResourcePersonApplications === 'function') loadPendingResourcePersonApplications();
                                if (typeof loadPendingUserApplications === 'function') loadPendingUserApplications();
                            } else {
                                alert('Error: ' + (data.message || 'Approval failed'));
                            }
                        })
                        .catch(err => alert('Failed to approve application.'));
                    }

                    function rejectResourcePerson(userId) {
                        const modalId = 'custom-reject-modal';
                        let existingModal = document.getElementById(modalId);
                        if (existingModal) existingModal.remove();

                        const modal = document.createElement('div');
                        modal.id = modalId;
                        modal.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4';
                        modal.innerHTML = `
                            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 border border-slate-200 text-left relative space-y-4">
                                <button onclick="document.getElementById('${modalId}').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-xl font-bold">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl">
                                    <i class="bi bi-person-x-fill"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Reject Application</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Please provide a brief reason for rejecting this application.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Rejection Rationale *</label>
                                    <textarea id="reject-reason-input" rows="3" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none" placeholder="e.g. Incomplete credentials, irrelevant department..."></textarea>
                                </div>
                                <div class="flex gap-3 pt-2">
                                    <button onclick="document.getElementById('${modalId}').remove()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 px-4 rounded-2xl font-semibold text-xs transition">
                                        Cancel
                                    </button>
                                    <button onclick="confirmRejectResourcePerson(${userId}, '${modalId}')" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-2xl font-semibold text-xs transition shadow-md flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="bi bi-x-circle-fill"></i> Confirm Rejection
                                    </button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(modal);
                    }

                    function confirmRejectResourcePerson(userId, modalId) {
                        const reasonInput = document.getElementById('reject-reason-input');
                        const reason = reasonInput ? reasonInput.value.trim() : '';
                        if (!reason) {
                            alert('Please enter a reason for rejection.');
                            return;
                        }

                        var formData = new FormData();
                        formData.append('user_id', userId);
                        formData.append('reason', reason);

                        fetch('API/resource_person_api.php?action=reject', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(data => {
                            var modal = document.getElementById(modalId);
                            if (modal) modal.remove();
                            if (data.success) {
                                alert('Application rejected.');
                                if (typeof loadPendingResourcePersonApplications === 'function') loadPendingResourcePersonApplications();
                                if (typeof loadPendingUserApplications === 'function') loadPendingUserApplications();
                            } else {
                                alert('Error: ' + (data.message || 'Rejection failed'));
                            }
                        })
                        .catch(err => alert('Failed to reject application.'));
                    }

                    function loadApprovedResourcePersons() {
                        var container = document.getElementById('resource-persons-list');
                        if (!container) return;

                        container.innerHTML = '<div class="text-center py-12 text-slate-500 col-span-full"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 text-red-600"></i><p>Loading resource persons...</p></div>';

                        fetch('API/resource_person_api.php?action=list_approved')
                            .then(r => r.json())
                            .then(res => {
                                if (!res.success || !res.data) {
                                    container.innerHTML = '<div class="text-center py-8 text-slate-500 col-span-full"><p>Failed to load resource persons.</p></div>';
                                    return;
                                }
                                var list = res.data;
                                if (list.length === 0) {
                                    container.innerHTML = '<div class="text-center py-12 text-slate-500 col-span-full"><i class="fa-solid fa-user-check text-4xl mb-3 text-slate-300"></i><p>No approved resource persons registered yet.</p></div>';
                                    return;
                                }

                                container.innerHTML = list.map(function(rp) {
                                    return `
                                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3 hover:shadow-md transition">
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold text-sm">
                                                    <i class="bi bi-person-badge"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-slate-800 text-sm">${escapeHtml(rp.fullname || 'Resource Person')}</h4>
                                                    <p class="text-xs text-slate-500">${escapeHtml(rp.department || 'Department')}</p>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">Verified</span>
                                        </div>

                                        <div class="space-y-1.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
                                            <p><i class="bi bi-envelope mr-1.5 text-slate-400"></i>${escapeHtml(rp.email || 'N/A')}</p>
                                            <p><i class="bi bi-telephone mr-1.5 text-slate-400"></i>${escapeHtml(rp.phone || 'N/A')}</p>
                                            <p><i class="bi bi-award mr-1.5 text-slate-400"></i><strong>Expertise:</strong> ${escapeHtml(rp.expertise_areas || 'General')}</p>
                                        </div>
                                    </div>`;
                                }).join('');
                            })
                            .catch(err => {
                                container.innerHTML = '<div class="text-center py-8 text-slate-500 col-span-full"><p>Error fetching resource persons.</p></div>';
                            });
                    }

                    function escapeHtml(str) {
                        if (typeof str !== 'string') return '';
                        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        fetch('API/resource_person_api.php?action=list_pending')
                            .then(r => r.json())
                            .then(res => {
                                if (res.success && res.data) {
                                    var badge = document.getElementById('pending-count');
                                    if (badge) badge.innerText = res.data.length;
                                }
                            }).catch(function(){});
                    });
                    </script>







                    <!-- DOCUMENT MANAGEMENT SECTION -->



                    <section id="document-management-section" class="mb-6" style="display: none;">



                        <div class="bg-white rounded-lg shadow-md p-6">



                            <div class="flex justify-between items-center mb-6">



                                <div>



                                    <h2 class="text-2xl font-bold text-gray-900">Document Management</h2>



                                    <p class="text-gray-600 text-sm mt-1">Manage official documents and charters</p>



                                </div>



                                <button class="btn-primary px-4 py-2">Upload Document</button>



                            </div>



                            



                            <!-- Five Section Tabs: Consultation, Feedback, Survey, Reports, Document Versions -->
                            <div class="flex flex-wrap gap-2 mt-6 border-b border-gray-200">
                                <button onclick="filterDocumentsByGroup('consultation')" class="px-6 py-3 font-semibold text-sm border-b-2 border-red-600 text-red-600 hover:bg-red-50 doc-group-tab active" data-group="consultation">
                                    <i class="bi bi-chat-left-quote mr-2"></i>Consultation
                                </button>
                                <button onclick="filterDocumentsByGroup('feedback')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-blue-600 hover:text-blue-600 transition doc-group-tab" data-group="feedback">
                                    <i class="bi bi-hand-thumbs-up mr-2"></i>Feedback
                                </button>
                                <button onclick="filterDocumentsByGroup('survey')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-green-600 hover:text-green-600 transition doc-group-tab" data-group="survey">
                                    <i class="bi bi-bar-chart mr-2"></i>Survey
                                </button>
                                <button onclick="filterDocumentsByGroup('uploaded')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-amber-600 hover:text-amber-600 transition doc-group-tab" data-group="uploaded">
                                    <i class="bi bi-cloud-arrow-up-fill mr-2"></i>Uploaded
                                </button>
                                <button onclick="filterDocumentsByGroup('reports')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-purple-600 hover:text-purple-600 transition doc-group-tab" data-group="reports">
                                    <i class="bi bi-file-earmark-text mr-2"></i>Reports
                                </button>
                                <button onclick="filterDocumentsByGroup('versions')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-indigo-600 hover:text-indigo-600 transition doc-group-tab" data-group="versions">
                                    <i class="bi bi-clock-history mr-2"></i>Document Versions
                                </button>
                            </div>

                            <!-- Documents Table for Selected Group -->
                            <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10 shadow-xs">
                                            <tr>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Document Title</th>
                                                <th class="px-6 py-3 text-left font-semibold text-gray-700">User / Type</th>
                                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Status Tracker</th>
                                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Size</th>
                                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Downloads</th>
                                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="group-documents-table-body">
                                            <tr><td colspan="6" class="text-center text-gray-400 p-6">No documents in this group</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>



                        </div>



                    </section>







                    <!-- CONSULTATION MANAGEMENT SECTION -->



                    <section id="consultation-management-section" class="mb-6" style="display: none;">



                        <div class="bg-white rounded-lg shadow-md p-6">



                            <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">



                                <div>



                                    <h2 class="text-2xl font-bold text-red-800 mb-2">Consultation Overview</h2>



                                    <p class="text-gray-600">Manage all public consultations, track feedback, and monitor engagement</p>



                                </div>



                                <div class="flex items-center gap-2 flex-wrap">
    <button type="button" onclick="openPCCalendarModal()" class="btn-outline px-4 py-2" title="Open Consultation Calendar">
        <i class="bi bi-calendar3 mr-2"></i>Calendar
    </button>
    <button type="button" onclick="openModuleReportModal('consultations')" class="btn-outline px-4 py-2">
        <i class="bi bi-file-earmark-bar-graph mr-2"></i>Generate Report
    </button>
</div>

                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 md:mt-0">



                                    <div class="bg-red-100 rounded-lg p-4 text-center">



                                        <div class="text-xs text-gray-600">Total Consultations</div>



                                        <div class="text-2xl font-bold text-red-800 mt-1"><?= (int)$consult_total ?></div>



                                    </div>



                                    <div class="bg-red-100 rounded-lg p-4 text-center">



                                        <div class="text-xs text-gray-600">Active Consultations</div>



                                        <div class="text-2xl font-bold text-red-800 mt-1"><?= (int)$consult_open ?></div>



                                    </div>



                                    <div class="bg-red-100 rounded-lg p-4 text-center">



                                        <div class="text-xs text-gray-600">Closed Consultations</div>



                                        <div class="text-2xl font-bold text-red-800 mt-1"><?= (int)$consult_scheduled ?></div>



                                    </div>



                                </div>



                            </div>





                            <?php
                            $isSurveyForm = function ($c) {
                                $mode = strtolower(trim((string)($c['response_mode'] ?? '')));
                                $surveyQuestion = trim((string)($c['survey_question'] ?? ''));
                                return $mode === 'survey' || ($surveyQuestion !== '' && $mode !== 'feedback');
                            };
                            $consultationForms = array_values(array_filter($consultations, function ($c) use ($isSurveyForm) {
                                return !$isSurveyForm($c) && strtolower((string)($c['type'] ?? 'admin')) === 'admin';
                            }));
                            $surveyForms = array_values(array_filter($consultations, function ($c) use ($isSurveyForm) {
                                return $isSurveyForm($c) && strtolower((string)($c['type'] ?? 'admin')) === 'admin';
                            }));
                            $userSubmissions = array_values(array_filter($consultations, function ($c) {
                                return strtolower((string)($c['type'] ?? 'admin')) === 'user';
                            }));
                            ?>

                              <!-- Consultation Tab Filter -->
                              <div class="mb-4 flex items-center justify-between gap-2 flex-wrap border-b border-gray-200 pb-1">
                                  <div class="flex gap-2 flex-wrap">
                                      <button onclick="document.getElementById('admin-created-tab-content').style.display='block'; document.getElementById('user-submissions-table').style.display='none'; document.getElementById('assigned-to-me-table').style.display='none'; this.classList.add('border-red-600', 'border-b-2', 'text-red-600'); this.classList.remove('border-gray-200', 'text-gray-600'); document.querySelectorAll('.consult-tab-btn').forEach(b => { if(b !== this) { b.classList.remove('border-red-600', 'border-b-2', 'text-red-600'); b.classList.add('border-gray-200', 'text-gray-600'); } });" class="consult-tab-btn px-4 py-2 text-sm font-medium border-b-2 border-red-600 text-red-600">Admin Created</button>
                                      <button onclick="document.getElementById('admin-created-tab-content').style.display='none'; document.getElementById('user-submissions-table').style.display='block'; document.getElementById('assigned-to-me-table').style.display='none'; this.classList.add('border-red-600', 'border-b-2', 'text-red-600'); this.classList.remove('border-gray-200', 'text-gray-600'); document.querySelectorAll('.consult-tab-btn').forEach(b => { if(b !== this) { b.classList.remove('border-red-600', 'border-b-2', 'text-red-600'); b.classList.add('border-gray-200', 'text-gray-600'); } });" class="consult-tab-btn px-4 py-2 text-sm font-medium border-b-2 border-gray-200 text-gray-600 hover:text-red-600">User Submissions</button>
                                      <?php if ($is_resource_person || $is_admin_or_super): ?>
                                      <button onclick="document.getElementById('admin-created-tab-content').style.display='none'; document.getElementById('user-submissions-table').style.display='none'; document.getElementById('assigned-to-me-table').style.display='block'; this.classList.add('border-red-600', 'border-b-2', 'text-red-600'); this.classList.remove('border-gray-200', 'text-gray-600'); document.querySelectorAll('.consult-tab-btn').forEach(b => { if(b !== this) { b.classList.remove('border-red-600', 'border-b-2', 'text-red-600'); b.classList.add('border-gray-200', 'text-gray-600'); } });" class="consult-tab-btn px-4 py-2 text-sm font-medium border-b-2 border-gray-200 text-gray-600 hover:text-red-600">Assigned to Me</button>
                                      <?php endif; ?>
                                  </div>
                                  <button onclick="openDeclinedSubmissionsModal()" type="button" class="relative inline-flex items-center justify-center w-10 h-10 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl border border-rose-200 transition shadow-2xs cursor-pointer mb-1" title="View Declined Consultations Archive">
                                      <i class="bi bi-archive-fill text-rose-600 text-base"></i>
                                      <span id="declined-bin-count" class="absolute -top-1.5 -right-1.5 min-w-[20px] h-[20px] px-1 bg-rose-600 text-white rounded-full text-[10px] font-extrabold flex items-center justify-center border-2 border-white shadow-xs">0</span>
                                  </button>
                              </div>

                              <!-- Admin-Created Consultations Table (Default View) -->
                              <div id="admin-created-tab-content" class="mt-6">
                                  <div class="overflow-x-auto mb-8">
                                      <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="p-2 text-left">Title</th>
                                                <th class="p-2 text-left">Category</th>
                                                <th class="p-2 text-left">User</th>
                                                <th class="p-2 text-left">Start</th>
                                                <th class="p-2 text-left">End</th>
                                                <th class="p-2 text-left">Status</th>
                                                <th class="p-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($consultationForms)): ?>
                                                <tr><td colspan="7" class="text-center text-gray-400 p-4">No admin-created consultations found.</td></tr>
                                            <?php else: foreach ($consultationForms as $c): ?>
                                                <?php $status = strtolower((string)($c['status'] ?? 'draft')); ?>
                                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                    <td class="p-2"><?= htmlspecialchars($c['title'] ?? '-') ?></td>
                                                    <td class="p-2"><?= htmlspecialchars($c['category'] ?? '-') ?></td>
                                                    <td class="p-2">
                                                        <span class="inline-flex items-center gap-1">
                                                            <i class="bi bi-person-badge text-blue-600"></i>
                                                            Admin
                                                        </span>
                                                    </td>
                                                    <td class="p-2"><?= !empty($c['start_date']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['start_date']))) : '-' ?></td>
                                                    <td class="p-2"><?= !empty($c['end_date']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['end_date']))) : '-' ?></td>
                                                    <td class="p-2">
                                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium <?= $status === 'active' ? 'bg-green-100 text-green-800' : (($status === 'closed') ? 'bg-gray-200 text-gray-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                            <?= htmlspecialchars(ucfirst($status)) ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-2">
                                                        <div class="flex gap-1 flex-wrap <?php echo $is_read_only_super_admin ? 'readonly-locked' : ''; ?>">
                                                            <?php if (!$is_read_only_super_admin): ?>
                                                                <button onclick="openForwardToExpertModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($c['category'] ?? '')) ?>')" class="text-red-700 hover:text-red-900 text-xs font-bold bg-red-50 hover:bg-red-100 px-2 py-0.5 rounded border border-red-200 inline-flex items-center gap-1"><i class="bi bi-send-fill text-[10px]"></i> Forward to Expert</button>
                                                             <button onclick="openExportChooser(<?= (int)$c['id'] ?>)" class="text-purple-600 hover:text-purple-800 text-xs font-medium">Export</button>
                                                            <?php else: ?>
                                                                <span class="text-purple-600 text-xs">Export</span>
                                                            <?php endif; ?>
                                                            <button <?php echo $is_read_only_super_admin ? 'disabled' : 'onclick="viewConsultation(' . (int)$c['id'] . ')"'; ?> class="text-blue-600 <?= $is_read_only_super_admin ? 'text-blue-300' : 'hover:text-blue-800' ?> text-xs font-medium"><?= $is_read_only_super_admin ? 'View' : 'View' ?></button>
                                                            <?php if (!empty($c['user_email'])): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                    <button onclick="sendEmailReply(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email']) ?>', '<?= htmlspecialchars($c['title']) ?>')" class="text-green-600 hover:text-green-800 text-xs font-medium">Email Reply</button>
                                                                <?php else: ?>
                                                                    <span class="text-green-600 text-xs">Email Reply</span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if ($is_resource_person || $is_admin_or_super): ?>
                                                                <button onclick="uploadResolutionReport(<?= (int)$c['id'] ?>)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Upload Report</button>
                                                                <button onclick="requestAdditionalInfo(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email'] ?? '') ?>', '<?= htmlspecialchars($c['title']) ?>')" class="text-orange-600 hover:text-orange-800 text-xs font-medium">Request Info</button>
                                                            <?php endif; ?>
                                                            <?php if ($status !== 'archived'): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                     <?php if ($status === 'declined' || $status === 'rejected'): ?>
                                                                         <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Declined</span>
                                                                     <?php else: ?>
                                                                         <select onchange="updateConsultationStatus(<?= (int)$c['id'] ?>, this.value, event)" class="text-xs border rounded px-1 py-0.5">
                                                                        <option value="">Set Status</option>
                                                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                                                        <option value="viewed" <?= $status === 'viewed' ? 'selected' : '' ?>>Viewed</option>
                                                                        <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                                                                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                                        <option value="declined" <?= ($status === 'declined' || $status === 'rejected') ? 'selected' : '' ?>>Declined</option>
                                                                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                                    </select>
                                                                         <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openDeclineCitizenSubmissionModal(<?= (int)$c['id'] ?>)" class="text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Decline</button>
                                                                     <?php endif; ?>
                                                                <?php else: ?>
                                                                    <select disabled class="text-xs border rounded px-1 py-0.5 opacity-60 cursor-not-allowed">
                                                                        <option>Set Status</option>
                                                                    </select>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>


                              <div class="mt-8" id="survey-forms-table">
                                  <h3 class="text-lg font-semibold text-gray-900 mb-3">Survey Forms</h3>
                                  <div class="overflow-x-auto">
                                      <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="p-2 text-left">Title</th>
                                                <th class="p-2 text-left">Category</th>
                                                <th class="p-2 text-left">User</th>
                                                <th class="p-2 text-left">Survey Question</th>
                                                <th class="p-2 text-left">Start</th>
                                                <th class="p-2 text-left">End</th>
                                                <th class="p-2 text-left">Status</th>
                                                <th class="p-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($surveyForms)): ?>
                                                <tr><td colspan="8" class="text-center text-gray-400">No survey forms found.</td></tr>
                                            <?php else: foreach ($surveyForms as $c): ?>
                                                <?php $status = strtolower((string)($c['status'] ?? 'draft')); ?>
                                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                    <td class="p-2"><?= htmlspecialchars($c['title'] ?? '-') ?></td>
                                                    <td class="p-2"><?= htmlspecialchars($c['category'] ?? '-') ?></td>
                                                    <td class="p-2">
                                                        <span class="inline-flex items-center gap-1">
                                                            <i class="bi bi-person-badge text-blue-600"></i>
                                                            Admin
                                                        </span>
                                                    </td>
                                                    <td class="p-2"><?= htmlspecialchars($c['survey_question'] ?? '-') ?></td>
                                                    <td class="p-2"><?= !empty($c['start_date']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['start_date']))) : '-' ?></td>
                                                    <td class="p-2"><?= !empty($c['end_date']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['end_date']))) : '-' ?></td>
                                                    <td class="p-2">
                                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium
                                                            <?= $status === 'active' ? 'bg-green-100 text-green-800' : (($status === 'closed') ? 'bg-gray-200 text-gray-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                            <?= htmlspecialchars(ucfirst($status)) ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-2">
                                                          <div class="flex gap-1 flex-wrap <?php echo $is_read_only_super_admin ? 'readonly-locked' : ''; ?>">
                                                              <?php if (!$is_read_only_super_admin): ?>
                                                                  <button onclick="openForwardToExpertModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($c['category'] ?? '')) ?>')" class="text-red-700 hover:text-red-900 text-xs font-bold bg-red-50 hover:bg-red-100 px-2 py-0.5 rounded border border-red-200 inline-flex items-center gap-1"><i class="bi bi-send-fill text-[10px]"></i> Forward to Expert</button>
                                                             <button onclick="openExportChooser(<?= (int)$c['id'] ?>)" class="text-purple-600 hover:text-purple-800 text-xs font-medium">Export</button>
                                                              <?php else: ?>
                                                                  <span class="text-purple-600 text-xs">Export</span>
                                                              <?php endif; ?>
                                                              <?php if (strtolower((string)($c['type'] ?? '')) === 'user'): ?>
                                                                  <?php if (!$is_read_only_super_admin): ?>
                                                                      <button onclick="openScheduleModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email'] ?? '') ?>', '<?= htmlspecialchars($c['title'] ?? '') ?>')" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Schedule</button>
                                                                  <?php else: ?>
                                                                      <span class="text-indigo-600 text-xs">Schedule</span>
                                                                  <?php endif; ?>
                                                              <?php endif; ?>
                                                              <button <?php echo $is_read_only_super_admin ? 'disabled' : 'onclick="viewConsultation(' . (int)$c['id'] . ')"'; ?> class="text-blue-600 <?= $is_read_only_super_admin ? 'text-blue-300' : 'hover:text-blue-800' ?> text-xs font-medium"><?= $is_read_only_super_admin ? 'View' : 'View' ?></button>
                                                            <?php if (!empty($c['user_email'])): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                    <button onclick="sendEmailReply(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email']) ?>', '<?= htmlspecialchars($c['title']) ?>')" class="text-green-600 hover:text-green-800 text-xs font-medium">Email Reply</button>
                                                                <?php else: ?>
                                                                    <span class="text-green-600 text-xs">Email Reply</span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if ($is_resource_person || $is_admin_or_super): ?>
                                                                <button onclick="uploadResolutionReport(<?= (int)$c['id'] ?>)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Upload Report</button>
                                                                <button onclick="requestAdditionalInfo(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email'] ?? '') ?>', '<?= htmlspecialchars($c['title']) ?>')" class="text-orange-600 hover:text-orange-800 text-xs font-medium">Request Info</button>
                                                            <?php endif; ?>
                                                            <?php if ($status !== 'archived'): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                     <?php if ($status === 'declined' || $status === 'rejected'): ?>
                                                                         <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Declined</span>
                                                                     <?php else: ?>
                                                                         <select onchange="updateConsultationStatus(<?= (int)$c['id'] ?>, this.value, event)" class="text-xs border rounded px-1 py-0.5">
                                                                        <option value="">Set Status</option>
                                                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                                                        <option value="viewed" <?= $status === 'viewed' ? 'selected' : '' ?>>Viewed</option>
                                                                        <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                                                                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                                        <option value="declined" <?= ($status === 'declined' || $status === 'rejected') ? 'selected' : '' ?>>Declined</option>
                                                                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                                    </select>
                                                                         <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openDeclineCitizenSubmissionModal(<?= (int)$c['id'] ?>)" class="text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Decline</button>
                                                                     <?php endif; ?>
                                                                <?php else: ?>
                                                                    <select disabled class="text-xs border rounded px-1 py-0.5 opacity-60 cursor-not-allowed">
                                                                        <option>Set Status</option>
                                                                    </select>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                              <!-- User Submissions Table -->
                              <div id="user-submissions-table" class="mt-6" style="display: none;">
                                  <h3 class="text-lg font-semibold text-gray-900 mb-3">User Submissions</h3>
                                  <div class="overflow-x-auto">
                                      <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="p-2 text-left">Title</th>
                                                <th class="p-2 text-left">Category</th>
                                                <th class="p-2 text-left">User</th>
                                                <th class="p-2 text-left">Submitted</th>
                                                <th class="p-2 text-left">Status</th>
                                                <th class="p-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($userSubmissions)): ?>
                                                <tr><td colspan="6" class="text-center text-gray-400 p-4">No user submissions found.</td></tr>
                                            <?php else: foreach ($userSubmissions as $c): ?>
                                                <?php $status = strtolower((string)($c['status'] ?? 'draft')); ?>
                                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                    <td class="p-2"><?= htmlspecialchars($c['title'] ?? '-') ?></td>
                                                    <td class="p-2"><?= htmlspecialchars($c['category'] ?? '-') ?></td>
                                                    <td class="p-2">
                                                        <span class="inline-flex items-center gap-1">
                                                            <i class="bi bi-person text-gray-400"></i>
                                                            <?= htmlspecialchars($c['name'] ?: 'Citizen') ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-2"><?= !empty($c['created_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['created_at']))) : '-' ?></td>
                                                    <td class="p-2">
                                                        <?php if ($status === 'declined' || $status === 'rejected'): ?>
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                                <i class="bi bi-x-circle-fill text-rose-600 text-[10px]"></i> Declined
                                                            </span>
                                                        <?php elseif ($status === 'active'): ?>
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                                                <i class="bi bi-globe text-green-600 text-[10px]"></i> Active
                                                            </span>
                                                        <?php elseif ($status === 'closed'): ?>
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-800">
                                                                Closed
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                                <?= htmlspecialchars(ucfirst($status)) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-2">
                                                        <div class="flex gap-1 flex-wrap items-center <?php echo $is_read_only_super_admin ? 'readonly-locked' : ''; ?>">
                                                            <?php if (!$is_read_only_super_admin): ?>
                                                                <button onclick="openForwardToExpertModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($c['category'] ?? '')) ?>')" class="text-red-700 hover:text-red-900 text-xs font-bold bg-red-50 hover:bg-red-100 px-2 py-0.5 rounded border border-red-200 inline-flex items-center gap-1"><i class="bi bi-send-fill text-[10px]"></i> Forward to Expert</button>
                                                             <button onclick="openExportChooser(<?= (int)$c['id'] ?>)" class="text-purple-600 hover:text-purple-800 text-xs font-medium">Export</button>
                                                            <?php else: ?>
                                                                <span class="text-purple-600 text-xs">Export</span>
                                                            <?php endif; ?>
                                                            <?php if (strtolower((string)($c['type'] ?? '')) === 'user'): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                    <button onclick="openScheduleModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email'] ?? '') ?>', '<?= htmlspecialchars($c['title'] ?? '') ?>')" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Schedule</button>
                                                                <?php else: ?>
                                                                    <span class="text-indigo-600 text-xs">Schedule</span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <button <?php echo $is_read_only_super_admin ? 'disabled' : 'onclick="viewConsultation(' . (int)$c['id'] . ')"'; ?> class="text-blue-600 <?= $is_read_only_super_admin ? 'text-blue-300' : 'hover:text-blue-800' ?> text-xs font-medium"><?= $is_read_only_super_admin ? 'View' : 'View' ?></button>
                                                            <?php if (!empty($c['user_email'])): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                    <button onclick="sendEmailReply(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['user_email']) ?>', '<?= htmlspecialchars($c['title']) ?>')" class="text-green-600 hover:text-green-800 text-xs font-medium">Email Reply</button>
                                                                <?php else: ?>
                                                                    <span class="text-green-600 text-xs">Email Reply</span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if ($status !== 'archived'): ?>
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                    <?php if ($status === 'declined' || $status === 'rejected'): ?>
                                                                        <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Declined</span>
                                                                        <button type="button" onclick="event.preventDefault(); event.stopPropagation(); deleteConsultation(<?= (int)$c['id'] ?>)" class="text-xs font-bold text-white bg-red-600 hover:bg-red-700 px-2 py-0.5 rounded border border-red-700 inline-flex items-center gap-1 cursor-pointer" title="Permanently Delete/Trash Rejected Proposal"><i class="bi bi-trash-fill text-[10px]"></i> Trash</button>
                                                                    <?php else: ?>
                                                                        <select onchange="updateConsultationStatus(<?= (int)$c['id'] ?>, this.value, event)" class="text-xs border rounded px-1 py-0.5">
                                                                            <option value="">Set Status</option>
                                                                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                                                            <option value="viewed" <?= $status === 'viewed' ? 'selected' : '' ?>>Viewed</option>
                                                                            <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                                                                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                                            <option value="declined" <?= ($status === 'declined' || $status === 'rejected') ? 'selected' : '' ?>>Declined</option>
                                                                            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                                        </select>
                                                                        <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openDeclineCitizenSubmissionModal(<?= (int)$c['id'] ?>)" class="text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Decline</button>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <select disabled class="text-xs border rounded px-1 py-0.5 opacity-60 cursor-not-allowed">
                                                                        <option>Set Status</option>
                                                                    </select>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>



                        </div>



                    </section>







                    <!-- FEEDBACK MANAGEMENT SECTION -->



                    <section id="feedback-management-section" class="mb-6" style="display: none;">



                        <div class="bg-white rounded-lg shadow-md p-6">



                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 gap-3">



                                <div>



                                    <h2 class="text-2xl font-bold text-gray-900">Feedback Management</h2>



                                    <p class="text-gray-600 text-sm mt-1">Review and respond to user feedback</p>



                                </div>

                                <button type="button" onclick="openModuleReportModal('feedback')" class="btn-outline px-4 py-2">
                                    <i class="bi bi-file-earmark-bar-graph mr-2"></i>Generate Report
                                </button>

                            </div>



                            



                            <div class="space-y-4">
                                <?php if (empty($consultationsFeedback)): ?>
                                    <div class="text-center text-gray-400 py-8 text-sm">
                                        No consultations with feedback have been submitted yet.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($consultationsFeedback as $item): ?>
                                        <?php 
                                            $consultation = $item['consultation'];
                                            $feedbackCount = $item['feedback_count'];
                                            $feedbackList = $item['feedback_list'];
                                            $avgRating = $item['avg_rating'];
                                            $consultId = (int)($consultation['id'] ?? 0);
                                        ?>
                                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                                            <!-- Consultation Header - Clickable -->
                                            <div 
                                                class="cursor-pointer bg-gradient-to-r from-gray-50 to-gray-100 hover:from-gray-100 hover:to-gray-150 p-4 flex items-center justify-between transition-colors" 
                                                onclick="document.getElementById('feedback-content-<?= $consultId ?>').style.display = document.getElementById('feedback-content-<?= $consultId ?>').style.display === 'none' ? 'block' : 'none'; this.parentElement.querySelector('.toggle-icon-<?= $consultId ?>').classList.toggle('rotate-180');"
                                            >
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <h3 class="font-bold text-gray-900">
                                                            <?= htmlspecialchars($consultation['title'] ?? '-') ?>
                                                        </h3>
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <?= $feedbackCount ?> feedback
                                                        </span>
                                                    </div>
                                                    <p class="text-sm text-gray-600 mt-1">
                                                        Category: <span class="font-medium"><?= htmlspecialchars($consultation['category'] ?? '-') ?></span>
                                                        | Status: <span class="font-medium"><?= htmlspecialchars(ucfirst($consultation['status'] ?? 'draft')) ?></span>
                                                        <?php if ($avgRating > 0): ?>
                                                            | Avg Rating: <span class="font-medium text-yellow-600">★ <?= $avgRating ?>/5</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="toggle-icon-<?= $consultId ?> text-gray-400 transition-transform duration-200">
                                                    <i class="bi bi-chevron-down text-xl"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Feedback List - Hidden by default -->
                                            <div id="feedback-content-<?= $consultId ?>" style="display: none;">
                                                <?php if ($feedbackCount === 0): ?>
                                                    <div class="bg-gray-50 border-t border-gray-200 p-4 text-center text-gray-500 text-sm">
                                                        No feedback for this consultation yet
                                                    </div>
                                                <?php else: ?>
                                                    <div class="border-t border-gray-200">
                                                        <?php foreach ($feedbackList as $index => $feedback): ?>
                                                            <div class="<?= $index !== 0 ? 'border-t border-gray-100' : '' ?> p-4 hover:bg-gray-50 transition-colors">
                                                                <!-- Feedback Header -->
                                                                <div class="flex items-start justify-between mb-2">
                                                                    <div>
                                                                        <div class="font-medium text-gray-900">
                                                                            <?= htmlspecialchars($feedback['guest_name'] ?? 'Anonymous Guest') ?>
                                                                        </div>
                                                                        <div class="text-xs text-gray-500">
                                                                            <?= htmlspecialchars($feedback['guest_email'] ?? '') ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <?php if ($feedback['rating']): ?>
                                                                            <div class="text-lg font-bold text-yellow-500">
                                                                                ★ <?= (int)$feedback['rating'] ?>/5
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium mt-1
                                                                            <?= ($feedback['status'] ?? 'new') === 'new' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                                                            <?= htmlspecialchars(ucfirst($feedback['status'] ?? 'new')) ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Feedback Category and Date -->
                                                                <div class="text-sm text-gray-600 mb-3">
                                                                    <span class="inline-block mr-4">
                                                                        <strong>Category:</strong> <?= htmlspecialchars($feedback['category'] ?? '-') ?>
                                                                    </span>
                                                                    <span class="inline-block">
                                                                        <strong>Submitted:</strong> <?= !empty($feedback['created_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($feedback['created_at']))) : '-' ?>
                                                                    </span>
                                                                </div>
                                                                
                                                                <!-- Feedback Message -->
                                                                <div class="bg-gray-50 rounded p-3 mb-3 text-sm text-gray-700">
                                                                    <strong>Feedback:</strong>
                                                                    <p class="mt-2"><?= htmlspecialchars($feedback['message'] ?? '') ?></p>
                                                                </div>
                                                                
                                                                <!-- Admin Response -->
                                                                <?php if ($feedback['admin_response']): ?>
                                                                    <div class="bg-blue-50 rounded p-3 text-sm text-gray-700 border-l-4 border-blue-400">
                                                                        <strong class="text-blue-900">Admin Response:</strong>
                                                                        <p class="mt-1"><?= htmlspecialchars($feedback['admin_response']) ?></p>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Actions -->
                                                                <?php if (!$is_read_only_super_admin): ?>
                                                                    <div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">
                                                                        <button type="button" onclick="openFeedbackReplyModal(<?= (int)$feedback['id'] ?>)" class="text-xs px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                                                                            <i class="bi bi-reply mr-1"></i>Reply
                                                                        </button>
                                                                        <button type="button" onclick="updateFeedbackStatus(<?= (int)$feedback['id'] ?>, 'reviewed')" class="text-xs px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                                                                            <i class="bi bi-check-circle mr-1"></i>Mark Reviewed
                                                                        </button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>



                        </div>



                    </section>



                </div>



            </main>



            



            <!-- Footer -->



            <footer class="bg-white border-t border-gray-200">



                <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 md:py-4">



                    <!-- Desktop Layout -->



                    <div class="hidden md:flex justify-between items-center">



                        <div class="flex items-center space-x-3">



                            <img src="images/logo.webp" alt="Valenzuela" class="w-10 h-10 object-contain">



                            <div class="text-sm text-gray-600">



                                &copy; 2025 City Government of Valenzuela - PCMS. All rights reserved.



                            </div>



                        </div>



                        <div class="flex items-center space-x-6">



                            <a href="#" class="text-sm text-gray-600 hover:text-red-600">Privacy</a>



                            <a href="#" class="text-sm text-gray-600 hover:text-red-600">Terms</a>



                            <a href="#" class="text-sm text-gray-600 hover:text-red-600">Support</a>



                        </div>



                    </div>



                    



                    <!-- Mobile Layout -->



                    <div class="md:hidden text-center">



                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">



                            <img src="images/logo.webp" alt="Valenzuela" style="width: 24px; height: 24px; object-fit: contain;">



                            <span class="text-xs text-gray-600">&copy; 2025 PCMS</span>



                        </div>



                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">



                            <a href="#" class="text-xs text-gray-500 hover:text-red-600">Privacy</a>



                            <span class="text-gray-300">•</span>



                            <a href="#" class="text-xs text-gray-500 hover:text-red-600">Terms</a>



                            <span class="text-gray-300">•</span>



                            <a href="#" class="text-xs text-gray-500 hover:text-red-600">Support</a>



                        </div>



                    </div>



                </div>



            </footer>



        </div>



    </div>



    



    <!-- Toast Notification Container -->



    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>







    <!-- Upload Document Modal -->



    <div id="upload-modal" class="modal">



        <div class="modal-content p-6 max-w-2xl">



            <div class="flex items-center justify-between mb-4">



                <h3 class="text-xl font-bold text-gray-900">Upload Document</h3>



                <button onclick="closeModal('upload-modal')" class="text-gray-400 hover:text-gray-600">



                    <i class="bi bi-x-lg text-xl"></i>



                </button>



            </div>



            <form id="upload-form" onsubmit="handleDocumentUpload(event)">



                <div class="space-y-4">



                    <!-- File Upload Area -->



                    <div id="dropzone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-red-500 transition cursor-pointer">



                        <i class="bi bi-cloud-upload text-5xl text-gray-400 mb-2"></i>



                        <p class="text-gray-600 mb-2">Drag and drop your file here or click to browse</p>



                        <input type="file" id="file-input" class="hidden" accept=".pdf,.doc,.docx" onchange="handleFileSelect(event)">



                        <button type="button" onclick="document.getElementById('file-input').click()" class="btn-outline mt-2">



                            <i class="bi bi-folder2-open mr-2"></i>Select File



                        </button>



                        <p id="file-name" class="text-sm text-gray-500 mt-2"></p>



                    </div>



                    



                    <div class="grid grid-cols-2 gap-4">



                        <div>



                            <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>



                            <input type="text" name="reference" class="input-field" placeholder="ORD-2025-001" required>



                        </div>



                        <div>



                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>



                            <select name="type" class="input-field" required>



                                <option value="">Select type</option>



                                <option value="ordinance">Ordinance</option>



                                <option value="resolution">Resolution</option>



                                <option value="session">Session Minutes</option>



                                <option value="agenda">Agenda</option>



                                <option value="committee">Committee Report</option>



                            </select>



                        </div>



                    </div>



                    



                    <div>



                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>



                        <input type="text" name="title" class="input-field" placeholder="Enter document title" required>



                    </div>



                    



                    <div class="grid grid-cols-2 gap-4">



                        <div>



                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Date</label>



                            <input type="date" name="date" class="input-field" required>



                        </div>



                        <div>



                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>



                            <select name="status" class="input-field" required>



                                <option value="draft">Draft</option>



                                <option value="pending">Pending Review</option>



                                <option value="approved">Approved</option>



                            </select>



                        </div>



                    </div>



                    



                    <div>



                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>



                        <textarea name="description" class="input-field" rows="3" placeholder="Enter document description"></textarea>



                    </div>



                    



                    <div class="grid grid-cols-2 gap-4">



                        <div>



                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags (comma separated)</label>



                            <input type="text" name="tags" class="input-field" placeholder="budget, finance, 2025">



                        </div>



                    </div>



                </div>



                <div class="flex justify-end space-x-3 mt-6">



                    <button type="button" onclick="closeModal('upload-modal')" class="btn-outline">Cancel</button>



                    <button type="submit" class="btn-primary">



                        <i class="bi bi-upload mr-2"></i>Upload Document



                    </button>



                </div>



            </form>



        </div>



    </div>







    <!-- Document View Modal -->



    <div id="view-modal" class="modal">



        <div class="modal-content p-6 max-w-4xl">



            <div class="flex items-center justify-between mb-4">



                <h3 class="text-xl font-bold text-gray-900">Document Details</h3>



                <button onclick="closeModal('view-modal')" class="text-gray-400 hover:text-gray-600">



                    <i class="bi bi-x-lg text-xl"></i>



                </button>



            </div>



            <div id="document-details"></div>



        </div>



    </div>







    <!-- Audit Log Details Modal -->



    <div id="audit-modal" class="modal">



        <div class="modal-content p-6 max-w-2xl">



            <div class="flex items-center justify-between mb-4">



                <h3 class="text-xl font-bold text-gray-900">Audit Log Details</h3>



                <button onclick="closeModal('audit-modal')" class="text-gray-400 hover:text-gray-600">



                    <i class="bi bi-x-lg text-xl"></i>



                </button>



            </div>



            <div id="audit-details" class="space-y-4">



                <!-- Details will be populated by JavaScript -->



            </div>



        </div>



    </div>







    <!-- Consultation View Modal -->
    <div id="consultation-view-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-0 max-w-4xl w-full max-h-screen overflow-y-auto rounded-2xl shadow-2xl">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="bi bi-eye"></i>
                    Consultation Details
                </h3>
                <button onclick="closeModal('consultation-view-modal')" class="text-white hover:text-gray-200 transition-colors">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <div id="consultation-view-content" class="p-6 bg-gray-50">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>



    <!-- Announcement Detail Modal -->



    <div id="announcement-detail-modal" class="modal" style="display: none; align-items: center; justify-content: center;">



        <div class="modal-content p-6 max-w-4xl w-full max-h-screen overflow-y-auto">



            <div class="flex items-center justify-between mb-4">



                <h3 id="ann-detail-title" class="text-2xl font-bold text-gray-900"></h3>



                <button onclick="closeModal('announcement-detail-modal')" class="text-gray-400 hover:text-gray-600">



                    <i class="bi bi-x-lg text-xl"></i>



                </button>



            </div>



            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">



                <!-- Announcement Detail (Left) -->



                <div>



                    <div id="ann-detail-content" class="text-gray-800 mb-4"></div>



                    <div id="ann-detail-meta" class="text-sm text-gray-600 mb-4"></div>



                    <div class="flex gap-3 text-sm">



                        <button type="button" id="ann-like-btn" onclick="toggleAnnouncementAction(event, null, 'like')" class="flex items-center gap-1 text-gray-600 hover:text-red-600">



                            <i class="bi bi-heart-fill"></i><span id="ann-like-count">0</span>



                        </button>



                        <button type="button" id="ann-save-btn" onclick="toggleAnnouncementAction(event, null, 'save')" class="flex items-center gap-1 text-gray-600 hover:text-blue-600">



                            <i class="bi bi-bookmark-fill"></i><span id="ann-save-count">0</span>



                        </button>



                    </div>



                </div>



            </div>



        </div>



    </div>

    <!-- Export Modal -->
    
    <!-- FORWARD AI SUMMARY TO RESOURCE PERSON MODAL -->
    <div id="forward-expert-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 relative border border-slate-200 space-y-5">
            <button type="button" onclick="closeForwardToExpertModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>

            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-2xl font-bold">
                    <i class="bi bi-send-check"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Forward AI Summary & Consultation</h3>
                    <p class="text-xs text-slate-500" id="forward-modal-consult-title">Consultation Title</p>
                </div>
            </div>

            <form id="forward-expert-form" onsubmit="handleForwardToExpertSubmit(event)" class="space-y-4">
                <input type="hidden" name="consultation_id" id="forward-consultation-id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Select Resource Person (Subject Matter Expert)</label>
                    <select name="resource_person_id" id="forward-expert-select" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-white font-medium outline-none">
                        <option value="0">-- Auto-Dispatch to All Experts Matching Category --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Review & Annotation Deadline</label>
                    <select name="deadline_days" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-white font-medium outline-none">
                        <option value="3">3 Days (Urgent)</option>
                        <option value="7" selected>7 Days (Standard)</option>
                        <option value="14">14 Days (Extended Review)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Admin Instructions / Specific Focus Area</label>
                    <textarea name="instructions" rows="3" class="w-full p-3 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-red-500 outline-none" placeholder="Provide specific advisory instructions or policy questions for the expert..."></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeForwardToExpertModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-2xl font-bold text-xs transition">Cancel</button>
                    <button type="submit" id="forward-submit-btn" class="flex-1 bg-red-700 hover:bg-red-800 text-white py-3 rounded-2xl font-extrabold text-xs transition shadow-md flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="bi bi-send-fill"></i> Forward AI Summary
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="export-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-xl w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 id="export-modal-title" class="text-lg font-semibold text-gray-900">Export Consultation</h3>
                <button onclick="closeModal('export-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div id="export-modal-body" class="text-sm text-gray-700">
                <!-- Filled dynamically -->
            </div>
            <div id="export-modal-actions" class="mt-5 flex flex-wrap gap-2 justify-end">
                <button onclick="closeModal('export-modal')" class="btn-outline">Close</button>
            </div>
        </div>
    </div>

    <!-- Consultation Calendar Modal -->
    <div id="calendar-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-4xl w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Consultation Availability Calendar</h3>
                <button onclick="closeModal('calendar-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-3">
                        <button class="btn-outline px-2 py-1" onclick="calendarChangeMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                        <div id="calendar-month-label" class="text-sm font-semibold text-gray-800"></div>
                        <button class="btn-outline px-2 py-1" onclick="calendarChangeMonth(1)"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-xs text-center text-gray-500 mb-2">
                        <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                    </div>
                    <div id="calendar-grid" class="grid grid-cols-7 gap-1"></div>
                    <div class="mt-3 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span> Available</span>
                        <span class="inline-flex items-center gap-1 ml-3"><span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span> Holiday</span>
                        <span class="inline-flex items-center gap-1 ml-3"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span> Unavailable / Weekends</span>
                    </div>
                </div>
                <div class="w-full lg:w-80 border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">Selected Date</h4>
                    <div id="calendar-selected-date" class="text-sm text-gray-700 mb-3">None</div>
                    <div class="mb-2">
                        <label class="text-xs text-gray-600">Start Time</label>
                        <input id="calendar-start-time" type="time" class="input-field w-full">
                    </div>
                    <div class="mb-2">
                        <label class="text-xs text-gray-600">End Time</label>
                        <input id="calendar-end-time" type="time" class="input-field w-full">
                    </div>
                    <div class="mb-2">
                        <label class="text-xs text-gray-600">Max Consultations</label>
                        <input id="calendar-max" type="number" min="1" max="20" class="input-field w-full" value="5">
                    </div>
                    <div class="mb-2">
                        <label class="text-xs text-gray-600">Notes</label>
                        <textarea id="calendar-notes" class="input-field w-full" rows="2"></textarea>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <input id="calendar-available" type="checkbox" checked>
                        <label for="calendar-available" class="text-xs text-gray-600">Available</label>
                    </div>
                    <div class="space-y-2">
                        <button class="btn-primary w-full" onclick="saveCalendarAvailability()">Save Availability</button>
                        <button class="btn-secondary w-full" type="button" onclick="markCalendarUnavailable()">Mark Unavailable</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Consultation Modal -->
    <div id="schedule-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-xl w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Confirm Consultation Schedule</h3>
                <button onclick="closeModal('schedule-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="space-y-3">
                <input type="hidden" id="schedule-consultation-id">
                <input type="hidden" id="schedule-consultation-email">
                <input type="hidden" id="schedule-consultation-title">
                <div>
                    <label class="text-xs text-gray-600">Date</label>
                    <input id="schedule-date" type="date" class="input-field w-full">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-600">Start Time</label>
                        <input id="schedule-start" type="time" class="input-field w-full">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">End Time</label>
                        <input id="schedule-end" type="time" class="input-field w-full">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Meeting Platform</label>
                    <select id="schedule-platform" class="input-field w-full">
                        <option value="">Select</option>
                        <option value="Google Meet">Google Meet</option>
                        <option value="Zoom">Zoom</option>
                        <option value="In Person">In Person</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Meeting Link (if online)</label>
                    <input id="schedule-link" type="url" class="input-field w-full" placeholder="https://...">
                </div>
                <div>
                    <label class="text-xs text-gray-600">Notes</label>
                    <textarea id="schedule-notes" class="input-field w-full" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button class="btn-outline" onclick="closeModal('schedule-modal')">Cancel</button>
                <button class="btn-primary" onclick="confirmSchedule()">Confirm & Email</button>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div id="alert-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-md w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900" id="alert-title">Alert</h3>
                <button onclick="closeModal('alert-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <p class="text-gray-700 mb-6" id="alert-message"></p>
            <div class="flex justify-end gap-2">
                <button class="btn-primary" onclick="closeModal('alert-modal')">OK</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirm-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-md w-full">
            <div class="flex items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900" id="confirm-title">Confirm</h3>
            </div>
            <p class="text-gray-700 mb-6" id="confirm-message"></p>
            <div class="flex justify-end gap-2">
                <button class="btn-outline" onclick="closeModal('confirm-modal')">Cancel</button>
                <button class="btn-primary" onclick="executeConfirmCallback()">OK</button>
            </div>
        </div>
    </div>

    <!-- Resolution Report Upload Modal -->
    <div id="resolution-report-modal" class="modal hidden" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-lg w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Upload Resolution Report</h3>
                <button onclick="closeResolutionReportModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="space-y-4">
                <input type="hidden" id="resolution-consultation-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Report File</label>
                    <input type="file" id="resolution-file" accept=".pdf,.doc,.docx" class="w-full border rounded px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">Accepted formats: PDF, DOC, DOCX</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                    <textarea id="resolution-notes" rows="3" class="w-full border rounded px-3 py-2" placeholder="Add any notes about this resolution report..."></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button onclick="closeResolutionReportModal()" class="btn-outline">Cancel</button>
                    <button onclick="submitResolutionReport()" class="btn-primary">Upload Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Additional Info Modal -->
    <div id="request-info-modal" class="modal hidden" style="display: none; align-items: center; justify-content: center;">
        <div class="modal-content p-6 max-w-lg w-full">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Request Additional Information</h3>
                <button onclick="closeRequestInfoModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="space-y-4">
                <input type="hidden" id="request-info-consultation-id">
                <input type="hidden" id="request-info-user-email">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consultation</label>
                    <p id="request-info-consultation-title" class="text-sm text-gray-600 font-medium"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message to Citizen</label>
                    <textarea id="request-info-message" rows="4" class="w-full border rounded px-3 py-2" placeholder="Specify what additional information you need from the citizen..."></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button onclick="closeRequestInfoModal()" class="btn-outline">Cancel</button>
                    <button onclick="submitRequestInfo()" class="btn-primary">Send Request</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Global notification dropdown toggle - called directly from onclick
    function toggleNotifDropdown(e) {
        e.preventDefault();
        e.stopPropagation();
        var dd = document.getElementById('notifications-dropdown');
        var profileDD = document.getElementById('profile-dropdown');
        if (profileDD) { profileDD.classList.add('hidden'); profileDD.style.display = 'none'; }
        if (!dd) return;
        var isHidden = dd.classList.contains('hidden');
        if (isHidden) {
            dd.classList.remove('hidden');
            dd.style.display = 'block';
            dd.style.position = 'absolute';
            dd.style.zIndex = '9999';
            if (typeof loadNotifications === 'function') loadNotifications();
        } else {
            dd.classList.add('hidden');
            dd.style.display = 'none';
        }
    }
    </script>

    <script src="script.js?v=1003"></script>



    <script>



        window.__CURRENT_USER__ = {



            id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,



            name: <?php echo json_encode($_SESSION['fullname'] ?? ($_SESSION['username'] ?? '')); ?>,



            email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,



            role: <?php echo json_encode($_SESSION['role'] ?? ''); ?>



        };
        window.__IS_SUPER_ADMIN__ = <?php echo $is_super_admin ? 'true' : 'false'; ?>;
        window.__IS_RESOURCE_PERSON__ = <?php echo $is_resource_person ? 'true' : 'false'; ?>;



    </script>



    <script src="app-features.js?v=<?php echo time(); ?>&nocache=<?php echo rand(1000, 9999); ?>"></script>



    <script>



    document.addEventListener('DOMContentLoaded', function() {

        var profileBtn = document.getElementById('profile-btn');



        var profileDD = document.getElementById('profile-dropdown');



        var notifBtn = document.getElementById('notifications-btn');



        var notifDD = document.getElementById('notifications-dropdown');







        if (profileBtn && profileDD) {



            profileBtn.addEventListener('click', function(e) {



                e.preventDefault();



                e.stopPropagation();



                try {



                    if (notifDD) notifDD.classList.add('hidden');



                    profileDD.classList.toggle('hidden');



                    



                    // Force display styles



                    if (profileDD.classList.contains('hidden')) {



                        profileDD.style.display = 'none';



                    } else {



                        profileDD.style.display = 'block';



                        profileDD.style.position = 'absolute';



                        profileDD.style.zIndex = '9999';



                    }



                } catch (error) {



                    console.error('Profile dropdown error:', error);



                }



            });



        } else {



            console.warn('Profile button or dropdown not found:', {profileBtn: !!profileBtn, profileDD: !!profileDD});



        }







        // Notification toggle is now handled by onclick="toggleNotifDropdown(event)" on the button
        // No addEventListener needed here







        if (profileDD) {



            profileDD.addEventListener('click', function(e) { e.stopPropagation(); });



        }



        if (notifDD) {



            notifDD.addEventListener('click', function(e) { e.stopPropagation(); });



        }







        document.addEventListener('click', function(e) {



            // Only close if clicking outside both dropdowns and their buttons



            if (!e.target.closest('#profile-btn') && !e.target.closest('#profile-dropdown')) {



                if (profileDD) {



                    profileDD.classList.add('hidden');



                    profileDD.style.display = 'none';



                }



            }



            if (!e.target.closest('#notifications-btn') && !e.target.closest('#notifications-dropdown')) {



                if (notifDD) {



                    notifDD.classList.add('hidden');



                    notifDD.style.display = 'none';



                }



            }



        });



    });



    </script>



    



    <!-- Desktop Sidebar Toggle Functionality - Must run after DOM is ready -->



    <script>



        // ========================================



        // Desktop Sidebar Toggle Functionality



        // ========================================



        (function() {



            const sidebarToggle = document.getElementById('sidebar-toggle');



            const sidebar = document.getElementById('sidebar');



            const mainContent = sidebar?.nextElementSibling;



            



            if (!sidebarToggle || !sidebar) {



                console.log('Sidebar toggle or sidebar not found');



                return;



            }



            



            // Ensure sidebar has proper initial classes



            if (!sidebar.classList.contains('sidebar-collapsed')) {



                sidebar.classList.add('sidebar-expanded');



            }



            



            // Check for saved sidebar state - apply immediately without animation



            const sidebarState = localStorage.getItem('sidebarCollapsed');



            if (sidebarState === 'true') {



                // Apply collapsed state immediately (no animation on page load)



                sidebar.style.transition = 'none';



                sidebar.classList.remove('sidebar-expanded', 'w-64');



                sidebar.classList.add('sidebar-collapsed');



                sidebarToggle.classList.add('sidebar-hidden');



                



                // Re-enable transitions after a frame



                requestAnimationFrame(() => {



                    requestAnimationFrame(() => {



                        sidebar.style.transition = '';



                    });



                });



            }



            



            // Toggle sidebar on button click



            sidebarToggle.addEventListener('click', function(e) {



                e.preventDefault();



                e.stopPropagation();



                



                const isExpanded = sidebar.classList.contains('sidebar-expanded');



                



                // Add a subtle scale animation to the button



                this.style.transform = 'scale(0.9)';



                setTimeout(() => {



                    this.style.transform = '';



                }, 150);



                



                if (isExpanded) {



                    // Collapse sidebar with smooth animation



                    sidebar.classList.remove('sidebar-expanded', 'w-64');



                    sidebar.classList.add('sidebar-collapsed');



                    this.classList.add('sidebar-hidden');



                    localStorage.setItem('sidebarCollapsed', 'true');



                } else {



                    // Expand sidebar with smooth animation



                    sidebar.classList.remove('sidebar-collapsed');



                    sidebar.classList.add('sidebar-expanded', 'w-64');



                    this.classList.remove('sidebar-hidden');



                    localStorage.setItem('sidebarCollapsed', 'false');



                } 



            });



            



            console.log('Desktop sidebar toggle initialized');



        })();



        



        // ========================================



        // Logout Function - Defined globally



        // ========================================



        function logout() {
            showConfirm('Are you sure you want to logout?', function() {
                // Clear any stored session data
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('currentUser');
                sessionStorage.removeItem('isLoggedIn');
                sessionStorage.removeItem('currentUser');
                
                // Redirect to login page with logout success message
                window.location.href = 'login.php?logout=success';
            }, 'Confirm Logout');
            return false;



        }



        



        // ========================================



        // Mobile Sidebar Toggle - Inline backup



        // ========================================



        (function() {



            const mobileMenuBtn = document.getElementById('mobile-menu-btn');



            const mobileSidebar = document.getElementById('mobile-sidebar');



            const sidebarOverlay = document.getElementById('sidebar-overlay');



            const closeMobileSidebarBtn = document.getElementById('close-mobile-sidebar');



            



            function openMobileSidebar() {



                if (!mobileSidebar || !sidebarOverlay) return;



                



                // Show overlay



                sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');



                sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');



                



                // Slide in sidebar



                mobileSidebar.classList.remove('-translate-x-full');



                mobileSidebar.classList.add('translate-x-0');



                



                // Prevent body scroll



                document.body.style.overflow = 'hidden';



            }



            



            function closeMobileSidebar() {



                if (!mobileSidebar || !sidebarOverlay) return;



                



                // Hide overlay



                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');



                sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');



                



                // Slide out sidebar



                mobileSidebar.classList.add('-translate-x-full');



                mobileSidebar.classList.remove('translate-x-0');



                



                // Restore body scroll



                document.body.style.overflow = '';



            }



            



            // Event listeners



            if (mobileMenuBtn) {



                mobileMenuBtn.addEventListener('click', function(e) {



                    e.preventDefault();



                    e.stopPropagation();



                    openMobileSidebar();



                });



            }



            



            if (closeMobileSidebarBtn) {



                closeMobileSidebarBtn.addEventListener('click', function(e) {



                    e.preventDefault();



                    closeMobileSidebar();



                });



            }



            



            if (sidebarOverlay) {



                sidebarOverlay.addEventListener('click', closeMobileSidebar);



            }



            



            // Close on escape key



            document.addEventListener('keydown', function(e) {



                if (e.key === 'Escape' && mobileSidebar && mobileSidebar.classList.contains('translate-x-0')) {



                    closeMobileSidebar();



                }



            });



            



            // Close sidebar when clicking navigation links



            const mobileNavLinks = mobileSidebar?.querySelectorAll('nav a');



            mobileNavLinks?.forEach(function(link) {



                link.addEventListener('click', function() {



                    setTimeout(closeMobileSidebar, 200);



                });



            });



            



            console.log('Mobile sidebar toggle initialized');



        })();







        // ========================================



        // Section Switching Functionality



        // ========================================



        function switchSection(sectionId) {



            // Hide all sections



            const sections = document.querySelectorAll('section');



            sections.forEach(section => {



                section.style.display = 'none';



            });



            



            // Show selected section



            const selectedSection = document.getElementById(sectionId);



            if (selectedSection) {



                selectedSection.style.display = 'block';



            }



            



            // Close mobile sidebar if open



            const sidebarOverlay = document.getElementById('sidebar-overlay');



            const mobileNav = document.getElementById('mobile-nav');



            if (sidebarOverlay && mobileNav) {



                sidebarOverlay.classList.remove('active');



                mobileNav.classList.remove('active');



            }



        }







        // ========================================



        // Audit Log Tabs Functionality



        // ========================================



        function switchAuditTab(tab) {



            const adminBtn = document.getElementById('admin-tab-btn');



            const userBtn = document.getElementById('user-tab-btn');



            const adminSection = document.getElementById('admin-logs-section');



            const userSection = document.getElementById('user-logs-section');







            if (tab === 'admin') {



                // Show admin logs



                adminSection.style.display = 'block';



                userSection.style.display = 'none';



                



                // Update button styles



                adminBtn.classList.add('text-gray-900', 'border-red-600');



                adminBtn.classList.remove('text-gray-600', 'border-transparent');



                userBtn.classList.add('text-gray-600', 'border-transparent');



                userBtn.classList.remove('text-gray-900', 'border-red-600');



            } else if (tab === 'user') {



                // Show user logs



                adminSection.style.display = 'none';



                userSection.style.display = 'block';



                



                // Update button styles



                userBtn.classList.add('text-gray-900', 'border-red-600');



                userBtn.classList.remove('text-gray-600', 'border-transparent');



                adminBtn.classList.add('text-gray-600', 'border-transparent');



                adminBtn.classList.remove('text-gray-900', 'border-red-600');



            }



        }







        // ========================================



        // Show Audit Log Details Modal



        // ========================================



        function showAuditDetails(log) {



            const modal = document.getElementById('audit-modal');



            const detailsDiv = document.getElementById('audit-details');



            



            if (!modal || !detailsDiv) return;







            // Build HTML for the details



            const detailsHTML = `



                <div class="grid grid-cols-2 gap-4">



                    <div>



                        <label class="text-sm font-semibold text-gray-600">Timestamp</label>



                        <p class="text-gray-900">${new Date(log.timestamp).toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">Action Summary</label>



                        <p class="text-gray-900">${(log.details || log.action || 'Performed an action').toString()}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">Admin/User</label>



                        <p class="text-gray-900">${log.admin_user || 'System'}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">Action</label>



                        <p class="text-gray-900">${log.action || 'N/A'}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">Entity Type</label>



                        <p class="text-gray-900">${log.entity_type || 'N/A'}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">Entity ID</label>



                        <p class="text-gray-900">${log.entity_id || 'N/A'}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">IP Address</label>



                        <p class="text-gray-900 font-mono text-sm">${log.ip_address || 'N/A'}</p>



                    </div>



                    <div>



                        <label class="text-sm font-semibold text-gray-600">User Agent</label>



                        <p class="text-gray-900 text-sm break-words">${log.user_agent || 'N/A'}</p>



                    </div>



                </div>



                ${log.details ? `<div class="mt-4"><label class="text-sm font-semibold text-gray-600">Details</label><p class="text-gray-900 mt-2 p-3 bg-gray-50 rounded">${log.details}</p></div>` : ''}



            `;







            detailsDiv.innerHTML = detailsHTML;







            // Show modal



            modal.style.display = 'flex';



            modal.style.alignItems = 'center';



            modal.style.justifyContent = 'center';



        }







        function closeModal(modalId) {



            const modal = document.getElementById(modalId);



            if (modal) {



                modal.style.display = 'none';



            }



        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
            }
        }

        // ========================================
        // Show Custom Alert Modal (replace window.alert)
        // ========================================
        function showAlert(message, title = 'Alert') {
            document.getElementById('alert-title').textContent = title;
            document.getElementById('alert-message').textContent = message;
            openModal('alert-modal');
        }

        // ========================================
        // Show Custom Confirmation Modal (replace window.confirm)
        // ========================================
        let confirmCallback = null;
        function showConfirm(message, callback, title = 'Confirm') {
            confirmCallback = callback;
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-message').textContent = message;
            openModal('confirm-modal');
        }

        function executeConfirmCallback() {
            if (confirmCallback && typeof confirmCallback === 'function') {
                confirmCallback();
            }
            closeModal('confirm-modal');
            confirmCallback = null;
        }

        // ========================================

        // Consultation Management Functions

        // ========================================
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        // ========================================
        // Consultation Calendar (Admin Availability)
        // ========================================
        let calendarYear = new Date().getFullYear();
        let calendarMonth = new Date().getMonth(); // 0-based
        let calendarAvailability = {};
        let calendarHolidays = new Set();
        let selectedCalendarDate = null;

        function openConsultationCalendar() {
            calendarYear = new Date().getFullYear();
            calendarMonth = new Date().getMonth();
            fetchCalendarData();
            openModal('calendar-modal');
        }

        function calendarChangeMonth(delta) {
            calendarMonth += delta;
            if (calendarMonth < 0) { calendarMonth = 11; calendarYear -= 1; }
            if (calendarMonth > 11) { calendarMonth = 0; calendarYear += 1; }
            fetchCalendarData();
        }

        function fetchCalendarData() {
            const month = calendarMonth + 1;
            fetch(`API/consultation_availability_api.php?action=list_month&year=${calendarYear}&month=${month}`)
                .then(r => r.json())
                .then(data => {
                    calendarAvailability = {};
                    if (data && data.availability) {
                        data.availability.forEach(row => {
                            calendarAvailability[row.date] = row;
                        });
                    }
                    calendarHolidays = new Set(data && data.holidays ? data.holidays : []);
                    renderCalendar();
                })
                .catch(() => {
                    calendarAvailability = {};
                    calendarHolidays = new Set();
                    renderCalendar();
                });
        }

        function renderCalendar() {
            const label = document.getElementById('calendar-month-label');
            const grid = document.getElementById('calendar-grid');
            if (!grid || !label) return;

            const first = new Date(calendarYear, calendarMonth, 1);
            const last = new Date(calendarYear, calendarMonth + 1, 0);
            label.textContent = first.toLocaleString('en-GB', { month: 'long', year: 'numeric' });

            const startDay = (first.getDay() + 6) % 7; // Monday-first
            const totalDays = last.getDate();
            const cells = Math.ceil((startDay + totalDays) / 7) * 7;

            let html = '';
            for (let i = 0; i < cells; i++) {
                const dayNum = i - startDay + 1;
                const isCurrent = dayNum >= 1 && dayNum <= totalDays;
                const dateStr = isCurrent ? `${calendarYear}-${String(calendarMonth + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}` : '';
                const avail = dateStr ? calendarAvailability[dateStr] : null;
                const isHoliday = dateStr && calendarHolidays.has(dateStr);
                const isAvailable = avail ? Number(avail.is_available) === 1 : false;
                const isWeekend = dateStr ? (() => {
                    const d = new Date(dateStr + 'T00:00:00');
                    const wd = d.getDay();
                    return wd === 0 || wd === 6;
                })() : false;

                let cls = 'p-2 text-xs rounded border text-center cursor-pointer ';
                if (!isCurrent) cls += 'bg-gray-50 text-gray-300 border-gray-100 cursor-default';
                else if (isHoliday) cls += 'bg-yellow-50 text-yellow-700 border-yellow-200';
                else if (isAvailable) cls += 'bg-green-50 text-green-700 border-green-200';
                else if (isWeekend) cls += 'bg-red-50 text-red-700 border-red-200';
                else cls += 'bg-red-50 text-red-700 border-red-200';

                html += `<div class="${cls}" onclick="${isCurrent ? `selectCalendarDate('${dateStr}')` : ''}">${isCurrent ? dayNum : ''}</div>`;
            }
            grid.innerHTML = html;
        }

        function selectCalendarDate(dateStr) {
            selectedCalendarDate = dateStr;
            const label = document.getElementById('calendar-selected-date');
            if (label) label.textContent = dateStr;

            const slot = calendarAvailability[dateStr] || null;
            document.getElementById('calendar-start-time').value = slot ? slot.start_time.slice(0,5) : '09:00';
            document.getElementById('calendar-end-time').value = slot ? slot.end_time.slice(0,5) : '17:00';
            document.getElementById('calendar-max').value = slot ? slot.max_consultations : 5;
            document.getElementById('calendar-notes').value = slot ? (slot.notes || '') : '';
            document.getElementById('calendar-available').checked = slot ? Number(slot.is_available) === 1 : true;
        }

        function saveCalendarAvailability() {
            if (!selectedCalendarDate) {
                showAlert('Please select a date.');
                return;
            }

            // Mini dashboard calendar functions (reuse admin calendar data)
            let dashboardCalYear = new Date().getFullYear();
            let dashboardCalMonth = new Date().getMonth();
            function dashboardCalendarChangeMonth(delta) {
                dashboardCalMonth += delta;
                if (dashboardCalMonth < 0) { dashboardCalMonth = 11; dashboardCalYear -= 1; }
                if (dashboardCalMonth > 11) { dashboardCalMonth = 0; dashboardCalYear += 1; }
                renderDashboardCalendar();
            }

            function renderDashboardCalendar() {
                const label = document.getElementById('dashboard-calendar-label');
                const grid = document.getElementById('dashboard-calendar-grid');
                if (!label || !grid) return;

                // Ensure we have the month's data loaded into calendarAvailability
                const month = dashboardCalMonth + 1;
                fetch(`API/consultation_availability_api.php?action=list_month&year=${dashboardCalYear}&month=${month}`)
                    .then(r => r.json())
                    .then(data => {
                        calendarAvailability = {};
                        if (data && data.availability) data.availability.forEach(row => calendarAvailability[row.date] = row);
                        const holidays = new Set(data && data.holidays ? data.holidays : []);

                        const first = new Date(dashboardCalYear, dashboardCalMonth, 1);
                        const last = new Date(dashboardCalYear, dashboardCalMonth + 1, 0);
                        label.textContent = first.toLocaleString('en-GB', { month: 'long', year: 'numeric' });

                        const startDay = (first.getDay() + 6) % 7;
                        const totalDays = last.getDate();
                        const cells = Math.ceil((startDay + totalDays) / 7) * 7;

                        let html = '';
                        for (let i = 0; i < cells; i++) {
                            const dayNum = i - startDay + 1;
                            const isCurrent = dayNum >= 1 && dayNum <= totalDays;
                            const dateStr = isCurrent ? `${dashboardCalYear}-${String(dashboardCalMonth + 1).padStart(2,'0')}-${String(dayNum).padStart(2,'0')}` : '';
                            const avail = dateStr ? calendarAvailability[dateStr] : null;
                            const isHoliday = dateStr && holidays.has(dateStr);
                            const isAvailable = avail ? Number(avail.is_available) === 1 && Number(avail.current_consultations) < Number(avail.max_consultations) : false;
                            const isWeekend = dateStr ? (() => {
                                const d = new Date(dateStr + 'T00:00:00');
                                const wd = d.getDay();
                                return wd === 0 || wd === 6;
                            })() : false;

                            let cls = 'p-2 text-xs rounded text-center ';
                            if (!isCurrent) cls += 'bg-gray-50 text-gray-300';
                            else if (isHoliday) cls += 'bg-yellow-50 text-yellow-700';
                            else if (isAvailable) cls += 'bg-green-50 text-green-700';
                            else cls += 'bg-red-50 text-red-700';
                            html += `<div class="${cls}">${isCurrent ? dayNum : ''}</div>`;
                        }
                        grid.innerHTML = html;
                    })
                    .catch(() => {
                        label.textContent = '';
                        grid.innerHTML = '';
                    });
            }

            // Initialize mini calendar on page load
            document.addEventListener('DOMContentLoaded', function() {
                renderDashboardCalendar();
                renderConsultationCalendar();
            });

            // Consultation Management Calendar functions
            let consultationCalYear = new Date().getFullYear();
            let consultationCalMonth = new Date().getMonth();
            function consultationCalendarChangeMonth(delta) {
                consultationCalMonth += delta;
                if (consultationCalMonth < 0) { consultationCalMonth = 11; consultationCalYear -= 1; }
                if (consultationCalMonth > 11) { consultationCalMonth = 0; consultationCalYear += 1; }
                renderConsultationCalendar();
            }

            function renderConsultationCalendar() {
                const label = document.getElementById('consultation-calendar-label');
                const grid = document.getElementById('consultation-calendar-grid');
                if (!label || !grid) return;

                const month = consultationCalMonth + 1;
                fetch(`API/consultation_availability_api.php?action=list_month&year=${consultationCalYear}&month=${month}`)
                    .then(r => r.json())
                    .then(data => {
                        calendarAvailability = {};
                        if (data && data.availability) data.availability.forEach(row => calendarAvailability[row.date] = row);
                        const holidays = new Set(data && data.holidays ? data.holidays : []);

                        const first = new Date(consultationCalYear, consultationCalMonth, 1);
                        const last = new Date(consultationCalYear, consultationCalMonth + 1, 0);
                        label.textContent = first.toLocaleString('en-GB', { month: 'long', year: 'numeric' });

                        const startDay = (first.getDay() + 6) % 7;
                        const totalDays = last.getDate();
                        const cells = Math.ceil((startDay + totalDays) / 7) * 7;

                        let html = '';
                        for (let i = 0; i < cells; i++) {
                            const dayNum = i - startDay + 1;
                            const isCurrent = dayNum >= 1 && dayNum <= totalDays;
                            const dateStr = isCurrent ? `${consultationCalYear}-${String(consultationCalMonth + 1).padStart(2,'0')}-${String(dayNum).padStart(2,'0')}` : '';
                            const avail = dateStr ? calendarAvailability[dateStr] : null;
                            const isHoliday = dateStr && holidays.has(dateStr);
                            const isAvailable = avail ? Number(avail.is_available) === 1 && Number(avail.current_consultations) < Number(avail.max_consultations) : false;
                            const isWeekend = dateStr ? (() => {
                                const d = new Date(dateStr + 'T00:00:00');
                                const wd = d.getDay();
                                return wd === 0 || wd === 6;
                            })() : false;

                            let cls = 'p-2 text-xs rounded text-center ';
                            if (!isCurrent) cls += 'bg-gray-50 text-gray-300';
                            else if (isHoliday) cls += 'bg-yellow-50 text-yellow-700';
                            else if (isAvailable) cls += 'bg-green-50 text-green-700';
                            else cls += 'bg-red-50 text-red-700';
                            html += `<div class="${cls}">${isCurrent ? dayNum : ''}</div>`;
                        }
                        grid.innerHTML = html;
                    })
                    .catch(() => {
                        label.textContent = '';
                        grid.innerHTML = '';
                    });
            }
            if (calendarHolidays.has(selectedCalendarDate)) {
                showAlert('This date is a holiday and cannot be set as available.');
                return;
            }
            const formData = new FormData();
            formData.append('date', selectedCalendarDate);
            formData.append('start_time', document.getElementById('calendar-start-time').value || '09:00');
            formData.append('end_time', document.getElementById('calendar-end-time').value || '17:00');
            formData.append('max_consultations', document.getElementById('calendar-max').value || '5');
            formData.append('notes', document.getElementById('calendar-notes').value || '');
            formData.append('is_available', document.getElementById('calendar-available').checked ? '1' : '0');

            fetch('API/consultation_availability_api.php?action=save_availability', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    showAlert('Availability saved successfully.');
                    fetchCalendarData();
                } else {
                    showAlert((data && data.error) ? data.error : 'Failed to save availability');
                }
            })
            .catch(() => showAlert('Failed to save availability'));
        }

        function markCalendarUnavailable() {
            if (!selectedCalendarDate) {
                showAlert('Please select a date first.');
                return;
            }
            showConfirm(`Mark ${selectedCalendarDate} as unavailable?`, function() {
                const checkbox = document.getElementById('calendar-available');
                if (checkbox) checkbox.checked = false;
                saveCalendarAvailability();
            }, 'Mark as Unavailable');
        }

        // ========================================
        // Schedule Confirmation
        // ========================================
        function openScheduleModal(consultationId, email, title) {
            document.getElementById('schedule-consultation-id').value = consultationId;
            document.getElementById('schedule-consultation-email').value = email;
            document.getElementById('schedule-consultation-title').value = title;
            openModal('schedule-modal');
        }

        function confirmSchedule() {
            const id = document.getElementById('schedule-consultation-id').value;
            const email = document.getElementById('schedule-consultation-email').value;
            const title = document.getElementById('schedule-consultation-title').value;
            const date = document.getElementById('schedule-date').value;
            const start = document.getElementById('schedule-start').value;
            const end = document.getElementById('schedule-end').value;
            const platform = document.getElementById('schedule-platform').value;
            const link = document.getElementById('schedule-link').value;
            const notes = document.getElementById('schedule-notes').value;

            if (!date || !start || !end) {
                showAlert('Date and time are required.');
                return;
            }

            fetch('system-template-full.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=confirm_schedule&consultation_id=${encodeURIComponent(id)}&email=${encodeURIComponent(email)}&subject=${encodeURIComponent(title)}&scheduled_date=${encodeURIComponent(date)}&scheduled_start=${encodeURIComponent(start)}&scheduled_end=${encodeURIComponent(end)}&meeting_platform=${encodeURIComponent(platform)}&meeting_link=${encodeURIComponent(link)}&notes=${encodeURIComponent(notes)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    showAlert('Schedule confirmed and email sent.');
                    window.location.reload();
                } else {
                    showAlert('Failed to confirm schedule: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(() => showAlert('Failed to confirm schedule.'));
        }

        function showExportModal(messageHtml, actionsHtml, titleText) {
            const body = document.getElementById('export-modal-body');
            const actions = document.getElementById('export-modal-actions');
            const title = document.getElementById('export-modal-title');
            if (body) body.innerHTML = messageHtml || '';
            if (actions) actions.innerHTML = actionsHtml || '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>';
            if (title && titleText) title.textContent = titleText;
            openModal('export-modal');
        }

        function openModuleReportModal(module) {
            const title = module === 'dashboard' ? 'System-wide Report' : (module === 'consultations' ? 'Consultation Report' : (module === 'feedback' ? 'Feedback Report' : (module === 'users' ? 'User Report' : 'Module Report')));
            const body = `
                <div class="text-gray-700">Generate a structured report from the current module data using live records.</div>
                <div class="text-xs text-gray-500 mt-2">The report will include participation counts and sentiment summaries where applicable.</div>
            `;
            const actions = `
                <button onclick="generateModuleReport('${module}', 'pdf')" class="btn-primary">PDF</button>
                <button onclick="generateModuleReport('${module}', 'excel')" class="btn-outline">Excel</button>
                <button onclick="generateModuleReport('${module}', 'word')" class="btn-outline">Word</button>
                <button onclick="closeModal('export-modal')" class="btn-outline">Cancel</button>
            `;
            showExportModal(body, actions, `Generate ${title}`);
        }

        function generateModuleReport(module, format) {
            showExportModal('<div class="text-gray-700">Generating report...</div>', '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>', 'Generating Report');

            const formData = new FormData();
            formData.append('action', 'generate_module_report');
            formData.append('module', module || 'dashboard');
            formData.append('format', format || 'pdf');
            formData.append('csrf_token', getCsrfToken());

            fetch('system-template-full.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    const msg = `
                        <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3">
                            Report generated successfully. ${data.title}
                        </div>
                        <div class="mt-3 text-gray-700">Download the generated ${String(format || 'file').toUpperCase()} report below.</div>
                    `;
                    const actions = `
                        <a href="${data.download_url}" target="_blank" class="btn-primary inline-block">Download Report</a>
                        <button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>
                    `;
                    showExportModal(msg, actions, 'Report Ready');
                } else {
                    const err = (data && data.message) ? data.message : 'Report generation failed.';
                    showExportModal(`<div class="bg-red-50 border border-red-200 text-red-800 rounded p-3">${err}</div>`, '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>', 'Report Failed');
                }
            })
            .catch(() => {
                showExportModal('<div class="bg-red-50 border border-red-200 text-red-800 rounded p-3">Report generation failed. Please try again.</div>', '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>', 'Report Failed');
            });
        }

        // Override any global error handlers to prevent toast notifications for report errors
        window.addEventListener('error', function(e) {
            if (e.message && e.message.includes('report')) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        function detectBestExportFormat(consultationId) {
            const row = document.querySelector(`[onclick="openExportChooser(${consultationId})"]`)?.closest('tr');
            const descCell = row ? row.querySelector('td:nth-child(1)') : null;
            const text = descCell ? descCell.textContent : '';
            if (text && text.length > 80) return 'pdf';
            return 'excel';
        }

        
        function openForwardToExpertModal(id, title, category) {
            document.getElementById('forward-consultation-id').value = id;
            document.getElementById('forward-modal-consult-title').textContent = title + ' (' + (category || 'General') + ')';
            
            const select = document.getElementById('forward-expert-select');
            select.innerHTML = '<option value="0">-- Auto-Dispatch to All Experts Matching Category (' + (category || 'General') + ') --</option>';

            fetch('API/resource_person_api.php?action=list_resource_persons')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    data.data.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.fullname + ' (' + (p.expertise_areas || p.department || 'Expert') + ')';
                        select.appendChild(opt);
                    });
                }
            }).catch(e => console.error(e));

            document.getElementById('forward-expert-modal').classList.remove('hidden');
        }

        function closeForwardToExpertModal() {
            document.getElementById('forward-expert-modal').classList.add('hidden');
        }

        function handleForwardToExpertSubmit(e) {
            e.preventDefault();
            const form = document.getElementById('forward-expert-form');
            const formData = new FormData(form);
            const btn = document.getElementById('forward-submit-btn');

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Forwarding...';

            fetch('API/forward_to_resource_person.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill"></i> Forward AI Summary';
                if (data.success) {
                    alert('✅ ' + data.message);
                    closeForwardToExpertModal();
                    location.reload();
                } else {
                    alert('⚠️ ' + (data.message || 'Failed to forward to expert'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill"></i> Forward AI Summary';
                alert('❌ Error: ' + err.message);
            });
        }

        function openExportChooser(consultationId) {
            const body = `
                <div class="text-gray-700">Choose how to export this consultation.</div>
                <div class="text-xs text-gray-500 mt-2">Auto will pick the best format based on content.</div>
            `;
            const actions = `
                <button onclick="exportConsultationWithFormat(${consultationId}, 'auto')" class="btn-primary">Auto (Recommended)</button>
                <button onclick="exportConsultationWithFormat(${consultationId}, 'pdf')" class="btn-outline">PDF</button>
                <button onclick="exportConsultationWithFormat(${consultationId}, 'excel')" class="btn-outline">Excel</button>
                <button onclick="closeModal('export-modal')" class="btn-outline">Cancel</button>
            `;
            showExportModal(body, actions, 'Export Consultation');
        }

        function exportConsultationWithFormat(consultationId, format) {
            const chosen = format === 'auto' ? detectBestExportFormat(consultationId) : format;
            showExportModal('<div class="text-gray-700">Generating file...</div>', '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>', 'Exporting...');

            const formData = new FormData();
            formData.append('action', 'export_consultations');
            formData.append('format', chosen);
            formData.append('mode', 'separate');
            formData.append('csrf_token', getCsrfToken());
            formData.append('ids[]', String(consultationId));

            fetch('system-template-full.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    const msg = `
                        <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3">
                            File generation complete. ${data.created} file(s) were added to Document Management.
                        </div>
                        <div class="mt-3 text-gray-700">Go to <strong>Document Management</strong> to download the file.</div>
                    `;
                    const actions = `
                        <button onclick="if (typeof showSection === 'function') { showSection('pc-documents'); } closeModal('export-modal');" class="btn-primary">Open Document Management</button>
                        <button onclick="closeModal('export-modal')" class="btn-outline">Close</button>
                    `;
                    showExportModal(msg, actions, 'Export Complete');
                } else {
                    const err = (data && data.message) ? data.message : 'Export failed.';
                    showExportModal(`<div class="bg-red-50 border border-red-200 text-red-800 rounded p-3">${err}</div>`, '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>', 'Export Failed');
                }
            })  
            .catch(() => {
                showExportModal('<div class="bg-red-50 border border-red-200 text-red-800 rounded p-3">Export failed. Please try again.</div>', '<button onclick="closeModal(\'export-modal\')" class="btn-outline">Close</button>', 'Export Failed');
            });
        }

        function viewConsultation(consultationId) {
            // Show loading state
            const modal = document.getElementById('consultation-view-modal');
            const content = document.getElementById('consultation-view-content');
            content.innerHTML = '<div class="flex items-center justify-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div></div>';
            openModal('consultation-view-modal');

            // Fetch consultation details
            fetch(`API/consultations_api.php?action=get&id=${consultationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.consultation) {
                        renderConsultationView(data.consultation);
                    } else {
                        content.innerHTML = '<div class="bg-red-50 border border-red-200 text-red-800 rounded p-4">Failed to load consultation details.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = '<div class="bg-red-50 border border-red-200 text-red-800 rounded p-4">Error loading consultation details.</div>';
                });
        }

        function renderConsultationView(consultation) {
            const content = document.getElementById('consultation-view-content');
            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'active': 'bg-green-100 text-green-800',
                'closed': 'bg-gray-100 text-gray-800',
                'rejected': 'bg-red-100 text-red-800'
            };
            const statusColor = statusColors[consultation.status] || 'bg-gray-100 text-gray-800';

            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Header Section -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold text-gray-900 mb-2">${consultation.title || 'Untitled Consultation'}</h2>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium ${statusColor}">${consultation.status || 'Unknown'}</span>
                                    <span class="text-sm text-gray-600">ID: #${consultation.id}</span>
                                    ${consultation.type ? `<span class="text-sm text-gray-600">Type: ${consultation.type}</span>` : ''}
                                </div>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                <div>Created: ${consultation.created_at || 'N/A'}</div>
                                ${consultation.updated_at ? `<div>Updated: ${consultation.updated_at}</div>` : ''}
                            </div>
                        </div>
                    </div>

                    <!-- Description Section -->
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i class="bi bi-file-text text-blue-600"></i>
                            Description
                        </h3>
                        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">${consultation.description || 'No description provided.'}</p>
                    </div>

                    <!-- User Information Section -->
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-person text-blue-600"></i>
                            Submitter Information
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Name</label>
                                <p class="text-gray-900">${consultation.user_name || 'Not provided'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">${consultation.user_email || 'Not provided'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Phone</label>
                                <p class="text-gray-900">${consultation.phone || 'Not provided'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email Notifications</label>
                                <p class="text-gray-900">${consultation.allow_email_notifications ? 'Yes' : 'No'}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details Section -->
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="bi bi-info-circle text-blue-600"></i>
                            Additional Details
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Tracking Number</label>
                                <p class="text-gray-900 font-mono">${consultation.tracking_number || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Response Mode</label>
                                <p class="text-gray-900">${consultation.response_mode || 'N/A'}</p>
                            </div>
                            ${consultation.category ? `
                            <div>
                                <label class="text-sm font-medium text-gray-500">Category</label>
                                <p class="text-gray-900">${consultation.category}</p>
                            </div>
                            ` : ''}
                            ${consultation.department ? `
                            <div>
                                <label class="text-sm font-medium text-gray-500">Department</label>
                                <p class="text-gray-900">${consultation.department}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button onclick="closeModal('consultation-view-modal')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">
                            Close
                        </button>
                        ${consultation.user_email ? `
                        <button onclick="sendEmailReply(${consultation.id}, '${consultation.user_email}', '${consultation.title.replace(/'/g, "\\'")}')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium flex items-center gap-2">
                            <i class="bi bi-envelope"></i>
                            Email Reply
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }



        function updateConsultationStatus(consultationId, newStatus, event) {

            if (!newStatus) {
                if (event && event.target) {
                    event.target.value = '';
                }
                return;
            }

            if (newStatus === 'declined' || newStatus === 'rejected') {
                if (typeof openDeclineCitizenSubmissionModal === 'function') {
                    openDeclineCitizenSubmissionModal(consultationId);
                } else if (typeof confirmDeclineCitizenSubmission === 'function') {
                    confirmDeclineCitizenSubmission(consultationId);
                }
                if (event && event.target) event.target.value = '';
                return;
            }

            showConfirm(`Update consultation status to "${newStatus}"?`, function() {
                fetch('system-template-full.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_consultation_status&consultation_id=${consultationId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show updated status
                        window.location.reload();
                    } else {
                        showAlert('Failed to update status: ' + (data.error || 'Unknown error'));
                        if (event && event.target) {
                            event.target.value = '';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating status');
                    if (event && event.target) {
                        event.target.value = '';
                    }
                });
            }, 'Confirm Status Update');
            
            // Reset the dropdown if user cancels
            setTimeout(() => {
                if (event && event.target && !event.target.value) {
                    // User cancelled, dropdown should already be empty
                }
            }, 100);
        }



        function sendEmailReply(consultationId, email, subject) {

            let message = prompt(`Enter your reply message for "${subject}":`);

            if (message === null) return;
            if (message.trim() === '') {
                message = 'No additional remarks were provided.';
            }

            const meetingPlatform = prompt('Optional: meeting platform (Zoom or Google Meet). Leave blank if none:', '');
            let meetingLink = '';
            if (meetingPlatform && meetingPlatform.trim() !== '') {
                meetingLink = prompt('Optional: meeting link (Zoom/Google Meet URL). Leave blank if none:', '');
            }

            showConfirm(`Send email reply to ${email}?`, function() {
                fetch('system-template-full.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_email_reply&consultation_id=${consultationId}&email=${encodeURIComponent(email)}&subject=${encodeURIComponent(subject)}&message=${encodeURIComponent(message)}&meeting_platform=${encodeURIComponent(meetingPlatform || '')}&meeting_link=${encodeURIComponent(meetingLink || '')}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Email sent successfully! Status updated to "Replied".');
                        window.location.reload();
                    } else {
                        showAlert('Failed to send email: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error sending email');
                });
            }, 'Confirm Email Reply');

        }

        // Feedback Management Functions
        function openFeedbackReplyModal(feedbackId) {
            let response = prompt('Enter your response to this feedback:');
            if (response === null || response.trim() === '') {
                return;
            }
            
            updateFeedbackResponse(feedbackId, response);
        }

        function updateFeedbackResponse(feedbackId, response) {
            showConfirm('Update feedback response?', function() {
                fetch('system-template-full.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_feedback_response&feedback_id=${feedbackId}&response=${encodeURIComponent(response)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Feedback response saved successfully!');
                        window.location.reload();
                    } else {
                        showAlert('Failed to save response: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error saving response');
                });
            }, 'Save Feedback Response');
        }

        function updateFeedbackStatus(feedbackId, status) {
            showConfirm(`Mark feedback as "${status}"?`, function() {
                fetch('system-template-full.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_feedback_status&feedback_id=${feedbackId}&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showAlert('Failed to update status: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating status');
                });
            }, 'Confirm Status Update');
        }

    </script>



    <script>



    // Mobile sidebar open/close handlers



    (function(){



      var openBtn = document.getElementById('open-mobile-sidebar');



      var closeBtn = document.getElementById('close-mobile-sidebar');



      var mobileSidebar = document.getElementById('mobile-sidebar');



      var overlay = document.getElementById('sidebar-overlay');



      if (openBtn) openBtn.addEventListener('click', function(){



          mobileSidebar.classList.remove('-translate-x-full');



          overlay.classList.remove('opacity-0','pointer-events-none');



      });



      if (closeBtn) closeBtn.addEventListener('click', function(){



          mobileSidebar.classList.add('-translate-x-full');



          overlay.classList.add('opacity-0','pointer-events-none');



      });



      if (overlay) overlay.addEventListener('click', function(){



          mobileSidebar.classList.add('-translate-x-full');



          overlay.classList.add('opacity-0','pointer-events-none');



      });



    })();



    </script>



<!-- Forward to LRS Modal -->
<div id="forward-lrs-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 m-4 transform transition-all">
        <div class="flex justify-between items-center pb-3 border-b border-gray-200">
            <h3 class="text-lg font-bold text-red-800 flex items-center gap-2">
                <i class="bi bi-send-fill text-red-600"></i> Forward Document to LRS
            </h3>
            <button onclick="closeForwardLRSModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mt-4 space-y-4 text-sm">
            <p class="text-gray-600">You are forwarding this official document to the <strong>Legislative Records System (LRS)</strong> via integration API.</p>
            <form id="forward-lrs-form" onsubmit="submitForwardToLRS(event)">
                <input type="hidden" id="lrs-doc-id" name="id">
                <input type="hidden" id="lrs-doc-source" name="source">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Document Reference</label>
                    <input type="text" id="lrs-doc-ref" readonly class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-50 text-gray-700 font-mono text-xs">
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Document Title</label>
                    <input type="text" id="lrs-doc-title" readonly class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-50 text-gray-700 font-medium">
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Target Endpoint</label>
                    <div class="text-xs bg-gray-100 text-gray-800 p-2 rounded border border-gray-200 font-mono break-all">
                        POST https://llrm.spvalenzuela.com/modules/integration/api/receive_document.php
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Custom Notes / Description</label>
                    <textarea id="lrs-doc-desc" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-red-500 focus:border-red-500 text-sm" placeholder="Provide description or notes for LRS..."></textarea>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" onclick="closeForwardLRSModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">Cancel</button>
                    <button type="submit" id="lrs-submit-btn" class="px-4 py-2 bg-red-700 text-white rounded-md text-sm font-semibold hover:bg-red-800 flex items-center gap-2">
                        <i class="bi bi-send-fill"></i> Forward to LRS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div></body>




</html>





