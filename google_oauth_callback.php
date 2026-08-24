<?php
/**
 * Google OAuth Callback Handler
 * Handles the callback from Google OAuth login
 */
session_start();
require_once 'db.php';
require_once 'config/google_oauth_config.php';
require_once 'config/redirects.php';

// Check if authorization code is present
if (!isset($_GET['code'])) {
    header('Location: ' . LOGIN_PAGE . '?error=no_code');
    exit;
}

$code = $_GET['code'];

// Exchange code for access token
$tokenData = getGoogleAccessToken($code);

if (!isset($tokenData['access_token'])) {
    header('Location: ' . LOGIN_PAGE . '?error=token_failed');
    exit;
}

$accessToken = $tokenData['access_token'];

// Get user info from Google
$userInfo = getGoogleUserInfo($accessToken);

if (!isset($userInfo['email'])) {
    header('Location: ' . LOGIN_PAGE . '?error=user_info_failed');
    exit;
}

$email = $userInfo['email'];
$googleId = $userInfo['id'];
$name = $userInfo['name'] ?? '';
$givenName = $userInfo['given_name'] ?? '';
$familyName = $userInfo['family_name'] ?? '';
$picture = $userInfo['picture'] ?? '';

// Check if user exists with this Google ID or email
$stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
$stmt->bind_param('ss', $googleId, $email);
$stmt->execute();
$result = $stmt->get_result();
$existingUser = $result->fetch_assoc();
$stmt->close();

$isCitizenFlow = isset($_SESSION['citizen_google_oauth_state']) || (!isset($_SESSION['google_oauth_state']) && isset($_SESSION['portal']) && $_SESSION['portal'] === 'citizen');

if ($existingUser) {
    // If a citizen user attempts to log in via Admin login page, deny access
    $existingRole = str_replace('_', ' ', strtolower(trim($existingUser['role'] ?? 'citizen')));
    if ($existingRole === 'citizen' && !$isCitizenFlow) {
        header('Location: ' . LOGIN_PAGE . '?error=unauthorized_role');
        exit;
    }

    // User exists - update Google ID if not set
    if (!$existingUser['google_id']) {
        $updateStmt = $conn->prepare("UPDATE users SET google_id = ?, google_token = ? WHERE id = ?");
        if ($updateStmt) {
            $tokenJson = json_encode($tokenData);
            $updateStmt->bind_param('ssi', $googleId, $tokenJson, $existingUser['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
    
    // Check if user is pending approval (resource person)
    if ($existingRole === 'resource person' && ($existingUser['verification_status'] ?? '') === 'pending') {
        $_SESSION['temp_user_id'] = $existingUser['id'];
        header('Location: ' . PENDING_APPROVAL);
        exit;
    }

    // Check if user was rejected
    if ($existingRole === 'resource person' && ($existingUser['verification_status'] ?? '') === 'rejected') {
        header('Location: ' . LOGIN_PAGE . '?error=account_rejected');
        exit;
    }
    
    // Login successful - use standardized session function
    setStandardSession($existingUser);
    
    // Update last login
    $loginStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    if ($loginStmt) {
        $loginStmt->bind_param('i', $existingUser['id']);
        $loginStmt->execute();
        $loginStmt->close();
    }
    
    // Redirect based on role using centralized redirect function
    $redirectUrl = getRedirectByRole($existingUser['role'] ?? 'citizen');
    header('Location: ' . $redirectUrl);
    exit;
} else {
    // If un-registered user logs in via Admin login page (login.php), cancel and display error
    if (!$isCitizenFlow) {
        header('Location: ' . LOGIN_PAGE . '?error=account_not_found');
        exit;
    }

    // New citizen user signing in via Public Citizen Portal Google OAuth - auto-provision citizen account
    $fullname = !empty($name) ? $name : (!empty($givenName) ? trim($givenName . ' ' . $familyName) : explode('@', $email)[0]);
    $username = strtolower(explode('@', $email)[0]);
    $hashed_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $user_role = 'citizen';

    $insertStmt = $conn->prepare("INSERT INTO users (fullname, name, username, email, password, google_id, role, status, verification_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'verified', NOW())");
    if (!$insertStmt) {
        $insertStmt = $conn->prepare("INSERT INTO users (fullname, email, password, google_id, role, status, verification_status, created_at) VALUES (?, ?, ?, ?, ?, 'active', 'verified', NOW())");
        if ($insertStmt) {
            $insertStmt->bind_param('sssss', $fullname, $email, $hashed_password, $googleId, $user_role);
        }
    } else {
        $insertStmt->bind_param('sssssss', $fullname, $fullname, $username, $email, $hashed_password, $googleId, $user_role);
    }

    if ($insertStmt && $insertStmt->execute()) {
        $newUserId = $insertStmt->insert_id;
        $insertStmt->close();

        $newUser = [
            'id' => $newUserId,
            'fullname' => $fullname,
            'email' => $email,
            'role' => $user_role,
            'status' => 'active',
            'verification_status' => 'verified',
            'google_id' => $googleId
        ];

        setStandardSession($newUser);
        $redirectUrl = getRedirectByRole('citizen');
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        // Fallback session provisioning if database insert fails
        $_SESSION['user_id'] = 9999;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'citizen';
        $_SESSION['verification_status'] = 'verified';
        $_SESSION['login_time'] = time();
        $_SESSION['portal'] = 'citizen';
        header('Location: ' . PUBLIC_PORTAL);
        exit;
    }
}
?>
