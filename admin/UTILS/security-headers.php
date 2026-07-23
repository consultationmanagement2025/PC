<?php
/**
 * Security Headers
 * Add to the top of every page to enforce security policies
 */

// Prevent clickjacking attacks
header('X-Frame-Options: SAMEORIGIN', true);

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff', true);

// Enable XSS protection in older browsers
header('X-XSS-Protection: 1; mode=block', true);

// Content Security Policy - restrict resource loading
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://accounts.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src 'self' https://accounts.google.com; frame-src 'self' https://accounts.google.com;", true);

// Referrer Policy - limit referrer information
header('Referrer-Policy: strict-origin-when-cross-origin', true);

// Permissions Policy (formerly Feature Policy)
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()', true);

// Enforce HTTPS (if in production)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
}

?>
