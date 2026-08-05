<?php
/**
 * Centralized Redirect Configuration
 * Single source of truth for all system redirects
 */

// Base URLs
define('BASE_URL', '/CAP101/PC');
define('ADMIN_PANEL', BASE_URL . '/system-template-full.php');
define('PUBLIC_PORTAL', BASE_URL . '/public/index.php');
define('LOGIN_PAGE', BASE_URL . '/login.php');
define('PUBLIC_LOGIN', BASE_URL . '/public/sign-in.php');
define('PUBLIC_SIGNUP', BASE_URL . '/public/sign-up.php');
define('PENDING_APPROVAL', BASE_URL . '/pending_approval.php');
define('RESOURCE_DASHBOARD', BASE_URL . '/resource_person_dashboard.php');

/**
 * Get redirect URL based on user role
 * @param string $role - User role
 * @return string - Redirect URL
 */
function getRedirectByRole($role) {
    $role = strtolower(trim($role));

    // Admin and staff go to admin panel
    $admin_roles = ['admin', 'administrator', 'super admin', 'superadmin', 'staff'];

    if (in_array($role, $admin_roles, true)) {
        return ADMIN_PANEL;
    }

    // Resource persons go to their dashboard
    if (in_array($role, ['resource person', 'resource_person'], true)) {
        return RESOURCE_DASHBOARD;
    }

    // Citizens go to public portal
    return PUBLIC_PORTAL;
}

/**
 * Safe redirect with proper URL construction
 * @param string $url - Target URL
 * @param array $params - Query parameters
 */
function safeRedirect($url, $params = []) {
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: $url");
    exit;
}

/**
 * Standardize session variable names
 * Ensures consistency across all authentication flows
 */
function setStandardSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'] ?? $user['full_name'] ?? $user['name'] ?? '';
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['verification_status'] = $user['verification_status'] ?? 'pending';
    $_SESSION['login_time'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

