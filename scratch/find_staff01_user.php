<?php
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT id, fullname, email, role, expertise_areas, department FROM users WHERE email LIKE '%staff01@gmail.com%' OR email LIKE '%staff%'");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
