<?php
/**
 * Edit Submission Page
 * Allows citizens to edit their consultation or feedback using a secure token link.
 * URL: edit-submission.php?type=consultation&id=X&token=XXXX
 *      edit-submission.php?type=feedback&id=X&token=XXXX
 */
session_start();
require_once 'db.php';
require_once 'UTILS/security.php';

$type = trim($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

$error = '';
$success = '';
$record = null;
$expired = false;

// Validate params
if (!in_array($type, ['consultation', 'feedback']) || $id <= 0 || $token === '') {
    $error = 'Invalid edit link. Please use the link provided in your confirmation email.';
}

// Ensure edit_token columns exist
if (!$error) {
    if ($type === 'consultation') {
        $colCheck = $conn->query("SHOW COLUMNS FROM consultations LIKE 'edit_token'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE consultations ADD COLUMN edit_token VARCHAR(64) DEFAULT NULL");
            $conn->query("ALTER TABLE consultations ADD COLUMN edit_token_expires DATETIME DEFAULT NULL");
        }
    } else {
        $colCheck = $conn->query("SHOW COLUMNS FROM feedback LIKE 'edit_token'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE feedback ADD COLUMN edit_token VARCHAR(64) DEFAULT NULL");
            $conn->query("ALTER TABLE feedback ADD COLUMN edit_token_expires DATETIME DEFAULT NULL");
        }
    }
}

// Validate token and load record
if (!$error) {
    if ($type === 'consultation') {
        $stmt = $conn->prepare("SELECT id, title, description, user_name, user_email, status, edit_token, edit_token_expires FROM consultations WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, guest_name, guest_email, message, category, consultation_id, rating, status, edit_token, edit_token_expires FROM feedback WHERE id = ?");
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if (!$record) {
        $error = 'Submission not found.';
    } elseif (empty($record['edit_token']) || !hash_equals($record['edit_token'], $token)) {
        $error = 'Invalid or expired edit link.';
    } elseif (!empty($record['edit_token_expires']) && strtotime($record['edit_token_expires']) < time()) {
        $error = 'This edit link has expired. Edit links are valid for 7 days after submission.';
        $expired = true;
    } elseif (in_array(strtolower($record['status'] ?? ''), ['closed', 'responded', 'resolved'])) {
        $error = 'This submission has already been processed and can no longer be edited.';
    }
}

// Handle form submission (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $record) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } else {
        if ($type === 'consultation') {
            $new_topic = trim($_POST['topic'] ?? '');
            $new_description = trim($_POST['description'] ?? '');
            $new_name = trim($_POST['name'] ?? '');
            $new_email = trim($_POST['email'] ?? '');

            $errors = [];
            if (empty($new_topic)) $errors[] = 'Topic is required';
            if (empty($new_description)) $errors[] = 'Description is required';
            if (empty($new_name)) $errors[] = 'Name is required';
            if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';

            if (empty($errors)) {
                $upd = $conn->prepare("UPDATE consultations SET title = ?, description = ?, user_name = ?, user_email = ?, updated_at = NOW() WHERE id = ? AND edit_token = ?");
                $upd->bind_param('ssssis', $new_topic, $new_description, $new_name, $new_email, $id, $token);
                if ($upd->execute() && $upd->affected_rows >= 0) {
                    $success = 'Your consultation has been updated successfully!';
                    // Reload record
                    $reload = $conn->prepare("SELECT id, title, description, user_name, user_email, status, edit_token, edit_token_expires FROM consultations WHERE id = ?");
                    $reload->bind_param('i', $id);
                    $reload->execute();
                    $record = $reload->get_result()->fetch_assoc();
                    $reload->close();
                } else {
                    $error = 'Failed to update. Please try again.';
                }
                $upd->close();
            } else {
                $error = implode(', ', $errors);
            }
        } else {
            // Feedback edit
            $new_name = trim($_POST['name'] ?? '');
            $new_message = trim($_POST['message'] ?? '');
            $new_category = trim($_POST['category'] ?? '');

            $errors = [];
            if (empty($new_name)) $errors[] = 'Name is required';
            if (empty($new_message)) $errors[] = 'Message is required';

            if (empty($errors)) {
                $upd = $conn->prepare("UPDATE feedback SET guest_name = ?, message = ?, category = ?, updated_at = NOW() WHERE id = ? AND edit_token = ?");
                $upd->bind_param('sssis', $new_name, $new_message, $new_category, $id, $token);
                if ($upd->execute() && $upd->affected_rows >= 0) {
                    $success = 'Your feedback has been updated successfully!';
                    // Reload record
                    $reload = $conn->prepare("SELECT id, guest_name, guest_email, message, category, consultation_id, rating, status, edit_token, edit_token_expires FROM feedback WHERE id = ?");
                    $reload->bind_param('i', $id);
                    $reload->execute();
                    $record = $reload->get_result()->fetch_assoc();
                    $reload->close();
                } else {
                    $error = 'Failed to update. Please try again.';
                }
                $upd->close();
            } else {
                $error = implode(', ', $errors);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Submission - Valenzuela City</title>
    <link rel="icon" type="image/png" href="images/logo.webp">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="ASSETS/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <script>
        if (localStorage.getItem('portal-theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; box-sizing: border-box; margin: 0; }
        body { background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); color: #1f2937; line-height: 1.6; min-height: 100vh; }
        .container { max-width: 700px; margin: 0 auto; padding: 2rem 1rem; }
        header { background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 1rem 0; margin-bottom: 2rem; }
        header .inner { max-width: 700px; margin: 0 auto; padding: 0 1rem; display: flex; align-items: center; gap: 1rem; }
        header img { width: 44px; height: 44px; border-radius: 10px; }
        header h1 { font-size: 1.25rem; font-weight: 800; color: #991b1b; }
        header p { font-size: 0.75rem; color: #9ca3af; font-weight: 500; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 2rem; border: 1px solid #f0f0f0; }
        .alert { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; }
        .alert-error { background: #fee2e2; color: #7f1d1d; border-left: 4px solid #ef4444; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 700; color: #374151; font-size: 0.95rem; }
        .form-input, .form-textarea, .form-select {
            width: 100%; padding: 0.85rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px;
            font-size: 1rem; background: white; color: #1f2937; transition: all 0.2s;
        }
        .form-textarea { min-height: 140px; resize: vertical; font-family: inherit; }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none; border-color: #991b1b; box-shadow: 0 0 0 4px rgba(153,27,27,0.1);
        }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.85rem 2rem; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; font-size: 0.95rem; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #991b1b, #7f1d1d); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(153,27,27,0.3); }
        .btn-secondary { background: white; color: #374151; border: 2px solid #e5e7eb; }
        .btn-secondary:hover { border-color: #991b1b; color: #991b1b; }
        .meta { font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; }
        .badge { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-new { background: #dbeafe; color: #1e40af; }
        .toolbar { display: flex; gap: 0.5rem; margin-left: auto; }
        .toolbar-btn { width: 36px; height: 36px; border-radius: 8px; border: 1.5px solid #e5e7eb; background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #6b7280; font-size: 1rem; transition: all 0.2s; }
        .toolbar-btn:hover { border-color: #991b1b; color: #991b1b; background: #fef2f2; }
        .lang-btn { width: auto; padding: 0 10px; font-size: 0.7rem; font-weight: 800; }

        /* Dark mode */
        .dark body { background: #111827 !important; color: #e5e7eb !important; }
        .dark header { background: #1f2937 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important; }
        .dark header h1 { color: #fca5a5 !important; }
        .dark .card { background: #1f2937 !important; border-color: #374151 !important; color: #e5e7eb !important; }
        .dark .form-input, .dark .form-textarea, .dark .form-select { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        .dark .form-input:focus, .dark .form-textarea:focus { border-color: #fca5a5 !important; box-shadow: 0 0 0 4px rgba(252,165,165,0.15) !important; }
        .dark .form-label { color: #d1d5db !important; }
        .dark .btn-secondary { background: #374151 !important; color: #e5e7eb !important; border-color: #4b5563 !important; }
        .dark .toolbar-btn { background: #374151 !important; border-color: #4b5563 !important; color: #9ca3af !important; }
        .dark .toolbar-btn:hover { border-color: #fca5a5 !important; color: #fca5a5 !important; }
        .dark h2, .dark h3 { color: #f3f4f6 !important; }
        .dark .meta { color: #9ca3af !important; }
    </style>
</head>
<body>

<header>
    <div class="inner">
        <img src="images/logo.webp" alt="Logo">
        <div>
            <h1 data-i18n="edit_header">Edit Your Submission</h1>
            <p data-i18n="edit_header_sub">Valenzuela City Public Consultation Portal</p>
        </div>
        <div class="toolbar">
            <button onclick="toggleEditLang()" class="toolbar-btn lang-btn" id="edit-lang-toggle" title="Switch to Tagalog">EN</button>
            <button onclick="toggleEditTheme()" class="toolbar-btn" id="edit-theme-toggle">
                <i class="bi bi-moon-fill" id="edit-dark-icon"></i>
                <i class="bi bi-sun-fill" id="edit-light-icon" style="display:none;"></i>
            </button>
        </div>
    </div>
</header>

<div class="container">

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="bi bi-exclamation-triangle" style="margin-right:0.5rem;"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <div style="text-align:center; margin-top:1.5rem;">
        <a href="public-portal.php" class="btn btn-secondary" data-i18n="back_to_portal">
            <i class="bi bi-arrow-left" style="margin-right:0.5rem;"></i>Back to Portal
        </a>
    </div>

<?php elseif ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle" style="margin-right:0.5rem;"></i><?php echo htmlspecialchars($success); ?>
    </div>
    <div class="card">
        <?php if ($type === 'consultation'): ?>
            <h3 style="margin-bottom:1rem;" data-i18n="edit_updated_consultation">Updated Consultation</h3>
            <p class="meta"><strong data-i18n="edit_label_topic">Topic:</strong> <?php echo htmlspecialchars($record['title']); ?></p>
            <p class="meta"><strong data-i18n="edit_label_name">Name:</strong> <?php echo htmlspecialchars($record['user_name']); ?></p>
            <p class="meta"><strong>Email:</strong> <?php echo htmlspecialchars($record['user_email']); ?></p>
            <p class="meta"><strong data-i18n="edit_label_description">Description:</strong></p>
            <div style="background:#f9fafb; padding:1rem; border-radius:8px; margin-top:0.5rem; white-space:pre-wrap; font-size:0.9rem;"><?php echo htmlspecialchars($record['description']); ?></div>
        <?php else: ?>
            <h3 style="margin-bottom:1rem;" data-i18n="edit_updated_feedback">Updated Feedback</h3>
            <p class="meta"><strong data-i18n="edit_label_name">Name:</strong> <?php echo htmlspecialchars($record['guest_name']); ?></p>
            <p class="meta"><strong data-i18n="edit_label_type">Type:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $record['category'] ?? 'general'))); ?></p>
            <p class="meta"><strong data-i18n="edit_label_message">Message:</strong></p>
            <div style="background:#f9fafb; padding:1rem; border-radius:8px; margin-top:0.5rem; white-space:pre-wrap; font-size:0.9rem;"><?php echo htmlspecialchars($record['message']); ?></div>
        <?php endif; ?>
    </div>
    <div style="text-align:center; margin-top:1.5rem;">
        <a href="public-portal.php" class="btn btn-secondary" data-i18n="back_to_portal">
            <i class="bi bi-arrow-left" style="margin-right:0.5rem;"></i>Back to Portal
        </a>
    </div>

<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-pencil-square" style="margin-right:0.5rem;"></i>
        <span data-i18n="edit_info">You can edit your submission below. Changes will be saved immediately.</span>
    </div>

    <div class="card">
        <?php if ($type === 'consultation'): ?>
            <h3 style="margin-bottom:0.5rem;" data-i18n="edit_consultation_heading">Edit Consultation Request</h3>
            <p class="meta" style="margin-bottom:1.5rem;">
                <span data-i18n="edit_ref_id">Reference ID:</span> #<?php echo $record['id']; ?>
                &nbsp;
                <?php
                    $st = strtolower($record['status'] ?? 'draft');
                    $badgeClass = $st === 'active' ? 'badge-active' : 'badge-draft';
                ?>
                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st); ?></span>
            </p>

            <form method="POST">
                <?php outputCSRFField(); ?>
                <div class="form-group">
                    <label class="form-label" data-i18n="edit_label_topic">Topic / Title</label>
                    <input type="text" name="topic" class="form-input" required value="<?php echo htmlspecialchars($record['title']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="edit_label_name">Your Name</label>
                    <input type="text" name="name" class="form-input" required value="<?php echo htmlspecialchars($record['user_name']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required value="<?php echo htmlspecialchars($record['user_email']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="edit_label_description">Description / Details</label>
                    <textarea name="description" class="form-textarea" required><?php echo htmlspecialchars($record['description']); ?></textarea>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg" style="margin-right:0.5rem;"></i><span data-i18n="btn_save_changes">Save Changes</span>
                    </button>
                    <a href="public-portal.php" class="btn btn-secondary" data-i18n="btn_cancel">Cancel</a>
                </div>
            </form>

        <?php else: ?>
            <h3 style="margin-bottom:0.5rem;" data-i18n="edit_feedback_heading">Edit Feedback</h3>
            <p class="meta" style="margin-bottom:1.5rem;">
                <span data-i18n="edit_ref_id">Reference ID:</span> #<?php echo $record['id']; ?>
                &nbsp;
                <span class="badge badge-new"><?php echo htmlspecialchars($record['status'] ?? 'new'); ?></span>
            </p>

            <form method="POST">
                <?php outputCSRFField(); ?>
                <div class="form-group">
                    <label class="form-label" data-i18n="edit_label_name">Your Name</label>
                    <input type="text" name="name" class="form-input" required value="<?php echo htmlspecialchars($record['guest_name']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="edit_label_type">Feedback Type</label>
                    <select name="category" class="form-select">
                        <?php
                        $cats = ['general' => 'General Feedback', 'support' => 'Support/Agreement', 'concern' => 'Concern/Objection', 'suggestion' => 'Suggestion', 'question' => 'Question'];
                        foreach ($cats as $val => $label) {
                            $sel = ($record['category'] === $val) ? 'selected' : '';
                            echo "<option value=\"$val\" $sel>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="edit_label_message">Your Feedback Message</label>
                    <textarea name="message" class="form-textarea" required><?php echo htmlspecialchars($record['message']); ?></textarea>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg" style="margin-right:0.5rem;"></i><span data-i18n="btn_save_changes">Save Changes</span>
                    </button>
                    <a href="public-portal.php" class="btn btn-secondary" data-i18n="btn_cancel">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<script>
// Dark mode
function toggleEditTheme() {
    var html = document.documentElement;
    html.classList.toggle('dark');
    var isDark = html.classList.contains('dark');
    localStorage.setItem('portal-theme', isDark ? 'dark' : 'light');
    document.getElementById('edit-dark-icon').style.display = isDark ? 'none' : '';
    document.getElementById('edit-light-icon').style.display = isDark ? '' : 'none';
}
(function(){
    var isDark = localStorage.getItem('portal-theme') === 'dark';
    if (isDark) {
        document.getElementById('edit-dark-icon').style.display = 'none';
        document.getElementById('edit-light-icon').style.display = '';
    }
})();

// Language
var editLang = localStorage.getItem('portal-lang') || 'en';
var editTr = {
    tl: {
        edit_header: 'I-edit ang Iyong Isinumite',
        edit_header_sub: 'Portal ng Pampublikong Konsultasyon ng Lungsod ng Valenzuela',
        back_to_portal: 'Bumalik sa Portal',
        edit_info: 'Maaari mong i-edit ang iyong isinumite sa ibaba. Ang mga pagbabago ay agad na mase-save.',
        edit_consultation_heading: 'I-edit ang Kahilingan para sa Konsultasyon',
        edit_feedback_heading: 'I-edit ang Feedback',
        edit_ref_id: 'Reference ID:',
        edit_label_topic: 'Paksa / Pamagat',
        edit_label_name: 'Iyong Pangalan',
        edit_label_description: 'Paglalarawan / Detalye',
        edit_label_message: 'Ang Iyong Mensahe ng Feedback',
        edit_label_type: 'Uri ng Feedback',
        edit_updated_consultation: 'Na-update na Konsultasyon',
        edit_updated_feedback: 'Na-update na Feedback',
        btn_save_changes: 'I-save ang mga Pagbabago',
        btn_cancel: 'Kanselahin'
    },
    en: {
        edit_header: 'Edit Your Submission',
        edit_header_sub: 'Valenzuela City Public Consultation Portal',
        back_to_portal: 'Back to Portal',
        edit_info: 'You can edit your submission below. Changes will be saved immediately.',
        edit_consultation_heading: 'Edit Consultation Request',
        edit_feedback_heading: 'Edit Feedback',
        edit_ref_id: 'Reference ID:',
        edit_label_topic: 'Topic / Title',
        edit_label_name: 'Your Name',
        edit_label_description: 'Description / Details',
        edit_label_message: 'Your Feedback Message',
        edit_label_type: 'Feedback Type',
        edit_updated_consultation: 'Updated Consultation',
        edit_updated_feedback: 'Updated Feedback',
        btn_save_changes: 'Save Changes',
        btn_cancel: 'Cancel'
    }
};

function applyEditTranslations(lang) {
    var dict = editTr[lang];
    if (!dict) return;
    var els = document.querySelectorAll('[data-i18n]');
    for (var i = 0; i < els.length; i++) {
        var key = els[i].getAttribute('data-i18n');
        if (dict[key] !== undefined) els[i].textContent = dict[key];
    }
    document.title = lang === 'tl' ? 'I-edit ang Isinumite - Lungsod ng Valenzuela' : 'Edit Submission - Valenzuela City';
}

function toggleEditLang() {
    editLang = (editLang === 'en') ? 'tl' : 'en';
    localStorage.setItem('portal-lang', editLang);
    var btn = document.getElementById('edit-lang-toggle');
    btn.textContent = editLang === 'en' ? 'EN' : 'TL';
    btn.title = editLang === 'en' ? 'Switch to Tagalog' : 'Lumipat sa English';
    applyEditTranslations(editLang);
}

(function(){
    var btn = document.getElementById('edit-lang-toggle');
    if (editLang === 'tl') {
        btn.textContent = 'TL';
        btn.title = 'Lumipat sa English';
        applyEditTranslations('tl');
    }
})();
</script>

</body>
</html>
