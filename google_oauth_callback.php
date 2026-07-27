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

if ($existingUser) {
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
    if ($existingUser['role'] === 'resource person' && $existingUser['verification_status'] === 'pending') {
        $_SESSION['temp_user_id'] = $existingUser['id'];
        header('Location: ' . PENDING_APPROVAL);
        exit;
    }

    // Check if user was rejected
    if ($existingUser['role'] === 'resource person' && $existingUser['verification_status'] === 'rejected') {
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
    // New user - store in session for registration
    $_SESSION['google_user'] = [
        'google_id' => $googleId,
        'email' => $email,
        'name' => $name,
        'given_name' => $givenName,
        'family_name' => $familyName,
        'picture' => $picture,
        'token' => $tokenData
    ];
    
    // Redirect to registration page
    header('Location: register_resource_person.php');
    exit;
}
?>
