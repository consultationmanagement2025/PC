<?php
$path = __DIR__ . '/../login.php';
$lines = file($path);
foreach ($lines as $i => $line) {
    $num = $i + 1;
    if (strpos($line, 'password_verify(') !== false || strpos($line, "login_verification_code") !== false || strpos($line, "show_email_verification") !== false || strpos($line, "Verify & Login") !== false) {
        echo str_pad($num, 4, ' ', STR_PAD_LEFT) . ': ' . rtrim($line) . "\n";
    }
}
?>