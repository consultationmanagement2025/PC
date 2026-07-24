<?php
require_once __DIR__ . '/../db.php';

echo "=== Checking Citizens Data ===\n\n";

// Check consultations with user_email
echo "1. Consultations with user_email:\n";
$sql1 = "SELECT user_email, user_name, COUNT(*) as count FROM consultations WHERE user_email IS NOT NULL AND user_email != '' GROUP BY user_email LIMIT 5";
$result1 = $conn->query($sql1);
if ($result1 && $result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        echo "   - Email: {$row['user_email']}, Name: {$row['user_name']}, Count: {$row['count']}\n";
    }
} else {
    echo "   No consultations with user_email found\n";
}

// Check feedback with guest_email
echo "\n2. Feedback with guest_email:\n";
$sql2 = "SELECT guest_email, guest_name, COUNT(*) as count FROM feedback WHERE guest_email IS NOT NULL AND guest_email != '' GROUP BY guest_email LIMIT 5";
$result2 = $conn->query($sql2);
if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        echo "   - Email: {$row['guest_email']}, Name: {$row['guest_name']}, Count: {$row['count']}\n";
    }
} else {
    echo "   No feedback with guest_email found\n";
}

// Check if there are any consultations at all
echo "\n3. Total consultations:\n";
$sql3 = "SELECT COUNT(*) as total FROM consultations";
$result3 = $conn->query($sql3);
if ($result3) {
    $row = $result3->fetch_assoc();
    echo "   Total: {$row['total']}\n";
}

// Check if there are any feedback at all
echo "\n4. Total feedback:\n";
$sql4 = "SELECT COUNT(*) as total FROM feedback";
$result4 = $conn->query($sql4);
if ($result4) {
    $row = $result4->fetch_assoc();
    echo "   Total: {$row['total']}\n";
}
?>
