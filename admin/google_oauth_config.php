<?php
/**
 * Google OAuth Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to Google Cloud Console (console.cloud.google.com)
 * 2. Create a new project or select existing one
 * 3. Enable Google+ API and Google OAuth2 API
 * 4. Create OAuth 2.0 credentials (Web application)
 * 5. Add authorized redirect URI: http://localhost/CAP101/PC/google_oauth_callback.php
 * 6. Copy Client ID and Client Secret below
 */

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', function_exists('app_env') ? app_env('GOOGLE_CLIENT_ID', '') : (getenv('GOOGLE_CLIENT_ID') ?: ''));
define('GOOGLE_CLIENT_SECRET', function_exists('app_env') ? app_env('GOOGLE_CLIENT_SECRET', '') : (getenv('GOOGLE_CLIENT_SECRET') ?: ''));
define('GOOGLE_REDIRECT_URI', function_exists('app_env') ? app_env('GOOGLE_REDIRECT_URI', 'http://localhost/CAP101/PC/google_oauth_callback.php') : (getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/CAP101/PC/google_oauth_callback.php'));

// Google OAuth endpoints
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USER_INFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');

// Scopes needed
define('GOOGLE_SCOPES', 'openid email profile');

/**
 * Generate Google OAuth login URL
 */
function getGoogleAuthUrl($state = null) {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => GOOGLE_SCOPES,
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ];
    
    if ($state) {
        $params['state'] = $state;
    }
    
    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 */
function getGoogleAccessToken($code) {
    $params = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ];
    
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Get user info from Google
 */
function getGoogleUserInfo($accessToken) {
    $ch = curl_init(GOOGLE_USER_INFO_URL . '?access_token=' . $accessToken);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?>
