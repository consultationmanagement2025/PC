<?php
session_start();
require 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['otp'] ?? '');
    if (empty($entered)) {
        $message = 'Please enter the OTP sent to your email.';
    } else {
        if (!isset($_SESSION['registration_otp'], $_SESSION['registration_user_id'], $_SESSION['registration_otp_expires'])) {
            $message = 'No pending registration found or OTP expired.';
        } elseif (time() > $_SESSION['registration_otp_expires']) {
            $message = 'OTP has expired. Please register again.';
            // clear session
            unset($_SESSION['registration_otp'], $_SESSION['registration_user_id'], $_SESSION['registration_otp_expires']);
        } else {
            if ($entered == $_SESSION['registration_otp']) {
                $userId = intval($_SESSION['registration_user_id']);
                $stmt = $conn->prepare("UPDATE users SET verification_status='verified' WHERE id=?");
                $stmt->bind_param('i', $userId);
                if ($stmt->execute()) {
                    // Clean up session and inform user
                    unset($_SESSION['registration_otp'], $_SESSION['registration_user_id'], $_SESSION['registration_otp_expires']);
                    $message = 'Your email has been verified. You can now sign in.';
                } else {
                    $message = 'Failed to verify account. Please contact support.';
                }
            } else {
                $message = 'Invalid OTP. Please check the code and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify OTP - PCMP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>body{font-family:Inter,Arial,Helvetica,sans-serif;background:#f7fafc;padding:30px} .box{max-width:420px;margin:0 auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}</style>
</head>
<body>
    <div class="box">
        <h2>Verify Your Email</h2>
        <?php if ($message): ?>
            <div style="margin:10px 0;padding:10px;border-radius:6px;background:#fff3cd;color:#856404;border:1px solid #ffeeba"><?=htmlspecialchars($message)?></div>
        <?php endif; ?>

        <form method="post" action="verify_otp.php">
            <label for="otp">Enter OTP</label>
            <input id="otp" name="otp" maxlength="6" required style="width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:6px">
            <button type="submit" style="width:100%;padding:10px;background:#dc2626;color:#fff;border:none;border-radius:6px">Verify</button>
        </form>

        <p style="font-size:13px;color:#666;margin-top:12px">If you didn't receive the mail, check spam or register again.</p>
        <p style="font-size:13px;margin-top:8px"><a href="login.php">Back to Sign In</a></p>
    </div>
</body>
</html>
