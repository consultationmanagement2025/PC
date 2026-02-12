<?php
/**
 * Security Utilities Module
 * Handles CSRF tokens, rate limiting, input validation, and password policies
 */

require_once __DIR__ . '/../db.php';

// ==================== CSRF TOKEN MANAGEMENT ====================
/**
 * Generate CSRF token and store in session
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST request
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * Output CSRF token as hidden input field
 */
function outputCSRFField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// ==================== RATE LIMITING & BRUTE FORCE PROTECTION ====================
/**
 * Check if IP/user is rate limited
 * @param string $identifier IP address or username
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_seconds Time window for attempts
 * @return array ['limited' => bool, 'remaining' => int, 'lockout_until' => timestamp or null]
 */
function checkRateLimit($identifier, $max_attempts = 5, $window_seconds = 900) {
    global $conn;
    
    // Initialize rate_limits table if needed
    initializeRateLimitsTable();
    
    $current_time = time();
    $window_start = $current_time - $window_seconds;
    
    // Clean old entries
    $conn->query("DELETE FROM rate_limits WHERE window_expires < " . $current_time);
    
    // Check if currently locked out
    $stmt = $conn->prepare("SELECT locked_until FROM rate_limits WHERE identifier = ? AND locked_until > ? LIMIT 1");
    $stmt->bind_param("si", $identifier, $current_time);
    $stmt->execute();
    $lock_result = $stmt->get_result();
    
    if ($lock_result->num_rows > 0) {
        $row = $lock_result->fetch_assoc();
        return [
            'limited' => true,
            'remaining' => 0,
            'locked_until' => $row['locked_until']
        ];
    }
    $stmt->close();
    
    // Count attempts in window
    $stmt = $conn->prepare("SELECT attempt_count FROM rate_limits WHERE identifier = ? AND window_start = ? LIMIT 1");
    $stmt->bind_param("si", $identifier, $window_start);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attempt_count = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $attempt_count = $row['attempt_count'];
    }
    $stmt->close();
    
    $remaining = max(0, $max_attempts - $attempt_count);
    $is_limited = $attempt_count >= $max_attempts;
    
    return [
        'limited' => $is_limited,
        'remaining' => $remaining,
        'locked_until' => null
    ];
}

/**
 * Record failed login attempt and apply lockout if needed
 */
function recordFailedAttempt($identifier, $lockout_duration = 900) {
    global $conn;
    
    initializeRateLimitsTable();
    
    $current_time = time();
    $window_start = $current_time - 900; // 15 min window
    
    // Get or create rate limit record
    $stmt = $conn->prepare("SELECT id, attempt_count FROM rate_limits WHERE identifier = ? AND window_start = ? LIMIT 1");
    $stmt->bind_param("si", $identifier, $window_start);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Increment existing
        $row = $result->fetch_assoc();
        $new_count = $row['attempt_count'] + 1;
        $stmt = $conn->prepare("UPDATE rate_limits SET attempt_count = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_count, $row['id']);
        $stmt->execute();
        $stmt->close();
        
        // If max attempts reached, apply lockout
        if ($new_count >= 5) {
            $locked_until = $current_time + $lockout_duration;
            $stmt = $conn->prepare("UPDATE rate_limits SET locked_until = ? WHERE id = ?");
            $stmt->bind_param("ii", $locked_until, $row['id']);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Create new entry
        $window_expires = $current_time + 900;
        $stmt = $conn->prepare("INSERT INTO rate_limits (identifier, window_start, window_expires, attempt_count) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sii", $identifier, $window_start, $window_expires);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Clear rate limit record (on successful login)
 */
function clearRateLimit($identifier) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM rate_limits WHERE identifier = ?");
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $stmt->close();
}

/**
 * Initialize rate_limits table
 */
function initializeRateLimitsTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255) NOT NULL UNIQUE,
        window_start INT NOT NULL,
        window_expires INT NOT NULL,
        attempt_count INT DEFAULT 1,
        locked_until INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_identifier (identifier),
        INDEX idx_locked (locked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        error_log('Failed to create rate_limits table: ' . $conn->error);
    }
}

// ==================== PASSWORD POLICY ENFORCEMENT ====================
/**
 * Validate password against policy
 * Policy: min 12 chars, uppercase, lowercase, number, symbol
 */
function validatePasswordPolicy($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&* etc)';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Check if password has been used before (password history)
 */
function checkPasswordHistory($user_id, $new_password, $history_count = 5) {
    global $conn;
    
    initializePasswordHistoryTable();
    
    // Get last N passwords for this user
    $stmt = $conn->prepare("SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $history_count);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (password_verify($new_password, $row['password_hash'])) {
            return false; // Password was used before
        }
    }
    $stmt->close();
    return true; // Password is new
}

/**
 * Add password to history when changed
 */
function recordPasswordChange($user_id, $old_password_hash) {
    global $conn;
    
    initializePasswordHistoryTable();
    
    $stmt = $conn->prepare("INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $old_password_hash);
    $stmt->execute();
    $stmt->close();
}

/**
 * Initialize password_history table
 */
function initializePasswordHistoryTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS password_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        error_log('Failed to create password_history table: ' . $conn->error);
    }
}

// ==================== INPUT VALIDATION & SANITIZATION ====================
/**
 * Sanitize string input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format and return sanitized version
 */
function validateEmail($email) {
    $email = trim($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    return false;
}

/**
 * Validate phone number format (Philippine)
 */
function validatePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (preg_match('/^(\+63|0)?[0-9]{10}$/', $phone)) {
        return $phone;
    }
    return false;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $max_size = 5242880, $allowed_extensions = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx']) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error: ' . $file['error'];
        return ['valid' => false, 'errors' => $errors];
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds ' . ($max_size / 1024 / 1024) . 'MB limit';
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        $errors[] = 'File type not allowed. Allowed: ' . implode(', ', $allowed_extensions);
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!in_array($mime, $allowed_mimes)) {
        $errors[] = 'Invalid file type (MIME type: ' . $mime . ')';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'mime' => $mime,
        'extension' => $ext
    ];
}

// ==================== SESSION SECURITY ====================
/**
 * Check session timeout and invalidate if expired
 * @param int $timeout_seconds Default 30 minutes
 */
function checkSessionTimeout($timeout_seconds = 1800) {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout_seconds) {
            session_unset();
            session_destroy();
            return false; // Session expired
        }
    }
    $_SESSION['last_activity'] = time();
    return true; // Session valid
}

/**
 * Require admin role and valid session
 */
function requireAdminRole() {
    if (!checkSessionTimeout()) {
        header('Location: login.php?error=Session%20expired');
        exit;
    }
    
    $current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
    if ($current_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
}

?>
