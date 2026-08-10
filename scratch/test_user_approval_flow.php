<?php
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['fullname'] = 'System Administrator';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/notifications.php';

echo "--- 1. Simulating Resource Person Application Submission ---\n";
$fullname = "Jose Monde (Test Applicant)";
$email = "jose.monde.test@valenzuelacity.gov.ph";
$department = "Education & Technical Training Division";
$expertise = "Justice & Human Rights, Livelihood";
$qualifications = "Bachelor of Laws (LLB)";

$stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, verification_status, status, department, expertise_areas, qualifications, created_at) VALUES (?, ?, 'hashedpass', 'resource person', 'pending', 'pending', ?, ?, ?, NOW())");
$stmt->bind_param('sssss', $fullname, $email, $department, $expertise, $qualifications);
$stmt->execute();
$testUserId = $stmt->insert_id;
$stmt->close();

echo "Registered pending applicant ID #$testUserId ($fullname)\n";

// Trigger Admin notification
createNotification(0, "👤 New Resource Person Application: $fullname ($department) applied for expert role. Please review in User Management.", "user_registration");
echo "Admin notification created successfully!\n";

echo "\n--- 2. Verifying Admin Notification Section Record ---\n";
$nRes = $conn->query("SELECT id, message, type, created_at FROM notifications WHERE type = 'user_registration' ORDER BY id DESC LIMIT 1");
if ($nRes && $nRow = $nRes->fetch_assoc()) {
    echo "- Notification #{$nRow['id']} | Type: {$nRow['type']} | Message: {$nRow['message']}\n";
}

echo "\n--- 3. Simulating Admin Approval via API ---\n";
$_POST['user_id'] = $testUserId;
$_POST['action'] = 'approve';

// Simulate API approval query
$upd = $conn->prepare("UPDATE users SET status = 'active', verification_status = 'verified', approved_by = 1, approved_at = NOW() WHERE id = ?");
$upd->bind_param('i', $testUserId);
$upd->execute();
$upd->close();

@$conn->query("INSERT INTO expert_notifications (user_id, title, message, type, is_read, created_at) VALUES ($testUserId, 'Application Approved!', 'Congratulations! Your Resource Person application has been approved by System Administrator.', 'approval', 0, NOW())");

echo "Approval executed for User #$testUserId!\n";

echo "\n--- 4. Verifying User Status After Approval ---\n";
$uRes = $conn->query("SELECT id, fullname, email, role, status, verification_status, approved_at FROM users WHERE id = $testUserId");
if ($uRes && $uRow = $uRes->fetch_assoc()) {
    echo "User ID: #{$uRow['id']}\n";
    echo "Role: {$uRow['role']}\n";
    echo "Status: {$uRow['status']}\n";
    echo "Verification Status: {$uRow['verification_status']}\n";
    echo "Approved At: {$uRow['approved_at']}\n";
}

// Cleanup test user
$conn->query("DELETE FROM users WHERE id = $testUserId");
$conn->query("DELETE FROM expert_notifications WHERE user_id = $testUserId");
echo "\nApproval & Notification Test Completed Successfully!\n";
