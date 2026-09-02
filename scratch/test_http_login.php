<?php
$urls = [
    'http://localhost/CAP101/PC/login.php',
    'http://localhost/CAP101/PC/public/sign-in.php',
    'http://localhost/CAP101/PC/admin-side/login.php',
    'http://localhost/CAP101/PC/admin/login.php'
];

foreach ($urls as $url) {
    echo "========================================\n";
    echo "Testing HTTP GET & POST to: $url\n";
    
    // 1. GET page to fetch cookies & CSRF token
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "GET HTTP Code: $httpCode\n";
    if (!$html) {
        echo "Failed to fetch page!\n";
        continue;
    }
    
    // Extract CSRF token if present
    preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $matches);
    $csrfToken = $matches[1] ?? '';
    echo "Extracted CSRF Token: " . ($csrfToken ?: 'None found') . "\n";
    
    // 2. POST login credentials
    $postFields = [
        'email' => 'consultationmanagement2026@gmail.com',
        'password' => 'consultation2026',
        'csrf_token' => $csrfToken
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // don't follow redirect to see headers
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $postHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "POST HTTP Code: $postHttpCode\n";
    
    // Check headers and error messages in response
    if (strpos($response, 'Location:') !== false) {
        preg_match('/Location:\s*([^\r\n]+)/i', $response, $locMatches);
        echo "SUCCESS! Redirected to: " . ($locMatches[1] ?? '') . "\n";
    } else {
        echo "Response snippet:\n";
        // Extract error message if present in HTML
        if (preg_match('/<div[^>]*class="[^"]*alert[^"]*"[^>]*>(.*?)<\/div>/s', $response, $errMatches)) {
            echo "ALERT FOUND: " . trim(strip_tags($errMatches[1])) . "\n";
        } elseif (preg_match('/<p[^>]*class="[^"]*error[^"]*"[^>]*>(.*?)<\/p>/s', $response, $errMatches)) {
            echo "ERROR FOUND: " . trim(strip_tags($errMatches[1])) . "\n";
        } else {
            echo substr(strip_tags($response), 0, 300) . "\n";
        }
    }
}
