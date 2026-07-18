<?php
// Predefined user accounts
$users = [
    [
        'id' => 1,
        'fullname' => 'Super Administrator',
        'email' => 'superadmin@valenzuela.gov.ph',
        'password' => password_hash('superadmin123', PASSWORD_DEFAULT),
        'role' => 'superadmin',
        'phone' => '(02) 8352-1000',
        'address' => 'Valenzuela City Hall'
    ],
    [
        'id' => 2,
        'fullname' => 'Administrator',
        'email' => 'admin@valenzuela.gov.ph',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'phone' => '(02) 8352-1001',
        'address' => 'Valenzuela City Hall'
    ],
    [
        'id' => 3,
        'fullname' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'password' => password_hash('citizen123', PASSWORD_DEFAULT),
        'role' => 'citizen',
        'phone' => '+63 912 345 6789',
        'address' => 'Brgy. Malinta, Valenzuela'
    ]
];

// Function to find user by email
function findUserByEmail($email) {
    global $users;
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
    return null;
}

// Function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Function to authenticate user
function authenticateUser($email, $password) {
    $user = findUserByEmail($email);
    if ($user && verifyPassword($password, $user['password'])) {
        // Remove password from user array before returning
        unset($user['password']);
        return $user;
    }
    return null;
}
?>
