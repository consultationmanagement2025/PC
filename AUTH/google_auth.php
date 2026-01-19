<?php
session_start();
require '../db.php';

header('Content-Type: application/json');

// Your Google Client ID (from Google Cloud Console)
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$token = $_POST['token'] ?? '';

if ($action !== 'google_login' || !$token) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Verify the Google token using Google's tokeninfo endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

$payload = json_decode($response, true);

if (!$payload || !isset($payload['email'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid token data']);
    exit;
}

// Extract user information from token
$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';
$google_id = $payload['sub'] ?? '';

if (!$email || !$google_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid token data']);
    exit;
}

try {
    // Check if user exists by email
    $stmt = $conn->prepare("SELECT id, fullname, role FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // User exists - login
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'] ?? 'citizen';
        
        // Update last login
        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $user['id']);
        $update_stmt->execute();
        
        $redirect = ($user['role'] === 'admin') ? "system-template-full.php" : "user-portal.php";
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'role' => $user['role'] ?? 'citizen',
            'redirect' => $redirect
        ]);
    } else {
        // User doesn't exist - create new account
        $username = explode('@', $email)[0]; // Use email prefix as username
        $hashed_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // Random password
        $role = 'citizen'; // Default role
        
        $insert_stmt = $conn->prepare("
            INSERT INTO users (username, email, fullname, password, role, created_at, last_login)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if (!$insert_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $insert_stmt->bind_param("sssss", $username, $email, $name, $hashed_password, $role);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to create user: " . $insert_stmt->error);
        }
        
        $user_id = $insert_stmt->insert_id;
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['fullname'] = $name;
        $_SESSION['role'] = 'citizen';
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created and logged in successfully',
            'role' => 'citizen',
            'redirect' => 'user-portal.php'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
