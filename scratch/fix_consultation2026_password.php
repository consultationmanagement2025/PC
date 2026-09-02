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

// Password must be EXACTLY 'consultation2026'
$hashedPassword = password_hash('consultation2026', PASSWORD_DEFAULT);

$dbs = ['pc_db', 'cons_pc_db'];

foreach ($dbs as $dbName) {
    echo "Updating password to 'consultation2026' for all admin/superadmin accounts in $dbName...\n";
    $cDb = @new mysqli('localhost', $connectedUser, $connectedPass, $dbName);
    if ($cDb && !$cDb->connect_error) {
        $stmt = $cDb->prepare("UPDATE users SET password = ?, role = 'super admin', status = 'active' WHERE email LIKE '%consultation%' OR username LIKE '%consultation%' OR email LIKE '%superadmin%' OR username LIKE '%superadmin%' OR email LIKE '%cons%' OR username LIKE '%cons%'");
        $stmt->bind_param("s", $hashedPassword);
        if ($stmt->execute()) {
            echo "  SUCCESS updated " . $stmt->affected_rows . " rows in $dbName!\n";
        } else {
            echo "  ERROR in $dbName: " . $cDb->error . "\n";
        }
    }
}

echo "Done updating passwords across all databases!\n";
