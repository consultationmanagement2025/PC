<?php
require_once 'db.php';
require_once 'DATABASE/consultations.php';

// --- Social links (official accounts) ---
$SOCIAL_FB = 'https://www.facebook.com/ValenzuelaCityGov/';
$SOCIAL_IG = 'https://www.instagram.com/valenzuelacitygov/';
$SOCIAL_YT = 'https://www.youtube.com/valenzuelagovph';

// Determine section
$section = isset($_GET['section']) ? $_GET['section'] : 'consultations';
$allowed_sections = ['consultations', 'detail', 'feedback', 'contact'];
if (!in_array($section, $allowed_sections)) {
    $section = 'consultations';
}

// Get consultation ID if viewing detail
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$consultation_detail = null;

if ($section === 'detail' && $consultation_id) {
    try {
        $consultation_detail = getConsultationById($consultation_id);
        if (!$consultation_detail) {
            $detail_error = 'Consultation not found.';
            $section = 'consultations';
        }
    } catch (Throwable $e) {
        error_log('Error fetching consultation detail: ' . $e->getMessage());
        $detail_error = 'An error occurred while loading the consultation details.';
        $section = 'consultations';
    }
}

// ==================== PHONE OTP SYSTEM ====================
$phone_otp_step = false;
$email_verification_step = false;
$phone_verified = false;
$email_verified = false;
$form_data = [];
$verification_error = '';
$verification_success = '';

// Generate and send phone OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_phone_otp'])) {
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($phone)) {
        $verification_error = 'Phone number is required';
    } elseif (!preg_match('/^(\+63|0)?[0-9]{10}$/', str_replace(['-', ' '], '', $phone))) {
        $verification_error = 'Invalid Philippine phone number format';
    } else {
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['phone_otp'] = $otp;
        $_SESSION['phone_otp_expires'] = time() + 10 * 60; // 10 minutes
        $_SESSION['pending_phone'] = $phone;
        
        // Send OTP via SMS (using mail as fallback - in production, use Twilio/Nexmo)
        $message = "Your verification code is: $otp. Valid for 10 minutes.";
        // In production, integrate with SMS gateway like Twilio
        // For now, we log it (you can enable SMS later)
        error_log("SMS OTP to $phone: $otp");
        
        $phone_otp_step = true;
        $verification_success = 'Verification code sent to ' . substr($phone, -4, 4) . '****';
        $_SESSION['form_step'] = 'phone_otp';
    }
}

// Verify phone OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_phone_otp'])) {
    $entered_otp = trim($_POST['phone_otp_code'] ?? '');
    
    if (empty($entered_otp)) {
        $verification_error = 'OTP code is required';
        $phone_otp_step = true;
    } elseif (!isset($_SESSION['phone_otp'])) {
        $verification_error = 'No active OTP request. Please start over.';
        unset($_SESSION['form_step']);
    } elseif (time() > $_SESSION['phone_otp_expires']) {
        $verification_error = 'OTP has expired. Please request a new one.';
        unset($_SESSION['phone_otp'], $_SESSION['phone_otp_expires'], $_SESSION['form_step']);
    } elseif ($entered_otp !== $_SESSION['phone_otp']) {
        $verification_error = 'Invalid OTP code. Please try again.';
        $phone_otp_step = true;
    } else {
        // Phone verified!
        $phone_verified = true;
        $_SESSION['form_step'] = 'email_verification';
        $_SESSION['verified_phone'] = $_SESSION['pending_phone'];
        $verification_success = 'Phone verified! Now confirm your email.';
    }
}

// Send email verification link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_email_verification'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $verification_error = 'Valid email address is required';
    } else {
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $_SESSION['email_verification_token'] = $token;
        $_SESSION['email_verification_expires'] = time() + 15 * 60; // 15 minutes
        $_SESSION['pending_email'] = $email;
        
        // Send verification email
        $verify_link = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "&verify_email=" . $token;
        $subject = "Verify Your Email - Valenzuela City Public Consultation";
        $body = "Hello,\n\n";
        $body .= "Please click the link below to verify your email address:\n\n";
        $body .= $verify_link . "\n\n";
        $body .= "This link will expire in 15 minutes.\n\n";
        $body .= "If you did not request this, please ignore this email.\n\n";
        $body .= "Regards,\nValenzuela City Government";
        
        $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($email, $subject, $body, $headers);
        
        $verification_success = 'Verification email sent to ' . htmlspecialchars($email) . '. Check your inbox!';
        $_SESSION['form_step'] = 'email_verification';
        $email_verification_step = true;
    }
}

// Check email verification via token
if (isset($_GET['verify_email'])) {
    $token = trim($_GET['verify_email']);
    
    if (!isset($_SESSION['email_verification_token'])) {
        $verification_error = 'No active email verification request.';
    } elseif (time() > $_SESSION['email_verification_expires']) {
        $verification_error = 'Email verification link has expired.';
        unset($_SESSION['email_verification_token'], $_SESSION['email_verification_expires']);
    } elseif ($token !== $_SESSION['email_verification_token']) {
        $verification_error = 'Invalid verification link.';
    } else {
        // Email verified!
        $email_verified = true;
        $_SESSION['verified_email'] = $_SESSION['pending_email'];
        $_SESSION['form_step'] = 'submit_form';
        $verification_success = 'Email verified! You can now submit your feedback.';
    }
}

// ==================== FORM SUBMISSION ====================
$submission_success = false;
$submission_message = '';

// Check if both verifications passed
$both_verified = isset($_SESSION['verified_phone']) && isset($_SESSION['verified_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback']) && $both_verified) {
    $name = trim($_POST['name'] ?? '');
    $phone = $_SESSION['verified_phone'];
    $email = $_SESSION['verified_email'];
    $message = trim($_POST['message'] ?? '');
    $feedback_type = trim($_POST['feedback_type'] ?? 'general');
    
    // Validation
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO feedback (name, email, phone, message, feedback_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sssss", $name, $email, $phone, $message, $feedback_type);
            if ($stmt->execute()) {
                // Send confirmation email
                $subject = "Public Consultation Portal - Feedback Received";
                $body = "Dear $name,\n\n";
                $body .= "Thank you for submitting your feedback through the Valenzuela City Public Consultation Portal.\n\n";
                $body .= "Your feedback has been received and will be reviewed by our team.\n\n";
                $body .= "Details:\n";
                $body .= "Type: " . ucfirst(str_replace('_', ' ', $feedback_type)) . "\n";
                $body .= "Submitted: " . date('F j, Y \a\t g:i A') . "\n\n";
                $body .= "We appreciate your input in making Valenzuela City better.\n\n";
                $body .= "Regards,\nValenzuela City Government";
                
                $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";
                @mail($email, $subject, $body, $headers);
                
                $submission_success = true;
                $submission_message = 'Thank you! Your feedback has been submitted successfully. A confirmation email has been sent.';
                
                // Clear session
                unset($_SESSION['verified_phone'], $_SESSION['verified_email'], $_SESSION['form_step']);
            }
            $stmt->close();
        }
    } else {
        $submission_message = 'Error: ' . implode(', ', $errors);
    }
}

// Handle contact/meeting request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact']) && $both_verified) {
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = $_SESSION['verified_phone'];
    $contact_email = $_SESSION['verified_email'];
    $contact_subject = trim($_POST['contact_subject'] ?? '');
    $contact_message_text = trim($_POST['contact_message_text'] ?? '');
    $meeting_type = trim($_POST['meeting_type'] ?? 'inquiry');
    
    // Validation
    $errors = [];
    if (empty($contact_name)) $errors[] = 'Name is required';
    if (empty($contact_subject)) $errors[] = 'Subject is required';
    if (empty($contact_message_text)) $errors[] = 'Message is required';
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO feedback (name, email, phone, message, feedback_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $full_message = "Meeting Type: " . ucfirst($meeting_type) . "\nSubject: " . $contact_subject . "\n\n" . $contact_message_text;
            $feedback_type = 'meeting_request';
            $stmt->bind_param("sssss", $contact_name, $contact_email, $contact_phone, $full_message, $feedback_type);
            if ($stmt->execute()) {
                // Send confirmation email
                $subject = "Valenzuela City - Get In Touch Request Received";
                $body = "Dear $contact_name,\n\n";
                $body .= "Thank you for reaching out to the Valenzuela City Government.\n\n";
                $body .= "We have received your " . strtolower($meeting_type) . " request and will contact you soon.\n\n";
                $body .= "Details:\n";
                $body .= "Subject: " . $contact_subject . "\n";
                $body .= "Contact: " . $contact_phone . "\n";
                $body .= "Submitted: " . date('F j, Y \a\t g:i A') . "\n\n";
                $body .= "We will get back to you within 2-3 business days.\n\n";
                $body .= "Regards,\nValenzuela City Government\nPublic Consultation Office";
                
                $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";
                @mail($contact_email, $subject, $body, $headers);
                
                $submission_success = true;
                $submission_message = 'Thank you! Your request has been submitted. A confirmation email has been sent. We will contact you within 2-3 business days.';
                
                // Clear session
                unset($_SESSION['verified_phone'], $_SESSION['verified_email'], $_SESSION['form_step']);
            }
            $stmt->close();
        }
    } else {
        $submission_message = 'Error: ' . implode(', ', $errors);
    }
}

// Ensure consultations table exists (do not drop/sample data here)
initializeConsultationsTable();

// Ensure optional columns exist: image_path, source_url
$colCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME IN ('image_path','source_url')");
$existingCols = [];
if ($colCheck) {
    while ($r = $colCheck->fetch_assoc()) {
        $existingCols[] = $r['COLUMN_NAME'];
    }
}
if (!in_array('image_path', $existingCols)) {
    $conn->query("ALTER TABLE consultations ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
}
if (!in_array('source_url', $existingCols)) {
    $conn->query("ALTER TABLE consultations ADD COLUMN source_url VARCHAR(255) DEFAULT NULL");
}

// Build optional filters from GET params (search + date range)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$start_filter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_filter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$in_description = isset($_GET['in_description']) && $_GET['in_description'] ? true : false;

// Active consultations query with filters
$where_active = "status = 'active'";
if ($q !== '') {
    $q_esc = $conn->real_escape_string($q);
    if ($in_description) {
        $where_active .= " AND (title LIKE '%$q_esc%' OR description LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";
    } else {
        $where_active .= " AND (title LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";
    }
}
if ($start_filter !== '') {
    $start_esc = $conn->real_escape_string($start_filter);
    $where_active .= " AND DATE(start_date) >= '$start_esc'";
}
if ($end_filter !== '') {
    $end_esc = $conn->real_escape_string($end_filter);
    $where_active .= " AND DATE(end_date) <= '$end_esc'";
}
$active_consultations = $conn->query("SELECT id, title, description, category, start_date, end_date, status, image_path, source_url FROM consultations WHERE $where_active ORDER BY start_date DESC LIMIT 50");

// Past consultations query with same optional filters (applied to end_date)
$where_past = "status = 'closed'";
if ($q !== '') {
    $q_esc = $conn->real_escape_string($q);
    if ($in_description) {
        $where_past .= " AND (title LIKE '%$q_esc%' OR description LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";
    } else {
        $where_past .= " AND (title LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";
    }
}
if ($start_filter !== '') {
    $start_esc = $conn->real_escape_string($start_filter);
    $where_past .= " AND DATE(end_date) >= '$start_esc'";
}
if ($end_filter !== '') {
    $end_esc = $conn->real_escape_string($end_filter);
    $where_past .= " AND DATE(end_date) <= '$end_esc'";
}
$past_consultations = $conn->query("SELECT id, title, description, category, start_date, end_date, status, image_path, source_url FROM consultations WHERE $where_past ORDER BY end_date DESC LIMIT 50");

// Determine current form step for display
$current_form_step = $_SESSION['form_step'] ?? 'phone_otp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#991b1b">
    <title>Public Consultation Portal - Valenzuela City</title>
    <link rel="icon" type="image/png" href="images/logo.webp">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            color: #1f2937;
            line-height: 1.6;
            margin: 0;
        }

        /* SECTION DISPLAY */
        .section-active { display: block; }
        .section-hidden { display: none; }

        /* HEADER STYLES */
        header {
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 40;
        }
        header .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        header img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(153,27,27,0.2);
        }
        header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #991b1b;
            margin: 0;
            letter-spacing: -0.5px;
        }
        header p {
            margin: 0;
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 500;
        }
        header .header-buttons {
            display: flex;
            gap: 0.75rem;
        }
        header a {
            padding: 0.65rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        header a:first-child {
            color: #991b1b;
            border: 2px solid #991b1b;
        }
        header a:first-child:hover {
            background: #fef2f2;
            transform: translateY(-2px);
        }
        header a:last-child {
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            color: white;
        }
        header a:last-child:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(153, 27, 27, 0.3);
        }

        /* MAIN LAYOUT */
        main {
            padding: 3rem 0;
        }

        /* NAVIGATION */
        .nav-link {
            padding: 0.85rem 2rem;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #6b7280;
            font-weight: 700;
            background: none;
            border: none;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        .nav-link.active {
            color: #991b1b;
            border-bottom-color: #991b1b;
            background: linear-gradient(to bottom, rgba(153,27,27,0.05), transparent);
        }
        .nav-link:hover {
            color: #991b1b;
            background: rgba(153,27,27,0.03);
        }

        /* CARDS */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 12px 32px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }

        /* BUTTONS */
        .btn {
            display: inline-block;
            padding: 0.85rem 2rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            color: white;
            box-shadow: 0 4px 12px rgba(153, 27, 27, 0.2);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(153, 27, 27, 0.35);
        }
        .btn-primary:active {
            transform: translateY(-1px);
        }
        .btn-primary:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        .btn-secondary:hover {
            border-color: #991b1b;
            color: #991b1b;
            background: #fef2f2;
        }

        /* FORMS */
        .form-group {
            margin-bottom: 1.75rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 700;
            color: #374151;
            font-size: 0.95rem;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.95rem 1.2rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }
        .form-textarea {
            min-height: 140px;
            resize: vertical;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #991b1b;
            box-shadow: 0 0 0 4px rgba(153, 27, 27, 0.1);
            background: #fafafa;
        }

        /* MESSAGES */
        .success-message {
            background: linear-gradient(135deg, #d1fae5, #ecfdf5);
            color: #065f46;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #10b981;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        }
        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fef2f2);
            color: #7f1d1d;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #ef4444;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        }
        .info-message {
            background: linear-gradient(135deg, #dbeafe, #f0f9ff);
            color: #1e40af;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #3b82f6;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* STEP INDICATOR */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3rem;
            gap: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            border-radius: 12px;
            border: 2px solid #f0f0f0;
        }
        .step {
            flex: 1;
            text-align: center;
            color: #9ca3af;
            font-weight: 700;
            position: relative;
        }
        .step i {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        .step.completed {
            color: #10b981;
        }
        .step.completed i {
            color: #10b981;
            transform: scale(1.1);
        }
        .step.active {
            color: #991b1b;
            font-weight: 800;
        }
        .step.active i {
            color: #991b1b;
            animation: pulse 1.5s ease-in-out infinite;
        }
        .step.pending {
            opacity: 0.5;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 800;
            margin-right: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-verified {
            background: linear-gradient(135deg, #dbeafe, #f0f9ff);
            color: #1e40af;
        }

        /* CONSULTATION GRID */
        .consultation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2.5rem;
            margin-bottom: 2rem;
        }
        .consultation-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }
        .consultation-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.15);
        }
        .consultation-card-header {
            background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
            color: white;
            padding: 2rem;
        }
        .consultation-card h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.4;
            letter-spacing: -0.5px;
        }
        .consultation-card-body {
            padding: 2rem;
        }
        .consultation-card p {
            margin: 0 0 1.5rem 0;
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.7;
        }
        .consultation-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            padding-bottom: 1.75rem;
            border-bottom: 2px solid #f0f0f0;
            font-size: 0.9rem;
            color: #9ca3af;
            font-weight: 600;
        }

        /* OTP INPUT */
        .otp-input {
            font-size: 2rem;
            letter-spacing: 1.2rem;
            text-align: center;
            font-weight: 900;
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            padding: 1rem;
            border-radius: 8px;
        }

        /* CONTAINER */
        .max-w-7xl {
            max-width: 80rem;
            margin-left: auto;
            margin-right: auto;
        }
        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .py-4 {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }

        /* FOOTER */
        footer {
            background: linear-gradient(135deg, #1f2937, #111827);
            color: white;
            margin-top: 4rem;
            padding: 2.5rem 0;
            text-align: center;
            font-weight: 600;
        }
        /* Footer follow-us styles */
        .footer-follow { display: flex; flex-direction: column; align-items: center; }
        .social-icons { display: flex; gap: 0.6rem; justify-content: center; }
        .social-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(255,255,255,0.03); transition: all 0.16s ease; color: #cbd5e1; }
        .social-icon:hover { background: rgba(255,255,255,0.12); transform: translateY(-3px); color: white; }
        .social-caption { margin-top: 6px; color: #9ca3af; font-size: 0.85rem; font-weight: 700; }
    </style>
</head>
<body>
<!-- HEADER -->
<header>
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="logo-section">
            <img src="images/logo.webp" alt="Logo">
            <div>
                <h1>Public Consultation</h1>
                <p>Valenzuela City Government</p>
            </div>
        </div>
        <div class="header-buttons">
            <a href="index.php">Back Home</a>
            <a href="login.php">Admin Login</a>
        </div>
    </div>
</header>

<!-- NAVIGATION -->
<div style="background: white; border-bottom: 2px solid #f0f0f0; position: sticky; top: 0; z-index: 30;">
    <div class="max-w-7xl mx-auto px-4 flex">
        <button onclick="switchSection('consultations')" class="nav-link active" id="nav-consultations">
            <i class="bi bi-file-text"></i>Active Consultations
        </button>
        <button onclick="switchSection('feedback')" class="nav-link" id="nav-feedback">
            <i class="bi bi-chat-dots"></i>Submit Feedback
        </button>
        <button onclick="switchSection('contact')" class="nav-link" id="nav-contact">
            <i class="bi bi-telephone"></i>Get In Touch
        </button>
    </div>
</div>

<!-- BANNER / HEADLINER -->
<div style="background: linear-gradient(135deg, #111827, #1f2937);">
    <div class="max-w-7xl mx-auto px-4 py-6" style="display:flex; align-items:center; justify-content:center;">
        <?php if (file_exists(__DIR__ . '/images/consultation.png')): ?>
            <img src="images/consultation.png" alt="Consultation headliner" style="width:100%; max-width:1200px; height:auto; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.25); display:block;">
        <?php else: ?>
            <div style="width:100%; max-width:1200px; height:180px; background:#f3f4f6; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#6b7280; font-weight:700;">Consultation Headliner</div>
        <?php endif; ?>
    </div>
</div>

<!-- MAIN CONTENT -->
<main style="max-width: 80rem; margin: 0 auto; padding: 2rem 1rem;">
    <!-- CONSULTATIONS SECTION -->
    <section id="section-consultations" class="section-active">
        <div style="margin-bottom: 2rem; text-align: center;">
            <h2 style="font-size: 2.5rem; font-weight: 800; color: #1f2937; margin: 0 0 0.75rem 0;">Active Consultations</h2>
            <p style="color: #6b7280; font-size: 1.1rem; margin: 0;">Review and provide feedback on proposed ordinances, programs, and policies</p>
        </div>

        <?php if ($section === 'detail' && $consultation_detail): ?>
            <!-- CONSULTATION DETAIL VIEW -->
            <?php if (!empty($detail_error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($detail_error); ?></div>
            <?php endif; ?>
            <div style="margin-bottom: 2rem;">
                <button onclick="switchToConsultationsList()" class="btn btn-secondary">
                    <i class="bi bi-arrow-left" style="margin-right: 0.5rem;"></i>Back to List
                </button>
            </div>

            <div class="card">
                <span class="badge" style="background: #d1fae5; color: #065f46;">
                    <?php echo ucfirst($consultation_detail['status']); ?>
                </span>
                <h1 style="font-size: 2.5rem; font-weight: 800; color: #1f2937; margin: 1rem 0;">
                    <?php echo htmlspecialchars($consultation_detail['title']); ?>
                </h1>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #f9fafb, #f3f4f6); border-radius: 10px;">
                    <div>
                        <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 600;">Category</p>
                        <p style="font-weight: 700; color: #1f2937;">
                            <?php echo htmlspecialchars($consultation_detail['category']); ?>
                        </p>
                    </div>
                    <div>
                        <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 600;">Start Date</p>
                        <p style="font-weight: 700; color: #1f2937;">
                            <?php echo date('M d, Y', strtotime($consultation_detail['start_date'])); ?>
                        </p>
                    </div>
                    <div>
                        <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 600;">End Date</p>
                        <p style="font-weight: 700; color: #1f2937;">
                            <?php echo date('M d, Y', strtotime($consultation_detail['end_date'])); ?>
                        </p>
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: #1f2937; margin-bottom: 1rem;">Description</h3>
                    <p style="color: #4b5563; line-height: 1.8; font-size: 1rem;">
                        <?php echo nl2br(htmlspecialchars($consultation_detail['description'])); ?>
                    </p>
                </div>

                <?php if (!empty($consultation_detail['source_url'])): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <p style="margin:0; font-weight:700; color:#1f2937;">Official source</p>
                        <p style="margin:0.5rem 0 0 0;"><a href="<?php echo htmlspecialchars($consultation_detail['source_url']); ?>" target="_blank" rel="noopener" style="color:#1e40af; font-weight:700;">Open official document / announcement</a></p>
                    </div>
                <?php endif; ?>

                <div style="background: linear-gradient(135deg, #dbeafe, #f0f9ff); padding: 1.75rem; border-radius: 10px; border-left: 5px solid #3b82f6; margin-bottom: 2rem;">
                    <h3 style="font-weight: 800; color: #1e40af; margin: 0 0 0.75rem 0;">Want to share your thoughts?</h3>
                    <p style="color: #1e40af; font-size: 0.95rem; margin: 0 0 1rem 0;">We value your feedback! Use the "Submit Feedback" form to share your opinions on this consultation.</p>
                    <button onclick="switchSection('feedback')" class="btn btn-primary">
                        <i class="bi bi-chat-dots" style="margin-right: 0.5rem;"></i>Submit Feedback Now
                    </button>
                </div>
            </div>

        <?php else: ?>
            <!-- CONSULTATIONS LIST VIEW - FULL WIDTH WITH IMAGES -->
            <div style="margin-bottom: 3rem;">
                <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 0.75rem;">
                    <i class="bi bi-megaphone" style="margin-right: 0.5rem; color: #991b1b;"></i>
                    Active Consultations
                </h3>

                <!-- Search & Date Filter -->
                <form method="GET" style="display:flex; gap:0.5rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap;">
                    <input type="hidden" name="section" value="consultations">
                    <input type="text" name="q" placeholder="Search consultations..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" class="form-input" style="max-width:360px;">
                    <label style="display:flex; align-items:center; gap:0.4rem;">
                        <small style="color:#6b7280; font-weight:700; margin-right:0.25rem;">From</small>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>" class="form-input" style="max-width:160px;">
                    </label>
                    <label style="display:flex; align-items:center; gap:0.4rem;">
                        <small style="color:#6b7280; font-weight:700; margin-right:0.25rem;">To</small>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="form-input" style="max-width:160px;">
                    </label>
                    <button type="submit" class="btn btn-primary" style="padding:0.65rem 1rem;">Search</button>
                    <a href="public-portal.php?section=consultations" class="btn btn-secondary" style="padding:0.65rem 1rem;">Clear</a>
                </form>

                <?php if ($active_consultations && $active_consultations->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <?php 
                        // Separate image maps for active and past consultations so images match titles
                        $active_images = [
                            1 => 'images/traffic.jpg',            // Traffic Management
                            2 => 'images/public cons.JPG',        // Environmental (generic)
                            3 => 'images/publicMarket.webp',      // Housing uses publicMarket image as requested
                            4 => null                             // Youth Development - no image
                        ];

                        $past_images = [
                            1 => 'images/illegaldumping.webp',   // Anti-Illegal Dumping (past)
                            2 => 'images/streetlights.png',      // Street Lighting (past)
                            3 => 'images/publicMarket.webp',     // Public Market (past)
                            4 => 'images/wawter.jpg'             // Water Quality (past)
                        ];

                        $image_counter = 1;
                        while ($consultation = $active_consultations->fetch_assoc()): 
                            // Decide image based on title/category so order doesn't break mappings
                            $title_l = strtolower($consultation['title']);
                            $cat_l = strtolower($consultation['category']);

                            // Prefer explicit DB image if present and file exists
                            $image_path = null;
                            if (!empty($consultation['image_path'])) {
                                $candidate = $consultation['image_path'];
                                // if stored filename without folder, prefix images/
                                if (!preg_match('#^images/#', $candidate)) {
                                    $candidate = 'images/' . ltrim($candidate, '/');
                                }
                                if (file_exists(__DIR__ . '/' . $candidate)) {
                                    $image_path = $candidate;
                                }
                            }

                            // If no DB image, decide based on title/category or fallback map
                            if (!$image_path) {
                                if (strpos($title_l, 'traffic') !== false || strpos($cat_l, 'transport') !== false) {
                                    $image_path = 'images/traffic.jpg';
                                } elseif (strpos($title_l, 'environment') !== false || strpos($cat_l, 'environment') !== false) {
                                    $image_path = 'images/environmental.jpg';
                                } elseif (strpos($title_l, 'housing') !== false || strpos($cat_l, 'housing') !== false || strpos($title_l, 'public market') !== false) {
                                    $image_path = 'images/housing.jpg';
                                } elseif (strpos($title_l, 'youth') !== false || strpos($cat_l, 'social') !== false) {
                                    // intentionally no image for youth until provided
                                    $image_path = null;
                                } else {
                                    // fallback to mapping by counter when keyword matching fails
                                    $image_path = isset($active_images[$image_counter]) && $active_images[$image_counter] ? $active_images[$image_counter] : null;
                                }
                            }
                            $days_left = ceil((strtotime($consultation['end_date']) - time()) / (60 * 60 * 24));
                        ?>
                            <div class="card" style="padding: 0; margin-bottom: 0; overflow: hidden; display: grid; grid-template-columns: 1fr 1.2fr; border: 1px solid #e5e7eb;">
                                <!-- IMAGE SECTION -->
                                <div style="background: linear-gradient(135deg, #991b1b, #7f1d1d); min-height: 300px; overflow: hidden; position: relative;">
                                    <?php if ($image_path): ?>
                                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($consultation['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; min-height:300px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#6b7280; font-weight:700;">No image yet</div>
                                    <?php endif; ?>
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.4)); pointer-events: none;"></div>
                                    <div style="position: absolute; top: 1.5rem; left: 1.5rem;">
                                        <span class="badge" style="background: #991b1b; color: white; padding: 0.6rem 1.2rem; font-size: 0.85rem;">
                                            <i class="bi bi-circle-fill" style="font-size: 0.4rem; margin-right: 0.5rem;"></i>
                                            ACTIVE
                                        </span>
                                    </div>
                                    <div style="position: absolute; bottom: 1.5rem; left: 1.5rem; background: white; padding: 0.75rem 1.2rem; border-radius: 8px; font-weight: 800; color: #991b1b; font-size: 0.95rem;">
                                        <?php echo $days_left > 0 ? $days_left . ' days left' : 'Ending soon'; ?>
                                    </div>
                                </div>

                                <!-- CONTENT SECTION -->
                                <div style="padding: 2rem; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                                            <span style="background: #fee2e2; color: #991b1b; padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <?php echo htmlspecialchars($consultation['category']); ?>
                                            </span>
                                        </div>
                                        <h4 style="font-size: 1.4rem; font-weight: 800; color: #1f2937; margin: 0 0 1rem 0; line-height: 1.3;">
                                            <?php echo htmlspecialchars($consultation['title']); ?>
                                        </h4>
                                        <p style="color: #4b5563; margin: 0 0 1.5rem 0; line-height: 1.7; font-size: 0.95rem;">
                                            <?php echo substr(htmlspecialchars($consultation['description']), 0, 280); ?>...
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <div style="display: flex; gap: 2rem; align-items: center; padding-top: 1.5rem; border-top: 2px solid #f0f0f0;">
                                            <div>
                                                <p style="font-size: 0.8rem; color: #6b7280; margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Consultation Period</p>
                                                <p style="font-weight: 800; color: #1f2937; margin: 0.5rem 0 0 0; font-size: 1rem;">
                                                    <?php echo date('M d', strtotime($consultation['start_date'])) . ' - ' . date('M d, Y', strtotime($consultation['end_date'])); ?>
                                                </p>
                                            </div>
                                            <a href="public-portal.php?section=detail&id=<?php echo $consultation['id']; ?>" class="btn btn-primary" style="margin-left: auto;">
                                                View Full Details <i class="bi bi-arrow-right" style="margin-left: 0.5rem;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                        $image_counter++;
                        endwhile; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <p style="text-align: center; color: #6b7280; margin: 0;">No active consultations at the moment. Please check back soon!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PAST CONSULTATIONS -->
            <div style="margin-bottom: 3rem;">
                <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 2rem;">
                    <i class="bi bi-check-circle" style="margin-right: 0.5rem; color: #10b981;"></i>
                    Completed Consultations
                </h3>
                <?php if ($past_consultations && $past_consultations->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <?php 
                        $past_counter = 1;
                        while ($consultation = $past_consultations->fetch_assoc()): 
                            $past_image_path = isset($past_images[$past_counter]) && $past_images[$past_counter] ? $past_images[$past_counter] : 'images/public cons.JPG';
                        ?>
                            <div class="card" style="padding: 0; margin-bottom: 0; overflow: hidden; display: grid; grid-template-columns: 1fr 1.2fr; border: 1px solid #e5e7eb; opacity: 0.85;">
                                <!-- IMAGE SECTION -->
                                <div style="background: linear-gradient(135deg, #6b7280, #4b5563); min-height: 300px; overflow: hidden; position: relative;">
                                    <img src="<?php echo $past_image_path; ?>" alt="<?php echo htmlspecialchars($consultation['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block; filter: grayscale(30%); opacity: 0.8;">
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.5)); pointer-events: none;"></div>
                                    <div style="position: absolute; top: 1.5rem; left: 1.5rem;">
                                        <span class="badge" style="background: #10b981; color: white; padding: 0.6rem 1.2rem; font-size: 0.85rem;">
                                            <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>
                                            COMPLETED
                                        </span>
                                    </div>
                                </div>

                                <!-- CONTENT SECTION -->
                                <div style="padding: 2rem; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                                            <span style="background: #d1fae5; color: #065f46; padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <?php echo htmlspecialchars($consultation['category']); ?>
                                            </span>
                                        </div>
                                        <h4 style="font-size: 1.4rem; font-weight: 800; color: #1f2937; margin: 0 0 1rem 0; line-height: 1.3;">
                                            <?php echo htmlspecialchars($consultation['title']); ?>
                                        </h4>
                                        <p style="color: #4b5563; margin: 0 0 1.5rem 0; line-height: 1.7; font-size: 0.95rem;">
                                            <?php echo substr(htmlspecialchars($consultation['description']), 0, 280); ?>...
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <div style="display: flex; gap: 2rem; align-items: center; padding-top: 1.5rem; border-top: 2px solid #f0f0f0;">
                                            <div>
                                                <p style="font-size: 0.8rem; color: #6b7280; margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Concluded</p>
                                                <p style="font-weight: 800; color: #1f2937; margin: 0.5rem 0 0 0; font-size: 1rem;">
                                                    <?php echo date('M d, Y', strtotime($consultation['end_date'])); ?>
                                                </p>
                                            </div>
                                            <a href="public-portal.php?section=detail&id=<?php echo $consultation['id']; ?>" class="btn btn-secondary" style="margin-left: auto;">
                                                View Details <i class="bi bi-arrow-right" style="margin-left: 0.5rem;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                        $past_counter++;
                        endwhile; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <p style="text-align: center; color: #6b7280; margin: 0;">No completed consultations yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- FEEDBACK SECTION -->
    <section id="section-feedback" class="section-hidden">
        <div style="margin-bottom: 2rem; text-align: center;">
            <h2 style="font-size: 2.5rem; font-weight: 800; color: #1f2937; margin: 0 0 0.75rem 0;">Submit Feedback</h2>
            <p style="color: #6b7280; font-size: 1.1rem; margin: 0;">Share your thoughts, suggestions, or concerns about proposed policies</p>
        </div>

        <?php if ($submission_success): ?>
            <div class="success-message">
                <i class="bi bi-check-circle mr-2"></i>
                <?php echo $submission_message; ?>
            </div>
        <?php elseif ($submission_message): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle mr-2"></i>
                <?php echo $submission_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($verification_success): ?>
            <div class="success-message">
                <i class="bi bi-check-circle mr-2"></i>
                <?php echo $verification_success; ?>
            </div>
        <?php elseif ($verification_error): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <?php echo $verification_error; ?>
            </div>
        <?php endif; ?>

        <!-- TWO-COLUMN LAYOUT -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 3rem; align-items: start;">
            <!-- LEFT COLUMN - INFO BOXES -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- INFO BOX 1 -->
                <div class="card" style="background: white; padding: 2rem; margin-bottom: 0;">
                    <h4 style="font-weight: 800; color: #991b1b; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="bi bi-chat-heart" style="margin-right: 0.5rem;"></i>
                        Share Your Views
                    </h4>
                    <p style="color: #6b7280; margin: 0; font-size: 0.95rem; line-height: 1.6;">Help shape better policies by sharing your feedback on proposed ordinances and programs.</p>
                </div>

                <!-- INFO BOX 2 -->
                <div class="card" style="background: white; padding: 2rem; margin-bottom: 0;">
                    <h4 style="font-weight: 800; color: #991b1b; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="bi bi-shield-check" style="margin-right: 0.5rem;"></i>
                        Secure & Verified
                    </h4>
                    <p style="color: #6b7280; margin: 0; font-size: 0.95rem; line-height: 1.6;">Your phone and email are verified to ensure legitimate submissions only.</p>
                </div>

                <!-- INFO BOX 3 -->
                <div class="card" style="background: white; padding: 2rem; margin-bottom: 0;">
                    <h4 style="font-weight: 800; color: #991b1b; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>
                        Reviewed Carefully
                    </h4>
                    <p style="color: #6b7280; margin: 0; font-size: 0.95rem; line-height: 1.6;">Every submission is reviewed and considered by our city officials.</p>
                </div>
            </div>

            <!-- RIGHT COLUMN - FORM -->
            <div class="card" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); color: white; padding: 2.5rem;">
                <!-- Step Indicator -->
                <div class="step-indicator" style="background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); gap: 1.5rem;">
                    <div class="step <?php echo isset($_SESSION['verified_phone']) ? 'completed' : ($current_form_step === 'phone_otp' ? 'active' : 'pending'); ?>">
                        <i class="bi bi-telephone" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; font-size: 0.8rem; font-weight: 700;">Phone</p>
                    </div>
                    <div class="step <?php echo $both_verified ? 'completed' : ($current_form_step === 'email_verification' ? 'active' : 'pending'); ?>">
                        <i class="bi bi-envelope" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; font-size: 0.8rem; font-weight: 700;">Email</p>
                    </div>
                    <div class="step <?php echo $both_verified ? 'active' : 'pending'; ?>">
                        <i class="bi bi-chat-dots" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; font-size: 0.8rem; font-weight: 700;">Submit</p>
                    </div>
                </div>

                <!-- PHONE OTP STEP -->
                <?php if (!isset($_SESSION['verified_phone'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-shield-check" style="margin-right: 0.5rem;"></i>Verify Your Phone
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="phone" style="color: white;">Philippine Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="09XX XXXX XXX or +63 9XX XXXX XXX" required style="background: white;">
                            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.8); margin-top: 0.5rem;">We'll send you a 6-digit code via SMS</p>
                        </div>
                        <button type="submit" name="request_phone_otp" class="btn btn-primary" style="width: 100%; background: white; color: #991b1b; border: none;">
                            <i class="bi bi-send" style="margin-right: 0.5rem;"></i>Send OTP Code
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- PHONE OTP VERIFICATION STEP -->
                <?php if (isset($_SESSION['phone_otp']) && !isset($_SESSION['verified_phone'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Enter Code
                    </h3>
                    <p style="color: rgba(255,255,255,0.9); margin-bottom: 1rem; font-size: 0.95rem;">Sent to <strong><?php echo substr($_SESSION['pending_phone'], -4, 4) . '****'; ?></strong></p>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="phone_otp_code" style="color: white;">Enter 6-digit Code *</label>
                            <input type="text" id="phone_otp_code" name="phone_otp_code" class="form-input otp-input" placeholder="000000" maxlength="6" required style="background: white; text-align: center;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <button type="submit" name="verify_phone_otp" class="btn btn-primary" style="background: white; color: #991b1b;">
                                <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Verify
                            </button>
                            <button type="button" onclick="location.reload()" class="btn btn-secondary" style="background: transparent; color: white; border: 2px solid white;">Start Over</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- PHONE VERIFIED INDICATOR -->
                <?php if (isset($_SESSION['verified_phone'])): ?>
                <div style="background: rgba(16, 185, 129, 0.2); border: 2px solid #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: white; font-weight: 700;"><i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Phone Verified: <?php echo substr($_SESSION['verified_phone'], -4, 4) . '****'; ?></p>
                </div>
                <?php endif; ?>

                <!-- EMAIL VERIFICATION STEP -->
                <?php if (isset($_SESSION['verified_phone']) && !isset($_SESSION['verified_email'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-envelope-check" style="margin-right: 0.5rem;"></i>Verify Email
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="email" style="color: white;">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com" required style="background: white;">
                            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.8); margin-top: 0.5rem;">We'll send a verification link</p>
                        </div>
                        <button type="submit" name="request_email_verification" class="btn btn-primary" style="width: 100%; background: white; color: #991b1b;">
                            <i class="bi bi-send" style="margin-right: 0.5rem;"></i>Send Email
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- EMAIL VERIFIED INDICATOR -->
                <?php if ($both_verified): ?>
                <div style="background: rgba(16, 185, 129, 0.2); border: 2px solid #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: white; font-weight: 700;"><i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Email Verified</p>
                </div>
                <?php endif; ?>

                <!-- FEEDBACK FORM STEP -->
                <?php if ($both_verified): ?>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-chat-dots" style="margin-right: 0.5rem;"></i>Share Feedback
                    </h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label" for="name" style="color: white;">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-input" required placeholder="Your full name" style="background: white;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="feedback_type" style="color: white;">Type *</label>
                            <select id="feedback_type" name="feedback_type" class="form-select" required style="background: white;">
                                <option value="general">General Feedback</option>
                                <option value="support">Support/Agreement</option>
                                <option value="concern">Concern/Objection</option>
                                <option value="suggestion">Suggestion</option>
                                <option value="question">Question</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="message" style="color: white;">Your Feedback *</label>
                            <textarea id="message" name="message" class="form-textarea" required placeholder="Please share your thoughts..." style="background: white;"></textarea>
                        </div>

                        <button type="submit" name="submit_feedback" class="btn btn-primary" style="width: 100%; background: white; color: #991b1b;">
                            <i class="bi bi-send" style="margin-right: 0.5rem;"></i>Submit Feedback
                        </button>
                        <p style="font-size: 0.85rem; color: rgba(255,255,255,0.8); margin-top: 1rem;">Your verified feedback will be reviewed by our team.</p>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- CONTACT/GET IN TOUCH SECTION -->
    <section id="section-contact" class="section-hidden">
        <div style="margin-bottom: 2rem; text-align: center;">
            <h2 style="font-size: 2.5rem; font-weight: 800; color: #1f2937; margin: 0 0 0.75rem 0;">Get In Touch</h2>
            <p style="color: #6b7280; font-size: 1.1rem; margin: 0;">Request a meeting or contact the public consultation office directly</p>
        </div>

        <?php if ($submission_success): ?>
            <div class="success-message">
                <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>
                <?php echo $submission_message; ?>
            </div>
        <?php elseif ($submission_message): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <?php echo $submission_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($verification_success): ?>
            <div class="success-message">
                <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>
                <?php echo $verification_success; ?>
            </div>
        <?php elseif ($verification_error): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <?php echo $verification_error; ?>
            </div>
        <?php endif; ?>

        <!-- TWO-COLUMN LAYOUT -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 3rem; align-items: start;">
            <!-- LEFT COLUMN - INFO BOXES -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- INFO BOX 1 -->
                <div class="card" style="background: white; padding: 2rem; margin-bottom: 0;">
                    <h4 style="font-weight: 800; color: #991b1b; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="bi bi-telephone" style="margin-right: 0.5rem;"></i>
                        Direct Contact
                    </h4>
                    <p style="color: #6b7280; margin: 0; font-size: 0.95rem; line-height: 1.6;">Schedule meetings or inquiries with our consultation office.</p>
                </div>

                <!-- INFO BOX 2 -->
                <div class="card" style="background: white; padding: 2rem; margin-bottom: 0;">
                    <h4 style="font-weight: 800; color: #991b1b; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="bi bi-clock" style="margin-right: 0.5rem;"></i>
                        Quick Response
                    </h4>
                    <p style="color: #6b7280; margin: 0; font-size: 0.95rem; line-height: 1.6;">We'll respond to your request within 2-3 business days.</p>
                </div>

                <!-- INFO BOX 3 -->
                <div class="card" style="background: white; padding: 2rem; margin-bottom: 0;">
                    <h4 style="font-weight: 800; color: #991b1b; margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="bi bi-geo-alt" style="margin-right: 0.5rem;"></i>
                        Office Location
                    </h4>
                    <p style="color: #6b7280; margin: 0; font-size: 0.95rem; line-height: 1.6;">City Hall, Valenzuela City<br>Monday - Friday, 8AM - 5PM</p>
                </div>
            </div>

            <!-- RIGHT COLUMN - FORM -->
            <div class="card" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); color: white; padding: 2.5rem;">
                <!-- VERIFICATION FOR CONTACT FORM -->
                <?php if (!$both_verified): ?>
                <!-- Step Indicator -->
                <div class="step-indicator" style="background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); gap: 1.5rem;">
                    <div class="step <?php echo isset($_SESSION['verified_phone']) ? 'completed' : 'active'; ?>">
                        <i class="bi bi-telephone" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; font-size: 0.8rem; font-weight: 700;">Phone</p>
                    </div>
                    <div class="step <?php echo $both_verified ? 'completed' : 'pending'; ?>">
                        <i class="bi bi-envelope" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; font-size: 0.8rem; font-weight: 700;">Email</p>
                    </div>
                </div>

                <!-- PHONE OTP STEP -->
                <?php if (!isset($_SESSION['verified_phone'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-shield-check" style="margin-right: 0.5rem;"></i>Verify Phone
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="phone" style="color: white;">Philippine Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="09XX XXXX XXX" required style="background: white;">
                        </div>
                        <button type="submit" name="request_phone_otp" class="btn btn-primary" style="width: 100%; background: white; color: #991b1b;">
                            <i class="bi bi-send" style="margin-right: 0.5rem;"></i>Send OTP
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- PHONE OTP VERIFICATION -->
                <?php if (isset($_SESSION['phone_otp']) && !isset($_SESSION['verified_phone'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Enter Code
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="phone_otp_code" style="color: white;">6-digit Code *</label>
                            <input type="text" id="phone_otp_code" name="phone_otp_code" class="form-input otp-input" placeholder="000000" maxlength="6" required style="background: white;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <button type="submit" name="verify_phone_otp" class="btn btn-primary" style="background: white; color: #991b1b;">Verify</button>
                            <button type="button" onclick="location.reload()" class="btn btn-secondary" style="background: transparent; color: white; border: 2px solid white;">Start Over</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- PHONE VERIFIED -->
                <?php if (isset($_SESSION['verified_phone'])): ?>
                <div style="background: rgba(16, 185, 129, 0.2); border: 2px solid #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: white; font-weight: 700;"><i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Phone: <?php echo substr($_SESSION['verified_phone'], -4, 4) . '****'; ?></p>
                </div>
                <?php endif; ?>

                <!-- EMAIL VERIFICATION -->
                <?php if (isset($_SESSION['verified_phone']) && !isset($_SESSION['verified_email'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-envelope-check" style="margin-right: 0.5rem;"></i>Verify Email
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="email" style="color: white;">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com" required style="background: white;">
                        </div>
                        <button type="submit" name="request_email_verification" class="btn btn-primary" style="width: 100%; background: white; color: #991b1b;">
                            <i class="bi bi-send" style="margin-right: 0.5rem;"></i>Send Email
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- EMAIL VERIFIED -->
                <?php if ($both_verified): ?>
                <div style="background: rgba(16, 185, 129, 0.2); border: 2px solid #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: white; font-weight: 700;"><i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>Email Verified</p>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- CONTACT FORM (after verification) -->
                <?php if ($both_verified): ?>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: white; margin: 1.5rem 0 1rem 0;">
                        <i class="bi bi-telephone" style="margin-right: 0.5rem;"></i>Send Request
                    </h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label" for="contact_name" style="color: white;">Full Name *</label>
                            <input type="text" id="contact_name" name="contact_name" class="form-input" required placeholder="Your full name" style="background: white;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="meeting_type" style="color: white;">Request Type *</label>
                            <select id="meeting_type" name="meeting_type" class="form-select" required style="background: white;">
                                <option value="inquiry">General Inquiry</option>
                                <option value="meeting">Schedule a Meeting</option>
                                <option value="presentation">Request Presentation</option>
                                <option value="workshop">Propose Workshop</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_subject" style="color: white;">Subject *</label>
                            <input type="text" id="contact_subject" name="contact_subject" class="form-input" required placeholder="What is this regarding?" style="background: white;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_message_text" style="color: white;">Message *</label>
                            <textarea id="contact_message_text" name="contact_message_text" class="form-textarea" required placeholder="Tell us more..." style="background: white;"></textarea>
                        </div>

                        <button type="submit" name="submit_contact" class="btn btn-primary" style="width: 100%; background: white; color: #991b1b;">
                            <i class="bi bi-send" style="margin-right: 0.5rem;"></i>Submit Request
                        </button>
                        <p style="font-size: 0.85rem; color: rgba(255,255,255,0.8); margin-top: 1rem;">We'll respond within 2-3 business days.</p>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- FOOTER -->
<footer style="background: linear-gradient(135deg, #1f2937, #111827); color: white; margin-top: 4rem; padding: 3rem 0;">
    <div style="max-width: 80rem; margin: 0 auto; padding: 0 1rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;">About</h4>
                <p style="color: #9ca3af; font-size: 0.9rem; margin: 0;">Public Consultation Portal of Valenzuela City Government</p>
            </div>
            <div>
                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;">Quick Links</h4>
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem;">
                    <li><a href="index.php" style="color: #9ca3af; text-decoration: none; font-size: 0.9rem;">Home</a></li>
                    <li><a href="#" id="openPrivacy" style="color: #9ca3af; text-decoration: none; font-size: 0.9rem; cursor:pointer;">Privacy Policy</a></li>
                    <li><a href="#" id="openTerms" style="color: #9ca3af; text-decoration: none; font-size: 0.9rem; cursor:pointer;">Terms of Use</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;">Contact</h4>
                <p style="color: #9ca3af; font-size: 0.9rem; margin: 0;">Valenzuela City Government<br>City Hall, Valenzuela City</p>
            </div>
            <div>
                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white; text-align:center;">Follow Us</h4>
                <div class="footer-follow">
                    <div class="social-icons">
                        <?php if (!empty($SOCIAL_FB)): ?>
                            <a class="social-icon" href="<?php echo htmlspecialchars($SOCIAL_FB); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="Valenzuela on Facebook">
                                <i class="bi bi-facebook" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($SOCIAL_IG)): ?>
                            <a class="social-icon" href="<?php echo htmlspecialchars($SOCIAL_IG); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="Valenzuela on Instagram">
                                <i class="bi bi-instagram" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($SOCIAL_YT)): ?>
                            <a class="social-icon" href="<?php echo htmlspecialchars($SOCIAL_YT); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="Valenzuela on YouTube">
                                <i class="bi bi-youtube" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="social-caption">Official Valenzuela City Government accounts</div>
                </div>
            </div>
            </div>
        </div>
        <div style="border-top: 1px solid #374151; padding-top: 2rem; text-align: center; color: #9ca3af; font-size: 0.9rem;">
            <p style="margin: 0;">&copy; 2026 Valenzuela City Government. All rights reserved.</p>
        </div>
    </div>
</footer>

    <!-- Modals for Privacy and Terms -->
    <div id="policyModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:60; align-items:center; justify-content:center;">
        <div style="background:white; width:90%; max-width:800px; border-radius:8px; overflow:auto; max-height:90vh; padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <h3 style="margin:0;">Privacy Policy</h3>
                <button onclick="closePolicyModal()" style="background:transparent; border:none; font-size:1.25rem;">&times;</button>
            </div>
            <div id="policyContent" style="color:#374151; line-height:1.6; font-size:0.95rem;">
                <p style="margin-top:0;"><strong>Official Privacy Policy</strong></p>
                <p>This modal is intended to display the official Privacy Policy of the Valenzuela City Government. Replace this placeholder with the authoritative policy text from your records (you can paste the HTML here or load `privacy.php` if you add it to the site).</p>
                <p style="margin-top:0.5rem; font-weight:700;">Assurance:</p>
                <p style="margin:0;">This portal links to and displays official Valenzuela City Government content only. For the full legal document, please consult the City Hall records or the official accounts linked in the footer.</p>
            </div>
        </div>
    </div>

    <div id="termsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:60; align-items:center; justify-content:center;">
        <div style="background:white; width:90%; max-width:800px; border-radius:8px; overflow:auto; max-height:90vh; padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <h3 style="margin:0;">Terms of Use</h3>
                <button onclick="closeTermsModal()" style="background:transparent; border:none; font-size:1.25rem;">&times;</button>
            </div>
            <div id="termsContent" style="color:#374151; line-height:1.6; font-size:0.95rem;">
                <p style="margin-top:0;"><strong>Official Terms of Use</strong></p>
                <p>This modal is intended to display the official Terms of Use of the Valenzuela City Government web services. Replace this placeholder with the authoritative terms text from your legal team (you can paste the HTML here or load `terms.php` if you add it to the site).</p>
                <p style="margin-top:0.5rem; font-weight:700;">Assurance:</p>
                <p style="margin:0;">Content presented on this portal is maintained under the City's authority. Users should rely on the official documents provided here and via the City's official social accounts for verified information.</p>
            </div>
        </div>
    </div>

<script>
    function switchSection(section) {
        document.querySelectorAll('[id^="section-"]').forEach(el => {
            el.classList.remove('section-active');
            el.classList.add('section-hidden');
        });

        document.getElementById('section-' + section).classList.add('section-active');
        document.getElementById('section-' + section).classList.remove('section-hidden');

        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        document.getElementById('nav-' + section).classList.add('active');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function switchToConsultationsList() {
        window.location.href = '?section=consultations';
    }

    // Modal controls
    document.getElementById('openPrivacy').addEventListener('click', function(e){
        e.preventDefault();
        document.getElementById('policyModal').style.display = 'flex';
    });
    document.getElementById('openTerms').addEventListener('click', function(e){
        e.preventDefault();
        document.getElementById('termsModal').style.display = 'flex';
    });
    function closePolicyModal(){ document.getElementById('policyModal').style.display = 'none'; }
    function closeTermsModal(){ document.getElementById('termsModal').style.display = 'none'; }
</script>

</body>
</html>
