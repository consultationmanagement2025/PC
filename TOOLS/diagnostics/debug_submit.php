<?php
// Simple debug script to test form submission
echo "<h1>Form Submission Debug</h1>";
echo "<p>Request Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>✅ SUCCESS! POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_FILES)) {
        echo "<h2>FILES Data:</h2>";
        echo "<pre>";
        print_r($_FILES);
        echo "</pre>";
    }
    
    if (isset($_POST['test_submit'])) {
        echo "<h3>🎉 Test button worked! Form can submit!</h3>";
    }
    
    if (isset($_POST['submit_consultation'])) {
        echo "<h3>📋 Consultation submit button worked!</h3>";
    }
} else {
    echo "<p>No POST data received yet.</p>";
}
?>
