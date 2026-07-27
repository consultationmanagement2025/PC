<?php
require_once "PHPMailer/src/Exception.php";
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";

$mail = new PHPMailer\PHPMailer\PHPMailer(true);
try {
    $mail->SMTPDebug = 4;  // Show detailed debug info
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = "consultationmanagement2025@gmail.com";
    $mail->Password = "avvfkwzxcjlbbyyg";
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;
    $mail->SMTPOptions = array(
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            "allow_self_signed" => true
        )
    );
    echo "[1] SMTP Settings configured\n";
    
    $mail->setFrom("consultationmanagement2025@gmail.com", "Valenzuela");
    $mail->addAddress("raymundo.almacen.developer@gmail.com");
    $mail->Subject = "Test";
    $mail->Body = "Test";
    
    echo "[2] About to send...\n";
    if ($mail->send()) {
        echo "SUCCESS: Email sent!\n";
    } else {
        echo "ERROR: " . $mail->ErrorInfo . "\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
?>
