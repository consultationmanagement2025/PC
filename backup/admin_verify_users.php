<?php
// admin_verify_users.php
require 'db.php';
session_start();
// TODO: Add admin authentication check here

// Approve or reject user
if (isset($_GET['action'], $_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'] === 'approve' ? 'verified' : 'rejected';
    $stmt = $conn->prepare("UPDATE users SET verification_status=? WHERE id=?");
    $stmt->bind_param('si', $action, $user_id);
    $stmt->execute();
    header('Location: admin_verify_users.php');
    exit;
}

// Get all users pending verification
$result = $conn->query("SELECT id, fullname, email, valid_id_path, verification_status FROM users WHERE verification_status='pending'");
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - User ID Verification</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 2em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        img { max-width: 200px; max-height: 120px; }
        .actions a { margin-right: 10px; }
    </style>
</head>
<body>
    <h1>User ID Verification (Admin)</h1>
    <?php if (empty($users)): ?>
        <p>No users pending verification.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Uploaded ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['fullname']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['valid_id_path']): ?>
                                <?php if (preg_match('/\\.(jpg|jpeg|png|gif)$/i', $user['valid_id_path'])): ?>
                                    <img src="<?= htmlspecialchars($user['valid_id_path']) ?>" alt="ID Image">
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($user['valid_id_path']) ?>" target="_blank">View File</a>
                                <?php endif; ?>
                            <?php else: ?>
                                No file
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a href="?action=approve&id=<?= $user['id'] ?>" style="color:green;">Approve</a>
                            <a href="?action=reject&id=<?= $user['id'] ?>" style="color:red;">Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
