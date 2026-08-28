<?php



// Start session FIRST before any headers



session_start();

if (!isset($_GET['noredirect'])) {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
    header('Location: public/index.php' . $queryString);
    exit;
}

error_log('=== PUBLIC PORTAL PHP LOADED ===');

error_log('REQUEST METHOD: ' . $_SERVER['REQUEST_METHOD']);

error_log('POST data: ' . json_encode($_POST));







require_once 'config.php';



require_once 'UTILS/security-headers.php';



require_once 'UTILS/security.php';



require_once 'db.php';



require_once 'DATABASE/consultations.php';



require_once 'DATABASE/feedback.php';

require_once 'UTILS/consultation_feedback_utils.php';

require_once 'DATABASE/user-logs.php';



/**

 * Generate a simple math captcha for consultation submissions.

 */

function generateConsultationCaptcha() {

    $a = random_int(2, 9);

    $b = random_int(1, 9);

    $_SESSION['consultation_captcha_question'] = "What is $a + $b?";

    $_SESSION['consultation_captcha_answer'] = $a + $b;

}



if (empty($_SESSION['consultation_captcha_question']) || empty($_SESSION['consultation_captcha_answer'])) {

    generateConsultationCaptcha();

}

function generateFeedbackCaptcha() {

    $a = random_int(2, 9);

    $b = random_int(1, 9);

    $_SESSION['feedback_captcha_question'] = "What is $a + $b?";

    $_SESSION['feedback_captcha_answer'] = $a + $b;

}

if (empty($_SESSION['feedback_captcha_question']) || empty($_SESSION['feedback_captcha_answer'])) {

    generateFeedbackCaptcha();

}



require_once 'email_config.php';







// --- Social links (official accounts) ---



$SOCIAL_FB = 'https://www.facebook.com/ValenzuelaCityGov/';



$SOCIAL_IG = 'https://www.instagram.com/valenzuelacitygov/';



$SOCIAL_YT = 'https://www.youtube.com/valenzuelagovph';







// Determine section



$section = isset($_GET['section']) ? $_GET['section'] : 'consultations';



$allowed_sections = ['consultations', 'detail', 'feedback', 'contact', 'submit-consultation'];



if (!in_array($section, $allowed_sections)) {



    $section = 'consultations';



}







$modal_title = '';



$modal_message = '';



$modal_type = '';







$consultation_form_values = [

    'name' => '',

    'age' => '',

    'gender' => '',

    'address' => '',

    'barangay' => '',

    'phone' => '',

    'consultation_topic' => '',

    'consultation_description' => '',

    'consultation_type' => '',

    'consultation_email' => '',

    'consultation_allow_email' => 1,

    'preferred_availability_id' => '',

    'consultation_captcha_answer' => '',

];







$feedback_form_values = [



    'name' => '',



    'email' => '',



    'phone' => '',



    'feedback_type' => '',



    'rating' => '',



    'message' => '',



    'allow_email_notifications' => '',



];







$feedback_form_errors = [];







// Get consultation ID if viewing detail



$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : null;



$consultation_detail = null;

$detail_response_mode = 'feedback';







if ($section === 'detail' && $consultation_id) {



    try {



        $consultation_detail = getConsultationById($consultation_id);



        if (!$consultation_detail) {



            $detail_error = 'Consultation not found.';



            $section = 'consultations';



        }

        $mode_raw = strtolower(trim((string)($consultation_detail['response_mode'] ?? 'feedback')));

        $detail_response_mode = in_array($mode_raw, ['feedback', 'survey', 'hybrid'], true) ? $mode_raw : 'feedback';



    } catch (Throwable $e) {



        error_log('Error fetching consultation detail: ' . $e->getMessage());



        $detail_error = 'An error occurred while loading the consultation details.';



        $section = 'consultations';



    }

}










// ==================== EMAIL VERIFICATION SYSTEM ====================



$email_verification_step = false;



$email_verified = false;



$form_data = [];



$verification_error = '';



$verification_success = '';







// Skip phone OTP - go directly to email verification



$_SESSION['form_step'] = 'email_verification';







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



        $verify_link = siteUrl((string)($_SERVER['REQUEST_URI'] ?? '/') . (strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '?') === false ? '?' : '&') . 'verify_email=' . urlencode($token));



        $subject = "Email Verification - Valenzuela City Public Consultation Portal";



        $body = "Dear Valenzuela City Citizen,\n\n";



        $body .= "Thank you for your interest in participating in our Public Consultation Portal. ";



        $body .= "To ensure the security and authenticity of your feedback, please verify your email address by clicking the link below:\n\n";



        $body .= $verify_link . "\n\n";



        $body .= "Important Information:\n";



        $body .= "• This verification link will expire in 15 minutes for security purposes\n";



        $body .= "• If you did not request this verification, please disregard this email\n";



        $body .= "• Your email address will only be used for official consultation communications\n\n";



        $body .= "Should you have any questions or concerns, please contact our office at consultation@valenzuelacity.gov.ph\n\n";



        $body .= "Respectfully,\n";



        $body .= "Valenzuela City Government\n";



        $body .= "Public Consultation Management Office";



        



        $mail_ok = sendGmailEmail($email, $subject, $body, false);



        



        if ($mail_ok) {



            $verification_success = 'Verification email sent to ' . htmlspecialchars($email) . '. Check your inbox!';



        } else {



            $verification_error = 'We could not send the verification email right now. Please contact the administrator or try again later.';



        }



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







// ==================== CONSULTATION SUBMISSION ====================



$consultation_submission_success = false;



$consultation_submission_message = '';



// Check for success message from session (consultation)

if (isset($_SESSION['consultation_success_message'])) {

    $consultation_submission_message = $_SESSION['consultation_success_message'];

    $consultation_submission_success = true;

    unset($_SESSION['consultation_success_message']);

}



if (!empty($consultation_submission_message) && empty($modal_message)) {

    $modal_title = $consultation_submission_success ? 'Submitted' : 'Please check your entries';

    $modal_message = $consultation_submission_message;

    $modal_type = $consultation_submission_success ? 'success' : 'error';

}



$consultation_field_errors = [];







// Rate limiting check (30 minutes cooldown)



$consultation_cooldown = 30 * 60; // 30 minutes in seconds



$last_consultation_time = $_SESSION['last_consultation_submission'] ?? 0;



$time_since_last = time() - $last_consultation_time;







// Feedback rate limiting variables



$feedback_cooldown = 30 * 60; // 30 minutes in seconds



$last_feedback_time = $_SESSION['last_feedback_submission'] ?? 0;



$time_since_last_feedback = time() - $last_feedback_time;







// Debug: Log ALL POST requests

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    error_log('POST request received: ' . json_encode(array_keys($_POST)));

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_tracking_number'])) {

    $section = 'track-consultation';

    $tracking_number_search = trim((string)($_POST['tracking_number'] ?? ''));

    if ($tracking_number_search === '') {

        $tracking_lookup_error = 'Please enter your consultation tracking number.';

    } else {

        $tracking_lookup_result = getConsultationByTrackingNumber($tracking_number_search);

        if (!$tracking_lookup_result) {

            $tracking_lookup_error = 'No consultation was found for that tracking number. Please check the number and try again.';

        }

    }

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_consultation'])) {



    // Debug: Log submission attempt

    error_log('=== CONSULTATION SUBMISSION POST HANDLER ENTERED ===');

    error_log('POST data: ' . json_encode($_POST));



    $consultation_submission_token = trim($_POST['consultation_submission_token'] ?? '');

    $consultation_submission_valid = true;



    if (empty($consultation_submission_token)

        || !isset($_SESSION['consultation_submission_token'])

        || $consultation_submission_token !== $_SESSION['consultation_submission_token']

        || (isset($_SESSION['consultation_submission_token_used']) && $_SESSION['consultation_submission_token_used'] === $consultation_submission_token)

    ) {

        $consultation_submission_message = 'This consultation form has already been submitted or is invalid. Please refresh the page and try again.';

        $consultation_submission_success = false;

        $consultation_submission_valid = false;

    }



    if ($consultation_submission_valid) {



        // Check rate limit

        if ($time_since_last < $consultation_cooldown) {



            $remaining_minutes = ceil(($consultation_cooldown - $time_since_last) / 60);



            $consultation_submission_message = '<div style="background: #fef3c7; color: #92400e; padding: 1rem; border-radius: 8px; border: 1px solid #fbbf24; margin-bottom: 1rem;">



                <strong>Please wait:</strong> You can only submit one consultation every 30 minutes. Please try again in ' . $remaining_minutes . ' minute(s).



            </div>';



        } else {



            $section = 'submit-consultation';



            $consultation_name = trim($_POST['name'] ?? '');

    $consultation_email = trim($_POST['consultation_email'] ?? '');

    $consultation_age = trim($_POST['age'] ?? '');

    $consultation_gender = trim($_POST['gender'] ?? '');

    $consultation_address = trim($_POST['address'] ?? '');

    $consultation_barangay = trim($_POST['barangay'] ?? '');

    $consultation_phone = trim($_POST['phone'] ?? '');

    $consultation_topic = trim($_POST['consultation_topic'] ?? '');

    $consultation_type = trim($_POST['consultation_type'] ?? '');

    $preferred_availability_id = (int)($_POST['preferred_availability_id'] ?? 0);

    $consultation_captcha_answer = trim($_POST['consultation_captcha_answer'] ?? '');

    $consultation_honeypot = trim($_POST['consultation_website'] ?? '');

    $consultation_description = trim($_POST['consultation_description'] ?? '');

    $consultation_allow_email = isset($_POST['consultation_allow_email']) ? 1 : 0;

    $phone = $consultation_phone;



    $consultation_form_values = [

        'name' => $consultation_name,

        'age' => $consultation_age,

        'gender' => $consultation_gender,

        'address' => $consultation_address,

        'barangay' => $consultation_barangay,

        'phone' => $consultation_phone,

        'consultation_topic' => $consultation_topic,

        'consultation_type' => $consultation_type,

        'consultation_description' => $consultation_description,

        'consultation_email' => $consultation_email,

        'consultation_allow_email' => $consultation_allow_email,

        'preferred_availability_id' => $preferred_availability_id,

        'consultation_captcha_answer' => $consultation_captcha_answer,

    ];



    // Validate CSRF token



    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {



        error_log('CSRF validation failed - token missing or invalid');



        $consultation_submission_message = 'Security validation failed. Please try again.';



    } else {



        error_log('CSRF validation passed, proceeding with consultation submission');



        $errors = [];



        if (empty($consultation_name)) {



            $errors[] = 'Name is required';



            $consultation_field_errors['name'] = 'Name is required';



        }



        if (empty($consultation_email) || !filter_var($consultation_email, FILTER_VALIDATE_EMAIL)) {



            $errors[] = 'Valid email is required';



            $consultation_field_errors['consultation_email'] = 'Valid email is required';



        }



        if (empty($consultation_topic)) {

            $errors[] = 'Topic is required';

            $consultation_field_errors['consultation_topic'] = 'Topic is required';

        }



        if (empty($consultation_description)) {

            $errors[] = 'Description is required';

            $consultation_field_errors['consultation_description'] = 'Description is required';

        }



        if (!empty($consultation_honeypot)) {

            $errors[] = 'Spam detected';

        }



        error_log('Consultation validation errors: ' . json_encode($errors));



        // Availability is optional; if provided we will attach it to the request.



        if (empty($errors)) {



            // Ensure consultations table exists



            if (file_exists(__DIR__ . '/DATABASE/consultations.php')) {



                require_once __DIR__ . '/DATABASE/consultations.php';



                initializeConsultationsTable();



            }







            // Save consultation submission into consultations table



            $stmt = $conn->prepare("INSERT INTO consultations (title, description, user_name, user_email, allow_email_notifications, status, created_at, type) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), 'user')");



            if ($stmt) {



                $title_val = $consultation_topic;



                $desc_val = $consultation_description;



                $name_val = ($consultation_name ?: 'Anonymous');



                $email_val = $consultation_email;



                $allow_val = $consultation_allow_email ? 1 : 0;



                $stmt->bind_param("ssssi", $title_val, $desc_val, $name_val, $email_val, $allow_val);



                // Debug: Log the values being inserted

                error_log("INSERT VALUES - Title: '$title_val', Name: '$name_val', Email: '$email_val', Allow: $allow_val");

                

                if ($stmt->execute()) {



                    $new_consultation_id = (int)$conn->insert_id;

                    $tracking_number = assignConsultationTrackingNumber($new_consultation_id);



                    // Attempt to generate consultation documents (best-effort)

                    try {

                        require_once __DIR__ . '/UTILS/generate_consultation_documents.php';

                        // generateConsultationDocuments($new_consultation_id, ['pdf' => true, 'docx' => false, 'created_by' => $_SESSION['user_id'] ?? 0]); // Document generated only upon ORTS forwarding

                    } catch (Throwable $e) {

                        error_log('Document generation (public-portal) failed: ' . $e->getMessage());

                    }



                    // Apply requested schedule from availability slot

                    if ($preferred_availability_id > 0) {

                        $conn->query("CREATE TABLE IF NOT EXISTS consultation_availability (

                            id INT AUTO_INCREMENT PRIMARY KEY,

                            admin_id INT NOT NULL,

                            date DATE NOT NULL,

                            start_time TIME NOT NULL DEFAULT '09:00:00',

                            end_time TIME NOT NULL DEFAULT '17:00:00',

                            is_available TINYINT(1) NOT NULL DEFAULT 1,

                            max_consultations INT NOT NULL DEFAULT 5,

                            current_consultations INT NOT NULL DEFAULT 0,

                            notes TEXT,

                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                            UNIQUE KEY unique_admin_date (admin_id, date),

                            INDEX idx_date (date),

                            INDEX idx_admin_id (admin_id)

                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                        $slotStmt = $conn->prepare("SELECT id, date, start_time, end_time, max_consultations, current_consultations

                            FROM consultation_availability

                            WHERE id = ? AND is_available = 1 AND current_consultations < max_consultations AND date >= CURDATE()

                            LIMIT 1");

                        if ($slotStmt) {

                            $slotStmt->bind_param('i', $preferred_availability_id);

                            $slotStmt->execute();

                            $slotRes = $slotStmt->get_result();

                            $slot = $slotRes ? $slotRes->fetch_assoc() : null;

                            $slotStmt->close();



                            if ($slot) {

                                $upd = $conn->prepare("UPDATE consultations

                                    SET availability_id = ?, requested_date = ?, requested_start_time = ?, requested_end_time = ?, schedule_status = 'requested'

                                    WHERE id = ?");

                                if ($upd) {

                                    $upd->bind_param('isssi', $preferred_availability_id, $slot['date'], $slot['start_time'], $slot['end_time'], $new_consultation_id);

                                    $upd->execute();

                                    $upd->close();

                                }

                                $inc = $conn->prepare("UPDATE consultation_availability SET current_consultations = current_consultations + 1 WHERE id = ?");

                                if ($inc) {

                                    $inc->bind_param('i', $preferred_availability_id);

                                    $inc->execute();

                                    $inc->close();

                                }

                            }

                        }

                    }







                    // Handle document upload if provided



                    $uploaded_document = null;



                    if (isset($_FILES['consultation_document']) && $_FILES['consultation_document']['error'] === UPLOAD_ERR_OK) {



                        try {



                            require_once __DIR__ . '/DATABASE/document-management.php';



                            $uploaded_document = uploadConsultationDocument(



                                $new_consultation_id,



                                $_FILES['consultation_document'],



                                'consultation_form',



                                'Supporting document for consultation: ' . $consultation_topic



                            );



                        } catch (Exception $e) {



                            error_log('Document upload failed: ' . $e->getMessage());



                            // Continue without document - consultation is still valid



                        }



                    }



                    // Generate PDF summary of the consultation

                    try {

                        require_once __DIR__ . '/UTILS/pdf_generator.php';

                        

                        $pdf_generator = new ConsultationPDFGenerator($new_consultation_id);

                        

                        $consultation_data = [

                            'id' => $new_consultation_id,

                            'name' => $name_val,

                            'email' => $consultation_email,

                            'phone' => $phone ?? 'Not provided',

                            'topic' => $consultation_topic,

                            'category' => $category_val,

                            'department' => 'Public Consultation Office',

                            'description' => $consultation_description

                        ];

                        

                        $pdf_dir = __DIR__ . '/uploads/documents/';

                        if (!is_dir($pdf_dir)) {

                            mkdir($pdf_dir, 0755, true);

                        }

                        

                        $pdf_path = $pdf_dir . $pdf_generator->getFilename();

                        

                        if ($pdf_generator->save($consultation_data, $pdf_path)) {

                            // Save PDF to document management database directly

                            require_once __DIR__ . '/DATABASE/document-management.php';

                            initializeDocumentsTable();

                            

                            global $conn;

                            

                            $reference_number = generateDocumentReference($new_consultation_id);

                            $original_filename = $pdf_generator->getFilename();

                            $stored_filename = 'CONSULT-' . str_pad($new_consultation_id, 6, '0', STR_PAD_LEFT) . '_summary_' . date('Y-m-d_H-i-s') . '.pdf';

                            $file_size = filesize($pdf_path);

                            

                            // Move PDF to proper location

                            $final_path = __DIR__ . '/uploads/documents/' . $stored_filename;

                            rename($pdf_path, $final_path);

                            

                            $stmt = $conn->prepare("

                                INSERT INTO documents (

                                    consultation_id, reference_number, original_filename, 

                                    stored_filename, file_type, file_size, uploaded_by, 

                                    document_type, description

                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)

                            ");

                            

                            if ($stmt) {

                                $user_id = (int)($_SESSION['user_id'] ?? 0);
                                if ($user_id <= 0) $user_id = null;

                                $file_type = 'application/pdf';

                                $document_type = 'consultation_summary';

                                $document_description = 'Auto-generated PDF summary of consultation submission';

                                $file_size = (int)$file_size;

                                $stmt->bind_param('isssissss', 

                                    $new_consultation_id, $reference_number, $original_filename, 

                                    $stored_filename, $file_type, $file_size, $user_id, 

                                    $document_type, $document_description

                                );

                                $stmt->execute();

                                $stmt->close();

                                

                                error_log('PDF summary saved to document management: ' . $original_filename);

                            }

                        }

                    } catch (Exception $e) {

                        error_log('PDF generation failed: ' . $e->getMessage());

                        // Continue without PDF - consultation is still valid

                    }



                    if (file_exists(__DIR__ . '/DATABASE/notifications.php')) {



                        require_once __DIR__ . '/DATABASE/notifications.php';



                        $nm = 'New consultation received (ID: ' . $new_consultation_id . ') from ' . ($name_val ?: 'Anonymous') . ' - ' . ($consultation_topic ?: 'No topic');



                        if ($uploaded_document) {



                            $nm .= ' (with document)';



                        }



                        @createNotification(0, $nm, 'consultation');



                    }







                    $summary_token = bin2hex(random_bytes(16));



                    $summary_expires_at = date('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);



                    $tokStmt = $conn->prepare("UPDATE consultations SET summary_token = ?, summary_token_expires = ? WHERE id = ?");



                    if ($tokStmt) {



                        $tokStmt->bind_param('ssi', $summary_token, $summary_expires_at, $new_consultation_id);



                        $tokStmt->execute();



                        $tokStmt->close();



                    }







                    // Generate edit token (valid for 7 days)



                    $edit_token = bin2hex(random_bytes(32));



                    $edit_token_expires = date('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);



                    // Ensure edit_token column exists



                    $colChk = $conn->query("SHOW COLUMNS FROM consultations LIKE 'edit_token'");



                    if ($colChk && $colChk->num_rows === 0) {



                        $conn->query("ALTER TABLE consultations ADD COLUMN edit_token VARCHAR(64) DEFAULT NULL");



                        $conn->query("ALTER TABLE consultations ADD COLUMN edit_token_expires DATETIME DEFAULT NULL");



                    }



                    $etStmt = $conn->prepare("UPDATE consultations SET edit_token = ?, edit_token_expires = ? WHERE id = ?");



                    if ($etStmt) {



                        $etStmt->bind_param('ssi', $edit_token, $edit_token_expires, $new_consultation_id);



                        $etStmt->execute();



                        $etStmt->close();



                    }







                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');



                    $summary_link = $baseUrl . '/CAP101/PC/download-consultation.php?id=' . $new_consultation_id . '&t=' . urlencode($summary_token);







                    $summary_html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Consultation Summary</title></head><body style="font-family:Arial, sans-serif; color:#111;">'



                        . '<h2 style="margin:0 0 12px 0;">Consultation Summary</h2>'



                        . '<div style="margin:0 0 10px 0;">Reference ID: <b>' . htmlspecialchars((string)$new_consultation_id) . '</b></div>'



                        . '<div style="margin:0 0 10px 0;">Submitted: <b>' . htmlspecialchars(date('F j, Y \a\t g:i A')) . '</b></div>'



                        . '<hr style="border:none;border-top:1px solid #ddd; margin:12px 0;">'



                        . '<div style="margin:0 0 10px 0;"><b>Topic:</b> ' . htmlspecialchars($consultation_topic ?: 'Not specified') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Name:</b> ' . htmlspecialchars($name_val ?: 'Not provided') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Email:</b> ' . htmlspecialchars($consultation_email ?: 'Not provided') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Phone:</b> ' . htmlspecialchars($consultation_phone ?: 'Not provided') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Address:</b> ' . htmlspecialchars($consultation_address ?: 'Not provided') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Barangay:</b> ' . htmlspecialchars($consultation_barangay ?: 'Not provided') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Allow Email Updates:</b> ' . ($consultation_allow_email ? 'Yes' : 'No') . '</div>'



                        . '<div style="margin:0 0 10px 0;"><b>Tracking Number:</b> <b>' . htmlspecialchars($tracking_number ?? 'N/A') . '</b></div>'



                        . '<div style="margin:0 0 10px 0;"><b>Details:</b><br><pre style="white-space:pre-wrap; background:#f7f7f7; padding:10px; border:1px solid #eee; border-radius:6px;">' . htmlspecialchars($desc_val ?: 'No description provided') . '</pre></div>'



                        . '<div style="margin-top:16px;">Download/Print link: <a href="' . htmlspecialchars($summary_link) . '">' . htmlspecialchars($summary_link) . '</a></div>'



                        . '</body></html>';







                    // Send confirmation email to submitter



                    $subject = "Valenzuela City - Consultation Request Received";



                    $edit_link = $baseUrl . '/CAP101/PC/edit-submission.php?type=consultation&id=' . $new_consultation_id . '&token=' . urlencode($edit_token);



                    



                    $edit_link = $baseUrl . '/CAP101/PC/edit-submission.php?type=consultation&id=' . $new_consultation_id . '&token=' . urlencode($edit_token);



                    



                    $body = "Hello,\n\n";



                    $body .= "Thank you for submitting your consultation request to the Valenzuela City Government.\n\n";



                    $body .= "Request Details:\n";



                    $body .= "Topic: " . ($consultation_topic ?: 'Not specified') . "\n";



                    $body .= "Submitted: " . date('F j, Y \\a\\t g:i A') . "\n";



                    $body .= "Reference ID: #" . $new_consultation_id . "\n";



                    $body .= "Tracking Number: " . ($tracking_number ?: 'N/A') . "\n";



                    $body .= "Name: " . ($consultation_name ?: 'Not provided') . "\n";



                    $body .= "Email: " . ($consultation_email ?: 'Not provided') . "\n";



                    $body .= "Phone: " . ($consultation_phone ?: 'Not provided') . "\n";



                    $body .= "Address: " . ($consultation_address ?: 'Not provided') . "\n";



                    $body .= "Barangay: " . ($consultation_barangay ?: 'Not provided') . "\n";



                    $body .= "Description:\n" . ($consultation_description ?: 'No description provided') . "\n\n";



                    



                    if ($consultation_allow_email) {



                        $body .= "✓ You have opted in to receive email updates about this consultation.\n";



                        $body .= "We will notify you once our team reviews your request and is ready to discuss.\n\n";



                    } else {



                        $body .= "You will not receive email notifications about this submission.\n";



                        $body .= "You can check your submission status by visiting our public portal.\n\n";



                    }



                    



                    $body .= "Important Links:\n";



                    $body .= "View/Download Summary: " . $summary_link . "\n";



                    $body .= "Edit Your Submission: " . $edit_link . " (valid for 7 days)\n\n";



                    



                    $body .= "We appreciate your interest in participating in our public consultation process.\n\n";



                    $body .= "Regards,\nValenzuela City Government\nPublic Consultation Office";







                    $mail_error = '';

                    $mail_ok = sendGmailEmail($consultation_email, $subject, $body, false, $mail_error);

                    if (!$mail_ok && !empty($mail_error)) {

                        error_log('Consultation email send failed: ' . $mail_error);

                    }



                    $consultation_submission_success = true;



                    // Update rate limiting timestamp

                    $_SESSION['last_consultation_submission'] = time();



                    // Store links in session for display

                    $_SESSION['consultation_edit_link'] = $edit_link;

                    $_SESSION['consultation_summary_link'] = $summary_link;

                    $_SESSION['consultation_reference'] = $tracking_number;

                    $_SESSION['consultation_id'] = $new_consultation_id;

                    $_SESSION['consultation_submission_token_used'] = $consultation_submission_token;



                    if ($mail_ok) {

                        $consultation_submission_message = 'Thank you! Your consultation request has been received and a confirmation email has been sent to ' . htmlspecialchars($consultation_email) . '.';

                    } else {

                        $consultation_submission_message = 'Thank you! Your consultation request has been received. Your confirmation email could not be delivered, but your links are displayed below.';

                    }

                    

                    // Store success message in session to persist after reload

                    $_SESSION['consultation_success_message'] = $consultation_submission_message;

                    

                    // Debug: Log successful submission

                    error_log("CONSULTATION SUBMISSION SUCCESSFUL - ID: $new_consultation_id, Email: $consultation_email");



                    // PRG: Redirect to prevent layout issues and double-submit

                    header('Location: public-portal.php?section=submit-consultation&submitted=1');

                    exit;



                } else {

                    error_log('Consultation insert failed: ' . $stmt->error);

                    $consultation_submission_message = 'Error: Could not save your request. Please try again.';

                }



                if ($stmt) {

                    $stmt->close();

                }



            } else {

                error_log('Consultation prepare failed: ' . $conn->error);

                $consultation_submission_message = 'Error: Database error. Please try again.';

            }



        } else {

            $consultation_submission_message = 'Error: ' . implode(', ', $errors);

        }



    } // Close validation else block



    } // Close rate limit else block



    } // Close consultation_submission_valid block

} // Close submit_consultation POST block





    if (!empty($consultation_submission_message)) {



        $modal_title = $consultation_submission_success ? 'Submitted' : 'Please check your entries';



        $modal_message = $consultation_submission_message;



        $modal_type = $consultation_submission_success ? 'success' : 'error';



    }










// ==================== FORM SUBMISSION ====================



$submission_success = false;



$submission_message = '';



// Check for success message from session (feedback PRG)

if (isset($_SESSION['feedback_success_message'])) {

    $submission_message = $_SESSION['feedback_success_message'];

    $submission_success = true;

    unset($_SESSION['feedback_success_message']);

}



// Check if email verification passed



$both_verified = isset($_SESSION['verified_email']);







// Variables for consultation email verification



$consultation_verification_error = '';



$consultation_verification_success = '';







// Handle consultation email verification



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_consultation_email'])) {



    $consultation_email = trim($_POST['consultation_email'] ?? '');



    $consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;



    



    if (empty($consultation_email) || !filter_var($consultation_email, FILTER_VALIDATE_EMAIL)) {



        $consultation_verification_error = 'Valid email address is required';



    } else {



        // Generate verification token



        $token = bin2hex(random_bytes(32));



        $_SESSION['consultation_verification_token_' . $consultation_id] = $token;



        $_SESSION['consultation_verification_expires_' . $consultation_id] = time() + 15 * 60; // 15 minutes



        $_SESSION['consultation_verified_email_' . $consultation_id] = $consultation_email;



        



        // Send verification email



        $verify_link = siteUrl((string)($_SERVER['REQUEST_URI'] ?? '/') . (strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '?') === false ? '?' : '&') . 'verify_consultation=' . urlencode($token) . '&consultation_id=' . urlencode((string)$consultation_id));



        $subject = "Email Verification - Consultation Feedback - Valenzuela City";



        $body = "Dear Valenzuela City Citizen,\n\n";



        $body .= "Thank you for your willingness to provide valuable feedback on our consultation. ";



        $body .= "To proceed with sharing your insights, please verify your email address by clicking the secure link below:\n\n";



        $body .= $verify_link . "\n\n";



        $body .= "Important Details:\n";



        $body .= "• This verification link expires in 15 minutes for your security\n";



        $body .= "• Your feedback will contribute to better governance and public service\n";



        $body .= "• If you did not initiate this request, please disregard this email\n\n";



        $body .= "For assistance, please contact: consultation@valenzuelacity.gov.ph\n\n";



        $body .= "Sincerely,\n";



        $body .= "Valenzuela City Government\n";



        $body .= "Public Consultation Management Division";



        



        $headers = "From: noreply@valenzuelacity.gov\r\nContent-Type: text/plain; charset=UTF-8";



        @mail($consultation_email, $subject, $body, $headers);



        



        $consultation_verification_success = 'Verification email sent to ' . htmlspecialchars($consultation_email) . '. Check your inbox!';



    }



}







// Check consultation email verification via token



if (isset($_GET['verify_consultation'])) {



    $token = trim($_GET['verify_consultation']);



    $consultation_id = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;



    



    $session_key_token = 'consultation_verification_token_' . $consultation_id;



    $session_key_expires = 'consultation_verification_expires_' . $consultation_id;



    $session_key_email = 'consultation_verified_email_' . $consultation_id;



    



    if (!isset($_SESSION[$session_key_token])) {



        $consultation_verification_error = 'No active email verification request.';



    } elseif (time() > $_SESSION[$session_key_expires]) {



        $consultation_verification_error = 'Email verification link has expired.';



        unset($_SESSION[$session_key_token], $_SESSION[$session_key_expires], $_SESSION[$session_key_email]);



    } elseif ($token !== $_SESSION[$session_key_token]) {



        $consultation_verification_error = 'Invalid verification link.';



    } else {



        // Email verified!



        $_SESSION['consultation_verified_email'] = $_SESSION[$session_key_email];



        $consultation_verification_success = 'Email verified! You can now submit your feedback.';



    }



}











// Handle 2-option survey submission from detail page

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey_vote'])) {

    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {

        $submission_message = 'Security validation failed. Please try again.';

    } else {

        $survey_consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;

        $vote_option = strtolower(trim((string)($_POST['vote_option'] ?? '')));

        $guest_email = trim((string)($_POST['survey_email'] ?? ''));

        $device_token = trim((string)($_POST['device_token'] ?? ''));

        $survey_reason = trim((string)($_POST['survey_reason'] ?? ''));



        if (!in_array($vote_option, ['agree', 'disagree'], true)) {

            $submission_message = 'Please choose one survey option.';

        } elseif ($guest_email !== '' && !filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {

            $submission_message = 'Please enter a valid email address.';

        } elseif ($survey_consultation_id <= 0) {

            $submission_message = 'Invalid consultation.';

        } else {

            $target = getConsultationById($survey_consultation_id);

            $target_mode = strtolower(trim((string)($target['response_mode'] ?? 'feedback')));

            if (!$target || !in_array($target_mode, ['survey', 'hybrid'], true)) {

                $submission_message = 'This consultation does not accept survey responses.';

            } else {

                if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $device_token)) {

                    $device_token = substr(hash('sha256', (string)session_id() . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 48);

                }

                $ip_hash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));

                $ua_hash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

                $ok = submitConsultationGuestVote(

                    $survey_consultation_id,

                    $device_token,

                    $vote_option,

                    $guest_email !== '' ? $guest_email : null,

                    $ip_hash,

                    $ua_hash,

                    0,

                    $survey_reason !== '' ? $survey_reason : null

                );

                if ($ok) {

                    $_SESSION['feedback_success_message'] = 'Survey response recorded. Thank you for participating.';

                    header('Location: public-portal.php?section=detail&id=' . $survey_consultation_id . '&submitted=1');

                    exit;

                }

                $submission_message = 'Failed to save survey response. Please try again.';

            }

        }

    }

}



// Handle feedback submission - allow both verified sessions and direct form submissions



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {

    // Check rate limit

    if (false && $time_since_last_feedback < $feedback_cooldown) {

        $remaining_minutes = ceil(($feedback_cooldown - $time_since_last_feedback) / 60);

        $submission_message = '<div style="background: #fef3c7; color: #92400e; padding: 1rem; border-radius: 8px; border: 1px solid #fbbf24; margin-bottom: 1rem;"><strong>Please wait:</strong> You can only submit one feedback every 30 minutes. Please try again in ' . $remaining_minutes . ' minute(s).</div>';

    } else {

        // Process feedback submission

        // If submitted from a consultation detail page, stay on that page
        if (!empty($_POST['consultation_id']) && isset($_GET['section']) && $_GET['section'] === 'detail') {

            $section = 'detail';

        } else {

            $section = 'feedback';

        }

        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {

            $submission_message = 'Security validation failed. Please try again.';

        } else {

            $name = trim($_POST['name'] ?? '');
            $email = $_SESSION['consultation_verified_email'] ?? ($both_verified ? $_SESSION['verified_email'] : (trim($_POST['email'] ?? '')));
            $message = trim($_POST['message'] ?? '');
            $feedback_type = trim($_POST['feedback_type'] ?? 'general');
            $allow_email_notifications = isset($_POST['allow_email_notifications']) ? 1 : 0;
            $consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
            $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            $phone = trim($_POST['phone'] ?? '');

            // Validation
            $errors = [];
            if ($consultation_id > 0) {
                $target = getConsultationById($consultation_id);
                $target_mode = strtolower(trim((string)($target['response_mode'] ?? 'feedback')));
                if ($target && $target_mode === 'survey') {
                    $errors[] = 'This is a survey-only consultation. Please submit a survey vote.';
                }
            }

            if (empty($name)) $errors[] = 'Name is required';
            if (empty($message)) $errors[] = 'Message is required';
            if (empty($email)) $errors[] = 'Email is required';
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';

            // Store form values for persistence on error
            $feedback_form_values = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'feedback_type' => $feedback_type,
                'rating' => $rating,
                'message' => $message,
                'allow_email_notifications' => $allow_email_notifications,
            ];

            // Store errors for display
            $feedback_form_errors = [
                'name' => empty($name) ? 'Name is required' : '',
                'email' => empty($email) ? 'Email is required' : (!filter_var($email, FILTER_VALIDATE_EMAIL) ? 'Valid email is required' : ''),
                'message' => empty($message) ? 'Message is required' : '',
            ];

            if (!empty($errors)) {
            }

            if (empty($errors)) {
                if ($consultation_id <= 0) {
                    $errors[] = 'Please select a consultation before submitting feedback.';
                }
                if (isSpamFeedback($name, $email, $message)) {
                    $errors[] = 'Your feedback appears spammy or contains inappropriate language. Please revise and submit again.';
                }
            }

            if (empty($errors)) {
                // If rating is provided, validate it (rating is optional)
                if ($rating > 0 && ($rating < 1 || $rating > 5)) {
                    $errors[] = 'Please provide a valid rating between 1 and 5.';
                }

                if (empty($errors)) {
                    $submitResult = submitFeedback($name, $email, $phone, $consultation_id, $rating, $feedback_type, $message, $allow_email_notifications);
                    if (is_array($submitResult) && isset($submitResult['error']) && $submitResult['error'] === 'duplicate') {
                        $errors[] = 'Duplicate feedback detected for this consultation. Please do not submit the same message again.';
                    } elseif (!$submitResult || empty($submitResult['id'])) {
                        $submission_message = 'Error: Could not save feedback to database. Please try again later.';
                        error_log('Feedback insert error: ' . json_encode($submitResult));
                    } else {
                        $new_feedback_id = (int)$submitResult['id'];
                        if (file_exists(__DIR__ . '/DATABASE/notifications.php')) {
                            require_once __DIR__ . '/DATABASE/notifications.php';
                            $nm = 'New feedback received (ID: ' . $new_feedback_id . ') from ' . ($name ?: 'Guest') . ' - ' . (ucfirst(str_replace('_', ' ', $feedback_type)) ?: 'Feedback');
                            @createNotification(0, $nm, 'feedback');
                        }

                        if ($stmt) {
                            $stmt->close();
                        }
                    }
                } else {
                    $submission_message = 'Error: ' . implode(', ', $errors);
                }
            } else {
                $submission_message = 'Error: ' . implode(', ', $errors);
            }
        }
    }

    // Ensure we stay on submit-consultation section after submission
    if (!empty($consultation_submission_message)) {
        $section = 'submit-consultation';
    }

} // Close feedback submission block







    if (!empty($submission_message)) {



        $modal_title = $submission_success ? 'Submitted' : 'Please check your entries';



        $modal_message = $submission_message;



        $modal_type = $submission_success ? 'success' : 'error';



    }







    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact']) && $both_verified) {



        $contact_name = trim($_POST['contact_name'] ?? '');



        $contact_phone = $_SESSION['verified_phone'];



        $contact_email = $_SESSION['verified_email'];



        $contact_subject = trim($_POST['contact_subject'] ?? '');



        $contact_message_text = trim($_POST['contact_message_text'] ?? '');



        $meeting_type = trim($_POST['meeting_type'] ?? 'inquiry');



        $allow_email_notifications = isset($_POST['allow_email_notifications']) ? 1 : 0;



        



        // Validation



        $errors = [];



        if (empty($contact_name)) $errors[] = 'Name is required';



        if (empty($contact_subject)) $errors[] = 'Subject is required';



        if (empty($contact_message_text)) $errors[] = 'Message is required';



        



        if (empty($errors)) {



            $contact_consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;



            $contact_rating = 0;



            $stmt = $conn->prepare("INSERT INTO feedback (name, email, phone, message, feedback_type, allow_email_notifications, consultation_id, rating, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");



            if ($stmt) {



                $full_message = "Meeting Type: " . ucfirst($meeting_type) . "\nSubject: " . $contact_subject . "\n\n" . $contact_message_text;



                $feedback_type = 'meeting_request';



                $stmt->bind_param("sssssiii", $contact_name, $contact_email, $contact_phone, $full_message, $feedback_type, $allow_email_notifications, $contact_consultation_id, $contact_rating);



                if ($stmt->execute()) {



                    // Handle optional file upload for contact request



                    $attachment_path = null;



                    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {



                        $file = $_FILES['attachment'];



                        $maxSize = 5 * 1024 * 1024; // 5MB



                        $allowedExt = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx'];



                        $origName = basename($file['name']);



                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));



                        if ($file['size'] <= $maxSize && in_array($ext, $allowedExt)) {



                            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));



                            $newName = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeName . '.' . $ext;



                            $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;



                            if (@move_uploaded_file($file['tmp_name'], $dest)) {



                                $attachment_path = 'uploads/attachments/' . $newName;



                            }



                        }



                    }



                    // Send confirmation email



                    $subject = "Valenzuela City - Get In Touch Request Received";



                    $body = "Dear $contact_name,\n\n";



                    $body .= "Thank you for reaching out to the Valenzuela City Government.\n\n";



                    $body .= "We have received your " . strtolower($meeting_type) . " request and will contact you soon.\n\n";



                    $body .= "Details:\n";



                    $body .= "Subject: " . $contact_subject . "\n";



                $body .= "Contact: " . $contact_phone . "\n";



                $body .= "Submitted: " . date('F j, Y \a\t g:i A') . "\n\n";



                if ($allow_email_notifications) {



                    $body .= "You have opted in to receive email updates about your request.\n";



                    $body .= "We will keep you informed of any relevant developments.\n\n";



                } else {



                    $body .= "You will not receive further email notifications about this submission.\n\n";



                }



                $body .= "We will get back to you within 2-3 business days.\n\n";



                $body .= "Regards,\nValenzuela City Government\nPublic Consultation Office";



                



                sendGmailEmail($contact_email, $subject, $body, false);



                



                // Update record with attachment if uploaded



                if (!empty($attachment_path)) {



                    $lastId = $conn->insert_id;



                    $escaped = $conn->real_escape_string($attachment_path);



                    $conn->query("UPDATE feedback SET attachment_path = '$escaped' WHERE id = " . (int)$lastId);



                }







                $submission_success = true;



                $submission_message = 'Thank you! Your request has been submitted. A confirmation email has been sent. We will contact you within 2-3 business days.';



                



                // Clear session



                unset($_SESSION['verified_phone'], $_SESSION['verified_email'], $_SESSION['form_step']);



            }



            if ($stmt) {

                $stmt->close();

            }



        }



    } else {



        $submission_message = 'Error: ' . implode(', ', $errors);



    }



}







// Ensure consultations table exists (do not drop/sample data here)



initializeConsultationsTable();







// Ensure feedback table exists



initializeFeedbackTable();







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







// Ensure consultation summary token columns exist



$sumColCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME IN ('summary_token','summary_token_expires')");



$sumCols = [];



if ($sumColCheck) {



    while ($r = $sumColCheck->fetch_assoc()) {



        $sumCols[] = $r['COLUMN_NAME'];



    }



}



if (!in_array('summary_token', $sumCols)) {



    $conn->query("ALTER TABLE consultations ADD COLUMN summary_token VARCHAR(64) DEFAULT NULL");



}



if (!in_array('summary_token_expires', $sumCols)) {



    $conn->query("ALTER TABLE consultations ADD COLUMN summary_token_expires DATETIME DEFAULT NULL");



}







// Ensure feedback table can store attachment path



$fbColCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback' AND COLUMN_NAME = 'attachment_path'");



$hasFbAttachment = false;



if ($fbColCheck) {



    while ($r = $fbColCheck->fetch_assoc()) {



        if ($r['COLUMN_NAME'] === 'attachment_path') $hasFbAttachment = true;



    }



}



if (!$hasFbAttachment) {



    $conn->query("ALTER TABLE feedback ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL");



}







// Ensure feedback table can store email notification preference



$fbEmailPrefCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback' AND COLUMN_NAME = 'allow_email_notifications'");



$hasFbEmailPref = false;



if ($fbEmailPrefCheck) {



    while ($r = $fbEmailPrefCheck->fetch_assoc()) {



        if ($r['COLUMN_NAME'] === 'allow_email_notifications') $hasFbEmailPref = true;



    }



}



if (!$hasFbEmailPref) {



    $conn->query("ALTER TABLE feedback ADD COLUMN allow_email_notifications TINYINT(1) DEFAULT 1");



}







// Ensure uploads directory exists



$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'attachments';



if (!is_dir($uploadDir)) {



    @mkdir($uploadDir, 0755, true);



}







// Build optional filters from GET params (search + date range)



$q = isset($_GET['q']) ? trim($_GET['q']) : '';



$start_filter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';



$end_filter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';



$in_description = isset($_GET['in_description']) && $_GET['in_description'] ? true : false;



function resolveConsultationCardImagePath(array $consultation): ?string {

    $candidate_paths = [];



    if (!empty($consultation['image_path'])) {

        $candidate = trim((string)$consultation['image_path']);

        if ($candidate !== '') {

            if (preg_match('#^https?://#i', $candidate)) {

                return $candidate;

            }

            $normalized = str_replace('\\', '/', ltrim($candidate, '/'));

            $candidate_paths[] = $normalized;

            $candidate_paths[] = 'ASSETS/images/consultations/' . basename($normalized);

            $candidate_paths[] = 'images/' . basename($normalized);

            $candidate_paths[] = 'uploads/' . basename($normalized);

        }

    }



    $id = (int)($consultation['id'] ?? 0);

    if ($id > 0) {

        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {

            $candidate_paths[] = "images/consultation_{$id}.{$ext}";

            $candidate_paths[] = "images/consultation-{$id}.{$ext}";

        }

    }



    $title = strtolower((string)($consultation['title'] ?? ''));

    $category = strtolower((string)($consultation['category'] ?? ''));

    $text = $title . ' ' . $category;

    $keyword_image_map = [

        'illegal' => 'images/illegaldumping.webp',

        'dump' => 'images/illegaldumping.webp',

        'housing' => 'images/housing.jpg',

        'infrastructure' => 'images/streetlights.png',

        'street' => 'images/streetlights.png',

        'light' => 'images/streetlights.png',

        'market' => 'images/publicMarket.webp',

        'ordinance' => 'images/public cons.JPG',

        'traffic' => 'images/traffic.jpg',

        'environment' => 'images/environmental.jpg',

        'water' => 'images/wawter.jpg',

        'youth' => 'images/local youth.webp',

    ];

    foreach ($keyword_image_map as $keyword => $path) {

        if (strpos($text, $keyword) !== false) {

            $candidate_paths[] = $path;

        }

    }



    foreach ($candidate_paths as $path) {

        if ($path !== '' && file_exists(__DIR__ . '/' . $path)) {

            return $path;

        }

    }



    if (file_exists(__DIR__ . '/images/public cons.JPG')) {

        return 'images/public cons.JPG';

    }

    if (file_exists(__DIR__ . '/images/consultation.png')) {

        return 'images/consultation.png';

    }

    return null;

}







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



$active_consultations = $conn->query("SELECT id, title, description, category, start_date, end_date, status, image_path, source_url, response_mode FROM consultations WHERE $where_active ORDER BY start_date DESC LIMIT 50");







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



$past_consultations = $conn->query("SELECT id, title, description, category, start_date, end_date, status, image_path, source_url, response_mode FROM consultations WHERE $where_past ORDER BY end_date DESC LIMIT 50");





// Survey transparency results (citizen-facing): include active + closed survey-capable consultations.

// Use normalized comparisons to support legacy/case-variant values.

$where_survey_results = "(

    LOWER(TRIM(COALESCE(response_mode, ''))) IN ('survey', 'hybrid')

    OR TRIM(COALESCE(survey_question, '')) <> ''

) AND LOWER(TRIM(COALESCE(status, ''))) IN ('active', 'closed')";

if ($q !== '') {

    $q_esc = $conn->real_escape_string($q);

    if ($in_description) {

        $where_survey_results .= " AND (title LIKE '%$q_esc%' OR description LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";

    } else {

        $where_survey_results .= " AND (title LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";

    }

}

if ($start_filter !== '') {

    $start_esc = $conn->real_escape_string($start_filter);

    $where_survey_results .= " AND DATE(COALESCE(start_date, end_date, created_at)) >= '$start_esc'";

}

if ($end_filter !== '') {

    $end_esc = $conn->real_escape_string($end_filter);

    $where_survey_results .= " AND DATE(COALESCE(end_date, start_date, created_at)) <= '$end_esc'";

}



$survey_results_rows = [];

$survey_results_query = $conn->query("SELECT id, title, category, status, start_date, end_date, response_mode, survey_question, survey_option_a, survey_option_b FROM consultations WHERE $where_survey_results ORDER BY updated_at DESC LIMIT 100");

if ($survey_results_query) {

    while ($row = $survey_results_query->fetch_assoc()) {

        $stats = getConsultationVoteStats((int)($row['id'] ?? 0));

        $row['vote_stats'] = $stats;

        $row['survey_question'] = trim((string)($row['survey_question'] ?? '')) !== '' ? $row['survey_question'] : 'Do you support this proposal?';

        $row['survey_option_a'] = trim((string)($row['survey_option_a'] ?? '')) !== '' ? $row['survey_option_a'] : 'Agree';

        $row['survey_option_b'] = trim((string)($row['survey_option_b'] ?? '')) !== '' ? $row['survey_option_b'] : 'Disagree';

        $survey_results_rows[] = $row;

    }

}







// Ensure feedback table has columns for consultation linking and rating (safe to run repeatedly)



$checkCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback'");



$haveCols = [];



if ($checkCols) {



    while ($r = $checkCols->fetch_assoc()) { $haveCols[] = $r['COLUMN_NAME']; }



}



if (!in_array('consultation_id', $haveCols)) {



    $conn->query("ALTER TABLE feedback ADD COLUMN consultation_id INT DEFAULT NULL");



}



if (!in_array('rating', $haveCols)) {



    $conn->query("ALTER TABLE feedback ADD COLUMN rating TINYINT DEFAULT NULL");



}







// Determine current form step for display



$current_form_step = $_SESSION['form_step'] ?? 'phone_otp';



?>



<!DOCTYPE html>



<html lang="en">



<head>



    <meta charset="UTF-8">



    <meta name="apple-mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <meta name="format-detection" content="telephone=no">

    <title>PCMS - Public Consultation Management Portal | City of Valenzuela</title>

    <meta name="description" content="Public Consultation Management Portal - City Government of Valenzuela, Metropolitan Manila">

    <meta name="keywords" content="PCMS, Valenzuela, Public Consultation, Consultation Management">

    <meta name="theme-color" content="#991b1b">



    <link rel="icon" type="image/png" href="images/logo.webp">



    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">



    <script src="https://cdn.tailwindcss.com"></script>



    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">



    <!-- Prevent dark mode flicker -->



    <script>



        if (localStorage.getItem('portal-theme') === 'dark') {



            document.documentElement.classList.add('dark');



        }



    </script>



    <script>

        function updateConsultationLocationInputs(lat, lng) {

            const status = document.getElementById('consultation-map-status');

            const latInput = document.getElementById('consultation-location-lat');

            const lngInput = document.getElementById('consultation-location-lng');

            if (latInput) latInput.value = Number.isFinite(lat) ? lat.toFixed(7) : '';

            if (lngInput) lngInput.value = Number.isFinite(lng) ? lng.toFixed(7) : '';

            if (status) {

                status.textContent = Number.isFinite(lat) && Number.isFinite(lng)

                    ? `Selected location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`

                    : 'No location selected yet. Click the map to choose a point inside Valenzuela.';

            }

        }



        document.addEventListener('DOMContentLoaded', function() {

            // initConsultationLocationMap();

        });

    </script>



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







        /* bootstrap icons font-family fallback to ensure icons render */



        .bi {

            font-family: 'bootstrap-icons' !important;

            font-style: normal;

            font-weight: 400;

            font-variant: normal;

            text-transform: none;

            line-height: 1;

            -webkit-font-smoothing: antialiased;

            -moz-osx-font-smoothing: grayscale;

        }







        /* Underlined (underscore) input style */



        .underscored-input {



            border: none;



            border-bottom: 2px solid #d1d5db;



            padding: 0.5rem 0.25rem;



            font-size: 0.95rem;



            width: 100%;



            background: transparent;



        }







        /* Loading Modal Styles */



        .loading-modal {



            position: fixed;



            top: 0;



            left: 0;



            width: 100%;



            height: 100%;



            background: rgba(0, 0, 0, 0.5);



            display: flex;



            justify-content: center;



            align-items: center;



            z-index: 10000;



        }







        .loading-modal-content {



            background: white;



            padding: 2.5rem;



            border-radius: 12px;



            text-align: center;



            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);



            max-width: 400px;



            width: 90%;



        }







        .loading-spinner {



            margin-bottom: 1.5rem;



        }







        .spinner {



            width: 50px;



            height: 50px;



            border: 4px solid #e5e7eb;



            border-top: 4px solid #991b1b;



            border-radius: 50%;



            animation: spin 1s linear infinite;



            margin: 0 auto;



        }







        @keyframes spin {



            0% { transform: rotate(0deg); }



            100% { transform: rotate(360deg); }



        }







        .loading-modal-content h3 {



            color: #1f2937;



            margin: 0 0 0.5rem 0;



            font-size: 1.5rem;



            font-weight: 700;



        }







        .loading-modal-content p {



            color: #6b7280;



            margin: 0;



            font-size: 1rem;



        }







        /* Center the form container */



        #section-submit-consultation > div:last-child {



            max-width: 800px;



            margin: 0 auto;



        }







        /* Loading Modal Styles */



        .loading-modal {



            position: fixed;



            top: 0;



            left: 0;



            width: 100%;



            height: 100%;



            background: rgba(0, 0, 0, 0.5);



            display: flex;



            justify-content: center;



            align-items: center;



            z-index: 10000;



        }







        .loading-modal-content {



            background: white;



            padding: 2.5rem;



            border-radius: 12px;



            text-align: center;



            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);



            max-width: 400px;



            width: 90%;



        }







        .loading-spinner {



            margin-bottom: 1.5rem;



        }







        .spinner {



            width: 50px;



            height: 50px;



            border: 4px solid #e5e7eb;



            border-top: 4px solid #991b1b;



            border-radius: 50%;



            animation: spin 1s linear infinite;



            margin: 0 auto;



        }







        @keyframes spin {



            0% { transform: rotate(0deg); }



            100% { transform: rotate(360deg); }



        }







        .loading-modal-content h3 {



            color: #1f2937;



            margin: 0 0 0.5rem 0;



            font-size: 1.5rem;



            font-weight: 700;



        }







        .loading-modal-content p {



            color: #6b7280;



            margin: 0;



            font-size: 1rem;



        }







        /* Center the form container */



        #section-submit-consultation > div:last-child {



            max-width: 800px;



            margin: 0 auto;



        }







        /* Two-column label / field table style for consultation form */



        .form-table {



            width: 100%;



            border: 1px solid #e5e7eb;



            border-radius: 6px;



            overflow: hidden;



            background: white;



        }



        .form-row {



            display: grid;



            grid-template-columns: 260px 1fr;



            gap: 1rem;



            align-items: start;



            padding: 12px 16px;



            border-bottom: 1px solid #eef2f7;



        }



        .form-row:last-child { border-bottom: none; }



        .form-label-cell {



            font-weight: 700;



            color: #374151;



            padding-top: 6px;



            font-size: 0.95rem;



        }



        .form-field-cell input[type="text"], .form-field-cell input[type="email"], .form-field-cell select, .form-field-cell textarea {



            width: 100%;



            padding: 0.6rem 0.75rem;



            border: 1px solid #d1d5db;



            border-radius: 6px;



            font-size: 0.95rem;



            background: white;



            box-sizing: border-box;



        }



        .form-field-cell textarea { min-height: 120px; resize: vertical; }



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







        .consultation-card-grid {



            display: grid;



            grid-template-columns: 1fr 1.2fr;



        }



        .consultation-card-grid .consultation-image-col {



            min-height: 300px;



            overflow: hidden;



            position: relative;



        }



        .consultation-card-grid .consultation-content-col {



            padding: 2rem;



            display: flex;



            flex-direction: column;



            justify-content: space-between;



        }







        /* MOBILE TWEAKS */



        @media (max-width: 640px) {



            header .max-w-7xl {



                align-items: flex-start;



                gap: 0.75rem;



            }



            header .header-buttons {



                flex-wrap: wrap;



                justify-content: flex-end;



                gap: 0.5rem;



            }



            header a {



                padding: 0.55rem 0.9rem;



                font-size: 0.85rem;



            }



            header h1 {



                font-size: 1.1rem;



            }







            .consultation-card-grid {



                grid-template-columns: 1fr !important;



            }



            .consultation-card-grid .consultation-image-col {



                min-height: 180px !important;



            }



            .consultation-card-grid .consultation-content-col {



                padding: 1.25rem !important;



            }







            /* Make tab nav scroll instead of wrapping/overlapping */



            .public-nav-scroll {



                overflow-x: auto;



                -webkit-overflow-scrolling: touch;



            }



            .public-nav-scroll::-webkit-scrollbar { height: 0; }



            .public-nav-scroll .nav-link {



                flex: 0 0 auto;



                white-space: nowrap;



                padding: 0.75rem 1rem;



            }



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



            display: inline-flex;



            align-items: center;



            justify-content: center;



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



            background: white !important;



            color: #1f2937 !important;



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



            font-size: 2rem !important;



            letter-spacing: 1.2rem !important;



            text-align: center !important;



            font-weight: 900 !important;



            font-family: 'Courier New', monospace !important;



            background: white !important;



            padding: 1rem !important;



            border-radius: 8px !important;



            color: #1f2937 !important;



            caret-color: #1f2937 !important;



            -webkit-text-fill-color: #1f2937 !important;



        }







        .otp-input::placeholder {



            color: #d1d5db !important;



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



        .social-icon { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(255,255,255,0.1); transition: all 0.16s ease; color: white; font-size: 1.25rem; text-decoration: none; }



        .social-icon:hover { background: rgba(255,255,255,0.2); transform: translateY(-3px); color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }



        .social-caption { margin-top: 6px; color: #9ca3af; font-size: 0.85rem; font-weight: 700; }







        /* ===================== DARK MODE ===================== */



        .dark body, .dark { background: #111827 !important; color: #e5e7eb !important; }



        .dark header { background: #1f2937 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important; }



        .dark header h1 { color: #fca5a5 !important; }



        .dark header p { color: #9ca3af !important; }



        .dark header a:first-child { color: #fca5a5 !important; border-color: #fca5a5 !important; }



        .dark header a:first-child:hover { background: rgba(252,165,165,0.1) !important; }



        .dark .nav-link { color: #9ca3af !important; }



        .dark .nav-link.active { color: #fca5a5 !important; border-bottom-color: #fca5a5 !important; background: rgba(252,165,165,0.05) !important; }



        .dark .nav-link:hover { color: #fca5a5 !important; background: rgba(252,165,165,0.05) !important; }



        .dark div[style*="background: white"][style*="border-bottom"] { background: #1f2937 !important; border-color: #374151 !important; }



        .dark main { background: #111827 !important; }



        .dark .card, .dark .consultation-card, .dark .form-table { background: #1f2937 !important; border-color: #374151 !important; color: #e5e7eb !important; }



        .dark .card:hover, .dark .consultation-card:hover { box-shadow: 0 16px 40px rgba(0,0,0,0.4) !important; }



        .dark .consultation-card-body { background: #1f2937 !important; }



        .dark .consultation-card-body p, .dark .consultation-card p { color: #9ca3af !important; }



        .dark .consultation-meta { border-color: #374151 !important; color: #6b7280 !important; }



        .dark h2, .dark h3, .dark h4 { color: #f3f4f6 !important; }



        .dark p { color: #d1d5db !important; }



        .dark .form-input, .dark .form-textarea, .dark .form-select, .dark input[type="text"], .dark input[type="email"], .dark input[type="number"], .dark select, .dark textarea {



            background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important;



        }



        .dark .form-input:focus, .dark .form-textarea:focus, .dark .form-select:focus { border-color: #fca5a5 !important; box-shadow: 0 0 0 4px rgba(252,165,165,0.15) !important; background: #1f2937 !important; }



        .dark .form-input::placeholder, .dark .form-textarea::placeholder, .dark input::placeholder, .dark textarea::placeholder { color: #6b7280 !important; }



        .dark .form-label, .dark .form-label-cell, .dark label { color: #d1d5db !important; }



        .dark .form-row { border-color: #374151 !important; }



        .dark .underscored-input { border-bottom-color: #4b5563 !important; color: #e5e7eb !important; }



        .dark .btn-secondary { background: #374151 !important; color: #e5e7eb !important; border-color: #4b5563 !important; }



        .dark .btn-secondary:hover { border-color: #fca5a5 !important; color: #fca5a5 !important; background: rgba(252,165,165,0.1) !important; }



        .dark .success-message { background: linear-gradient(135deg, #064e3b, #065f46) !important; color: #a7f3d0 !important; border-left-color: #10b981 !important; }



        .dark .error-message { background: linear-gradient(135deg, #7f1d1d, #991b1b) !important; color: #fecaca !important; border-left-color: #ef4444 !important; }



        .dark .info-message { background: linear-gradient(135deg, #1e3a5f, #1e40af) !important; color: #bfdbfe !important; border-left-color: #3b82f6 !important; }



        .dark .step-indicator { background: linear-gradient(135deg, #1f2937, #374151) !important; border-color: #374151 !important; }



        .dark .badge-verified { background: rgba(59,130,246,0.2) !important; color: #93c5fd !important; }



        .dark footer { background: linear-gradient(135deg, #0f172a, #1e293b) !important; }



        .dark .social-icon { background: rgba(255,255,255,0.08) !important; }



        .dark .social-icon:hover { background: rgba(255,255,255,0.15) !important; }



        .dark #app-modal > div { background: #1f2937 !important; }



        .dark #app-modal [role="dialog"] { background: #1f2937 !important; color: #e5e7eb !important; }



        .dark #app-modal [role="dialog"] div[style*="border-bottom"] { border-color: #374151 !important; }



        .dark #app-modal [role="dialog"] [id="app-modal-title"] { color: #f3f4f6 !important; }



        .dark #app-modal [role="dialog"] div[style*="color:#374151"] { color: #d1d5db !important; }



        /* Dark chatbot */



        .dark #chatbot-window { background: #1f2937 !important; }



        .dark #chatbot-messages { background: #111827 !important; }



        .dark .chat-msg.bot div { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }



        .dark #chatbot-window div[style*="border-top:1px solid #e5e7eb"] { border-color: #374151 !important; background: #1f2937 !important; }



        .dark #chatbot-input { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }



        .dark #chatbot-quick { background: #111827 !important; border-color: #374151 !important; }



        .dark #chatbot-quick button { background: #374151 !important; border-color: #4b5563 !important; color: #fca5a5 !important; }







        /* ===================== TOOLBAR BUTTONS ===================== */



        .portal-toolbar { display: flex; align-items: center; gap: 0.5rem; }



        .toolbar-btn {



            width: 38px; height: 38px; border-radius: 8px; border: 1.5px solid #e5e7eb; background: white;



            display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;



            color: #6b7280; font-size: 1.1rem;



        }



        .toolbar-btn:hover { border-color: #991b1b; color: #991b1b; background: #fef2f2; }



        .dark .toolbar-btn { background: #374151 !important; border-color: #4b5563 !important; color: #9ca3af !important; }



        .dark .toolbar-btn:hover { border-color: #fca5a5 !important; color: #fca5a5 !important; background: rgba(252,165,165,0.1) !important; }



        .lang-btn { width: auto; padding: 0 10px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.5px; }



    </style>

    <script src="app-features.js?v=<?php echo time(); ?>&nocache=<?php echo rand(1000, 9999); ?>"></script>



</head>



<body>







<?php if (!empty($modal_message)): ?>



    <div id="app-modal" style="position:fixed; top:20px; right:20px; z-index:9998; display:flex; align-items:center; justify-content:flex-end;" data-type="<?php echo htmlspecialchars($modal_type); ?>">



        <div style="background:<?php echo $modal_type === 'success' ? '#10b981' : '#ef4444'; ?>; color:white; border-radius:8px; padding:1rem 1.5rem; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05); max-width:400px; display:flex; align-items:center; gap:0.75rem;">



            <i class="bi bi-<?php echo $modal_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>" style="font-size:1.2rem; flex-shrink:0;"></i>



            <div style="flex:1;">



                <div style="font-weight:700; font-size:0.9rem; margin-bottom:0.25rem;"><?php echo htmlspecialchars($modal_title); ?></div>



                <div style="font-size:0.85rem; opacity:0.9;"><?php 



                        $safe_msg = htmlspecialchars($modal_message);



                        $safe_msg = preg_replace('/(https?:\/\/[^\s&<]+)/', '<a href="$1" target="_blank" style="color:#fff; text-decoration:underline; word-break:break-all;">$1</a>', $safe_msg);



                        echo nl2br($safe_msg);



                    ?></div>



                <?php if ($modal_type === 'success'): ?>



                    <div style="margin-top:1rem;">



                        <button onclick="this.closest('#app-modal').style.display='none'" style="background:rgba(255,255,255,0.2); border:none; color:white; padding:0.5rem 1rem; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.85rem;">



                            Got it ✓



                        </button>



                    </div>



                <?php endif; ?>



            </div>



        </div>



    </div>



    



    <script>



        // Auto-hide notification after 8 seconds (errors only, success stays)



        setTimeout(function() {



            var modal = document.getElementById('app-modal');



            if (modal && modal.dataset.type !== 'success') {



                modal.style.transition = 'opacity 0.5s';



                modal.style.opacity = '0';



                setTimeout(function() {



                    if (modal) modal.style.display = 'none';



                }, 500);



            }



        }, 8000);



    </script>



<?php endif; ?>



<!-- Success Links Section - Display after consultation submission -->



<?php if (isset($_SESSION['consultation_edit_link']) && isset($_SESSION['consultation_summary_link'])): ?>



    <div style="margin: 2rem auto; max-width: 600px; padding: 2rem; background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 101, 52, 0.1)); border: 2px solid #22c55e; border-radius: 12px;">



        <div style="text-align: center; margin-bottom: 1.5rem;">



            <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; color: #22c55e; margin-bottom: 0.5rem;"></i>



            <h2 style="color: #166534; margin: 0.5rem 0; font-size: 1.5rem;">Submission Successful!</h2>



            <p style="color: #6b7280; margin: 0.25rem 0;">Your consultation request has been recorded.</p>



        </div>



        



        <div style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 1rem;">



            <div style="margin-bottom: 1.5rem;">



                <p style="font-size: 0.9rem; color: #6b7280; margin: 0 0 0.5rem 0; font-weight: 600;">Reference Number (save this):</p>



                <div style="padding: 0.75rem; background: #f3f4f6; border-left: 4px solid #2563eb; border-radius: 4px; font-family: monospace; font-weight: 700; color: #1f2937; word-break: break-all;">



                    <?php echo htmlspecialchars($_SESSION['consultation_reference'] ?? ''); ?>



                </div>



            </div>



            



            <div style="margin-bottom: 1rem;">



                <p style="font-size: 0.9rem; color: #6b7280; margin: 0 0 0.5rem 0; font-weight: 600;">📥 Download Your Submission Summary:</p>



                <a href="<?php echo htmlspecialchars($_SESSION['consultation_summary_link']); ?>" target="_blank" style="display: inline-block; padding: 0.75rem 1.25rem; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; transition: background 0.3s;">



                    <i class="bi bi-download"></i> Download PDF Summary



                </a>



            </div>



            



            <div>



                <p style="font-size: 0.9rem; color: #6b7280; margin: 0 0 0.5rem 0; font-weight: 600;">✏️ Edit Your Submission (valid for 7 days):</p>



                <a href="<?php echo htmlspecialchars($_SESSION['consultation_edit_link']); ?>" target="_blank" style="display: inline-block; padding: 0.75rem 1.25rem; background: #7c3aed; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; transition: background 0.3s;">



                    <i class="bi bi-pencil-square"></i> Edit Your Submission



                </a>



            </div>



        </div>



        



        <p style="font-size: 0.85rem; color: #6b7280; text-align: center; margin: 0;">



            <strong>Note:</strong> These links are valid for 7 days. Save them for your records.



        </p>



    </div>



    



    <?php 



        // Clear the session variables after displaying

        unset($_SESSION['consultation_edit_link']);

        unset($_SESSION['consultation_summary_link']);

        unset($_SESSION['consultation_reference']);

        unset($_SESSION['consultation_id']);

    ?>



<?php endif; ?>







<!-- HEADER -->



<header>



    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">



        <div class="logo-section">



            <img src="images/logo.webp" alt="Logo">



            <div>



                <h1 data-i18n="header_title">Public Consultation</h1>



                <p data-i18n="header_subtitle">Valenzuela City Government</p>



            </div>



        </div>



        <div class="header-buttons" style="display:flex; align-items:center; gap:0.5rem;">



            <div class="portal-toolbar">



                <button onclick="startPortalTour()" class="toolbar-btn" id="tour-btn" title="Take a guided tour">



                    <i class="bi bi-question-circle-fill"></i>



                </button>



                <button onclick="togglePortalLang()" class="toolbar-btn lang-btn" id="lang-toggle" title="Switch to Tagalog">EN</button>



                <button onclick="togglePortalTheme()" class="toolbar-btn" id="theme-toggle-portal" title="Toggle dark mode">



                    <i class="bi bi-moon-fill" id="portal-dark-icon"></i>



                    <i class="bi bi-sun-fill" id="portal-light-icon" style="display:none;"></i>



                </button>



            </div>



            <a href="index.php" data-i18n="back_home">Back Home</a>



        </div>



    </div>



</header>







<!-- FLASH / NOTIFICATIONS -->



<?php if (!empty($verification_error)): ?>



    <div style="max-width:80rem; margin: 1rem auto; padding: 0 1rem;">



        <?php if (!empty($verification_error)): ?>



            <div style="background:#fee2e2; color:#7f1d1d; padding:1rem; border-radius:8px; border:1px solid #fca5a5; margin-bottom:0.75rem; font-weight:700;"><?php echo htmlspecialchars($verification_error); ?></div>



        <?php endif; ?>



    </div>



<?php endif; ?>







<!-- NAVIGATION -->



<div style="background: white; border-bottom: 2px solid #f0f0f0; position: sticky; top: 0; z-index: 30;" class="portal-nav-bar">



    <div class="max-w-7xl mx-auto px-4 flex public-nav-scroll">



        <button type="button" onclick="switchSection('consultations')" class="nav-link active" id="nav-consultations">



            <i class="bi bi-file-text"></i><span data-i18n="nav_consultations">Active Consultations</span>



        </button>



        <button type="button" onclick="switchSection('submit-consultation')" class="nav-link" id="nav-submit-consultation">



            <i class="bi bi-pencil-square"></i><span data-i18n="nav_submit">Submit Consultation</span>



        </button>

        <button type="button" onclick="switchSection('survey-results')" class="nav-link" id="nav-survey-results">



            <i class="bi bi-bar-chart-line"></i><span data-i18n="nav_survey_results">Survey Results</span>



        </button>



            </div>



</div>







<!-- BANNER / HEADLINER removed from global header - now rendered inside consultations section only -->







<!-- MAIN CONTENT -->



<main style="max-width: 80rem; margin: 0 auto; padding: 2rem 1rem;">



    <!-- CONSULTATIONS SECTION -->



    <section id="section-consultations" class="section-active">







        <?php if ($section !== 'detail'): ?>



            <div style="margin-bottom: 2rem; text-align: center;">



                <h2 style="font-size: 2.5rem; font-weight: 800; color: #1f2937; margin: 0 0 0.75rem 0;" data-i18n="consultations_title">Active Consultations</h2>



                <p style="color: #6b7280; font-size: 1.1rem; margin: 0;" data-i18n="consultations_subtitle">Review and provide feedback on proposed ordinances, programs, and policies</p>



            </div>







            <!-- SECTION BANNER (only for consultations) -->



            <div style="background: linear-gradient(135deg, #111827, #1f2937); margin-bottom: 1.5rem; border-radius: 12px; overflow: hidden;">



                <div class="max-w-7xl mx-auto px-4 py-6" style="display:flex; align-items:center; justify-content:center;">



                    <?php if (file_exists(__DIR__ . '/images/consultation.png')): ?>



                        <img src="images/consultation.png" alt="Consultation headliner" style="width:100%; max-width:1200px; height:auto; display:block;">



                    <?php else: ?>



                        <div style="width:100%; max-width:1200px; height:140px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#6b7280; font-weight:700;">Consultation Headliner</div>



                    <?php endif; ?>



                </div>



            </div>



            <?php endif; ?>







        <?php if ($section === 'detail' && $consultation_detail): ?>



            <!-- CONSULTATION DETAIL VIEW -->



            <?php if (!empty($detail_error)): ?>



                <div class="error-message"><?php echo htmlspecialchars($detail_error); ?></div>



            <?php endif; ?>



            <div style="margin-bottom: 2rem; display:flex; justify-content:flex-end;">


                <button type="button" onclick="window.location.href='?section=consultations'" class="btn btn-secondary" style="display:flex; align-items:center; gap:0.5rem;">


                    <i class="bi bi-x-lg"></i>Close


                </button>


            </div>







            <div class="card">

                <?php

                $detail_mode = strtolower(trim((string)($consultation_detail['response_mode'] ?? 'feedback')));

                if (!in_array($detail_mode, ['feedback', 'survey', 'hybrid'], true)) {

                    $detail_mode = 'feedback';

                }

                $is_survey_only_detail = ($detail_mode === 'survey');

                $detail_survey_question = trim((string)($consultation_detail['survey_question'] ?? ''));
                $detail_survey_option_a = trim((string)($consultation_detail['survey_option_a'] ?? ''));
                $detail_survey_option_b = trim((string)($consultation_detail['survey_option_b'] ?? ''));

                // Only set default survey values if this is actually a survey-type consultation
                if ($is_survey_only_detail || $detail_mode === 'hybrid') {
                    if ($detail_survey_question === '') $detail_survey_question = 'Do you support this proposal?';
                    if ($detail_survey_option_a === '') $detail_survey_option_a = 'Agree';
                    if ($detail_survey_option_b === '') $detail_survey_option_b = 'Disagree';
                }

                $detail_vote_stats = getConsultationVoteStats((int)$consultation_detail['id']);

                ?>



                <!-- Feedback trigger button -->
                <button type="button" data-bs-toggle="modal" data-bs-target="#feedbackModal" aria-label="Submit feedback about this consultation" style="width: 48px; height: 48px; border-radius: 50%; border: none; background: #10b981; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); transition: transform 0.2s ease, background 0.2s ease; position: fixed; bottom: 24px; right: 24px; z-index: 1000;">
                    <i class="bi bi-chat-left-text"></i>
                </button>

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







                <?php if (!$is_survey_only_detail): ?>

                <!-- EXISTING FEEDBACK SUBMISSIONS -->



                <?php



                // Fix: Use simpler query and proper error handling



                try {



                    $feedback_query = "SELECT * FROM feedback 



                                     WHERE consultation_id = ? AND status = 'approved'



                                     ORDER BY created_at DESC";



                    $feedback_stmt = $conn->prepare($feedback_query);



                    



                    if ($feedback_stmt === false) {



                        throw new Exception("Failed to prepare feedback query");



                    }



                    



                    $feedback_stmt->bind_param("i", $consultation_detail['id']);



                    $feedback_stmt->execute();



                    $feedback_result = $feedback_stmt->get_result();



                } catch (Exception $e) {



                    // If query fails, set empty result to avoid breaking the page



                    $feedback_result = false;



                    error_log("Feedback query error: " . $e->getMessage());



                }



                ?>



                



                <?php if ($feedback_result && $feedback_result->num_rows > 0): ?>



                    <div style="margin-bottom: 2rem;">



                        <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem;">



                            <i class="bi bi-chat-dots" style="margin-right: 0.5rem; color: #10b981;"></i>



                            Community Feedback (<?php echo $feedback_result->num_rows; ?> submissions)



                        </h3>



                        



                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">



                            <?php while ($feedback = $feedback_result->fetch_assoc()): ?>



                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">



                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">



                                        <div>



                                            <h4 style="font-weight: 700; color: #1f2937; margin: 0 0 0.25rem 0;">



                                                <?php echo htmlspecialchars($feedback['name'] ?? 'Anonymous Citizen'); ?>



                                            </h4>



                                            <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">



                                                <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>



                                            </p>



                                        </div>



                                        <?php if (!empty($feedback['rating'])): ?>



                                            <div style="display: flex; gap: 0.25rem;">



                                                <?php for ($i = 1; $i <= 5; $i++): ?>



                                                    <i class="bi bi-star-fill" style="color: <?php echo $i <= $feedback['rating'] ? '#f59e0b' : '#d1d5db'; ?>; font-size: 1rem;"></i>



                                                <?php endfor; ?>



                                            </div>



                                        <?php endif; ?>



                                    </div>



                                    



                                    <?php if (!empty($feedback['message'])): ?>



                                        <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">



                                            <p style="color: #374151; line-height: 1.6; margin: 0;">



                                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>



                                            </p>



                                        </div>



                                    <?php endif; ?>



                                    



                                    <?php if (!empty($feedback['feedback_type'])): ?>



                                        <span style="display: inline-block; background: #eff6ff; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">



                                            <?php echo htmlspecialchars(ucfirst($feedback['feedback_type'])); ?>



                                        </span>



                                    <?php endif; ?>



                                </div>



                            <?php endwhile; ?>



                        </div>



                    </div>



                <?php endif; ?>







                <!-- SHOW ACTUAL FEEDBACK SUBMISSIONS FOR THIS CONSULTATION -->



                <?php



                // Get all feedback for this consultation



                try {



                    $all_feedback_query = "SELECT * FROM feedback 



                                         WHERE consultation_id = ? 



                                         ORDER BY created_at DESC";



                    $all_feedback_stmt = $conn->prepare($all_feedback_query);



                    



                    if ($all_feedback_stmt === false) {



                        throw new Exception("Failed to prepare feedback query");



                    }



                    



                    $all_feedback_stmt->bind_param("i", $consultation_detail['id']);



                    $all_feedback_stmt->execute();



                    $all_feedback_result = $all_feedback_stmt->get_result();



                } catch (Exception $e) {



                    $all_feedback_result = false;



                    error_log("All feedback query error: " . $e->getMessage());



                }



                ?>



                



                <?php if ($all_feedback_result && $all_feedback_result->num_rows > 0): ?>



                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 2rem;">



                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">



                            <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin: 0;">



                                <i class="bi bi-chat-dots-fill" style="margin-right: 0.5rem; color: #10b981;"></i>



                                Feedback Submissions (<?php echo $all_feedback_result->num_rows; ?> total)



                            </h3>



                            <button type="button" onclick="toggleFeedbackVisibility()" id="feedback-toggle-btn" style="background: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: background 0.2s;">



                                <i class="bi bi-eye" id="feedback-toggle-icon"></i>



                                <span id="feedback-toggle-text">See Other Feedback</span>



                            </button>



                        </div>



                        

                        

                        <div id="feedback-list-container" style="display: none; flex-direction: column; gap: 1.5rem;">



                            <?php while ($feedback = $all_feedback_result->fetch_assoc()): ?>



                                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1.5rem;">



                                    <!-- Header with name, email, rating -->



                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">



                                        <div style="flex: 1; min-width: 200px;">



                                            <h4 style="font-weight: 700; color: #1f2937; margin: 0 0 0.25rem 0; font-size: 1.1rem;">



                                                <?php echo htmlspecialchars($feedback['name'] ?? 'Anonymous Citizen'); ?>



                                            </h4>



                                            <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">



                                                📧 <?php echo htmlspecialchars($feedback['email'] ?? 'No email provided'); ?>



                                            </p>



                                            <p style="color: #9ca3af; font-size: 0.8rem; margin: 0.25rem 0 0 0;">



                                                <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>



                                            </p>



                                            <?php if (!empty($feedback['phone'])): ?>



                                                <p style="color: #6b7280; font-size: 0.875rem; margin: 0.25rem 0 0 0;">



                                                    📱 <?php echo htmlspecialchars($feedback['phone']); ?>



                                                </p>



                                            <?php endif; ?>



                                        </div>



                                        



                                        <?php if (!empty($feedback['rating'])): ?>



                                            <div style="display: flex; align-items: center; gap: 0.5rem;">



                                                <span style="font-weight: 600; color: #374151; font-size: 0.9rem;">Rating:</span>



                                                <div style="display: flex; gap: 0.25rem;">



                                                    <?php for ($i = 1; $i <= 5; $i++): ?>



                                                        <i class="bi bi-star-fill" style="color: <?php echo $i <= $feedback['rating'] ? '#f59e0b' : '#d1d5db'; ?>; font-size: 1.1rem;"></i>



                                                    <?php endfor; ?>



                                                </div>



                                                <span style="font-weight: 700; color: #f59e0b; font-size: 1rem;"><?php echo $feedback['rating']; ?>/5</span>



                                            </div>



                                        <?php endif; ?>



                                    </div>



                                    



                                    <!-- Feedback Message -->



                                    <?php if (!empty($feedback['message'])): ?>



                                        <div style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #10b981;">



                                            <h5 style="font-weight: 600; color: #374151; margin: 0 0 0.5rem 0; font-size: 0.9rem;">💬 Feedback Message:</h5>



                                            <p style="color: #374151; line-height: 1.6; margin: 0; white-space: pre-wrap;">



                                                <?php echo htmlspecialchars($feedback['message']); ?>



                                            </p>



                                        </div>



                                    <?php endif; ?>



                                    



                                    <!-- Status and Type -->



                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">



                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">



                                            <span style="display: inline-block; background: <?php echo $feedback['status'] === 'approved' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $feedback['status'] === 'approved' ? '#065f46' : '#92400e'; ?>; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">



                                                <?php echo htmlspecialchars(ucfirst($feedback['status'])); ?>



                                            </span>



                                            <?php if (!empty($feedback['feedback_type'])): ?>



                                                <span style="display: inline-block; background: #eff6ff; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">



                                                    <?php echo htmlspecialchars(ucfirst($feedback['feedback_type'])); ?>



                                                </span>



                                            <?php endif; ?>



                                        </div>



                                    </div>



                                </div>



                            <?php endwhile; ?>



                        </div>



                    </div>



                <?php else: ?>



                    <!-- No feedback yet -->
                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2rem; text-align: center; margin-bottom: 2rem;">
                        <img src="images/traffic.jpg" alt="Consultation Illustration" style="max-width: 320px; width: 100%; height: auto; margin: 0 auto 1rem auto; display: block; border-radius: 16px; box-shadow: 0 10px 30px rgba(15,23,42,0.08);">
                        <p style="color: #6b7280; font-size: 1rem; margin: 0;">This consultation is open for community input. Share your feedback to help the City of Valenzuela shape better policies.</p>
                    </div>



                <?php endif; ?>







                <!-- Feedback modal -->
                <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="feedbackModalLabel">Submit Feedback</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (!isset($_SESSION['user_id'])): ?>
                                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; padding: 1.25rem; border-radius: 10px;">
                                        <p style="margin:0 0 0.75rem 0; font-weight: 700;">Please login to submit feedback.</p>
                                        <p style="margin:0 0 0.75rem 0;">You need an account to send feedback on this consultation.</p>
                                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                                            <a href="login.php" class="btn btn-primary" style="padding:0.75rem 1.25rem;">Login</a>
                                            <a href="AUTH/register.php" class="btn btn-secondary" style="padding:0.75rem 1.25rem;">Register</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <form id="feedbackForm" method="POST" action="public-portal.php?section=detail&id=<?php echo $consultation_detail['id']; ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
                                        <input type="hidden" name="submit_feedback" value="1">
                                        <input type="hidden" name="consultation_id" value="<?php echo $consultation_detail['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                                            <div>
                                                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Full Name *</label>
                                                <input type="text" name="name" required style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" value="<?php echo htmlspecialchars($feedback_form_values['name'] ?? ''); ?>">
                                                <?php if (!empty($feedback_form_errors['name'])): ?>
                                                    <small style="color: #ef4444;"><?php echo htmlspecialchars($feedback_form_errors['name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Email Address *</label>
                                                <input type="email" name="email" required style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" value="<?php echo htmlspecialchars($feedback_form_values['email'] ?? ''); ?>">
                                                <?php if (!empty($feedback_form_errors['email'])): ?>
                                                    <small style="color: #ef4444;"><?php echo htmlspecialchars($feedback_form_errors['email']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;">
                                            <div>
                                                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Phone Number</label>
                                                <input type="tel" name="phone" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" value="<?php echo htmlspecialchars($feedback_form_values['phone'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Feedback Type</label>
                                                <select name="feedback_type" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                                                    <option value="">Select type...</option>
                                                    <option value="suggestion" <?php echo ($feedback_form_values['feedback_type'] ?? '') === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                                                    <option value="concern" <?php echo ($feedback_form_values['feedback_type'] ?? '') === 'concern' ? 'selected' : ''; ?>>Concern</option>
                                                    <option value="question" <?php echo ($feedback_form_values['feedback_type'] ?? '') === 'question' ? 'selected' : ''; ?>>Question</option>
                                                    <option value="support" <?php echo ($feedback_form_values['feedback_type'] ?? '') === 'support' ? 'selected' : ''; ?>>Support</option>
                                                    <option value="opposition" <?php echo ($feedback_form_values['feedback_type'] ?? '') === 'opposition' ? 'selected' : ''; ?>>Opposition</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Rating (optional)</label>
                                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <label style="cursor: pointer; font-size: 1.5rem;">
                                                        <input type="radio" name="rating" value="<?php echo $i; ?>" style="display: none;" <?php echo ($feedback_form_values['rating'] ?? '') == $i ? 'checked' : ''; ?>>
                                                        <i class="bi bi-star<?php echo (($feedback_form_values['rating'] ?? 0) >= $i) ? '-fill' : ''; ?>" style="color: <?php echo (($feedback_form_values['rating'] ?? 0) >= $i) ? '#f59e0b' : '#d1d5db'; ?>; transition: color 0.2s;"></i>
                                                    </label>
                                                <?php endfor; ?>
                                                <span style="margin-left: 0.5rem; color: #6b7280;">Click to rate</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Your Feedback *</label>
                                            <textarea name="message" required rows="5" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; resize: vertical;" placeholder="Share your thoughts, suggestions, or concerns about this consultation..."><?php echo htmlspecialchars($feedback_form_values['message'] ?? ''); ?></textarea>
                                            <?php if (!empty($feedback_form_errors['message'])): ?>
                                                <small style="color: #ef4444;"><?php echo htmlspecialchars($feedback_form_errors['message']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="checkbox" name="allow_email_notifications" value="1" <?php echo !empty($feedback_form_values['allow_email_notifications']) ? 'checked' : ''; ?> style="width: 1rem; height: 1rem;">
                                            <label style="color: #6b7280; font-size: 0.9rem; cursor: pointer;">I'd like to receive email updates about this consultation</label>
                                        </div>
                                        <?php
                                            $can_submit_feedback = true;
                                            if (!$can_submit_feedback):
                                                $remaining_minutes = ceil(($feedback_cooldown - (time() - $last_feedback_time)) / 60);
                                        ?>
                                            <div style="background: linear-gradient(135deg, #fef3c7, #fed7aa); color: #92400e; padding: 1.25rem; border-radius: 12px; border: 2px solid #fbbf24; margin-bottom: 1rem; box-shadow: 0 4px 6px -1px rgba(251, 191, 36, 0.1);">
                                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                                    <i class="bi bi-clock-fill" style="font-size: 1.25rem; margin-right: 0.75rem; color: #d97706;"></i>
                                                    <strong style="font-size: 1.1rem; color: #92400e;">Please wait:</strong>
                                                </div>
                                                <div style="font-size: 0.95rem; color: #78350f; line-height: 1.5;">
                                                    You can only submit one feedback every 30 minutes. Please try again in <strong style="color: #92400e; font-size: 1.05rem;"> <?php echo $remaining_minutes; ?> minute(s)</strong>.
                                                </div>
                                            </div>
                                            <button type="button" disabled class="btn" style="padding: 0.75rem 2rem; font-weight: 600; background: #9ca3af; cursor: not-allowed; opacity: 0.6;">
                                                <i class="bi bi-send" style="margin-right: 0.5rem;"></i>
                                                <span>Please Wait...</span>
                                            </button>
                                        <?php else: ?>
                                            <div style="display: flex; gap: 1rem;">
                                                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-weight: 600;">
                                                    <i class="bi bi-send" style="margin-right: 0.5rem;"></i>
                                                    <span>Submit Feedback</span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                        <?php endif; ?>

                    </form>



                </div>

                <?php else: ?>

                    <?php
                    // Only show survey section if this is actually a survey-type consultation
                    $has_survey_data = !empty($detail_survey_question) || !empty($detail_survey_option_a) || !empty($detail_survey_option_b);
                    if ($has_survey_data):
                    ?>

                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                        <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1rem;">

                            <i class="bi bi-ui-radios-grid" style="margin-right: 0.5rem; color: #2563eb;"></i>

                            Survey Response

                        </h3>

                        <p style="color: #4b5563; margin-bottom: 1rem;"><?php echo htmlspecialchars($detail_survey_question); ?></p>

                        <div style="font-size: 0.9rem; color: #6b7280; margin-bottom: 1rem;">

                            Responses: <strong><?php echo htmlspecialchars($detail_survey_option_a); ?></strong> <?php echo (int)($detail_vote_stats['agree_votes'] ?? 0); ?> |

                            <strong><?php echo htmlspecialchars($detail_survey_option_b); ?></strong> <?php echo (int)($detail_vote_stats['disagree_votes'] ?? 0); ?> |

                            Total <?php echo (int)($detail_vote_stats['total_votes'] ?? 0); ?>

                        </div>

                        <form method="POST" action="public-portal.php?section=detail&id=<?php echo (int)$consultation_detail['id']; ?>" style="display:flex; flex-direction:column; gap:1rem;">

                            <input type="hidden" name="consultation_id" value="<?php echo (int)$consultation_detail['id']; ?>">

                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <input type="hidden" name="device_token" id="survey-device-token" value="">



                            <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600; color:#1f2937;">

                                <input type="radio" name="vote_option" value="agree" required onchange="document.getElementById('survey-reason-wrap').style.display='none'; document.getElementById('survey-reason').value='';">

                                <?php echo htmlspecialchars($detail_survey_option_a); ?>

                            </label>

                            <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600; color:#1f2937;">

                                <input type="radio" name="vote_option" value="disagree" required onchange="document.getElementById('survey-reason-wrap').style.display='block';">

                                <?php echo htmlspecialchars($detail_survey_option_b); ?>

                            </label>



                            <div id="survey-reason-wrap" style="display:none;">

                                <label for="survey-reason" style="display:block; font-weight:600; color:#374151; margin-bottom:0.4rem;">Kindly state your reason</label>

                                <textarea id="survey-reason" name="survey_reason" rows="3" style="width:100%; max-width:420px; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px; font-size:1rem;" placeholder="Share any remarks you would like to include."><?php echo htmlspecialchars(trim((string)($_POST['survey_reason'] ?? ''))); ?></textarea>

                            </div>



                            <div>

                                <label style="display:block; font-weight:600; color:#374151; margin-bottom:0.4rem;">Email (optional)</label>

                                <input type="email" name="survey_email" style="width:100%; max-width:420px; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px; font-size:1rem;" placeholder="you@example.com">

                            </div>



                            <div style="display:flex; gap:1rem;">

                                <button type="submit" name="submit_survey_vote" class="btn btn-primary" style="padding:0.75rem 2rem; font-weight:600;">

                                    <i class="bi bi-check2-circle" style="margin-right:0.5rem;"></i>

                                    Submit Survey

                                </button>

                            </div>

                        </form>

                    </div>

                <?php endif; ?>
                <?php endif; ?>



            </div>



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



                    <div style="position: relative;">



                        

                        



                        <!-- Cards Container with Slide Animation -->



                        <div style="overflow: hidden; border-radius: 12px;">



                            <div id="consultations-slider" style="display: flex; transition: transform 0.5s ease-in-out;">



                                <div style="display: flex; flex-direction: column; gap: 2rem; width: 100%; min-width: 100%;">



                        <?php 



                        while ($consultation = $active_consultations->fetch_assoc()): 



                            $mode_raw = strtolower(trim((string)($consultation['response_mode'] ?? 'feedback')));

                            $is_survey_card = ($mode_raw === 'survey');

                            $image_path = resolveConsultationCardImagePath($consultation);



                            $days_left = ceil((strtotime($consultation['end_date']) - time()) / (60 * 60 * 24));



                        ?>



                            <div class="card consultation-card-grid" style="padding: 0; margin-bottom: 0; overflow: hidden; border: 1px solid #e5e7eb;">



                                <!-- IMAGE SECTION -->



                                <div class="consultation-image-col" style="background: linear-gradient(135deg, #991b1b, #7f1d1d);">



                                    <?php if ($image_path): ?>



                                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($consultation['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">



                                    <?php else: ?>



                                        <img src="images/consultation.png" alt="Consultation default image" style="width: 100%; height: 100%; object-fit: cover; display: block;">



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



                                <div class="consultation-content-col">



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



                                            <button type="button" onclick="toggleConsultationDetail(<?php echo $consultation['id']; ?>)" class="btn btn-primary" style="margin-left: auto; display:flex; align-items:center; gap:0.5rem;">



                                                <i class="bi bi-chevron-down"></i>



                                                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" style="margin-left:0.5rem; display:inline-block; vertical-align:middle;" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">



                                                    <path d="M5 12h14" />



                                                    <path d="M13 5l7 7-7 7" />



                                                </svg>



                                            </button>



                                        </div>



                                    </div>



                                </div>



                            </div>



                        <?php endwhile; ?>



                                </div>



                            </div>



                        </div>



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

                        while ($consultation = $past_consultations->fetch_assoc()): 

                            $past_image_path = resolveConsultationCardImagePath($consultation);

                        ?>



                            <div class="card consultation-card-grid" style="padding: 0; margin-bottom: 0; overflow: hidden; border: 1px solid #e5e7eb; opacity: 0.85;">



                                <!-- IMAGE SECTION -->



                                <div class="consultation-image-col" style="background: linear-gradient(135deg, #6b7280, #4b5563);">



                                    <?php if ($past_image_path): ?>

                                        <img src="<?php echo $past_image_path; ?>" alt="<?php echo htmlspecialchars($consultation['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block; filter: grayscale(30%); opacity: 0.8;">

                                    <?php else: ?>

                                        <img src="images/consultation.png" alt="Consultation default image" style="width: 100%; height: 100%; object-fit: cover; display: block; filter: grayscale(30%); opacity: 0.8;">

                                    <?php endif; ?>



                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.5)); pointer-events: none;"></div>



                                    <div style="position: absolute; top: 1.5rem; left: 1.5rem;">



                                        <span class="badge" style="background: #10b981; color: white; padding: 0.6rem 1.2rem; font-size: 0.85rem;">



                                            <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>



                                            COMPLETED



                                        </span>



                                    </div>



                                </div>







                                <!-- CONTENT SECTION -->



                                <div class="consultation-content-col">



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



                                    



                                    <div style="display: flex; gap: 2rem; align-items: center; padding-top: 1.5rem; border-top: 2px solid #f0f0f0;">
                                        <div>
                                            <p style="font-size: 0.8rem; color: #6b7280; margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Concluded</p>
                                            <p style="font-weight: 800; color: #1f2937; margin: 0.5rem 0 0 0; font-size: 1rem;">
                                                <?php echo date('M d, Y', strtotime($consultation['end_date'])); ?>
                                            </p>
                                        </div>

                                        <button type="button" onclick="viewConsultationDetail(<?php echo $consultation['id']; ?>)" class="btn btn-secondary" style="margin-left: auto; display:flex; align-items:center; gap:0.5rem;">
                                            View Details
                                            <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" style="margin-left:0.5rem; display:inline-block; vertical-align:middle;" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M5 12h14" />
                                                <path d="M13 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>



                        <?php endwhile; ?>



                    </div>



                <?php else: ?>



                    <div class="card">



                        <p style="text-align: center; color: #6b7280; margin: 0;">No completed consultations yet.</p>



                    </div>



                <?php endif; ?>



            </div>



    </section>



    <!-- SURVEY RESULTS SECTION -->

    <section id="section-survey-results" class="section-hidden">

            <div id="survey-results-transparency" style="margin-bottom: 3rem;">



                <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 0.75rem;">



                    <i class="bi bi-bar-chart-line" style="margin-right: 0.5rem; color: #2563eb;"></i>

                    Survey Results Transparency



                </h3>

                <p style="color: #6b7280; margin: 0 0 1.5rem 0; font-size: 0.95rem;">

                    Live results for survey-type consultations (Agree/Disagree).

                </p>



                <?php if (!empty($survey_results_rows)): ?>

                    <div style="display:flex; flex-direction:column; gap:1rem;">

                        <?php foreach ($survey_results_rows as $sr):

                            $sr_status = strtolower(trim((string)($sr['status'] ?? '')));

                            $is_sr_active = ($sr_status === 'active');

                            $sr_votes = $sr['vote_stats'] ?? ['agree_votes' => 0, 'disagree_votes' => 0, 'total_votes' => 0, 'agree_percent' => 0, 'disagree_percent' => 0];

                            $a_votes = (int)($sr_votes['agree_votes'] ?? 0);

                            $b_votes = (int)($sr_votes['disagree_votes'] ?? 0);

                            $total_votes = (int)($sr_votes['total_votes'] ?? 0);

                            $a_percent = (float)($sr_votes['agree_percent'] ?? 0);

                            $b_percent = (float)($sr_votes['disagree_percent'] ?? 0);

                        ?>

                            <div class="card" style="padding:1.25rem; border:1px solid #e5e7eb;">

                                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-bottom:0.85rem;">

                                    <div>

                                        <h4 style="font-size:1.1rem; font-weight:800; color:#1f2937; margin:0 0 0.4rem 0;">

                                            <?php echo htmlspecialchars($sr['title']); ?>

                                        </h4>

                                        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">

                                            <span style="background:#eff6ff; color:#1d4ed8; padding:0.2rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:700;">

                                                <?php echo htmlspecialchars($sr['category'] ?? 'General'); ?>

                                            </span>

                                            <span style="background:<?php echo $is_sr_active ? '#dcfce7' : '#e5e7eb'; ?>; color:<?php echo $is_sr_active ? '#166534' : '#374151'; ?>; padding:0.2rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:700; text-transform:uppercase;">

                                                <?php echo $is_sr_active ? 'Active' : 'Completed'; ?>

                                            </span>

                                        </div>

                                    </div>

                                    <div style="text-align:right;">

                                        <div style="font-size:0.8rem; color:#6b7280;">Total responses</div>

                                        <div style="font-size:1.25rem; font-weight:800; color:#1f2937;"><?php echo $total_votes; ?></div>

                                    </div>

                                </div>



                                <p style="color:#374151; margin:0 0 1rem 0; font-weight:600;">

                                    <?php echo htmlspecialchars($sr['survey_question']); ?>

                                </p>



                                <div style="display:flex; flex-direction:column; gap:0.85rem;">

                                    <div>

                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">

                                            <span style="font-size:0.9rem; font-weight:700; color:#065f46;"><?php echo htmlspecialchars($sr['survey_option_a']); ?></span>

                                            <span style="font-size:0.9rem; color:#065f46; font-weight:700;"><?php echo number_format($a_percent, 1); ?>% (<?php echo $a_votes; ?>)</span>

                                        </div>

                                        <div style="height:12px; background:#e5e7eb; border-radius:999px; overflow:hidden;">

                                            <div style="height:100%; width:<?php echo max(0, min(100, $a_percent)); ?>%; background:linear-gradient(90deg, #10b981, #059669);"></div>

                                        </div>

                                    </div>



                                    <div>

                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">

                                            <span style="font-size:0.9rem; font-weight:700; color:#7f1d1d;"><?php echo htmlspecialchars($sr['survey_option_b']); ?></span>

                                            <span style="font-size:0.9rem; color:#7f1d1d; font-weight:700;"><?php echo number_format($b_percent, 1); ?>% (<?php echo $b_votes; ?>)</span>

                                        </div>

                                        <div style="height:12px; background:#e5e7eb; border-radius:999px; overflow:hidden;">

                                            <div style="height:100%; width:<?php echo max(0, min(100, $b_percent)); ?>%; background:linear-gradient(90deg, #ef4444, #dc2626);"></div>

                                        </div>

                                    </div>

                                </div>



                                <div style="margin-top:1rem; display:flex; justify-content:flex-end;">

                                    <button type="button" onclick="viewConsultationDetail(<?php echo (int)$sr['id']; ?>)" class="btn btn-secondary" style="padding:0.55rem 1rem;">

                                        View Details

                                    </button>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="card">

                        <p style="text-align: center; color: #6b7280; margin: 0;">No survey results available yet.</p>

                    </div>

                <?php endif; ?>

            </div>

    </section>







    <!-- SUBMIT CONSULTATION SECTION -->



    <section id="section-submit-consultation" class="section-hidden">



        <div style="margin-bottom: 2rem; text-align: center;">



            <h2 style="font-size: 2.5rem; font-weight: 800; color: #1f2937; margin: 0 0 0.75rem 0;" data-i18n="submit_title">Submit a Consultation Request</h2>



            <p style="color: #6b7280; font-size: 1.1rem; margin: 0;" data-i18n="submit_subtitle">Have a topic you'd like the city to consult the public on? Submit your request here.</p>



        </div>







        <!-- SUBMIT CONSULTATION SECTION -->



        <div>



            <h3 style="font-size: 1.5rem; font-weight: 800; color: #1f2937; margin-bottom: 1.5rem;">



                <i class="bi bi-pencil-square" style="margin-right: 0.5rem; color: #991b1b;"></i>



                <span data-i18n="submit_form_title">Submit Your Consultation Request</span>



            </h3>



            



            <div style="background: linear-gradient(135deg, rgba(153, 27, 27, 0.08), rgba(127, 29, 29, 0.08)); padding: 2.5rem; border-radius: 12px; border-left: 4px solid #991b1b;">



                <form method="POST" action="public-portal.php?section=submit-consultation" enctype="multipart/form-data">

    <?php

        if (!isset($_SESSION['consultation_submission_token']) || (isset($_SESSION['consultation_submission_token_used']) && $_SESSION['consultation_submission_token_used'] === $_SESSION['consultation_submission_token'])) {

            $_SESSION['consultation_submission_token'] = bin2hex(random_bytes(16));

            unset($_SESSION['consultation_submission_token_used']);

        }

    ?>

    <input type="hidden" name="consultation_submission_token" value="<?php echo htmlspecialchars($_SESSION['consultation_submission_token'] ?? ''); ?>">



                    <!-- CSRF Token -->



                    <?php outputCSRFField(); ?>

                    <input type="hidden" name="consultation_website" value="">







                    <div class="form-table" role="presentation">



                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_name">Name</div>



                            <div class="form-field-cell" style="display:flex; gap:0.75rem; align-items:center;">



                                <input type="text" name="name" placeholder="Your full name" required value="<?php echo htmlspecialchars($consultation_form_values['name']); ?>" style="flex:1; <?php echo isset($consultation_field_errors['name']) ? 'border:2px solid #ef4444; outline:none;' : ''; ?>">



                                <input type="number" name="age" placeholder="Age" min="0" max="120" value="<?php echo htmlspecialchars($consultation_form_values['age']); ?>" style="width:100px; padding:0.55rem 0.75rem; border:1px solid #d1d5db; border-radius:6px;">



                                <select name="gender" style="width:140px; padding:0.55rem 0.75rem; border:1px solid #d1d5db; border-radius:6px;">



                                    <option value="">Gender</option>



                                    <option value="Female" <?php echo ($consultation_form_values['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>



                                    <option value="Male" <?php echo ($consultation_form_values['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>



                                    <option value="Other" <?php echo ($consultation_form_values['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>



                                    <option value="Prefer not to say" <?php echo ($consultation_form_values['gender'] === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>



                                </select>



                            </div>



                        </div>







                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_phone">Phone</div>



                            <div class="form-field-cell">



                                <input type="tel" name="phone" placeholder="Your phone number (optional)" value="<?php echo htmlspecialchars($consultation_form_values['phone'] ?? ''); ?>">



                            </div>



                        </div>







                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_email">Email *</div>



                            <div class="form-field-cell">



                                <input type="email" name="consultation_email" placeholder="Your email address" required value="<?php echo htmlspecialchars($consultation_form_values['consultation_email'] ?? ''); ?>">



                            </div>



                        </div>







                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_topic">Topic / Title *</div>



                            <div class="form-field-cell">



                                <select name="consultation_topic" required style="width:100%; padding:0.55rem 0.75rem; border:1px solid #d1d5db; border-radius:6px; <?php echo isset($consultation_field_errors['consultation_topic']) ? 'border:2px solid #ef4444; outline:none;' : ''; ?>">

                                    <option value="">Select topic / category</option>

                                    <option value="Appropriations">Appropriations</option>

                                    <option value="Ways & Means">Ways & Means</option>

                                    <option value="Women, Family & Gender Equality">Women, Family & Gender Equality</option>

                                    <option value="Justice & Human Rights">Justice & Human Rights</option>

                                    <option value="Higher & Technical Education">Higher & Technical Education</option>

                                    <option value="Cooperatives">Cooperatives</option>

                                    <option value="Health & Sanitation">Health & Sanitation</option>

                                    <option value="Social Services">Social Services</option>

                                    <option value="Livelihood, Trade, Commerce & Industry">Livelihood, Trade, Commerce & Industry</option>

                                    <option value="Food & Agriculture">Food & Agriculture</option>

                                    <option value="Urban Planning, Housing & Development">Urban Planning, Housing & Development</option>

                                    <option value="Public Utilities & Facilities">Public Utilities & Facilities</option>

                                    <option value="Market & Slaughterhouse">Market & Slaughterhouse</option>

                                    <option value="Rules & Privileges">Rules & Privileges</option>

                                </select>



                                <p style="font-size: 0.8rem; color: #6b7280; margin-top: 0.5rem; display:flex; gap:0.35rem; align-items:flex-start;">

                                    <i class="bi bi-exclamation-circle" style="color:#2563eb; font-size:1rem; line-height:1.2;"></i>

                                    <span>Please describe a consultation topic, not a service request. For example, sidewalk repair is a service issue and may not receive an official consultation acknowledgement.</span>

                                </p>



                            </div>



                        </div>



                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_description">Description / Details *</div>



                            <div class="form-field-cell">



                                <textarea name="consultation_description" placeholder="Please provide details about your consultation request..." required style="<?php echo isset($consultation_field_errors['consultation_description']) ? 'border:2px solid #ef4444; outline:none;' : ''; ?> height:140px; width:100%;"><?php echo htmlspecialchars($consultation_form_values['consultation_description']); ?></textarea>



                            </div>



                        </div>

                        <input type="hidden" id="consultation-location-lat" name="consultation_lat" value="<?php echo htmlspecialchars($consultation_form_values['consultation_lat'] ?? ''); ?>">

                        <input type="hidden" id="consultation-location-lng" name="consultation_lng" value="<?php echo htmlspecialchars($consultation_form_values['consultation_lng'] ?? ''); ?>">



                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_address">Address</div>



                            <div class="form-field-cell">



                                <textarea name="address" placeholder="House number, street, barangay, city"><?php echo htmlspecialchars($consultation_form_values['address']); ?></textarea>



                            </div>



                        </div>







                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_barangay">Barangay</div>



                            <div class="form-field-cell">



                                <select name="barangay">



                                    <option value="">Select barangay</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Bignay') ? 'selected' : ''; ?>>Bignay</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Bagbaguin') ? 'selected' : ''; ?>>Bagbaguin</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Balangkas') ? 'selected' : ''; ?>>Balangkas</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Barangay 162 (Brgy.162)') ? 'selected' : ''; ?>>Barangay 162 (Brgy.162)</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Canumay') ? 'selected' : ''; ?>>Canumay</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Caruhatan') ? 'selected' : ''; ?>>Caruhatan</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Dalandanan') ? 'selected' : ''; ?>>Dalandanan</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Gen. T. de Leon') ? 'selected' : ''; ?>>Gen. T. de Leon</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Karuhatan') ? 'selected' : ''; ?>>Karuhatan</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Malinta') ? 'selected' : ''; ?>>Malinta</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Maysan') ? 'selected' : ''; ?>>Maysan</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Marulas') ? 'selected' : ''; ?>>Marulas</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Mapulang Lupa') ? 'selected' : ''; ?>>Mapulang Lupa</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Palasan') ? 'selected' : ''; ?>>Palasan</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Parada') ? 'selected' : ''; ?>>Parada</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Poblacion') ? 'selected' : ''; ?>>Poblacion</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Valenzuela') ? 'selected' : ''; ?>>Valenzuela</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Veinte Reales') ? 'selected' : ''; ?>>Veinte Reales</option>



                                    <option <?php echo ($consultation_form_values['barangay'] === 'Wawang Pulo') ? 'selected' : ''; ?>>Wawang Pulo</option>



                                </select>



                            </div>



                        </div>

















                        <div class="form-row">







                            <div class="form-label-cell" data-i18n="form_notifications">Notifications</div>







                            <div class="form-field-cell">







                                <label style="display:flex; gap:0.75rem; align-items:flex-start;">



                                    <input type="checkbox" name="consultation_allow_email" value="1" <?php echo !empty($consultation_form_values['consultation_allow_email']) ? 'checked' : ''; ?> style="width:18px; height:18px;">



                                    <div>



                                        <div style="font-weight:700; color:#111;" data-i18n="form_email_updates">Send me email updates about this consultation</div>



                                        <div style="font-size:0.85rem; color:#6b7280; margin-top:0.25rem;">Our team will notify you when your consultation is reviewed and scheduled. You can opt out anytime by replying to the email.</div>



                                    </div>



                                </label>



                            </div>



                        </div>






                        <div class="form-row">



                            <div class="form-label-cell" data-i18n="form_document">Supporting Document</div>



                            <div class="form-field-cell">



                                <div style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 1.5rem; text-align: center; background: #f9fafb;">



                                    <input type="file" id="consultation-document" name="consultation_document" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png" style="display: none;">



                                    <label for="consultation-document" style="cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">



                                        <i class="bi bi-file-earmark-pdf" style="font-size: 2rem; color: #6b7280;"></i>



                                        <span style="color: #374151; font-weight: 600;">Click to upload document</span>



                                        <span style="color: #6b7280; font-size: 0.85rem;">PDF, Word, Text, or Images (Max 10MB)</span>



                                    </label>



                                </div>



                                <?php



                                // Debug session values

                                error_log('Session values - last_consultation_time: ' . ($last_consultation_time ?? 'null') . ', current_time: ' . time() . ', cooldown: ' . $consultation_cooldown);

                                

                                $can_submit_consultation = true;

                                

                                error_log('Can submit consultation: ' . ($can_submit_consultation ? 'YES' : 'NO'));



                                if (!$can_submit_consultation):



                                    $remaining_minutes = ceil(($consultation_cooldown - (time() - $last_consultation_time)) / 60);



                                ?>



                                    <div style="background: #fef3c7; color: #92400e; padding: 1rem; border-radius: 8px; border: 1px solid #fbbf24; margin-bottom: 1rem;">



                                        <i class="bi bi-clock" style="margin-right: 0.5rem;"></i>



                                        <strong>Rate Limit:</strong> Please wait <strong><?php echo $remaining_minutes; ?></strong> minute(s) before submitting another consultation request.



                                    </div>



                                <?php endif; ?>



                            </div>



                        </div>



                        <div class="form-row">

                            <div class="form-label-cell"></div>

                            <div class="form-field-cell" style="display:flex; justify-content:flex-end;">

                                <?php if (!$can_submit_consultation): ?>

                                    <button type="button" disabled style="background: #9ca3af; color: white; font-weight:700; padding:0.6rem 1rem; border-radius:6px; border:none; cursor:not-allowed; font-size:0.95rem; opacity:0.8;">

                                        <i class="bi bi-send" style="margin-right:0.4rem; vertical-align:middle;"></i> <span>Please Wait...</span>

                                    </button>

                                <?php else: ?>

                                    <button type="submit" name="submit_consultation" id="btn-submit-consultation" style="background: linear-gradient(135deg, #991b1b, #7f1d1d); color: white; font-weight:700; padding:0.6rem 1rem; border-radius:6px; border:none; cursor:pointer; font-size:0.95rem;">

                                        <i class="bi bi-send" style="margin-right:0.4rem; vertical-align:middle;"></i> <span data-i18n="btn_submit_consultation">Submit Consultation Request</span>

                                    </button>

                                <?php endif; ?>

                            </div>

                        </div>



                    </div>



                </form>



            </div>



        </div>



    </section>











    



    <!-- CONTACT section removed (duplicate of Submit Consultation) -->



</main>







<!-- FOOTER -->



<footer style="background: linear-gradient(135deg, #1f2937, #111827); color: white; margin-top: 4rem; padding: 3rem 0;">



    <div style="max-width: 80rem; margin: 0 auto; padding: 0 1rem;">



        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; align-items: start;">



            <div>



                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;" data-i18n="footer_about">About</h4>



                <p style="color: #9ca3af; font-size: 0.9rem; margin: 0;" data-i18n="footer_about_desc">Public Consultation Portal of Valenzuela City Government</p>



            </div>



            <div>



                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;" data-i18n="footer_links">Quick Links</h4>



                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem;">



                    <li><a href="index.php" style="color: #9ca3af; text-decoration: none; font-size: 0.9rem;">Home</a></li>



                    <li><a href="#" id="openPrivacy" style="color: #9ca3af; text-decoration: none; font-size: 0.9rem; cursor:pointer;">Privacy Policy</a></li>



                    <li><a href="#" id="openTerms" style="color: #9ca3af; text-decoration: none; font-size: 0.9rem; cursor:pointer;">Terms of Use</a></li>



                </ul>



            </div>



            <div>



                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;" data-i18n="footer_contact">Contact</h4>



                <p style="color: #9ca3af; font-size: 0.9rem; margin: 0;">Valenzuela City Government<br>City Hall, Valenzuela City</p>



            </div>



            <div>



                <h4 style="font-weight: 800; margin-bottom: 1rem; color: white;" data-i18n="footer_follow">Follow Us</h4>



                <div class="footer-follow">



                    <div class="social-icons">



                        <?php if (!empty($SOCIAL_FB)): ?>



                            <a class="social-icon" href="<?php echo htmlspecialchars($SOCIAL_FB); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="Valenzuela on Facebook">



                                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" style="display:block" fill="currentColor">



                                    <path d="M22 12a10 10 0 1 0-11.56 9.87v-6.99H7.9V12h2.54V9.8c0-2.5 1.5-3.88 3.78-3.88 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.62.77-1.62 1.56V12h2.76l-.44 2.88h-2.32v6.99A10 10 0 0 0 22 12z"/>



                                </svg>



                            </a>



                        <?php endif; ?>



                        <?php if (!empty($SOCIAL_IG)): ?>



                            <a class="social-icon" href="<?php echo htmlspecialchars($SOCIAL_IG); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="Valenzuela on Instagram">



                                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" style="display:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">



                                    <rect x="3" y="3" width="18" height="18" rx="5" ry="5" />



                                    <path d="M16 11.37a4 4 0 1 1-7.88 1.17 4 4 0 0 1 7.88-1.17z" />



                                    <path d="M17.5 6.5h.01" />



                                </svg>



                            </a>



                        <?php endif; ?>



                        <?php if (!empty($SOCIAL_YT)): ?>



                            <a class="social-icon" href="<?php echo htmlspecialchars($SOCIAL_YT); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="Valenzuela on YouTube">



                                <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" style="display:block" fill="currentColor">



                                    <path d="M23.5 6.2a3.1 3.1 0 0 0-2.2-2.2C19.4 3.5 12 3.5 12 3.5s-7.4 0-9.3.5A3.1 3.1 0 0 0 .5 6.2 32.6 32.6 0 0 0 0 12s0 3.9.5 5.8a3.1 3.1 0 0 0 2.2 2.2c1.9.5 9.3.5 9.3.5s7.4 0 9.3-.5a3.1 3.1 0 0 0 2.2-2.2c.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8z"/>



                                    <path d="M9.6 15.6V8.4L15.8 12l-6.2 3.6z" fill="#111827"/>



                                </svg>



                            </a>



                        <?php endif; ?>



                    </div>



                    <div class="social-caption">Official Valenzuela City Government accounts</div>



                </div>



                </div>



            </div>



            </div>



        </div>



        <div style="border-top: 1px solid #374151; padding-top: 2rem; text-align: center; color: #9ca3af; font-size: 0.9rem;">



            <p style="margin: 0;" data-i18n="footer_rights">&copy; 2026 Valenzuela City Government. All rights reserved.</p>



        </div>



    </div>



</footer>







    <!-- ==================== PRIVACY POLICY MODAL ==================== -->



    <div id="policyModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:60; align-items:center; justify-content:center;">



        <div style="background:white; width:94%; max-width:720px; border-radius:16px; overflow:hidden; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 25px 60px rgba(0,0,0,0.3);">



            <!-- Header -->



            <div style="background:linear-gradient(135deg,#991b1b,#7f1d1d); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">



                <div>



                    <h2 style="margin:0; font-size:1.4rem; font-weight:800;">Privacy Policy</h2>



                    <p style="margin:0.25rem 0 0; font-size:0.8rem; opacity:0.8;">Valenzuela City Government &mdash; Public Consultation Management Portal</p>



                </div>



                <button type="button" onclick="closePolicyModal()" style="background:rgba(255,255,255,0.15); border:none; color:white; font-size:1.5rem; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>



            </div>



            <!-- Scrollable Content -->



            <div id="policyContent" style="overflow-y:auto; padding:2rem; color:#374151; line-height:1.8; font-size:0.92rem; flex:1;">



                <p style="color:#6b7280; font-size:0.8rem; margin-top:0;">Last Updated: February 2026</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">1. Introduction</h3>



                <p>The Valenzuela City Government ("we," "us," or "our") is committed to protecting the privacy and personal information of all citizens, residents, and users ("you" or "your") who access and use the Public Consultation Management Portal (the "Portal"). This Privacy Policy explains how we collect, use, store, protect, and share your personal information in accordance with the <strong>Republic Act No. 10173</strong>, also known as the <strong>Data Privacy Act of 2012</strong>, and its Implementing Rules and Regulations.</p>



                <p>By accessing or using this Portal, you acknowledge that you have read, understood, and agree to be bound by this Privacy Policy. If you do not agree with any part of this policy, please do not use the Portal.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">2. Information We Collect</h3>



                <p>We may collect the following types of personal information when you use the Portal:</p>



                <p><strong>2.1 Information You Provide Directly:</strong></p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Full Name</strong> &mdash; Used to identify you as a participant in public consultations and feedback submissions.</li>



                    <li><strong>Email Address</strong> &mdash; Used to send confirmation receipts, edit links, status updates, and other Portal-related communications.</li>



                    <li><strong>Phone Number</strong> (optional) &mdash; Used for follow-up communications if you choose to provide it.</li>



                    <li><strong>Consultation Content</strong> &mdash; The subject, description, and any details you include in your consultation submissions.</li>



                    <li><strong>Feedback Content</strong> &mdash; The type, message, and any details you include in your feedback submissions.</li>



                    <li><strong>File Attachments</strong> &mdash; Any documents, images, or files you upload to support your submissions.</li>



                </ul>



                <p><strong>2.2 Information Collected Automatically:</strong></p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Browser Type and Version</strong> &mdash; For compatibility and troubleshooting purposes.</li>



                    <li><strong>IP Address</strong> &mdash; For security monitoring and abuse prevention.</li>



                    <li><strong>Access Timestamps</strong> &mdash; Date and time of your visits and submissions.</li>



                    <li><strong>Cookies and Local Storage</strong> &mdash; Used to remember your language preference (English/Tagalog) and theme preference (light/dark mode).</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">3. How We Use Your Information</h3>



                <p>Your personal information is used solely for the following purposes:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Processing Submissions</strong> &mdash; To receive, review, and respond to your consultation requests and feedback.</li>



                    <li><strong>Communication</strong> &mdash; To send you confirmation emails, edit links for your submissions, status updates, and responses to your inquiries.</li>



                    <li><strong>Service Improvement</strong> &mdash; To analyze aggregated, anonymized data to improve public services and the Portal experience.</li>



                    <li><strong>Legal Compliance</strong> &mdash; To comply with applicable laws, regulations, and legal processes.</li>



                    <li><strong>Security</strong> &mdash; To detect, prevent, and address fraud, abuse, security issues, and technical problems.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">4. Data Storage and Security</h3>



                <p>We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>Secure server infrastructure maintained by the Valenzuela City Government IT Department.</li>



                    <li>Encrypted data transmission using HTTPS/SSL protocols.</li>



                    <li>Password hashing for all administrative accounts.</li>



                    <li>CSRF (Cross-Site Request Forgery) protection on all forms.</li>



                    <li>Time-limited edit tokens for submission modifications (valid for 7 days).</li>



                    <li>Regular security audits and access logging.</li>



                    <li>Role-based access control for administrative personnel.</li>



                </ul>



                <p>Your data is stored on secure servers within the Philippines. We retain your personal information only for as long as necessary to fulfill the purposes outlined in this policy, or as required by law.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">5. Data Sharing and Disclosure</h3>



                <p>We do <strong>not</strong> sell, trade, or rent your personal information to third parties. Your information may be shared only in the following circumstances:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Within the LGU</strong> &mdash; With authorized Valenzuela City Government officials and staff who need access to process and respond to your submissions.</li>



                    <li><strong>Legal Requirements</strong> &mdash; When required by law, court order, or government regulation.</li>



                    <li><strong>Public Interest</strong> &mdash; When necessary to protect the rights, safety, or property of the Valenzuela City Government or the public.</li>



                    <li><strong>Anonymized Data</strong> &mdash; Aggregated, non-identifiable data may be used for statistical analysis and public reporting.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">6. Your Rights Under the Data Privacy Act</h3>



                <p>Under the Data Privacy Act of 2012, you have the following rights:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Right to Be Informed</strong> &mdash; You have the right to know how your personal data is being collected and processed.</li>



                    <li><strong>Right to Access</strong> &mdash; You may request access to your personal data held by us.</li>



                    <li><strong>Right to Rectification</strong> &mdash; You may request correction of inaccurate or incomplete personal data. You can also use the edit link provided after submission to modify your entries within 7 days.</li>



                    <li><strong>Right to Erasure</strong> &mdash; You may request deletion of your personal data, subject to legal retention requirements.</li>



                    <li><strong>Right to Object</strong> &mdash; You may object to the processing of your personal data under certain circumstances.</li>



                    <li><strong>Right to Data Portability</strong> &mdash; You may request a copy of your personal data in a structured, commonly used format.</li>



                    <li><strong>Right to File a Complaint</strong> &mdash; You may file a complaint with the National Privacy Commission if you believe your data privacy rights have been violated.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">7. Cookies and Local Storage</h3>



                <p>The Portal uses cookies and browser local storage for the following purposes only:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Theme Preference</strong> &mdash; Remembers your light/dark mode selection.</li>



                    <li><strong>Language Preference</strong> &mdash; Remembers your English/Tagalog language selection.</li>



                    <li><strong>Session Management</strong> &mdash; Maintains your browsing session for security purposes.</li>



                </ul>



                <p>We do <strong>not</strong> use tracking cookies, advertising cookies, or any third-party analytics cookies.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">8. Children's Privacy</h3>



                <p>The Portal is not intended for use by individuals under the age of 18. We do not knowingly collect personal information from minors. If you are under 18, please use this Portal only with the guidance and consent of a parent or legal guardian.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">9. Changes to This Privacy Policy</h3>



                <p>We reserve the right to update or modify this Privacy Policy at any time. Any changes will be posted on this page with an updated "Last Updated" date. We encourage you to review this policy periodically. Continued use of the Portal after any changes constitutes your acceptance of the updated policy.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">10. Contact Information</h3>



                <p>If you have any questions, concerns, or requests regarding this Privacy Policy or the handling of your personal data, please contact:</p>



                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:1rem; margin:0.75rem 0;">



                    <p style="margin:0;"><strong>Data Protection Officer</strong></p>



                    <p style="margin:0.25rem 0 0;">Valenzuela City Government</p>



                    <p style="margin:0.25rem 0 0;">City Hall, MacArthur Highway, Karuhatan, Valenzuela City</p>



                    <p style="margin:0.25rem 0 0;">Email: <a href="mailto:dpo@valenzuela.gov.ph" style="color:#991b1b;">dpo@valenzuela.gov.ph</a></p>



                    <p style="margin:0.25rem 0 0;">Phone: (02) 8443-8444</p>



                </div>



                <p>You may also file a complaint with the <strong>National Privacy Commission</strong> at <a href="https://www.privacy.gov.ph" target="_blank" style="color:#991b1b;">www.privacy.gov.ph</a>.</p>







                <div style="border-top:2px solid #e5e7eb; margin-top:2rem; padding-top:1rem;">



                    <p style="color:#6b7280; font-size:0.8rem; margin:0; text-align:center;">&copy; 2026 Valenzuela City Government. All rights reserved.<br>This Privacy Policy is governed by the laws of the Republic of the Philippines.</p>



                </div>



            </div>



        </div>



    </div>







    <!-- ==================== TERMS OF USE MODAL ==================== -->



    <div id="termsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:60; align-items:center; justify-content:center;">



        <div style="background:white; width:94%; max-width:720px; border-radius:16px; overflow:hidden; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 25px 60px rgba(0,0,0,0.3);">



            <!-- Header -->



            <div style="background:linear-gradient(135deg,#991b1b,#7f1d1d); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">



                <div>



                    <h2 style="margin:0; font-size:1.4rem; font-weight:800;">Terms of Use</h2>



                    <p style="margin:0.25rem 0 0; font-size:0.8rem; opacity:0.8;">Valenzuela City Government &mdash; Public Consultation Management Portal</p>



                </div>



                <button type="button" onclick="closeTermsModal()" style="background:rgba(255,255,255,0.15); border:none; color:white; font-size:1.5rem; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>



            </div>



            <!-- Scrollable Content -->



            <div id="termsContent" style="overflow-y:auto; padding:2rem; color:#374151; line-height:1.8; font-size:0.92rem; flex:1;">



                <p style="color:#6b7280; font-size:0.8rem; margin-top:0;">Last Updated: February 2026</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">1. Acceptance of Terms</h3>



                <p>By accessing, browsing, or using the Public Consultation Management Portal (the "Portal") operated by the Valenzuela City Government (the "City"), you acknowledge that you have read, understood, and agree to be bound by these Terms of Use ("Terms"). If you do not agree to these Terms, you must immediately discontinue use of the Portal.</p>



                <p>These Terms constitute a legally binding agreement between you and the Valenzuela City Government. The City reserves the right to modify these Terms at any time, and your continued use of the Portal following any changes constitutes acceptance of the revised Terms.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">2. Purpose of the Portal</h3>



                <p>The Portal is an official digital platform of the Valenzuela City Government designed to:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>Facilitate public consultations between citizens and the local government.</li>



                    <li>Collect citizen feedback on government services, programs, and policies.</li>



                    <li>Promote transparency, accountability, and citizen participation in local governance.</li>



                    <li>Provide a secure and accessible channel for civic engagement.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">3. User Eligibility</h3>



                <p>The Portal is available to all citizens, residents, and stakeholders of Valenzuela City. By using the Portal, you represent and warrant that:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>You are at least 18 years of age, or are using the Portal with the consent and supervision of a parent or legal guardian.</li>



                    <li>You have the legal capacity to enter into a binding agreement.</li>



                    <li>The information you provide is accurate, truthful, and complete.</li>



                    <li>You will use the Portal only for lawful purposes and in accordance with these Terms.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">4. User Conduct and Responsibilities</h3>



                <p>When using the Portal, you agree to the following:</p>



                <p><strong>4.1 You SHALL:</strong></p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>Provide accurate and truthful information in all submissions.</li>



                    <li>Use respectful and appropriate language in all communications.</li>



                    <li>Respect the rights and privacy of other users and government personnel.</li>



                    <li>Report any security vulnerabilities or technical issues you discover.</li>



                </ul>



                <p><strong>4.2 You SHALL NOT:</strong></p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>Submit false, misleading, or fraudulent information.</li>



                    <li>Use the Portal to harass, threaten, defame, or intimidate any person.</li>



                    <li>Upload malicious software, viruses, or any harmful code.</li>



                    <li>Attempt to gain unauthorized access to the Portal's systems, servers, or databases.</li>



                    <li>Use automated tools, bots, or scripts to interact with the Portal without authorization.</li>



                    <li>Impersonate any person or entity, or misrepresent your affiliation with any person or entity.</li>



                    <li>Submit content that is obscene, offensive, discriminatory, or violates any applicable law.</li>



                    <li>Interfere with or disrupt the Portal's functionality or the experience of other users.</li>



                    <li>Use the Portal for any commercial purpose or personal gain.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">5. Submissions and Content</h3>



                <p><strong>5.1 Ownership:</strong> You retain ownership of the content you submit through the Portal. However, by submitting content, you grant the Valenzuela City Government a non-exclusive, royalty-free, perpetual license to use, reproduce, and display your submissions for the purposes of public consultation, governance, and service improvement.</p>



                <p><strong>5.2 Review and Moderation:</strong> The City reserves the right to review, moderate, edit, or remove any submission that violates these Terms, applicable laws, or is deemed inappropriate. Submissions may be reviewed by authorized government personnel before being made publicly visible.</p>



                <p><strong>5.3 Editing Submissions:</strong> After submitting a consultation or feedback, you will receive a secure edit link valid for 7 days. You may use this link to modify your submission within the allowed timeframe. After expiration, modifications must be requested through official channels.</p>



                <p><strong>5.4 File Attachments:</strong> You may upload supporting documents and images with your submissions. You are responsible for ensuring that any files you upload do not contain malicious content, do not violate any copyright or intellectual property rights, and are relevant to your submission.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">6. Privacy and Data Protection</h3>



                <p>Your use of the Portal is also governed by our <a href="#" onclick="closeTermsModal(); setTimeout(function(){ var pm=document.getElementById('policyModal'); if(pm) pm.style.display='flex'; },300);" style="color:#991b1b; font-weight:600;">Privacy Policy</a>, which describes how we collect, use, and protect your personal information. By using the Portal, you consent to the collection and use of your information as described in the Privacy Policy, in compliance with the Data Privacy Act of 2012 (RA 10173).</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">7. Intellectual Property</h3>



                <p>All content, design, graphics, logos, icons, and software on the Portal are the property of the Valenzuela City Government or its licensors and are protected by Philippine intellectual property laws. You may not reproduce, distribute, modify, or create derivative works from any Portal content without prior written consent from the City.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">8. Disclaimer of Warranties</h3>



                <p>The Portal is provided on an <strong>"as is"</strong> and <strong>"as available"</strong> basis. The Valenzuela City Government makes no warranties, express or implied, regarding:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>The accuracy, completeness, or reliability of any information on the Portal.</li>



                    <li>The uninterrupted, error-free, or secure operation of the Portal.</li>



                    <li>The fitness of the Portal for any particular purpose.</li>



                    <li>The absence of viruses or other harmful components.</li>



                </ul>



                <p>The City shall make reasonable efforts to maintain the Portal's availability and accuracy but does not guarantee continuous, uninterrupted access.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">9. Limitation of Liability</h3>



                <p>To the maximum extent permitted by Philippine law, the Valenzuela City Government, its officials, employees, and agents shall not be liable for any direct, indirect, incidental, consequential, or punitive damages arising from:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>Your use of or inability to use the Portal.</li>



                    <li>Any errors, omissions, or inaccuracies in the Portal's content.</li>



                    <li>Unauthorized access to or alteration of your data.</li>



                    <li>Any third-party conduct or content on the Portal.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">10. Termination and Suspension</h3>



                <p>The City reserves the right to suspend or terminate your access to the Portal at any time, without prior notice, if:</p>



                <ul style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li>You violate any provision of these Terms.</li>



                    <li>Your use poses a security risk to the Portal or other users.</li>



                    <li>Required by law or government regulation.</li>



                    <li>The Portal is discontinued or undergoes significant changes.</li>



                </ul>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">11. Governing Law and Dispute Resolution</h3>



                <p>These Terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes arising from or relating to these Terms or your use of the Portal shall be resolved through:</p>



                <ol style="margin:0.5rem 0; padding-left:1.5rem;">



                    <li><strong>Amicable Settlement</strong> &mdash; The parties shall first attempt to resolve disputes through good-faith negotiation.</li>



                    <li><strong>Mediation</strong> &mdash; If negotiation fails, disputes shall be submitted to mediation under the rules of the Philippine Mediation Center.</li>



                    <li><strong>Litigation</strong> &mdash; If mediation fails, disputes shall be submitted to the exclusive jurisdiction of the courts of Valenzuela City.</li>



                </ol>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">12. Severability</h3>



                <p>If any provision of these Terms is found to be invalid, illegal, or unenforceable, the remaining provisions shall continue in full force and effect. The invalid provision shall be modified to the minimum extent necessary to make it valid and enforceable.</p>







                <h3 style="color:#991b1b; margin-top:1.5rem; font-size:1.1rem;">13. Contact Information</h3>



                <p>For questions or concerns regarding these Terms of Use, please contact:</p>



                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:1rem; margin:0.75rem 0;">



                    <p style="margin:0;"><strong>Office of the City Administrator</strong></p>



                    <p style="margin:0.25rem 0 0;">Valenzuela City Government</p>



                    <p style="margin:0.25rem 0 0;">City Hall, MacArthur Highway, Karuhatan, Valenzuela City</p>



                    <p style="margin:0.25rem 0 0;">Email: <a href="mailto:admin@valenzuela.gov.ph" style="color:#991b1b;">admin@valenzuela.gov.ph</a></p>



                    <p style="margin:0.25rem 0 0;">Phone: (02) 8443-8444</p>



                </div>







                <div style="border-top:2px solid #e5e7eb; margin-top:2rem; padding-top:1rem;">



                    <p style="color:#6b7280; font-size:0.8rem; margin:0; text-align:center;">&copy; 2026 Valenzuela City Government. All rights reserved.<br>These Terms of Use are governed by the laws of the Republic of the Philippines.</p>



                </div>



            </div>



        </div>



    </div>







<script>



    function switchSection(section) {



        document.querySelectorAll('[id^="section-"]').forEach(el => {



            el.classList.remove('section-active');



            el.classList.add('section-hidden');



        });



        document.querySelectorAll('.nav-link').forEach(function(nav) {
            nav.classList.remove('active');
        });



        const target = document.getElementById('section-' + section);
        if (target) {
            target.classList.remove('section-hidden');
            target.classList.add('section-active');
        }

        const navItem = document.getElementById('nav-' + section);
        if (navItem) {
            navItem.classList.add('active');
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function toggleConsultationDetail(consultationId) {
        var panel = document.getElementById('consultation-detail-' + consultationId);
        if (!panel) {
            window.location.href = '?section=detail&id=' + consultationId;
            return;
        }
        var isOpen = panel.style.display !== 'none' && panel.style.display !== '';
        if (isOpen) {
            panel.style.display = 'none';
        } else {
            document.querySelectorAll('.consultation-detail-panel').forEach(function(other){ other.style.display = 'none'; });
            panel.style.display = 'block';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function switchToConsultationsList() {
        window.location.href = '?section=consultations';



    }



    



    function viewConsultationDetail(consultationId) {
        window.location.href = '?section=detail&id=' + consultationId;
    }

    function closeAppModal() {

        var el = document.getElementById('app-modal');

        if (el) el.style.display = 'none';

    }







    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAppModal();
    });

    document.addEventListener('DOMContentLoaded', function() {
        var shouldOpenFeedbackModal = <?php echo json_encode(!empty($feedback_form_errors ?? null)); ?>;

        if (shouldOpenFeedbackModal) {
            var feedbackModalEl = document.getElementById('feedbackModal');
            if (feedbackModalEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    new bootstrap.Modal(feedbackModalEl).show();
                } else {
                    feedbackModalEl.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            }
        }
    });







    // Ensure correct section is visible on initial page load (based on server-rendered ?section=...)



    (function(){



        var initial = <?php echo json_encode(($section === 'detail') ? 'consultations' : $section); ?>;



        if (initial && document.getElementById('section-' + initial) && document.getElementById('nav-' + initial)) {



            switchSection(initial);



        }



    })();







    // Modal controls - guarded so missing elements don't break other JS



    (function(){



        var openPrivacy = document.getElementById('openPrivacy');



        if (openPrivacy) {



            openPrivacy.addEventListener('click', function(e){



                e.preventDefault();



                var pm = document.getElementById('policyModal');



                if (pm) pm.style.display = 'flex';



            });



        }







        var openTerms = document.getElementById('openTerms');



        if (openTerms) {



            openTerms.addEventListener('click', function(e){



                e.preventDefault();



                var tm = document.getElementById('termsModal');



                if (tm) tm.style.display = 'flex';



            });



        }







        window.closePolicyModal = function(){ var pm = document.getElementById('policyModal'); if (pm) pm.style.display = 'none'; };



        window.closeTermsModal = function(){ var tm = document.getElementById('termsModal'); if (tm) tm.style.display = 'none'; };



    })();



</script>







<!-- AI CHATBOT WIDGET -->



<div id="chatbot-widget" style="position:fixed; bottom:24px; right:24px; z-index:9999; font-family:'Segoe UI',system-ui,-apple-system,sans-serif;">



    <!-- Chat Toggle Button -->



    <button id="chatbot-toggle" onclick="toggleChatbot()" style="width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg,#dc2626,#991b1b); color:#fff; border:none; cursor:pointer; box-shadow:0 4px 20px rgba(220,38,38,0.4); display:flex; align-items:center; justify-content:center; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">



        <svg id="chatbot-icon-open" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>



        <svg id="chatbot-icon-close" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;"><path d="M18 6L6 18M6 6l12 12"/></svg>



    </button>







    <!-- Chat Window -->



    <div id="chatbot-window" style="display:none; position:absolute; bottom:72px; right:0; width:380px; max-width:calc(100vw - 32px); background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); overflow:hidden; animation:chatSlideUp 0.3s ease;">



        <!-- Header -->



        <div style="background:linear-gradient(135deg,#dc2626,#991b1b); color:#fff; padding:16px 20px; display:flex; align-items:center; gap:12px;">



            <div style="width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center;">



                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>



            </div>



            <div>



                <div style="font-weight:700; font-size:15px;">PCMS Assistant</div>



                <div style="font-size:11px; opacity:0.85;">Public Consultation Help</div>



            </div>



        </div>







        <!-- Messages -->



        <div id="chatbot-messages" style="height:340px; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:12px; background:#f9fafb;">



            <!-- Welcome message -->



            <div class="chat-msg bot">



                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px 12px 12px 2px; padding:12px 14px; max-width:85%; font-size:13px; line-height:1.5; color:#374151; box-shadow:0 1px 3px rgba(0,0,0,0.06);">



                    Hello! I'm the <strong>PCMS Assistant</strong>. I can help you with questions about the Public Consultation Portal — submitting consultations, feedback, privacy, and more.<br><br>How can I help you today?



                </div>



            </div>



        </div>







        <!-- Quick Actions -->



        <div id="chatbot-quick" style="padding:8px 16px; background:#f9fafb; border-top:1px solid #f0f0f0; display:flex; gap:6px; flex-wrap:wrap;">



            <button onclick="sendChatbotQuick('How do I submit a consultation?')" style="font-size:11px; padding:5px 10px; border-radius:20px; border:1px solid #e5e7eb; background:#fff; color:#dc2626; cursor:pointer; white-space:nowrap; font-weight:600;">Submit Consultation</button>



            <button onclick="sendChatbotQuick('How do I submit feedback?')" style="font-size:11px; padding:5px 10px; border-radius:20px; border:1px solid #e5e7eb; background:#fff; color:#dc2626; cursor:pointer; white-space:nowrap; font-weight:600;">Give Feedback</button>



            <button onclick="sendChatbotQuick('What topics can I consult about?')" style="font-size:11px; padding:5px 10px; border-radius:20px; border:1px solid #e5e7eb; background:#fff; color:#dc2626; cursor:pointer; white-space:nowrap; font-weight:600;">Topics</button>



            <button onclick="sendChatbotQuick('Is my data safe?')" style="font-size:11px; padding:5px 10px; border-radius:20px; border:1px solid #e5e7eb; background:#fff; color:#dc2626; cursor:pointer; white-space:nowrap; font-weight:600;">Privacy</button>



        </div>







        <!-- Input -->



        <div style="padding:12px 16px; border-top:1px solid #e5e7eb; display:flex; gap:8px; align-items:flex-end; background:#fff;">



            <textarea id="chatbot-input" placeholder="Type your question..." rows="2"



                style="flex:1; padding:10px 14px; border:1px solid #d1d5db; border-radius:16px; font-size:13px; outline:none; transition:border-color 0.2s; resize:none; font-family:inherit; line-height:1.4; max-height:100px; overflow-y:auto;"



                onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#d1d5db'"



                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatbotMessage();}"></textarea>



            <button onclick="sendChatbotMessage()" style="width:40px; height:40px; border-radius:50%; background:#dc2626; color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background 0.2s; margin-bottom:2px;" onmouseover="this.style.background='#991b1b'" onmouseout="this.style.background='#dc2626'">



                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>



            </button>



        </div>



    </div>



</div>







<style>



@keyframes chatSlideUp {



    from { opacity:0; transform:translateY(16px); }



    to { opacity:1; transform:translateY(0); }



}



.chat-msg.bot { align-self:flex-start; max-width:88%; }



.chat-msg.user { align-self:flex-end; max-width:80%; }



.chat-msg.bot div { white-space:pre-line; }



#chatbot-messages::-webkit-scrollbar { width:4px; }



#chatbot-messages::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }



.chatbot-typing span {



    display:inline-block; width:7px; height:7px; background:#9ca3af; border-radius:50%; margin:0 2px;



    animation:chatTyping 1.2s infinite;



}



.chatbot-typing span:nth-child(2) { animation-delay:0.2s; }



.chatbot-typing span:nth-child(3) { animation-delay:0.4s; }



@keyframes chatTyping {



    0%,60%,100% { transform:translateY(0); opacity:0.4; }



    30% { transform:translateY(-6px); opacity:1; }



}



</style>







<script>



var chatbotOpen = false;







function toggleChatbot() {



    chatbotOpen = !chatbotOpen;



    document.getElementById('chatbot-window').style.display = chatbotOpen ? 'block' : 'none';



    document.getElementById('chatbot-icon-open').style.display = chatbotOpen ? 'none' : 'block';



    document.getElementById('chatbot-icon-close').style.display = chatbotOpen ? 'block' : 'none';



    if (chatbotOpen) {



        document.getElementById('chatbot-input').focus();



    }



}







function sendChatbotQuick(text) {



    document.getElementById('chatbot-input').value = text;



    sendChatbotMessage();



    // Hide quick actions after first use



    var qa = document.getElementById('chatbot-quick');



    if (qa) qa.style.display = 'none';



}







function appendChatMessage(text, sender) {



    var container = document.getElementById('chatbot-messages');



    var div = document.createElement('div');



    div.className = 'chat-msg ' + sender;







    if (sender === 'user') {



        div.innerHTML = '<div style="background:#dc2626; color:#fff; border-radius:12px 12px 2px 12px; padding:10px 14px; font-size:13px; line-height:1.5;">' + escapeChat(text) + '</div>';



    } else {



        // Parse basic markdown bold



        var formatted = escapeChat(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');



        // Parse bullet points



        formatted = formatted.replace(/^• /gm, '<span style="color:#dc2626;">•</span> ');



        div.innerHTML = '<div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px 12px 12px 2px; padding:12px 14px; max-width:100%; font-size:13px; line-height:1.6; color:#374151; box-shadow:0 1px 3px rgba(0,0,0,0.06);">' + formatted + '</div>';



    }







    container.appendChild(div);



    container.scrollTop = container.scrollHeight;



    return div;



}







function showTypingIndicator() {



    var container = document.getElementById('chatbot-messages');



    var div = document.createElement('div');



    div.className = 'chat-msg bot';



    div.id = 'chatbot-typing';



    div.innerHTML = '<div class="chatbot-typing" style="background:#fff; border:1px solid #e5e7eb; border-radius:12px 12px 12px 2px; padding:12px 18px; box-shadow:0 1px 3px rgba(0,0,0,0.06);"><span></span><span></span><span></span></div>';



    container.appendChild(div);



    container.scrollTop = container.scrollHeight;



}







function removeTypingIndicator() {



    var el = document.getElementById('chatbot-typing');



    if (el) el.remove();



}







function escapeChat(str) {



    var d = document.createElement('div');



    d.appendChild(document.createTextNode(str));



    return d.innerHTML;



}







function sendChatbotMessage() {



    var input = document.getElementById('chatbot-input');



    var text = input.value.trim();



    if (!text) return;







    input.value = '';



    appendChatMessage(text, 'user');



    showTypingIndicator();







    // Hide quick actions



    var qa = document.getElementById('chatbot-quick');



    if (qa) qa.style.display = 'none';







    fetch('API/chatbot_api.php', {



        method: 'POST',



        headers: { 'Content-Type': 'application/json' },



        body: JSON.stringify({ message: text })



    })



    .then(function(res) { return res.json(); })



    .then(function(data) {



        removeTypingIndicator();



        if (data.success && data.reply) {



            appendChatMessage(data.reply, 'bot');



        } else {



            appendChatMessage('Sorry, I encountered an error. Please try again.', 'bot');



        }



    })



    .catch(function() {



        removeTypingIndicator();



        appendChatMessage('Sorry, I\'m unable to connect right now. Please try again later.', 'bot');



    });



}



</script>







<!-- ===================== DARK MODE & LANGUAGE SYSTEM ===================== -->



<script>



// ── Dark Mode Toggle ──



function togglePortalTheme() {



    var html = document.documentElement;



    html.classList.toggle('dark');



    var isDark = html.classList.contains('dark');



    localStorage.setItem('portal-theme', isDark ? 'dark' : 'light');



    document.getElementById('portal-dark-icon').style.display = isDark ? 'none' : '';



    document.getElementById('portal-light-icon').style.display = isDark ? '' : 'none';



    // Update nav bar background



    var navBar = document.querySelector('.portal-nav-bar');



    if (navBar) {



        navBar.style.background = isDark ? '#1f2937' : 'white';



        navBar.style.borderColor = isDark ? '#374151' : '#f0f0f0';



    }



}







// Initialize theme on load



(function() {



    var isDark = localStorage.getItem('portal-theme') === 'dark';



    if (isDark) {



        document.getElementById('portal-dark-icon').style.display = 'none';



        document.getElementById('portal-light-icon').style.display = '';



        var navBar = document.querySelector('.portal-nav-bar');



        if (navBar) {



            navBar.style.background = '#1f2937';



            navBar.style.borderColor = '#374151';



        }



    }



})();







// ── Translation System ──



var portalLang = localStorage.getItem('portal-lang') || 'en';







var translations = {



    tl: {



        // Header



        header_title: 'Pampublikong Konsultasyon',



        header_subtitle: 'Pamahalaang Lungsod ng Valenzuela',



        back_home: 'Bumalik sa Home',







        // Navigation



        nav_consultations: 'Mga Aktibong Konsultasyon',



        nav_submit: 'Magsumite ng Konsultasyon',

        nav_survey_results: 'Resulta ng Survey',



        nav_feedback: 'Magsumite ng Feedback',







        // Consultations section



        consultations_title: 'Mga Aktibong Konsultasyon',



        consultations_subtitle: 'Suriin at magbigay ng puna sa mga iminungkahing ordinansa, programa, at patakaran',







        // Feature cards



        feat_consultations: 'Pampublikong Konsultasyon',



        feat_consultations_desc: 'Mag-browse at lumahok sa mga aktibong konsultasyon ng gobyerno tungkol sa mga patakaran at programa.',



        feat_feedback: 'Magsumite ng Feedback',



        feat_feedback_desc: 'Ibahagi ang iyong mga saloobin, mungkahi, at alalahanin nang direkta sa mga opisyal ng lungsod.',



        feat_chatbot: 'AI Assistant',



        feat_chatbot_desc: 'Kailangan ng tulong? I-click ang chat icon sa ibaba-kanan para magtanong sa aming AI assistant.',



        feat_secure: 'Ligtas at Pribado',



        feat_secure_desc: 'Ang iyong data ay protektado sa ilalim ng Data Privacy Act. Lahat ng submission ay naka-encrypt.',







        // Submit consultation section



        submit_title: 'Magsumite ng Kahilingan para sa Konsultasyon',



        submit_subtitle: 'May paksa ba na gusto mong ikonsulta ng lungsod sa publiko? Isumite ang iyong kahilingan dito.',



        submit_form_title: 'Isumite ang Iyong Kahilingan para sa Konsultasyon',







        // Form labels



        form_name: 'Pangalan',



        form_topic: 'Paksa / Pamagat *',



        form_address: 'Tirahan',



        form_barangay: 'Barangay',



        form_description: 'Paglalarawan / Detalye *',



        form_email: 'Iyong Email Address *',



        form_notifications: 'Mga Abiso',



        form_email_updates: 'Padalhan ako ng mga update sa email tungkol sa konsultasyong ito',



        btn_submit_consultation: 'Isumite ang Kahilingan para sa Konsultasyon',



        agree_terms_consultation: 'Nabasa ko at sumasang-ayon ako sa <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var pm=document.getElementById(\'policyModal\'); if(pm) pm.style.display=\'flex\';" style="color:#991b1b; font-weight:600; text-decoration:underline;">Patakaran sa Privacy</a> at <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var tm=document.getElementById(\'termsModal\'); if(tm) tm.style.display=\'flex\';" style="color:#991b1b; font-weight:600; text-decoration:underline;">Mga Tuntunin ng Paggamit</a> ng Public Consultation Portal.',







        // Feedback section



        feedback_title: 'Magsumite ng Feedback',



        feedback_subtitle: 'Ibahagi ang iyong mga saloobin sa mga aktibong konsultasyon',







        // Feedback info boxes



        fb_info1_title: 'Ibahagi ang Iyong Pananaw',



        fb_info1_desc: 'Tumulong sa pagbuo ng mas magandang patakaran sa pamamagitan ng pagbabahagi ng iyong feedback sa mga iminungkahing ordinansa at programa.',



        fb_info2_title: 'Ligtas at Na-verify',



        fb_info2_desc: 'Ang iyong telepono at email ay na-verify upang matiyak na lehitimo lamang ang mga isinumite.',



        fb_info3_title: 'Maingat na Sinusuri',



        fb_info3_desc: 'Bawat isinumite ay sinusuri at isinasaalang-alang ng aming mga opisyal ng lungsod.',







        // Feedback form



        verify_email: 'I-verify ang Email',



        form_email_label: 'Email Address *',



        share_feedback: 'Ibahagi ang Feedback',



        form_fullname: 'Buong Pangalan *',



        form_type: 'Uri *',



        form_your_feedback: 'Ang Iyong Feedback *',



        form_attachment: 'Mag-attach ng Larawan o Dokumento (opsyonal)',



        btn_submit_feedback: 'Isumite ang Feedback',



        form_name_label: 'Buong Pangalan *',



        form_phone_label: 'Numero ng Telepono',



        form_feedback_type: 'Uri ng Feedback',



        form_rating: 'Rating',



        form_message: 'Ang Iyong Feedback *',



        submit_feedback: 'Isumite ang Feedback',



        reset_form: 'I-reset',



        form_email_notifications: 'Gusto kong makatanggap ng email update tungkol sa konsultasyong ito',



        form_name_label: 'Buong Pangalan *',



        form_phone_label: 'Numero ng Telepono',



        form_feedback_type: 'Uri ng Feedback',



        form_rating: 'Rating',



        form_message: 'Ang Iyong Feedback *',



        submit_feedback: 'Isumite ang Feedback',



        reset_form: 'I-reset',



        form_email_notifications: 'Gusto kong makatanggap ng email update tungkol sa konsultasyong ito',



        agree_terms_feedback: 'Nabasa ko at sumasang-ayon ako sa <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var pm=document.getElementById(\'policyModal\'); if(pm) pm.style.display=\'flex\';" style="color:#fecaca; font-weight:600; text-decoration:underline;">Patakaran sa Privacy</a> at <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var tm=document.getElementById(\'termsModal\'); if(tm) tm.style.display=\'flex\';" style="color:#fecaca; font-weight:600; text-decoration:underline;">Mga Tuntunin ng Paggamit</a> ng Public Consultation Portal.',



        fb_reviewed_note: 'Ang iyong na-verify na feedback ay susuriin ng aming koponan.',







        // Footer



        footer_about: 'Tungkol',



        footer_about_desc: 'Portal ng Pampublikong Konsultasyon ng Pamahalaang Lungsod ng Valenzuela',



        footer_links: 'Mga Mabilisang Link',



        footer_contact: 'Makipag-ugnayan',



        footer_follow: 'Sundan Kami',



        footer_rights: '\u00A9 2026 Pamahalaang Lungsod ng Valenzuela. Lahat ng karapatan ay nakalaan.',







        // Consultation cards



        view_details: 'Tingnan ang Detalye',



        read_more: 'Magbasa pa',



        status_active: 'Aktibo',



        status_closed: 'Sarado',



        no_consultations: 'Wala pang aktibong konsultasyon sa ngayon.',



        past_consultations: 'Mga Nakaraang Konsultasyon',







        // Chatbot



        chatbot_title: 'PCMS Assistant',



        chatbot_subtitle: 'Tulong sa Pampublikong Konsultasyon',



        chatbot_placeholder: 'I-type ang iyong tanong...',



        chatbot_welcome: 'Kumusta! Ako ang PCMS Assistant. Makakatulong ako sa mga tanong tungkol sa Portal ng Pampublikong Konsultasyon — pagsusumite ng konsultasyon, feedback, privacy, at iba pa.\n\nPaano kita matutulungan ngayon?',



        chatbot_quick_submit: 'Magsumite ng Konsultasyon',



        chatbot_quick_feedback: 'Magbigay ng Feedback',



        chatbot_quick_topics: 'Mga Paksa',



        chatbot_quick_privacy: 'Privacy'



    },



    en: {



        header_title: 'Public Consultation',



        header_subtitle: 'Valenzuela City Government',



        back_home: 'Back Home',



        nav_consultations: 'Active Consultations',



        nav_submit: 'Submit Consultation',

        nav_survey_results: 'Survey Results',



        nav_feedback: 'Submit Feedback',



        consultations_title: 'Active Consultations',



        consultations_subtitle: 'Review and provide feedback on proposed ordinances, programs, and policies',



        feat_consultations: 'Public Consultations',



        feat_consultations_desc: 'Browse and participate in active government consultations on policies and programs.',



        feat_feedback: 'Submit Feedback',



        feat_feedback_desc: 'Share your thoughts, suggestions, and concerns directly with city officials.',



        feat_chatbot: 'AI Assistant',



        feat_chatbot_desc: 'Need help? Click the chat icon at the bottom-right to ask our AI assistant.',



        feat_secure: 'Secure & Private',



        feat_secure_desc: 'Your data is protected under the Data Privacy Act. All submissions are encrypted.',



        submit_title: 'Submit a Consultation Request',



        submit_subtitle: "Have a topic you'd like the city to consult the public on? Submit your request here.",



        submit_form_title: 'Submit Your Consultation Request',



        form_name: 'Name',



        form_topic: 'Topic / Title *',



        form_address: 'Address',



        form_barangay: 'Barangay',



        form_description: 'Description / Details *',



        form_email: 'Your Email Address *',



        form_notifications: 'Notifications',



        form_email_updates: 'Send me email updates about this consultation',



        btn_submit_consultation: 'Submit Consultation Request',



        agree_terms_consultation: 'I have read and agree to the <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var pm=document.getElementById(\'policyModal\'); if(pm) pm.style.display=\'flex\';" style="color:#991b1b; font-weight:600; text-decoration:underline;">Privacy Policy</a> and <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var tm=document.getElementById(\'termsModal\'); if(tm) tm.style.display=\'flex\';" style="color:#991b1b; font-weight:600; text-decoration:underline;">Terms of Use</a> of the Public Consultation Portal.',



        feedback_title: 'Submit Feedback',



        feedback_subtitle: 'Share your thoughts on active consultations',



        fb_info1_title: 'Share Your Views',



        fb_info1_desc: 'Help shape better policies by sharing your feedback on proposed ordinances and programs.',



        fb_info2_title: 'Secure & Verified',



        fb_info2_desc: 'Your phone and email are verified to ensure legitimate submissions only.',



        fb_info3_title: 'Reviewed Carefully',



        fb_info3_desc: 'Every submission is reviewed and considered by our city officials.',



        verify_email: 'Verify Email',



        form_email_label: 'Email Address *',



        share_feedback: 'Share Feedback',



        form_fullname: 'Full Name *',



        form_type: 'Type *',



        form_your_feedback: 'Your Feedback *',



        form_attachment: 'Attach Image or Document (optional)',



        btn_submit_feedback: 'Submit Feedback',



        form_name_label: 'Full Name *',



        form_phone_label: 'Phone Number',



        form_feedback_type: 'Feedback Type',



        form_rating: 'Rating',



        form_message: 'Your Feedback *',



        submit_feedback: 'Submit Feedback',



        reset_form: 'Reset',



        form_email_notifications: 'I\'d like to receive email updates about this consultation',



        form_name_label: 'Full Name *',



        form_phone_label: 'Phone Number',



        form_feedback_type: 'Feedback Type',



        form_rating: 'Rating',



        form_message: 'Your Feedback *',



        submit_feedback: 'Submit Feedback',



        reset_form: 'Reset',



        form_email_notifications: 'I\'d like to receive email updates about this consultation',



        agree_terms_feedback: 'I have read and agree to the <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var pm=document.getElementById(\'policyModal\'); if(pm) pm.style.display=\'flex\';" style="color:#fecaca; font-weight:600; text-decoration:underline;">Privacy Policy</a> and <a href="#" onclick="event.preventDefault(); event.stopPropagation(); var tm=document.getElementById(\'termsModal\'); if(tm) tm.style.display=\'flex\';" style="color:#fecaca; font-weight:600; text-decoration:underline;">Terms of Use</a> of the Public Consultation Portal.',



        fb_reviewed_note: 'Your verified feedback will be reviewed by our team.',



        footer_about: 'About',



        footer_about_desc: 'Public Consultation Portal of Valenzuela City Government',



        footer_links: 'Quick Links',



        footer_contact: 'Contact',



        footer_follow: 'Follow Us',



        footer_rights: '\u00A9 2026 Valenzuela City Government. All rights reserved.',



        view_details: 'View Details',



        read_more: 'Read More',



        status_active: 'Active',



        status_closed: 'Closed',



        no_consultations: 'No active consultations at this time.',



        past_consultations: 'Past Consultations',



        chatbot_title: 'PCMS Assistant',



        chatbot_subtitle: 'Public Consultation Help',



        chatbot_placeholder: 'Type your question...',



        chatbot_welcome: "Hello! I'm the PCMS Assistant. I can help you with questions about the Public Consultation Portal — submitting consultations, feedback, privacy, and more.\n\nHow can I help you today?",



        chatbot_quick_submit: 'Submit Consultation',



        chatbot_quick_feedback: 'Give Feedback',



        chatbot_quick_topics: 'Topics',



        chatbot_quick_privacy: 'Privacy'



    }



};







function applyTranslations(lang) {



    var dict = translations[lang];



    if (!dict) return;







    // Keys that contain HTML (links, etc.) — use innerHTML instead of textContent



    var htmlKeys = ['agree_terms_consultation', 'agree_terms_feedback'];







    // Update all elements with data-i18n attribute



    var els = document.querySelectorAll('[data-i18n]');



    for (var i = 0; i < els.length; i++) {



        var key = els[i].getAttribute('data-i18n');



        if (dict[key] !== undefined) {



            if (htmlKeys.indexOf(key) !== -1) {



                els[i].innerHTML = dict[key];



            } else {



                els[i].textContent = dict[key];



            }



        }



    }







    // Update placeholders



    var placeholders = {



        'chatbot-input': lang === 'tl' ? 'I-type ang iyong tanong...' : 'Type your question...'



    };



    for (var id in placeholders) {



        var el = document.getElementById(id);



        if (el) el.placeholder = placeholders[id];



    }







    // Update input placeholders based on language



    if (lang === 'tl') {



        setPlaceholders({



            'name': 'Iyong buong pangalan',



            'consultation_topic': 'hal., Iminungkahing Pamamahala ng Trapiko sa Barangay X',



            'address': 'Numero ng bahay, kalye, barangay, lungsod',



            'consultation_description': 'Mangyaring magbigay ng detalye tungkol sa iyong kahilingan...',



            'consultation_email': 'iyong.email@halimbawa.com',



            'email': 'iyong@email.com',



            'message': 'Mangyaring ibahagi ang iyong mga saloobin...',



            'phone': '09XX-XXX-XXXX',



            'feedback_message': 'Ibahagi ang iyong mga saloobin, mungkahi, o alalahanin...'



        });



    } else {



        setPlaceholders({



            'name': 'Your full name',



            'consultation_topic': 'e.g., Proposed Traffic Management in Barangay X',



            'address': 'House number, street, barangay, city',



            'consultation_description': 'Please provide details about your consultation request...',



            'consultation_email': 'your.email@example.com',



            'email': 'your@email.com',



            'message': 'Please share your thoughts...',



            'phone': '09XX-XXX-XXXX',



            'feedback_message': 'Share your thoughts, suggestions, or concerns...'



        });



    }







    // Update chatbot quick action buttons



    var quickBtns = document.querySelectorAll('#chatbot-quick button');



    var quickKeys = ['chatbot_quick_submit', 'chatbot_quick_feedback', 'chatbot_quick_topics', 'chatbot_quick_privacy'];



    for (var j = 0; j < quickBtns.length && j < quickKeys.length; j++) {



        if (dict[quickKeys[j]]) quickBtns[j].textContent = dict[quickKeys[j]];



    }







    // Update chatbot header



    var chatTitle = document.querySelector('#chatbot-window div div:nth-child(2) div:first-child');



    var chatSub = document.querySelector('#chatbot-window div div:nth-child(2) div:last-child');



    if (chatTitle && dict.chatbot_title) chatTitle.textContent = dict.chatbot_title;



    if (chatSub && dict.chatbot_subtitle) chatSub.textContent = dict.chatbot_subtitle;







    // Update select option text for gender



    var genderSelect = document.querySelector('select[name="gender"]');



    if (genderSelect) {



        var gOpts = genderSelect.options;



        if (lang === 'tl') {



            if (gOpts[0]) gOpts[0].text = 'Kasarian';



            if (gOpts[1]) gOpts[1].text = 'Babae';



            if (gOpts[2]) gOpts[2].text = 'Lalaki';



            if (gOpts[3]) gOpts[3].text = 'Iba pa';



            if (gOpts[4]) gOpts[4].text = 'Ayaw sabihin';



        } else {



            if (gOpts[0]) gOpts[0].text = 'Gender';



            if (gOpts[1]) gOpts[1].text = 'Female';



            if (gOpts[2]) gOpts[2].text = 'Male';



            if (gOpts[3]) gOpts[3].text = 'Other';



            if (gOpts[4]) gOpts[4].text = 'Prefer not to say';



        }



    }







    // Update barangay select first option



    var brgySelect = document.querySelector('select[name="barangay"]');



    if (brgySelect && brgySelect.options[0]) {



        brgySelect.options[0].text = lang === 'tl' ? 'Pumili ng barangay' : 'Select barangay';



    }







    // Update feedback type select



    var fbTypeSelect = document.querySelector('select[name="feedback_type"]');



    if (fbTypeSelect) {



        var fOpts = fbTypeSelect.options;



        if (lang === 'tl') {



            if (fOpts[0]) fOpts[0].text = 'Pumili ng uri...';



            if (fOpts[1]) fOpts[1].text = 'Mungkahi';



            if (fOpts[2]) fOpts[2].text = 'Alalahanin';



            if (fOpts[3]) fOpts[3].text = 'Tanong';



            if (fOpts[4]) fOpts[4].text = 'Suporta';



            if (fOpts[5]) fOpts[5].text = 'Pagtutol';



        } else {



            if (fOpts[0]) fOpts[0].text = 'Select type...';



            if (fOpts[1]) fOpts[1].text = 'Suggestion';



            if (fOpts[2]) fOpts[2].text = 'Concern';



            if (fOpts[3]) fOpts[3].text = 'Question';



            if (fOpts[4]) fOpts[4].text = 'Support';



            if (fOpts[5]) fOpts[5].text = 'Opposition';



        }



    }







    // Update page title



    document.title = lang === 'tl'



        ? 'Portal ng Pampublikong Konsultasyon - Lungsod ng Valenzuela'



        : 'Public Consultation Portal - Valenzuela City';



}







function setPlaceholders(map) {



    for (var name in map) {



        var els = document.querySelectorAll('[name="' + name + '"], #' + name);



        for (var i = 0; i < els.length; i++) {



            if (els[i].placeholder !== undefined) els[i].placeholder = map[name];



        }



    }



}







function togglePortalLang() {



    portalLang = (portalLang === 'en') ? 'tl' : 'en';



    localStorage.setItem('portal-lang', portalLang);



    var btn = document.getElementById('lang-toggle');



    btn.textContent = portalLang === 'en' ? 'EN' : 'TL';



    btn.title = portalLang === 'en' ? 'Switch to Tagalog' : 'Lumipat sa English';



    applyTranslations(portalLang);



}







function toggleFeedbackVisibility() {



    var container = document.getElementById('feedback-list-container');



    var btn = document.getElementById('feedback-toggle-btn');



    var icon = document.getElementById('feedback-toggle-icon');



    var text = document.getElementById('feedback-toggle-text');



    



    if (container.style.display === 'none') {



        container.style.display = 'flex';



        icon.className = 'bi bi-eye-slash';



        text.textContent = 'Hide Other Feedback';



        btn.style.background = '#6b7280';



    } else {



        container.style.display = 'none';



        icon.className = 'bi bi-eye';



        text.textContent = 'See Other Feedback';



        btn.style.background = '#10b981';



    }



}







// Initialize language on load



(function() {



    var btn = document.getElementById('lang-toggle');



    if (portalLang === 'tl') {



        btn.textContent = 'TL';



        btn.title = 'Lumipat sa English';



        applyTranslations('tl');



    } else {



        btn.textContent = 'EN';



        btn.title = 'Switch to Tagalog';



    }



})();







// Star rating interaction



document.addEventListener('DOMContentLoaded', function() {



    const ratingInputs = document.querySelectorAll('input[name="rating"]');



    ratingInputs.forEach(function(input) {



        input.addEventListener('change', function() {



            const stars = this.parentElement.parentElement.querySelectorAll('i.bi-star');



            const selectedValue = parseInt(this.value);



            stars.forEach(function(star, index) {



                if (index < selectedValue) {



                    star.classList.remove('bi-star');



                    star.classList.add('bi-star-fill');



                    star.style.color = '#f59e0b';



                } else {



                    star.classList.remove('bi-star-fill');



                    star.classList.add('bi-star');



                    star.style.color = '#d1d5db';



                }



            });



        });



    });



});







// Star rating interaction



document.addEventListener('DOMContentLoaded', function() {



    const ratingInputs = document.querySelectorAll('input[name="rating"]');



    ratingInputs.forEach(function(input) {



        input.addEventListener('change', function() {



            const stars = this.parentElement.parentElement.querySelectorAll('i.bi-star');



            const selectedValue = parseInt(this.value);



            stars.forEach(function(star, index) {



                if (index < selectedValue) {



                    star.classList.remove('bi-star');



                    star.classList.add('bi-star-fill');



                    star.style.color = '#f59e0b';



                } else {



                    star.classList.remove('bi-star-fill');



                    star.classList.add('bi-star');



                    star.style.color = '#d1d5db';



                }



            });



        });



    });



});



</script>







<!-- ==================== GUIDED TOUR / WALKTHROUGH ==================== -->



<style>



    .tour-overlay { position:fixed; inset:0; z-index:9990; pointer-events:none; transition:opacity 0.3s; }



    .tour-overlay.active { pointer-events:auto; }



    .tour-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9991; transition:opacity 0.3s; }



    .tour-highlight-ring { position:fixed; z-index:9993; border-radius:8px; box-shadow:0 0 0 4px #dc2626, 0 0 0 9999px rgba(0,0,0,0.55); transition:all 0.4s ease; pointer-events:none; }



    .tour-tooltip { position:fixed; z-index:9994; background:white; border-radius:14px; box-shadow:0 20px 50px rgba(0,0,0,0.25); max-width:360px; width:90vw; overflow:hidden; animation:tourFadeIn 0.3s ease; }



    .tour-tooltip-header { background:linear-gradient(135deg,#991b1b,#7f1d1d); color:white; padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; }



    .tour-tooltip-header h4 { margin:0; font-size:1rem; font-weight:800; }



    .tour-tooltip-header .tour-step-badge { background:rgba(255,255,255,0.2); padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; }



    .tour-tooltip-body { padding:1.25rem; }



    .tour-tooltip-body p { margin:0 0 1rem; color:#374151; font-size:0.9rem; line-height:1.6; }



    .tour-tooltip-actions { display:flex; gap:0.5rem; justify-content:flex-end; }



    .tour-btn-skip { background:none; border:1px solid #d1d5db; color:#6b7280; padding:0.5rem 1rem; border-radius:8px; font-size:0.85rem; cursor:pointer; font-weight:600; }



    .tour-btn-skip:hover { background:#f3f4f6; }



    .tour-btn-next { background:#991b1b; color:white; border:none; padding:0.5rem 1.25rem; border-radius:8px; font-size:0.85rem; cursor:pointer; font-weight:700; }



    .tour-btn-next:hover { background:#7f1d1d; }



    .tour-btn-prev { background:none; border:1px solid #d1d5db; color:#374151; padding:0.5rem 1rem; border-radius:8px; font-size:0.85rem; cursor:pointer; font-weight:600; }



    .tour-btn-prev:hover { background:#f3f4f6; }



    .tour-progress { display:flex; gap:4px; justify-content:center; margin-bottom:0.75rem; }



    .tour-progress-dot { width:8px; height:8px; border-radius:50%; background:#e5e7eb; transition:background 0.2s; }



    .tour-progress-dot.active { background:#991b1b; }



    .tour-progress-dot.done { background:#fca5a5; }



    @keyframes tourFadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }



    .dark .tour-tooltip { background:#1f2937 !important; }



    .dark .tour-tooltip-body p { color:#d1d5db !important; }



    .dark .tour-btn-skip { border-color:#4b5563 !important; color:#9ca3af !important; }



    .dark .tour-btn-skip:hover { background:#374151 !important; }



    .dark .tour-btn-prev { border-color:#4b5563 !important; color:#d1d5db !important; }



    .dark .tour-btn-prev:hover { background:#374151 !important; }



</style>







<div id="tour-overlay" class="tour-overlay" style="display:none;">



    <div class="tour-highlight-ring" id="tour-ring"></div>



    <div class="tour-tooltip" id="tour-tooltip"></div>



</div>







<script>



    var tourSteps = [



        {



            target: '.logo-section',



            title: 'Welcome!',



            text: 'Welcome to the <strong>Public Consultation Management Portal</strong> of Valenzuela City! This quick tour will show you around. You can skip anytime.',



            position: 'bottom'



        },



        {



            target: '#nav-consultations',



            title: 'Active Consultations',



            text: 'View all <strong>active public consultations</strong> here. These are proposed ordinances, programs, and policies open for citizen input.',



            position: 'bottom'



        },



        {



            target: '#nav-submit-consultation',



            title: 'Submit a Consultation',



            text: 'Have a topic you want the city to address? Click here to <strong>submit your own consultation request</strong>. Fill in the details and our team will review it.',



            position: 'bottom'



        },



        {



            target: '#nav-feedback',



            title: 'Submit Feedback',



            text: 'Share your <strong>thoughts, suggestions, or concerns</strong> about any active consultation. Your feedback helps shape better policies!',



            position: 'bottom'



        },



        {



            target: '#tour-btn',



            title: 'Replay This Tour',



            text: 'Want to see this tour again? Click the <strong>? button</strong> anytime to restart the guided walkthrough.',



            position: 'bottom'



        },



        {



            target: '#lang-toggle',



            title: 'Language Toggle',



            text: 'Switch between <strong>English</strong> and <strong>Tagalog</strong> with one click. The entire portal translates instantly.',



            position: 'bottom'



        },



        {



            target: '#theme-toggle-portal',



            title: 'Dark Mode',



            text: 'Prefer a darker look? Toggle <strong>dark mode</strong> on or off here. Your preference is saved automatically.',



            position: 'bottom'



        },



        {



            target: '#chatbot-toggle',



            title: 'AI Chatbot Assistant',



            text: 'Need help? Click this button to open the <strong>AI Chatbot</strong>. Ask it anything about the portal — how to submit, what topics are available, privacy info, and more!',



            position: 'top'



        }



    ];







    var currentStep = 0;



    var tourActive = false;







    window.startPortalTour = function() {



        currentStep = 0;



        tourActive = true;



        const overlay = document.getElementById('tour-overlay');



        if (overlay) {



            overlay.style.display = 'block';



            overlay.classList.add('active');



        document.body.style.overflow = 'hidden';



        showTourStep(currentStep);



        }



    };







    function endTour() {



        tourActive = false;



        document.getElementById('tour-overlay').style.display = 'none';



        document.getElementById('tour-overlay').classList.remove('active');



        document.body.style.overflow = '';



        localStorage.setItem('portal-tour-done', '1');



    }







    function showTourStep(idx) {



        if (idx < 0 || idx >= tourSteps.length) { endTour(); return; }



        currentStep = idx;



        var step = tourSteps[idx];



        var el = document.querySelector(step.target);



        var ring = document.getElementById('tour-ring');



        var tooltip = document.getElementById('tour-tooltip');







        // If target not found, skip to next



        if (!el) { 



            showTourStep(idx + 1); 



            return; 



        }







        // Scroll element into view



        el.scrollIntoView({ behavior: 'smooth', block: 'center' });







        setTimeout(function() {



            var rect = el.getBoundingClientRect();



            var pad = 6;







            // Position highlight ring



            ring.style.top = (rect.top - pad) + 'px';



            ring.style.left = (rect.left - pad) + 'px';



            ring.style.width = (rect.width + pad * 2) + 'px';



            ring.style.height = (rect.height + pad * 2) + 'px';







            // Build progress dots



            var dots = '';



            for (var i = 0; i < tourSteps.length; i++) {



                var cls = i < idx ? 'done' : (i === idx ? 'active' : '');



                dots += '<div class="tour-progress-dot ' + cls + '"></div>';



            }







            // Build tooltip HTML



            var isFirst = idx === 0;



            var isLast = idx === tourSteps.length - 1;



            tooltip.innerHTML = '' +



                '<div class="tour-tooltip-header">' +



                    '<h4>' + step.title + '</h4>' +



                    '<span class="tour-step-badge">' + (idx + 1) + ' / ' + tourSteps.length + '</span>' +



                '</div>' +



                '<div class="tour-tooltip-body">' +



                    '<div class="tour-progress">' + dots + '</div>' +



                    '<p>' + step.text + '</p>' +



                    '<div class="tour-tooltip-actions">' +



                        '<button class="tour-btn-skip" onclick="endPortalTour()">Skip Tour</button>' +



                        (!isFirst ? '<button class="tour-btn-prev" onclick="tourPrev()">Back</button>' : '') +



                        '<button class="tour-btn-next" onclick="tourNext()">' + (isLast ? 'Finish' : 'Next') + '</button>' +



                    '</div>' +



                '</div>';







            // Position tooltip



            var tw = Math.min(360, window.innerWidth * 0.9);



            var ttop, tleft;







            if (step.position === 'bottom') {



                ttop = rect.bottom + 14;



                tleft = rect.left + rect.width / 2 - tw / 2;



            } else {



                ttop = rect.top - 14;



                tleft = rect.left + rect.width / 2 - tw / 2;



            }







            // Keep within viewport



            if (tleft < 10) tleft = 10;



            if (tleft + tw > window.innerWidth - 10) tleft = window.innerWidth - tw - 10;







            if (step.position === 'top') {



                tooltip.style.top = 'auto';



                tooltip.style.bottom = (window.innerHeight - rect.top + 14) + 'px';



            } else {



                tooltip.style.top = ttop + 'px';



                tooltip.style.bottom = 'auto';



            }



            tooltip.style.left = tleft + 'px';



            tooltip.style.width = tw + 'px';



        }, 350);



    }







    window.tourNext = function() { showTourStep(currentStep + 1); };



    window.tourPrev = function() { showTourStep(currentStep - 1); };



    window.endPortalTour = endTour;







    // Auto-start tour on first visit



    document.addEventListener('DOMContentLoaded', function() {



        if (!localStorage.getItem('portal-tour-done')) {



            setTimeout(function() { 



                startPortalTour(); 



            }, 800);



        }



    });



</script>



<script>



// Loading Modal for Form Submissions



document.addEventListener('DOMContentLoaded', function() {



    // Show loading modal when consultation form is submitted



    const consultationForm = document.querySelector('#section-submit-consultation form[method="POST"]');



    if (consultationForm) {

        consultationForm.addEventListener('submit', function(e) {

            console.log('Form submitting...');

            const submitBtn = this.querySelector('#btn-submit-consultation');

            if (submitBtn && !submitBtn.disabled) {

                // Disable button AFTER form submits to ensure name is sent
                setTimeout(function() {
                    submitBtn.disabled = true;
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.innerHTML = '<i class="bi bi-send" style="margin-right:0.5rem; vertical-align:middle;"></i> Submitting...';
                }, 100);
            }

            // Let the form submit normally

        });

    }



    



    // Show loading modal when feedback form is submitted



    const feedbackForms = document.querySelectorAll('form[action*="public-portal.php?section=detail"]');



    feedbackForms.forEach(form => {



        form.addEventListener('submit', function(e) {



            const submitBtn = this.querySelector('[name="submit_feedback"]');



            if (submitBtn && !submitBtn.disabled) {



                // Don't prevent default, just show modal and let form submit normally



                showLoadingModal();



            }



        });



    });



});



function selectStarRating(label, value) {

    const group = label.closest('.star-rating-group');

    if (!group) return;

    const labels = group.querySelectorAll('label');

    labels.forEach(function(lbl, idx) {

        const icon = lbl.querySelector('i');

        if (!icon) return;

        if ((idx + 1) <= value) {

            icon.className = 'bi bi-star-fill';

            icon.style.color = '#f59e0b';

        } else {

            icon.className = 'bi bi-star';

            icon.style.color = '#d1d5db';

        }

    });

    var radio = label.querySelector('input[type="radio"]');

    if (radio) radio.checked = true;

}



function showLoadingModal() {



    const modal = document.getElementById('loadingModal');



    if (modal) {



        modal.style.display = 'flex';



        document.body.style.overflow = 'hidden';



    }



}







function hideLoadingModal() {



    const modal = document.getElementById('loadingModal');



    if (modal) {



        modal.style.display = 'none';



        document.body.style.overflow = '';



    }



}







// Hide modal when page loads (in case of refresh)



window.addEventListener('load', function() {



    hideLoadingModal();



    



    // If there's a success/error message modal, ensure it's visible



    const successModal = document.getElementById('app-modal');



    if (successModal) {



        // Make sure it's above the loading modal



        successModal.style.zIndex = '10001';



        



        // Auto-hide after 8 seconds (longer for important messages)



        setTimeout(function() {



            if (successModal) {



                successModal.style.transition = 'opacity 0.5s';



                successModal.style.opacity = '0';



                setTimeout(function() {



                    if (successModal) successModal.style.display = 'none';



                }, 500);



            }



        }, 8000);



    }



});







// File upload functionality



document.getElementById('consultation-document').addEventListener('change', function(e) {



    const file = e.target.files[0];



    const fileInfo = document.getElementById('file-info');



    const fileName = document.getElementById('file-name');



    



    if (file) {



        // Check file size (10MB max)



        const maxSize = 10 * 1024 * 1024;



        if (file.size > maxSize) {



            alert('File size exceeds 10MB limit. Please choose a smaller file.');



            e.target.value = '';



            return;



        }



        



        // Show file info



        fileName.textContent = file.name;



        fileInfo.style.display = 'block';



    } else {



        fileInfo.style.display = 'none';



    }



});







function clearFile() {



    const fileInput = document.getElementById('consultation-document');



    const fileInfo = document.getElementById('file-info');



    



    fileInput.value = '';



    fileInfo.style.display = 'none';



}







// Consultation Cards Pagination with Slide Transitions



document.addEventListener('DOMContentLoaded', function() {



    const slider = document.getElementById('consultations-slider');



    const prevBtn = document.getElementById('consultations-prev');



    const nextBtn = document.getElementById('consultations-next');



    const dotsContainer = document.getElementById('consultation-dots');



    



    if (!slider || !prevBtn || !nextBtn || !dotsContainer) return;



    



    const cards = slider.querySelectorAll('.card.consultation-card-grid');



    if (cards.length <= 1) {



        // Hide navigation if only one or no cards



        prevBtn.style.display = 'none';



        nextBtn.style.display = 'none';



        dotsContainer.style.display = 'none';



        return;



    }



    



    let currentIndex = 0;



    const totalCards = cards.length;



    



    // Create dots



    for (let i = 0; i < totalCards; i++) {



        const dot = document.createElement('button');



        dot.className = 'pagination-dot';



        dot.style.cssText = `



            width: 10px;



            height: 10px;



            border-radius: 50%;



            border: 2px solid #991b1b;



            background: ${i === 0 ? '#991b1b' : 'transparent'};



            cursor: pointer;



            transition: all 0.3s ease;



        `;



        dot.addEventListener('click', () => goToSlide(i));



        dotsContainer.appendChild(dot);



    }



    



    const dots = dotsContainer.querySelectorAll('.pagination-dot');



    



    function updateSlider() {



        // Update slider position



        slider.style.transform = `translateX(-${currentIndex * 100}%)`;



        



        // Update buttons



        prevBtn.style.opacity = currentIndex > 0 ? '1' : '0.3';



        prevBtn.style.pointerEvents = currentIndex > 0 ? 'auto' : 'none';



        



        nextBtn.style.opacity = currentIndex < totalCards - 1 ? '1' : '0.3';



        nextBtn.style.pointerEvents = currentIndex < totalCards - 1 ? 'auto' : 'none';



        



        // Update dots



        dots.forEach((dot, index) => {



            dot.style.background = index === currentIndex ? '#991b1b' : 'transparent';



        });



    }



    



    function goToSlide(index) {



        if (index < 0 || index >= totalCards) return;



        



        // Add smooth transition effect



        slider.style.transition = 'transform 0.5s ease-in-out';



        currentIndex = index;



        updateSlider();



    }



    



    function nextSlide() {



        if (currentIndex < totalCards - 1) {



            goToSlide(currentIndex + 1);



        }



    }



    



    function prevSlide() {



        if (currentIndex > 0) {



            goToSlide(currentIndex - 1);



        }



    }



    



    // Event listeners



    nextBtn.addEventListener('click', nextSlide);



    prevBtn.addEventListener('click', prevSlide);



    



    // Keyboard navigation



    document.addEventListener('keydown', (e) => {



        if (e.key === 'ArrowLeft') prevSlide();



        if (e.key === 'ArrowRight') nextSlide();



    });



    



    // Touch/swipe support for mobile



    let touchStartX = 0;



    let touchEndX = 0;



    



    slider.addEventListener('touchstart', (e) => {



        touchStartX = e.changedTouches[0].screenX;



    });



    



    slider.addEventListener('touchend', (e) => {



        touchEndX = e.changedTouches[0].screenX;



        handleSwipe();



    });



    



    function handleSwipe() {



        const swipeThreshold = 50;



        const diff = touchStartX - touchEndX;



        



        if (Math.abs(diff) > swipeThreshold) {



            if (diff > 0) {



                nextSlide(); // Swipe left, go to next



            } else {



                prevSlide(); // Swipe right, go to previous



            }



        }



    }



    



    // Auto-play optional (commented out - can be enabled if desired)



    // let autoPlayInterval = setInterval(nextSlide, 5000);



    // slider.addEventListener('mouseenter', () => clearInterval(autoPlayInterval));



    // slider.addEventListener('mouseleave', () => {



    //     autoPlayInterval = setInterval(nextSlide, 5000);



    // });



    



    // Initialize



    updateSlider();



});









</script>



<script>

document.addEventListener('DOMContentLoaded', function () {

    const el = document.getElementById('survey-device-token');

    if (!el) return;

    const key = 'pcms_guest_device_token';

    let token = localStorage.getItem(key);

    if (!token || !/^[A-Za-z0-9_-]{16,64}$/.test(token)) {

        try {

            const arr = new Uint8Array(24);

            window.crypto.getRandomValues(arr);

            token = Array.from(arr, b => b.toString(16).padStart(2, '0')).join('');

        } catch (e) {

            token = (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 48);

        }

        localStorage.setItem(key, token);

    }

    el.value = token;

});

</script>







</body>



</html>











