<?php
// Simple test form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    echo "Form submitted!\n";
    print_r($_POST);
    echo "</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Form</title>
</head>
<body>
    <form method="POST">
        <input type="text" name="test" placeholder="Test field">
        <button type="submit" name="submit">Submit</button>
    </form>
</body>
</html>
