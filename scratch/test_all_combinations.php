<?php
$emailsToTest = [
    'cons2026',
    'cons2026@gmail.com',
    'consultation2026',
    'consultation2026@gmail.com',
    'consultationmanagement2026',
    'consultationmanagement2026@gmail.com',
    'superadmin',
    'superadmin2026'
];

$passwordsToTest = [
    'cons2026',
    'consultation2026'
];

$url = 'http://localhost/CAP101/PC/login.php';

echo "Testing all login credential variations against $url...\n";

foreach ($emailsToTest as $email) {
    foreach ($passwordsToTest as $pass) {
        // GET CSRF
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);
        
        preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $matches);
        $csrfToken = $matches[1] ?? '';
        
        $postFields = [
            'email' => $email,
            'password' => $pass,
            'csrf_token' => $csrfToken
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $redirect = '';
        if (preg_match('/Location:\s*([^\r\n]+)/i', $response, $locMatches)) {
            $redirect = trim($locMatches[1]);
        }
        
        $statusStr = ($redirect && strpos($redirect, 'system-template-full.php') !== false) ? "SUCCESS (Redirects to $redirect)" : "FAILED (HTTP $httpCode)";
        
        echo sprintf("User: %-38s | Pass: %-18s | Result: %s\n", "'$email'", "'$pass'", $statusStr);
    }
}
