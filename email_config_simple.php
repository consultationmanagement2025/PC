<?php
// Simplified Gmail Configuration
require_once __DIR__ . '/email_config.php';

if (!function_exists('sendGmailEmailSimple')) {
    function sendGmailEmailSimple($to, $subject, $body, $isHTML = false) {
        if (function_exists('sendGmailEmail')) {
            return sendGmailEmail($to, $subject, $body, $isHTML);
        }
        return false;
    }
}
?>
