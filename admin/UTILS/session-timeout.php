<?php
// Session Timeout and Idle Logout System
// Default idle timeout increased to 30 minutes for user login flows

if (!function_exists('checkSessionTimeout')) {
function checkSessionTimeout($timeout_duration = 1800) {
    // If user is currently on login page, DO NOT time out
    $script_name = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
    if ($script_name === 'login.php' || strpos($_SERVER['REQUEST_URI'] ?? '', 'login.php') !== false) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['last_activity'] = time();
        }
        return true;
    }

    // Check if user is logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
        
        $timeout_duration = (int)$timeout_duration;
        $current_time = time();
        $last_activity = $_SESSION['last_activity'] ?? $_SESSION['login_time'];
        
        // Check if session has timed out
        if (($current_time - $last_activity) >= $timeout_duration) {
            
            // Log the timeout
            if (function_exists('logAction')) {
                logAction(
                    $_SESSION['user_id'], 
                    $_SESSION['fullname'] ?? 'User', 
                    "Auto Logout (Session Timeout)", 
                    "user", 
                    $_SESSION['user_id'], 
                    null, 
                    null, 
                    'warning', 
                    "Session expired after inactivity from IP: " . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
                );
            }
            
            // Destroy session completely
            session_unset();
            session_destroy();
            
            // Redirect to login with timeout message
            header("Location: login.php?timeout=1");
            exit();
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $current_time;
        
        $time_remaining = $timeout_duration - ($current_time - $last_activity);
        if ($time_remaining <= 60 && $time_remaining > 0) { // 1 minute
            $_SESSION['session_warning'] = true;
            $_SESSION['time_remaining'] = floor($time_remaining / 60);
        }
    }
    return true;
}

// Function to get session status for JavaScript
function getSessionStatus() {
    $script_name = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
    if ($script_name === 'login.php' || strpos($_SERVER['REQUEST_URI'] ?? '', 'login.php') !== false) {
        return ['logged_in' => false, 'is_login_page' => true];
    }

    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        $timeout_duration = 1800;
        $current_time = time();
        $last_activity = $_SESSION['last_activity'];
        $time_remaining = $timeout_duration - ($current_time - $last_activity);
        
        return [
            'logged_in' => true,
            'time_remaining' => max(0, $time_remaining),
            'warning' => isset($_SESSION['session_warning']),
            'minutes_left' => floor(max(0, $time_remaining) / 60)
        ];
    }
    
    return ['logged_in' => false];
}
}

// Call timeout checks only when a session is already active and NOT on login page
if (function_exists('checkSessionTimeout') && session_status() === PHP_SESSION_ACTIVE) {
    $script_name = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
    if ($script_name !== 'login.php' && strpos($_SERVER['REQUEST_URI'] ?? '', 'login.php') === false) {
        checkSessionTimeout(1800);
    } else {
        $_SESSION['last_activity'] = time();
    }
}
?>
