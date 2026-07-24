<?php
require_once __DIR__ . '/../../db.php';

echo "=== TEST ADMIN LOGIN ===\n\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "Testing login for: " . $email . "\n\n";
    
    // Find user
    $stmt = $conn->prepare("SELECT id, fullname, password, role, email FROM users WHERE email=?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            echo "User found:\n";
            echo "ID: " . $user['id'] . "\n";
            echo "Name: " . $user['fullname'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Role: " . $user['role'] . "\n";
                        echo "Password Hash: " . substr($user['password'], 0, 20) . "...\n\n";
            
            // Test password verification
            if (password_verify($password, $user['password'])) {
                echo "✅ PASSWORD VERIFICATION SUCCESSFUL!\n";
                echo "Login would work for this user.\n";
            } else {
                echo "❌ PASSWORD VERIFICATION FAILED!\n";
                echo "The password provided does not match the stored hash.\n\n";
                
                // Test with common passwords
                $common_passwords = ['admin', 'password', '123456', 'admin123', 'Valenzuela2025'];
                echo "Testing common passwords:\n";
                foreach ($common_passwords as $test_pass) {
                    if (password_verify($test_pass, $user['password'])) {
                        echo "✅ Found matching password: " . $test_pass . "\n";
                    }
                }
            }
        } else {
            echo "❌ USER NOT FOUND!\n";
            echo "No user exists with email: " . $email . "\n";
        }
        $stmt->close();
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Please POST email and password to test login.\n";
    echo "Usage: curl -X POST -d 'email=admin@test.com&password=yourpassword' test_admin_login.php\n";
}

$conn->close();
?>
