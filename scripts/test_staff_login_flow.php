<?php
// Automated test: login and verify staff account on local server
$base = 'http://localhost/CAP101/PC/';
$loginUrl = $base . 'login.php';
$cookieFile = __DIR__ . '/cookiejar.txt';
$email = 'samplestaff01@gmail.com';
$password = 'staff12345';

function curl_get($url, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

function curl_post($url, $data, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

@unlink($cookieFile);

// 1) GET login page
list($html, $info) = curl_get($loginUrl, $cookieFile);
if (!$html) { echo "Failed to GET login page\n"; exit(1); }
// Extract csrf token
if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m)) {
    $csrf = $m[1];
    echo "CSRF token: $csrf\n";
} else {
    echo "CSRF token not found\n";
    exit(1);
}

// 2) POST credentials
$postData = [
    'email' => $email,
    'password' => $password,
    'csrf_token' => $csrf
];
list($postRes, $postInfo) = curl_post($loginUrl, $postData, $cookieFile);
echo "POST login HTTP code: " . $postInfo['http_code'] . "\n";

// 3) GET login page again to ensure verification modal present
list($html2, $info2) = curl_get($loginUrl, $cookieFile);
if (strpos($html2, 'Enter 6-digit code') === false) {
    echo "Verification modal not present after login POST\n";
    // print some debug
    // save HTML to file
    file_put_contents(__DIR__.'/debug_after_post.html', $html2);
} else {
    echo "Verification modal present\n";
}

// 4) Read PHP session file to find login_verification_code
// find PHPSESSID in cookie file
$cookieContents = file_get_contents($cookieFile);
if (!preg_match('/PHPSESSID\t([^\t\n\r ]+)/', $cookieContents, $m)) {
    // try Netscape cookie file format
    if (preg_match('/PHPSESSID\t([^\t\n\r ]+)\r?\n/', $cookieContents, $m)) {
        $phpsess = $m[1];
    } else {
        // parse as simple text
        if (preg_match('/PHPSESSID\s+([^\s]+)/', $cookieContents, $m)) {
            $phpsess = $m[1];
        } else {
            echo "Could not locate PHPSESSID in cookie file\n";
            exit(1);
        }
    }
} else {
    $phpsess = $m[1];
}
echo "PHPSESSID: $phpsess\n";

// Determine session save path
$savePath = ini_get('session.save_path');
if (!$savePath) { $savePath = sys_get_temp_dir(); }
// Windows may have value like "" or path
$savePath = str_replace('\\', '/', $savePath);
$sessionFile = $savePath . '/sess_' . $phpsess;
echo "Session file: $sessionFile\n";
if (!file_exists($sessionFile)) { echo "Session file not found\n"; exit(1); }
$sessionData = file_get_contents($sessionFile);
if (preg_match('/login_verification_code\|s:\d+:"(\d{6})";/', $sessionData, $m2)) {
    $code = $m2[1];
    echo "Found verification code in session: $code\n";
} else {
    echo "Verification code not found in session file\n";
    // dump session
    file_put_contents(__DIR__.'/session_dump.txt', $sessionData);
    exit(1);
}

// 5) Extract csrf token again from html2
if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html2, $m3)) {
    $csrf2 = $m3[1];
    echo "CSRF token for verify: $csrf2\n";
} else {
    echo "CSRF token not found in modal page\n";
    exit(1);
}

// 6) POST verification code
$verifyPost = [
    'email_verification_code' => $code,
    'verify_email_code' => '1',
    'csrf_token' => $csrf2
];
list($verifyRes, $verifyInfo) = curl_post($loginUrl, $verifyPost, $cookieFile);
echo "Verify POST HTTP code: " . $verifyInfo['http_code'] . "\n";
// Save result for inspection
file_put_contents(__DIR__.'/verify_result.html', $verifyRes);

// Check if final page contains system-template-full marker
if (strpos($verifyRes, 'PCMS') !== false && strpos($verifyRes, 'system-template-full') !== false) {
    echo "Likely landed on system template\n";
} else {
    echo "Final page may not be system template; saving debug files.\n";
}

echo "Done. Debug files: debug_after_post.html, session_dump.txt (if any), verify_result.html\n";

?>