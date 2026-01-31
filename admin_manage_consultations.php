<?php
// admin_manage_consultations.php
require 'db.php';
session_start();
// TODO: Add admin authentication check here

if (isset($_POST['action'], $_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $admin_note = trim($_POST['admin_note'] ?? '');
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? null;
    if ($action === 'approve' && $scheduled_datetime) {
        $stmt = $conn->prepare("UPDATE consultations SET status='approved', scheduled_datetime=?, admin_note=? WHERE id=?");
        $stmt->bind_param('ssi', $scheduled_datetime, $admin_note, $id);
        $stmt->execute();
    } elseif ($action === 'disapprove') {
        $stmt = $conn->prepare("UPDATE consultations SET status='disapproved', admin_note=? WHERE id=?");
        $stmt->bind_param('si', $admin_note, $id);
        $stmt->execute();
    }
    header('Location: admin_manage_consultations.php');
    exit;
}

$result = $conn->query("SELECT c.*, u.fullname FROM consultations c JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
$consultations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Admin - Manage Consultations</title>
    <link rel='stylesheet' href='styles.css'>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 2em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .actions form { display: inline; }
    </style>
</head>
<body>
    <h1>Consultation Management (Admin)</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Topic</th>
                <th>Description</th>
                <th>Preferred Date/Time</th>
                <th>Status</th>
                <th>Scheduled</th>
                <th>Admin Note</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($consultations)): ?>
                <tr><td colspan="9" style="text-align:center;">No consultations found.</td></tr>
            <?php else: foreach ($consultations as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['fullname']) ?></td>
                <td><?= htmlspecialchars($c['topic']) ?></td>
                <td><?= htmlspecialchars($c['description']) ?></td>
                <td><?= htmlspecialchars($c['preferred_datetime']) ?></td>
                <td><?= htmlspecialchars($c['status']) ?></td>
                <td><?= htmlspecialchars($c['scheduled_datetime']) ?></td>
                <td><?= htmlspecialchars($c['admin_note']) ?></td>
                <td class='actions'>
                    <?php if ($c['status'] === 'pending'): ?>
                        <form method='POST' style='display:inline;'>
                            <input type='hidden' name='id' value='<?= $c['id'] ?>'>
                            <input type='hidden' name='action' value='approve'>
                            <input type='datetime-local' name='scheduled_datetime' required>
                            <input type='text' name='admin_note' placeholder='Admin note'>
                            <button type='submit'>Approve</button>
                        </form>
                        <form method='POST' style='display:inline;'>
                            <input type='hidden' name='id' value='<?= $c['id'] ?>'>
                            <input type='hidden' name='action' value='disapprove'>
                            <input type='text' name='admin_note' placeholder='Reason'>
                            <button type='submit'>Disapprove</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</body>
</html>
