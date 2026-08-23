<?php
require_once __DIR__ . '/../db.php';

$res1 = $conn->query("SELECT * FROM users WHERE fullname LIKE '%Aljan%' OR username LIKE '%Aljan%'");
echo "USERS WITH ALJAN:\n";
if ($res1 && $res1->num_rows > 0) {
    while ($r = $res1->fetch_assoc()) print_r($r);
} else {
    echo "None.\n";
}

$res2 = $conn->query("SHOW TABLES LIKE 'resource_person_applications'");
if ($res2 && $res2->num_rows > 0) {
    $res3 = $conn->query("SELECT * FROM resource_person_applications WHERE fullname LIKE '%Aljan%'");
    echo "RESOURCE PERSON APPS WITH ALJAN:\n";
    if ($res3 && $res3->num_rows > 0) {
        while ($r = $res3->fetch_assoc()) print_r($r);
    } else {
        echo "None.\n";
    }
}
