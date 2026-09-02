<?php
require_once __DIR__ . '/../db.php';

$passwordsToTry = ['%wE!*-vMg4GCbB#3', 'e3sEe1sf!g6+uoak', 'consultation2025', 'admin', ''];
$usersToTry = ['cons_pc_db', 'consu2396_cons_pc_db', 'consu2396_pc_db', 'root'];

foreach ($usersToTry as $u) {
    foreach ($passwordsToTry as $p) {
        $c = @new mysqli('localhost', $u, $p);
        if ($c && !$c->connect_error) {
            echo "Connected to MySQL with user '$u'!\n";
            $res = $c->query('SHOW DATABASES');
            while ($row = $res->fetch_row()) {
                $dbName = $row[0];
                echo "- $dbName\n";
                $dbConn = @new mysqli('localhost', $u, $p, $dbName);
                if ($dbConn && !$dbConn->connect_error) {
                    $tRes = @$dbConn->query("SHOW TABLES LIKE 'users'");
                    if ($tRes && $tRes->num_rows > 0) {
                        echo "  [Has 'users' table! Listing accounts in $dbName:]\n";
                        $uRes = $dbConn->query("SELECT id, fullname, username, email, role, status, password FROM users");
                        while ($usr = $uRes->fetch_assoc()) {
                            $match2026 = password_verify('consultation2026', $usr['password']) ? 'YES' : 'NO';
                            echo "    DB: $dbName | ID: {$usr['id']} | Email: {$usr['email']} | User: {$usr['username']} | Role: {$usr['role']} | PassMatch2026: $match2026\n";
                        }
                    }
                }
            }
            break 2;
        }
    }
}
