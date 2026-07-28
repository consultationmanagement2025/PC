<?php
// Gmail SMTP Configuration - SECURE VERSION
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Load environment variables for root module
if (!function_exists('loadEnv')) {
    function loadEnv($file = '.env') {
    $filePath = __DIR__ . '/' . $file;
    if (!file_exists($filePath)) {
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        if (strlen($value) >= 2) {
            $first = substr($value, 0, 1);
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
    }
}

// Load environment variables
loadEnv();

if (!function_exists('sendGmailEmail')) {
    function sendGmailEmail($to, $subject, $body, $isHTML = false, ?string &$error = null) {
        $mail = new PHPMailer(true);
        
        try {
            // Get credentials from environment or fallback
            $username = getenv('EMAIL_USERNAME') ?: (isset($_ENV['EMAIL_USERNAME']) ? $_ENV['EMAIL_USERNAME'] : (defined('EMAIL_USERNAME') ? EMAIL_USERNAME : 'consultationmanagement@gmail.com'));
            $password = getenv('EMAIL_PASSWORD') ?: (isset($_ENV['EMAIL_PASSWORD']) ? $_ENV['EMAIL_PASSWORD'] : (defined('EMAIL_PASSWORD') ? EMAIL_PASSWORD : ''));
            $fromName = getenv('EMAIL_FROM') ?: (isset($_ENV['EMAIL_FROM']) ? $_ENV['EMAIL_FROM'] : (defined('EMAIL_FROM') ? EMAIL_FROM : 'Valenzuela City Government - Consultation System'));
            
            if (empty($password)) {
                $error = 'Email password not configured. Set EMAIL_PASSWORD in .env file.';
                error_log($error);
                return false;
            }
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->SMTPAutoTLS = true;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPDebug = 0;
            
            // SSL settings - XAMPP local development always disables verification
            $isLocalhost = (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '.local') !== false)) 
                        || (!isset($_SERVER['HTTP_HOST']) && PHP_SAPI === 'cli');
            
            if ($isLocalhost || strpos(__DIR__, 'xampp') !== false) {
                // Localhost/XAMPP - disable SSL verification for local development
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
            } else {
                // Production - use proper SSL verification
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false
                    )
                );
            }
            
            // Recipients
            $mail->setFrom($username, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($username, $fromName);
            
            // Additional headers for deliverability
            $mail->addCustomHeader('X-Mailer', 'Valenzuela City Consultation System');
            $mail->addCustomHeader('Organization', $fromName);
            
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $isHTML ? strip_tags($body) : $body;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            $error = $mail->ErrorInfo ?: $e->getMessage();
            error_log('Email failed: ' . $error);
            return false;
        }
    }
}
?>
