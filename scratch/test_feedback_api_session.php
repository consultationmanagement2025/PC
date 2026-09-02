<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super admin';
$_SESSION['fullname'] = 'Super Administrator';

$ch = curl_init('http://localhost/CAP101/PC/API/feedback_api.php?action=list&limit=200&offset=0');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
$resp = curl_exec($ch);
curl_close($ch);

echo "Response from feedback_api.php:\n";
echo substr($resp, 0, 500) . "\n";
