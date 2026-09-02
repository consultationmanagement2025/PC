<?php
mysqli_report(MYSQLI_REPORT_OFF);

$passwordsToTry = ['%wE!*-vMg4GCbB#3', 'e3sEe1sf!g6+uoak', 'consultation2025', 'admin', ''];
$usersToTry = ['root', 'cons_pc_db', 'consu2396_cons_pc_db', 'consu2396_pc_db'];

$connectedUser = null;
$connectedPass = null;

foreach ($usersToTry as $u) {
    foreach ($passwordsToTry as $p) {
        try {
            $c = @new mysqli('localhost', $u, $p);
            if (!$c->connect_error) {
                $connectedUser = $u;
                $connectedPass = $p;
                echo "Connected as '$u'\n";
                break 2;
            }
        } catch (Throwable $t) {}
    }
}

if (!$connectedUser) {
    die("Could not connect to MySQL");
}

$email = 'consultationmanagement2026@gmail.com';
$password = 'consultation2026';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$fullname = "Super Administrator";
$username = "consultationmanagement2026";
$role = "super admin";
$status = "active";

$dbs = ['pc_db', 'cons_pc_db'];

foreach ($dbs as $dbName) {
    echo "Syncing $dbName...\n";
    $cDb = @new mysqli('localhost', $connectedUser, $connectedPass, $dbName);
    if (!$cDb || $cDb->connect_error) {
        echo "Could not connect to $dbName: " . ($cDb ? $cDb->connect_error : 'error') . "\n";
        continue;
    }
    
    // Check if user exists
    $stmt = $cDb->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        echo "  Updating existing user ID {$row['id']} in $dbName...\n";
        $uStmt = $cDb->prepare("UPDATE users SET password = ?, role = 'super admin', status = 'active', fullname = ?, username = ?, email = ? WHERE id = ?");
        $uStmt->bind_param("ssssi", $hashedPassword, $fullname, $username, $email, $row['id']);
        if ($uStmt->execute()) {
            echo "  SUCCESS updating $dbName\n";
        } else {
            echo "  ERROR in $dbName: " . $cDb->error . "\n";
        }
    } else {
        echo "  Inserting user into $dbName...\n";
        $iStmt = $cDb->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $iStmt->bind_param("ssssss", $fullname, $username, $email, $hashedPassword, $role, $status);
        if ($iStmt->execute()) {
            echo "  SUCCESS inserting into $dbName, ID: " . $cDb->insert_id . "\n";
        } else {
            echo "  ERROR inserting into $dbName: " . $cDb->error . "\n";
        }
    }
    
    // Also sync consultationmanagement2025@gmail.com
    $email25 = 'consultationmanagement2025@gmail.com';
    $username25 = 'consultationmanagement2025';
    $stmt25 = $cDb->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt25->bind_param("ss", $email25, $username25);
    $stmt25->execute();
    $res25 = $stmt25->get_result();
    if ($row25 = $res25->fetch_assoc()) {
        $uStmt25 = $cDb->prepare("UPDATE users SET password = ?, role = 'super admin', status = 'active' WHERE id = ?");
        $uStmt25->bind_param("si", $hashedPassword, $row25['id']);
        $uStmt25->execute();
    } else {
        $iStmt25 = $cDb->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $iStmt25->bind_param("ssssss", $fullname, $username25, $email25, $hashedPassword, $role, $status);
        $iStmt25->execute();
    }
}

echo "Done syncing all databases!\n";
