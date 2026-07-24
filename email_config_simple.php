<?php
// Simplified Gmail Configuration
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendGmailEmailSimple($to, $subject, $body, $isHTML = false) {
    $mail = new PHPMailer();
    
    try {
        // Basic SMTP settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'consultationmanagement2025@gmail.com';
        $mail->Password = 'avvf kwzx cjlb byyg';
        $mail->SMTPSecure = 'tls';  // Try TLS instead of STARTTLS
        $mail->Port = 587;
        
        // From and to
        $mail->setFrom('consultationmanagement2025@gmail.com', 'Valenzuela City');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        return "Error: " . $mail->ErrorInfo;
    }
}
?>
