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
    die("Could not connect to MySQL\n");
}

$hash = password_hash('cons2026', PASSWORD_DEFAULT);

$accountVariations = [
    ['fullname' => 'Super Administrator', 'username' => 'cons2026', 'email' => 'cons2026@gmail.com'],
    ['fullname' => 'Super Administrator', 'username' => 'consultation2026', 'email' => 'consultation2026@gmail.com'],
    ['fullname' => 'Super Administrator', 'username' => 'consultationmanagement2026', 'email' => 'consultationmanagement2026@gmail.com'],
    ['fullname' => 'Super Administrator', 'username' => 'superadmin2026', 'email' => 'superadmin2026@gmail.com'],
    ['fullname' => 'Super Administrator', 'username' => 'superadmin', 'email' => 'superadmin@gmail.com'],
];

$dbs = ['pc_db', 'cons_pc_db'];

foreach ($dbs as $dbName) {
    echo "========================================\n";
    echo "Seeding/Updating superadmin accounts in $dbName...\n";
    $cDb = @new mysqli('localhost', $connectedUser, $connectedPass, $dbName);
    if (!$cDb || $cDb->connect_error) {
        echo "Could not connect to $dbName: " . ($cDb ? $cDb->connect_error : 'error') . "\n";
        continue;
    }

    foreach ($accountVariations as $acc) {
        $fn = $acc['fullname'];
        $un = $acc['username'];
        $em = $acc['email'];

        // Check by email or username
        $stmt = $cDb->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)");
        $stmt->bind_param("ss", $em, $un);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            echo "  Updating existing user ID {$row['id']} ($em / $un) in $dbName...\n";
            $uStmt = $cDb->prepare("UPDATE users SET password = ?, role = 'super admin', status = 'active', fullname = ?, username = ?, email = ? WHERE id = ?");
            $uStmt->bind_param("ssssi", $hash, $fn, $un, $em, $row['id']);
            if ($uStmt->execute()) {
                echo "    SUCCESS update $em\n";
            } else {
                echo "    ERROR update $em: " . $cDb->error . "\n";
            }
        } else {
            echo "  Inserting new user ($em / $un) into $dbName...\n";
            $iStmt = $cDb->prepare("INSERT INTO users (fullname, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'super admin', 'active', NOW())");
            $iStmt->bind_param("ssss", $fn, $un, $em, $hash);
            if ($iStmt->execute()) {
                echo "    SUCCESS insert $em (ID: {$cDb->insert_id})\n";
            } else {
                echo "    ERROR insert $em: " . $cDb->error . "\n";
            }
        }
    }
}

echo "Done seeding all superadmin variations!\n";
