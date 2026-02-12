<?php
/**
 * Two-Factor Authentication (2FA) using TOTP
 * Supports Google Authenticator, Authy, Microsoft Authenticator, etc.
 */

require_once __DIR__ . '/../db.php';

// ==================== TOTP IMPLEMENTATION ====================
/**
 * Generate a new TOTP secret
 * Returns base32-encoded secret suitable for QR code scanning
 */
function generateTOTPSecret() {
    $bytes = random_bytes(20);
    return base32_encode($bytes);
}

/**
 * Base32 encode (for TOTP secrets)
 */
function base32_encode($data) {
    $base32_alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    if (empty($data)) return '';
    
    $binary = '';
    foreach (str_split($data) as $char) {
        $binary .= str_pad(base_convert(ord($char), 10, 2), 8, '0', STR_PAD_LEFT);
    }
    $binary = str_pad($binary, ceil(strlen($binary) / 5) * 5, '0', STR_PAD_RIGHT);
    $base32 = '';
    foreach (str_split($binary, 5) as $chunk) {
        $base32 .= $base32_alphabet[base_convert($chunk, 2, 10)];
    }
    return $base32;
}

/**
 * Base32 decode (for verification)
 */
function base32_decode($data) {
    $base32_alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper($data);
    $binary = '';
    foreach (str_split($data) as $char) {
        $char_pos = strpos($base32_alphabet, $char);
        if ($char_pos === false) return false;
        $binary .= str_pad(base_convert($char_pos, 10, 2), 5, '0', STR_PAD_LEFT);
    }
    $binary = substr($binary, 0, strlen($binary) - (strlen($binary) % 8));
    $output = '';
    foreach (str_split($binary, 8) as $chunk) {
        $output .= chr(base_convert($chunk, 2, 10));
    }
    return $output;
}

/**
 * Generate TOTP code from secret
 * @param string $secret Base32-encoded secret
 * @param int $time Unix timestamp (default: current time)
 * @return int 6-digit TOTP code
 */
function generateTOTPCode($secret, $time = null) {
    if ($time === null) {
        $time = time();
    }
    
    $secret_bin = base32_decode($secret);
    if ($secret_bin === false) return false;
    
    $time_step = 30; // TOTP uses 30-second time steps
    $time_counter = intval($time / $time_step);
    
    // Create binary counter
    $time_bin = '';
    for ($i = 7; $i >= 0; $i--) {
        $time_bin = chr(($time_counter >> ($i * 8)) & 0xFF) . $time_bin;
    }
    
    // Generate HMAC-SHA1
    $hmac = hash_hmac('SHA1', $time_bin, $secret_bin, true);
    $offset = ord($hmac[19]) & 0x0F;
    $hash_part = substr($hmac, $offset, 4);
    
    $value = (ord($hash_part[0]) & 0x7F) << 24 |
             (ord($hash_part[1]) & 0xFF) << 16 |
             (ord($hash_part[2]) & 0xFF) << 8 |
             (ord($hash_part[3]) & 0xFF);
    
    return $value % 1000000;
}

/**
 * Verify TOTP code with time window (allows for clock skew)
 * @param string $secret Base32-encoded secret
 * @param int $code 6-digit code entered by user
 * @param int $window_size Number of 30-second steps to check (default: 1 = ±30 seconds)
 */
function verifyTOTPCode($secret, $code, $window_size = 1) {
    $time = time();
    $time_step = 30;
    
    for ($i = -$window_size; $i <= $window_size; $i++) {
        $test_time = $time + ($i * $time_step);
        $generated_code = generateTOTPCode($secret, $test_time);
        
        if ($generated_code === intval($code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate QR code URL for easy scanning
 * Uses qr-server.com (no dependencies needed)
 */
function generateQRCodeURL($secret, $user_email, $issuer = 'Valenzuela PCMP') {
    $label = urlencode("$issuer ($user_email)");
    $data = "otpauth://totp/$label?secret=$secret&issuer=" . urlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
    return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($data);
}

// ==================== 2FA DATABASE MANAGEMENT ====================
/**
 * Initialize 2FA table
 */
function initialize2FATable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS two_factor_auth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        totp_secret VARCHAR(32) NOT NULL,
        backup_codes VARCHAR(500),
        enabled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        error_log('Failed to create two_factor_auth table: ' . $conn->error);
    }
}

/**
 * Enable 2FA for user
 */
function enable2FA($user_id, $totp_secret) {
    global $conn;
    initialize2FATable();
    
    // Generate 10 backup codes
    $backup_codes = [];
    for ($i = 0; $i < 10; $i++) {
        $backup_codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    $backup_codes_str = implode(',', $backup_codes);
    
    $stmt = $conn->prepare("INSERT INTO two_factor_auth (user_id, totp_secret, backup_codes, enabled) 
                           VALUES (?, ?, ?, 1)
                           ON DUPLICATE KEY UPDATE 
                           totp_secret = VALUES(totp_secret), 
                           backup_codes = VALUES(backup_codes),
                           enabled = 1");
    $stmt->bind_param("iss", $user_id, $totp_secret, $backup_codes_str);
    $stmt->execute();
    $stmt->close();
    
    return $backup_codes;
}

/**
 * Disable 2FA for user
 */
function disable2FA($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE two_factor_auth SET enabled = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get 2FA status for user
 */
function get2FAStatus($user_id) {
    global $conn;
    initialize2FATable();
    
    $stmt = $conn->prepare("SELECT enabled, totp_secret FROM two_factor_auth WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'enabled' => $row['enabled'] == 1,
            'has_secret' => !empty($row['totp_secret'])
        ];
    }
    $stmt->close();
    return ['enabled' => false, 'has_secret' => false];
}

/**
 * Verify TOTP code or backup code
 */
function verify2FACode($user_id, $code) {
    global $conn;
    initialize2FATable();
    
    $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    
    // Check if it's a valid TOTP code
    if (strlen($code) === 6) {
        $stmt = $conn->prepare("SELECT totp_secret FROM two_factor_auth WHERE user_id = ? AND enabled = 1 LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (verifyTOTPCode($row['totp_secret'], $code)) {
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
    }
    
    // Check if it's a backup code
    if (strlen($code) === 8) {
        $stmt = $conn->prepare("SELECT backup_codes FROM two_factor_auth WHERE user_id = ? AND enabled = 1 LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $backup_codes = explode(',', $row['backup_codes']);
            
            if (in_array($code, $backup_codes)) {
                // Remove used backup code
                $backup_codes = array_filter($backup_codes, function($c) use ($code) {
                    return $c !== $code;
                });
                $new_backup_codes = implode(',', $backup_codes);
                
                $update_stmt = $conn->prepare("UPDATE two_factor_auth SET backup_codes = ? WHERE user_id = ?");
                $update_stmt->bind_param("si", $new_backup_codes, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
    }
    
    return false;
}

?>
