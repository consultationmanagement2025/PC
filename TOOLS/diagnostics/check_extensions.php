<?php
echo "<h3>PHP Extensions Check</h3>";
echo "OpenSSL: " . (extension_loaded('openssl') ? "✅ Enabled" : "❌ Disabled") . "<br>";
echo "Sockets: " . (extension_loaded('sockets') ? "✅ Enabled" : "❌ Disabled") . "<br>";
echo "MBString: " . (extension_loaded('mbstring') ? "✅ Enabled" : "❌ Disabled") . "<br>";
echo "<br>";
echo "<h3>PHP Version</h3>";
echo "Version: " . phpversion() . "<br>";
echo "<br>";
echo "<h3>Required Files</h3>";
echo "Exception.php: " . (file_exists(__DIR__ . '/../../PHPMailer/src/Exception.php') ? "✅ Found" : "❌ Missing") . "<br>";
echo "PHPMailer.php: " . (file_exists(__DIR__ . '/../../PHPMailer/src/PHPMailer.php') ? "✅ Found" : "❌ Missing") . "<br>";
echo "SMTP.php: " . (file_exists(__DIR__ . '/../../PHPMailer/src/SMTP.php') ? "✅ Found" : "❌ Missing") . "<br>";
?>
